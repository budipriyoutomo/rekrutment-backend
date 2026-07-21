<?php

namespace Tests\Feature;

use App\Domains\SalarySlip\Actions\GeneratePayslipPdfAction;
use App\Domains\SalarySlip\Actions\ImportSalarySlipsAction;
use App\Domains\SalarySlip\Jobs\SendSalarySlipJob;
use App\Domains\SalarySlip\Mail\SalarySlipMail;
use App\Domains\SalarySlip\Models\MailAccount;
use App\Domains\SalarySlip\Models\SalarySlip;
use App\Domains\SalarySlip\Models\SendJob;
use App\Domains\SalarySlip\Resources\MailAccountResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

/**
 * Phase 7 — Hardening & E2E.
 *
 * Menutup skenario §10.3 (alur penuh API), §10.2 (retry transient), dan
 * §10.4 (volume besar, kredensial tak bocor) dari brief eslip.md.
 *
 * Queue driver di test = sync (phpunit.xml), jadi job pengiriman berjalan
 * inline saat endpoint /send dipanggil — cocok untuk E2E level API.
 */
class SalarySlipE2ETest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    private const HEADER = [
        'nik', 'nama', 'jabatan', 'periode', 'cabang', 'perusahaan', 'email',
        'gaji_pokok', 'total_penerimaan', 'total_potongan', 'take_home_pay',
    ];

    private function row(array $o = []): array
    {
        return array_merge([
            'nik' => '1', 'nama' => 'Budi', 'jabatan' => 'Staff', 'periode' => '2026-06',
            'cabang' => 'Jakarta', 'perusahaan' => 'PT Maju', 'email' => 'budi@example.com',
            'gaji_pokok' => 5000000, 'total_penerimaan' => 5000000,
            'total_potongan' => 0, 'take_home_pay' => 5000000,
        ], $o);
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function csvUpload(array $rows): UploadedFile
    {
        $lines = [implode(',', self::HEADER)];
        foreach ($rows as $r) {
            $lines[] = implode(',', array_map(fn ($k) => $r[$k] ?? '', self::HEADER));
        }

        return UploadedFile::fake()->createWithContent('slip.csv', implode("\n", $lines));
    }

    /** Buat PDF dummy & mock generator agar E2E tak butuh LibreOffice. */
    private function fakePdfGenerator(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'slip') . '.pdf';
        file_put_contents($path, '%PDF-1.4 dummy');

        $this->mock(GeneratePayslipPdfAction::class, function ($m) use ($path) {
            $m->shouldReceive('execute')->andReturn($path);
        });

        return $path;
    }

    private function hrAccount(): MailAccount
    {
        return MailAccount::create([
            'label' => 'HR', 'driver' => 'smtp', 'from_email' => 'hr@altima.co.id',
            'from_name' => 'HR Altima', 'smtp_host' => 'smtp.altima.co.id', 'smtp_port' => 587,
            'smtp_username' => 'hr@altima.co.id', 'smtp_password_encrypted' => 'super-secret',
            'smtp_encryption' => 'tls', 'is_active' => true, 'is_default' => true,
        ]);
    }

    // -------------------------------------------------------- §10.3 alur penuh (E2E)

    public function test_full_flow_import_send_and_status_becomes_sent_with_hr_from(): void
    {
        Mail::fake();
        $pdf = $this->fakePdfGenerator();
        $hr  = $this->hrAccount();

        // 1) Import 2 baris via endpoint.
        $file = $this->csvUpload([
            $this->row(['nik' => 'E1', 'email' => 'e1@example.com']),
            $this->row(['nik' => 'E2', 'email' => 'e2@example.com']),
        ]);

        $this->post('/api/salary-slips/import', ['file' => $file])
            ->assertStatus(200)
            ->assertJsonPath('data.success_rows', 2)
            ->assertJsonPath('data.failed_rows', 0);

        $ids = SalarySlip::pluck('id')->all();
        $this->assertCount(2, $ids);

        // 2) Kirim dengan akun HR (queue sync -> job jalan inline).
        $this->postJson('/api/salary-slips/send', [
            'ids'             => $ids,
            'mail_account_id' => $hr->id,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.dispatched', 2)
            ->assertJsonPath('data.skipped', 0);

        // 3) "Poll" status via endpoint list -> keduanya sent + sent_at terisi.
        $list = $this->getJson('/api/salary-slips')->assertStatus(200)->json('data.data');
        foreach ($list as $slip) {
            $this->assertSame(SalarySlip::STATUS_SENT, $slip['sendStatus']);
            $this->assertNotNull($slip['sentAt']);
            $this->assertSame($hr->id, $slip['mailAccountId']);
        }

        // 4) Email terkirim dari akun HR, PDF ter-attach.
        Mail::assertSent(SalarySlipMail::class, 2);
        Mail::assertSent(SalarySlipMail::class, function (SalarySlipMail $mail) use ($hr, $pdf) {
            return $mail->envelope()->from?->address === $hr->from_email
                && $mail->pdfPath === $pdf;
        });

        @unlink($pdf);
    }

    public function test_resend_of_sent_slip_is_noop_without_flag_then_resends_with_flag(): void
    {
        Mail::fake();
        $this->fakePdfGenerator();

        $slip = SalarySlip::create($this->row([
            'nik' => 'R1', 'send_status' => SalarySlip::STATUS_SENT, 'sent_at' => now(),
        ]));

        // Tanpa resend -> dilewati, tidak ada email baru.
        $this->postJson('/api/salary-slips/send', ['ids' => [$slip->id]])
            ->assertStatus(200)
            ->assertJsonPath('data.dispatched', 0)
            ->assertJsonPath('data.skipped', 1);
        Mail::assertNothingSent();

        // Dengan resend eksplisit -> dikirim ulang.
        $this->postJson('/api/salary-slips/send', ['ids' => [$slip->id], 'resend' => true])
            ->assertStatus(200)
            ->assertJsonPath('data.dispatched', 1);
        Mail::assertSent(SalarySlipMail::class, 1);
    }

    // ------------------------------------------------------ §10.2 retry transient

    public function test_transient_send_failure_is_retried_then_succeeds(): void
    {
        Mail::fake();

        $path = tempnam(sys_get_temp_dir(), 'slip') . '.pdf';
        file_put_contents($path, '%PDF-1.4 dummy');
        $this->mock(GeneratePayslipPdfAction::class, function ($m) use ($path) {
            $m->shouldReceive('execute')->andReturn($path);
        });

        // Simulasikan pengiriman gagal 2x (SMTP timeout) lalu sukses di ke-3.
        $attempts = 0;
        $pending = Mockery::mock();
        $pending->shouldReceive('send')->andReturnUsing(function () use (&$attempts) {
            $attempts++;
            if ($attempts < 3) {
                throw new \RuntimeException('SMTP timeout');
            }
            return null;
        });
        Mail::shouldReceive('to')->andReturn($pending);

        $slip = SalarySlip::create($this->row(['nik' => 'T1']));
        $sendJob = SendJob::create([
            'salary_slip_id' => $slip->id, 'job_batch_id' => 'b', 'status' => SendJob::STATUS_QUEUED, 'attempt' => 0,
        ]);

        $pdfAction = app(GeneratePayslipPdfAction::class);
        $resolver = app(\App\Domains\SalarySlip\Services\MailAccountResolver::class);

        // Percobaan 1 & 2 melempar exception (queue akan menjadwal ulang).
        for ($i = 1; $i <= 2; $i++) {
            try {
                (new SendSalarySlipJob($slip->id, $sendJob->id))->handle($pdfAction, $resolver);
                $this->fail('Percobaan gagal seharusnya melempar exception');
            } catch (\RuntimeException $e) {
                $this->assertSame('SMTP timeout', $e->getMessage());
            }
            $this->assertSame(SalarySlip::STATUS_PROCESSING, $slip->fresh()->send_status);
            $this->assertSame($i, $sendJob->fresh()->attempt);
        }

        // Percobaan ke-3 sukses.
        (new SendSalarySlipJob($slip->id, $sendJob->id))->handle($pdfAction, $resolver);

        $this->assertSame(SalarySlip::STATUS_SENT, $slip->fresh()->send_status);
        $this->assertNotNull($slip->fresh()->sent_at);
        $this->assertSame(SendJob::STATUS_SUCCESS, $sendJob->fresh()->status);
        $this->assertSame(3, $sendJob->fresh()->attempt);

        @unlink($path);
    }

    // -------------------------------------------------- §10.4 volume & kredensial

    public function test_large_import_of_1000_rows_all_saved_without_timeout(): void
    {
        $rows = [];
        for ($i = 1; $i <= 1000; $i++) {
            $rows[] = $this->row(['nik' => "V{$i}", 'email' => "v{$i}@example.com"]);
        }

        $start = microtime(true);
        $summary = app(ImportSalarySlipsAction::class)->execute($this->csvUpload($rows));
        $elapsed = microtime(true) - $start;

        $this->assertSame(1000, $summary['total_rows']);
        $this->assertSame(1000, $summary['success_rows']);
        $this->assertSame(0, $summary['failed_rows']);
        $this->assertSame(1000, SalarySlip::count());
        // Batas longgar, hanya menjaga dari regresi katastrofik (bukan tolok ukur ketat).
        $this->assertLessThan(30, $elapsed, 'Import 1000 baris terlalu lambat');
    }

    public function test_mail_account_credential_never_leaks_in_serialization_or_storage(): void
    {
        $account = $this->hrAccount();
        $secret = 'super-secret';

        // Tersimpan terenkripsi di DB (bukan plaintext).
        $raw = DB::table('mail_accounts')->where('id', $account->id)->value('smtp_password_encrypted');
        $this->assertNotSame($secret, $raw);
        $this->assertStringNotContainsString($secret, (string) $raw);

        // Tidak bocor lewat serialisasi model maupun API Resource.
        $this->assertStringNotContainsString($secret, json_encode($account->toArray()));
        $resource = (new MailAccountResource($account))->toArray(request());
        $this->assertArrayNotHasKey('smtpPassword', $resource);
        $this->assertStringNotContainsString($secret, json_encode($resource));
        $this->assertTrue($resource['hasPassword']);
    }
}
