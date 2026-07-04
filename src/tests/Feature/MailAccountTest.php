<?php

namespace Tests\Feature;

use App\Domains\SalarySlip\Actions\GeneratePayslipPdfAction;
use App\Domains\SalarySlip\Jobs\SendSalarySlipJob;
use App\Domains\SalarySlip\Mail\SalarySlipMail;
use App\Domains\SalarySlip\Models\MailAccount;
use App\Domains\SalarySlip\Models\SalarySlip;
use App\Domains\SalarySlip\Models\SendJob;
use App\Domains\SalarySlip\Services\MailAccountResolver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class MailAccountTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(string $role): self
    {
        $user = User::factory()->create(['role' => $role]);
        $token = JWTAuth::fromUser($user);

        return $this->withHeader('Authorization', "Bearer {$token}");
    }

    private function payload(array $o = []): array
    {
        return array_merge([
            'label'           => 'HR Team',
            'driver'          => 'smtp',
            'from_email'      => 'hr@altima.co.id',
            'from_name'       => 'HR Altima',
            'smtp_host'       => 'smtp.altima.co.id',
            'smtp_port'       => 587,
            'smtp_username'   => 'hr@altima.co.id',
            'smtp_password'   => 'super-secret',
            'smtp_encryption' => 'tls',
            'is_default'      => true,
        ], $o);
    }

    private function makeAccount(array $o = []): MailAccount
    {
        return MailAccount::create(array_merge([
            'label' => 'HR', 'driver' => 'smtp', 'from_email' => 'hr@altima.co.id',
            'from_name' => 'HR Altima', 'smtp_host' => 'smtp.altima.co.id', 'smtp_port' => 587,
            'smtp_username' => 'hr@altima.co.id', 'smtp_password_encrypted' => 'super-secret',
            'smtp_encryption' => 'tls', 'is_active' => true,
        ], $o));
    }

    // -------------------------------------------------------------- authorization

    public function test_admin_can_create_account(): void
    {
        $this->actingAsRole('admin')
            ->postJson('/api/mail-accounts', $this->payload())
            ->assertStatus(200)
            ->assertJsonPath('data.label', 'HR Team')
            ->assertJsonPath('data.fromEmail', 'hr@altima.co.id');

        $this->assertDatabaseHas('mail_accounts', ['label' => 'HR Team']);
    }

    public function test_non_admin_forbidden_to_create(): void
    {
        $this->actingAsRole('user')
            ->postJson('/api/mail-accounts', $this->payload())
            ->assertStatus(403);
    }

    public function test_guest_unauthorized_to_create(): void
    {
        $this->postJson('/api/mail-accounts', $this->payload())->assertStatus(401);
    }

    public function test_super_admin_can_delete(): void
    {
        $account = $this->makeAccount();

        $this->actingAsRole('super_admin')
            ->deleteJson("/api/mail-accounts/{$account->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('mail_accounts', ['id' => $account->id]);
    }

    public function test_list_open_for_dropdown(): void
    {
        $this->makeAccount(['label' => 'HR']);
        $this->makeAccount(['label' => 'Recruitment', 'from_email' => 'rec@altima.co.id']);

        $this->getJson('/api/mail-accounts')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    // ------------------------------------------------------------------- security

    public function test_password_encrypted_at_rest_and_never_in_response(): void
    {
        $response = $this->actingAsRole('admin')
            ->postJson('/api/mail-accounts', $this->payload())
            ->assertStatus(200);

        // Tidak ada password di response
        $json = $response->json('data');
        $this->assertArrayNotHasKey('smtpPassword', $json);
        $this->assertArrayNotHasKey('smtp_password_encrypted', $json);
        $this->assertTrue($json['hasPassword']);

        // Terenkripsi di DB (bukan plaintext)
        $raw = DB::table('mail_accounts')->where('label', 'HR Team')->value('smtp_password_encrypted');
        $this->assertNotSame('super-secret', $raw);
        $this->assertNotEmpty($raw);
    }

    public function test_update_without_password_keeps_existing(): void
    {
        $account = $this->makeAccount();

        $this->actingAsRole('admin')
            ->putJson("/api/mail-accounts/{$account->id}", ['label' => 'HR Baru'])
            ->assertStatus(200)
            ->assertJsonPath('data.label', 'HR Baru');

        // Password tetap terdekripsi ke nilai lama
        $this->assertSame('super-secret', $account->fresh()->smtp_password_encrypted);
    }

    public function test_only_one_default_account(): void
    {
        $a = $this->makeAccount(['label' => 'A', 'is_default' => true]);

        $this->actingAsRole('admin')
            ->postJson('/api/mail-accounts', $this->payload(['label' => 'B', 'from_email' => 'b@altima.co.id', 'is_default' => true]))
            ->assertStatus(200);

        $this->assertFalse($a->fresh()->is_default);
        $this->assertSame(1, MailAccount::where('is_default', true)->count());
    }

    // ------------------------------------------------------------------- resolver

    public function test_resolver_builds_smtp_config_with_decrypted_password(): void
    {
        $account = $this->makeAccount();
        $name = app(MailAccountResolver::class)->build($account);

        $cfg = config("mail.mailers.{$name}");
        $this->assertSame('smtp', $cfg['transport']);
        $this->assertSame('smtp.altima.co.id', $cfg['host']);
        $this->assertSame(587, $cfg['port']);
        $this->assertSame('super-secret', $cfg['password']); // didekripsi runtime
    }

    // ---------------------------------------------------- from header saat kirim

    public function test_send_uses_selected_account_from_header(): void
    {
        Mail::fake();

        // mock PDF agar tak butuh LibreOffice
        $path = tempnam(sys_get_temp_dir(), 'slip') . '.pdf';
        file_put_contents($path, '%PDF-1.4');
        $this->mock(GeneratePayslipPdfAction::class, fn ($m) => $m->shouldReceive('execute')->andReturn($path));

        $account = $this->makeAccount();
        $slip = SalarySlip::create([
            'nik' => '1', 'nama' => 'Budi', 'jabatan' => 'Staff', 'periode' => '2026-06',
            'cabang' => 'Jakarta', 'perusahaan' => 'PT Maju', 'email' => 'budi@example.com',
            'take_home_pay' => 5000000, 'mail_account_id' => $account->id,
        ]);
        $sendJob = SendJob::create([
            'salary_slip_id' => $slip->id, 'status' => SendJob::STATUS_QUEUED, 'attempt' => 0,
        ]);

        (new SendSalarySlipJob($slip->id, $sendJob->id))
            ->handle(app(GeneratePayslipPdfAction::class), app(MailAccountResolver::class));

        Mail::assertSent(SalarySlipMail::class, function (SalarySlipMail $mail) use ($account) {
            $from = $mail->envelope()->from;

            return $from
                && $from->address === $account->from_email
                && $from->name === $account->from_name;
        });

        @unlink($path);
    }
}
