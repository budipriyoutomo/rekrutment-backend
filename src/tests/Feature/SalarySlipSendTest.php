<?php

namespace Tests\Feature;

use App\Domains\SalarySlip\Actions\GeneratePayslipPdfAction;
use App\Domains\SalarySlip\Actions\SendSalarySlipAction;
use App\Domains\SalarySlip\Jobs\SendSalarySlipJob;
use App\Domains\SalarySlip\Mail\SalarySlipMail;
use App\Domains\SalarySlip\Models\SalarySlip;
use App\Domains\SalarySlip\Models\SendJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SalarySlipSendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    private function makeSlip(array $o = []): SalarySlip
    {
        return SalarySlip::create(array_merge([
            'nik' => '12345', 'nama' => 'Budi', 'jabatan' => 'Staff', 'periode' => '2026-06',
            'cabang' => 'Jakarta', 'perusahaan' => 'PT Maju', 'email' => 'budi@example.com',
            'take_home_pay' => 5000000,
        ], $o));
    }

    /** Buat file PDF dummy & mock action generate agar tak butuh LibreOffice. */
    private function fakePdf(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'slip') . '.pdf';
        file_put_contents($path, '%PDF-1.4 dummy');

        $this->mock(GeneratePayslipPdfAction::class, function ($m) use ($path) {
            $m->shouldReceive('execute')->andReturn($path);
        });

        return $path;
    }

    private function runJob(SalarySlip $slip, SendJob $sendJob, bool $resend = false): void
    {
        (new SendSalarySlipJob($slip->id, $sendJob->id, $resend))
            ->handle(
                app(GeneratePayslipPdfAction::class),
                app(\App\Domains\SalarySlip\Services\MailAccountResolver::class),
            );
    }

    private function makeSendJob(SalarySlip $slip): SendJob
    {
        return SendJob::create([
            'salary_slip_id' => $slip->id,
            'job_batch_id'   => 'batch-1',
            'status'         => SendJob::STATUS_QUEUED,
            'attempt'        => 0,
        ]);
    }

    // ------------------------------------------------------------ dispatch (action)

    public function test_dispatch_marks_queued_and_creates_send_jobs(): void
    {
        Queue::fake();
        $a = $this->makeSlip(['nik' => 'A']);
        $b = $this->makeSlip(['nik' => 'B']);

        $result = app(SendSalarySlipAction::class)->execute([$a->id, $b->id], 'hr-account-id');

        $this->assertSame(2, $result['dispatched']);
        $this->assertSame(0, $result['skipped']);

        Queue::assertPushed(SendSalarySlipJob::class, 2);
        $this->assertSame(SalarySlip::STATUS_QUEUED, $a->fresh()->send_status);
        $this->assertSame('hr-account-id', $a->fresh()->mail_account_id);
        $this->assertSame(2, SendJob::where('status', SendJob::STATUS_QUEUED)->count());
    }

    public function test_dispatch_skips_already_sent_without_resend(): void
    {
        Queue::fake();
        $sent = $this->makeSlip(['nik' => 'A', 'send_status' => SalarySlip::STATUS_SENT]);
        $fresh = $this->makeSlip(['nik' => 'B']);

        $result = app(SendSalarySlipAction::class)->execute([$sent->id, $fresh->id]);

        $this->assertSame(1, $result['dispatched']);
        $this->assertSame(1, $result['skipped']);
        Queue::assertPushed(SendSalarySlipJob::class, 1);
    }

    public function test_dispatch_resend_includes_sent_slips(): void
    {
        Queue::fake();
        $sent = $this->makeSlip(['send_status' => SalarySlip::STATUS_SENT]);

        $result = app(SendSalarySlipAction::class)->execute([$sent->id], null, resend: true);

        $this->assertSame(1, $result['dispatched']);
        $this->assertSame(0, $result['skipped']);
        Queue::assertPushed(SendSalarySlipJob::class, 1);
    }

    // ------------------------------------------------------------- job lifecycle

    public function test_job_sends_mail_with_pdf_and_marks_sent(): void
    {
        Mail::fake();
        $path = $this->fakePdf();

        $slip = $this->makeSlip();
        $sendJob = $this->makeSendJob($slip);

        $this->runJob($slip, $sendJob);

        $slip->refresh();
        $this->assertSame(SalarySlip::STATUS_SENT, $slip->send_status);
        $this->assertNotNull($slip->sent_at);
        $this->assertNull($slip->send_error);

        $this->assertSame(SendJob::STATUS_SUCCESS, $sendJob->fresh()->status);
        $this->assertSame(1, $sendJob->fresh()->attempt);

        Mail::assertSent(SalarySlipMail::class, function (SalarySlipMail $mail) use ($slip, $path) {
            return $mail->hasTo($slip->email) && $mail->pdfPath === $path;
        });

        @unlink($path);
    }

    public function test_job_is_noop_when_already_sent_and_not_resend(): void
    {
        Mail::fake();
        $this->fakePdf();

        $slip = $this->makeSlip(['send_status' => SalarySlip::STATUS_SENT, 'sent_at' => now()]);
        $sendJob = $this->makeSendJob($slip);

        $this->runJob($slip, $sendJob, resend: false);

        Mail::assertNothingSent();
        $this->assertSame(SalarySlip::STATUS_SENT, $slip->fresh()->send_status);
        $this->assertSame(SendJob::STATUS_SUCCESS, $sendJob->fresh()->status);
    }

    public function test_job_resends_when_flag_true(): void
    {
        Mail::fake();
        $this->fakePdf();

        $slip = $this->makeSlip(['send_status' => SalarySlip::STATUS_SENT, 'sent_at' => now()]);
        $sendJob = $this->makeSendJob($slip);

        $this->runJob($slip, $sendJob, resend: true);

        Mail::assertSent(SalarySlipMail::class);
        $this->assertSame(SalarySlip::STATUS_SENT, $slip->fresh()->send_status);
    }

    // ------------------------------------------------------------------- failure

    public function test_failed_handler_marks_failed_with_error(): void
    {
        $slip = $this->makeSlip(['send_status' => SalarySlip::STATUS_PROCESSING]);
        $sendJob = $this->makeSendJob($slip);

        $job = new SendSalarySlipJob($slip->id, $sendJob->id);
        $job->failed(new \RuntimeException('SMTP connection failed'));

        $slip->refresh();
        $this->assertSame(SalarySlip::STATUS_FAILED, $slip->send_status);
        $this->assertStringContainsString('SMTP connection failed', $slip->send_error);

        $sj = $sendJob->fresh();
        $this->assertSame(SendJob::STATUS_FAILED, $sj->status);
        $this->assertStringContainsString('SMTP connection failed', $sj->error_message);
    }

    public function test_job_config_has_retry_and_backoff(): void
    {
        $job = new SendSalarySlipJob('x', 'y');
        $this->assertSame(3, $job->tries);
        $this->assertSame([30, 60, 120], $job->backoff);
    }

    // ------------------------------------------------------------------ endpoint

    public function test_send_endpoint_returns_dispatch_summary(): void
    {
        Queue::fake();
        $slip = $this->makeSlip();

        $this->postJson('/api/salary-slips/send', [
            'ids'             => [$slip->id],
            'mail_account_id' => 'hr-id',
        ])
            ->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.dispatched', 1)
            ->assertJsonStructure(['data' => ['dispatched', 'skipped', 'job_batch_id']]);

        Queue::assertPushed(SendSalarySlipJob::class, 1);
    }

    public function test_send_endpoint_requires_ids(): void
    {
        $this->postJson('/api/salary-slips/send', ['ids' => []])->assertStatus(422);
    }
}
