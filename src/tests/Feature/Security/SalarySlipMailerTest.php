<?php

namespace Tests\Feature\Security;

use App\Domains\SalarySlip\Actions\GeneratePayslipPdfAction;
use App\Domains\SalarySlip\Jobs\SendSalarySlipJob;
use App\Domains\SalarySlip\Mail\SalarySlipMail;
use App\Domains\SalarySlip\Models\MailAccount;
use App\Domains\SalarySlip\Models\SalarySlip;
use App\Domains\SalarySlip\Models\SendJob;
use App\Domains\SalarySlip\Services\MailAccountResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SalarySlipMailerTest extends TestCase
{
    use RefreshDatabase;

    private function makeAccount(array $o = []): MailAccount
    {
        return MailAccount::create(array_merge([
            'label' => 'HR', 'driver' => 'smtp', 'purpose' => MailAccount::PURPOSE_SALARY_SLIP,
            'from_email' => 'hr@altima.co.id', 'from_name' => 'HR', 'smtp_host' => 'smtp.altima.co.id',
            'smtp_port' => 587, 'smtp_username' => 'hr@altima.co.id',
            'smtp_password_encrypted' => 'lama', 'smtp_encryption' => 'tls', 'is_active' => true,
        ], $o));
    }

    private function makeSlip(?string $mailAccountId = null): SalarySlip
    {
        return SalarySlip::create([
            'nik' => '1001', 'nama' => 'Budi', 'jabatan' => 'Staff', 'periode' => '2026-06',
            'cabang' => 'Jakarta', 'perusahaan' => 'PT Maju', 'email' => 'budi@example.com',
            'take_home_pay' => 5000000, 'mail_account_id' => $mailAccountId,
            'send_status' => SalarySlip::STATUS_QUEUED,
        ]);
    }

    private function runJob(SalarySlip $slip): SendJob
    {
        $path = tempnam(sys_get_temp_dir(), 'slip').'.pdf';
        file_put_contents($path, '%PDF-1.4 dummy');
        $this->mock(GeneratePayslipPdfAction::class, fn ($m) => $m->shouldReceive('execute')->andReturn($path));

        $sendJob = SendJob::create([
            'salary_slip_id' => $slip->id,
            'mail_account_id' => $slip->mail_account_id,
            'job_batch_id' => 'batch',
            'status' => SendJob::STATUS_QUEUED,
            'attempt' => 0,
        ]);

        (new SendSalarySlipJob($slip->id, $sendJob->id))
            ->handle(app(GeneratePayslipPdfAction::class), app(MailAccountResolver::class));

        return $sendJob;
    }

    public function test_resolver_uses_updated_credentials_after_purge(): void
    {
        $account = $this->makeAccount();
        $resolver = app(MailAccountResolver::class);

        $name = $resolver->build($account);
        $this->assertSame('lama', Mail::mailer($name)->getSymfonyTransport()->getPassword());

        $account->update(['smtp_password_encrypted' => 'baru']);
        $name = $resolver->build($account->fresh());

        $this->assertSame('baru', Mail::mailer($name)->getSymfonyTransport()->getPassword());
    }

    public function test_resolver_sets_smtp_timeout(): void
    {
        $name = app(MailAccountResolver::class)->build($this->makeAccount());

        $this->assertSame(30, config("mail.mailers.{$name}.timeout"));
    }

    public function test_job_fails_when_selected_account_inactive(): void
    {
        Mail::fake();
        $account = $this->makeAccount(['is_active' => false]);
        $slip = $this->makeSlip($account->id);

        $sendJob = $this->runJob($slip);

        Mail::assertNothingSent();
        $this->assertSame(SalarySlip::STATUS_FAILED, $slip->fresh()->send_status);
        $this->assertStringContainsString('Akun email pengirim tidak aktif', $slip->fresh()->send_error);
        $this->assertSame(SendJob::STATUS_FAILED, $sendJob->fresh()->status);
    }

    public function test_job_fails_when_selected_account_deleted(): void
    {
        Mail::fake();
        $account = $this->makeAccount();
        $slip = $this->makeSlip($account->id);
        $account->delete();

        $this->runJob($slip);

        Mail::assertNothingSent();
        $this->assertSame(SalarySlip::STATUS_FAILED, $slip->fresh()->send_status);
    }

    public function test_job_uses_default_salary_slip_account_when_none_selected(): void
    {
        Mail::fake();
        $this->makeAccount(['label' => 'Lain', 'from_email' => 'lain@altima.co.id', 'is_default' => false]);
        $default = $this->makeAccount(['is_default' => true]);
        $slip = $this->makeSlip();

        $this->runJob($slip);

        Mail::assertSent(SalarySlipMail::class, fn (SalarySlipMail $mail) => $mail->mailer === 'mail_account_'.$default->id);
        $this->assertSame(SalarySlip::STATUS_SENT, $slip->fresh()->send_status);
    }

    public function test_job_fails_when_no_salary_slip_account_configured(): void
    {
        Mail::fake();
        // Akun rekrutmen default tidak boleh dipakai untuk slip gaji.
        $this->makeAccount(['purpose' => MailAccount::PURPOSE_RECRUITMENT, 'is_default' => true]);
        $slip = $this->makeSlip();

        $this->runJob($slip);

        Mail::assertNothingSent();
        $this->assertSame(SalarySlip::STATUS_FAILED, $slip->fresh()->send_status);
        $this->assertStringContainsString('Belum ada akun email Slip Gaji', $slip->fresh()->send_error);
    }
}
