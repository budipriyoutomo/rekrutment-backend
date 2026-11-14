<?php

namespace Tests\Feature\Security;

use App\Domains\SalarySlip\Jobs\SendSalarySlipJob;
use App\Domains\SalarySlip\Models\MailAccount;
use App\Domains\SalarySlip\Models\SalarySlip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalarySlipSendPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function makeSlip(array $o = []): SalarySlip
    {
        static $n = 0;
        $n++;

        return SalarySlip::create(array_merge([
            'nik' => "N{$n}", 'nama' => 'Budi', 'jabatan' => 'Staff', 'periode' => '2026-06',
            'cabang' => 'Jakarta', 'perusahaan' => 'PT Maju', 'email' => 'budi@example.com',
            'take_home_pay' => 5000000,
        ], $o));
    }

    private function makeAccount(array $o = []): MailAccount
    {
        return MailAccount::create(array_merge([
            'label' => 'HR', 'driver' => 'smtp', 'purpose' => MailAccount::PURPOSE_SALARY_SLIP,
            'from_email' => 'hr@altima.co.id', 'from_name' => 'HR', 'smtp_host' => 'smtp.altima.co.id',
            'smtp_port' => 587, 'smtp_username' => 'hr@altima.co.id',
            'smtp_password_encrypted' => 'secret', 'smtp_encryption' => 'tls', 'is_active' => true,
        ], $o));
    }

    public function test_report_permission_cannot_send_unsent_slip(): void
    {
        $this->actingAsUserWithPermissions(['salary-slip-reports']);
        $slip = $this->makeSlip();

        $this->postJson('/api/salary-slips/send', ['ids' => [$slip->id], 'resend' => true])
            ->assertStatus(200)
            ->assertJsonPath('data.dispatched', 0)
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.skipped_details.0.id', $slip->id);

        Queue::assertNothingPushed();
        $this->assertSame(SalarySlip::STATUS_DRAFT, $slip->fresh()->send_status ?? SalarySlip::STATUS_DRAFT);
    }

    public function test_report_permission_can_resend_sent_slip(): void
    {
        $this->actingAsUserWithPermissions(['salary-slip-reports']);
        $slip = $this->makeSlip(['send_status' => SalarySlip::STATUS_SENT, 'sent_at' => now()]);

        $this->postJson('/api/salary-slips/send', ['ids' => [$slip->id], 'resend' => true])
            ->assertStatus(200)
            ->assertJsonPath('data.dispatched', 1);

        Queue::assertPushed(SendSalarySlipJob::class, 1);
    }

    public function test_salary_slip_permission_can_send_unsent_slip(): void
    {
        $this->actingAsUserWithPermissions(['salary-slips']);
        $slip = $this->makeSlip();

        $this->postJson('/api/salary-slips/send', ['ids' => [$slip->id]])
            ->assertStatus(200)
            ->assertJsonPath('data.dispatched', 1);
    }

    public function test_send_rejects_inactive_or_recruitment_mail_account(): void
    {
        $this->actingAsAdmin();
        $slip = $this->makeSlip();
        $inactive = $this->makeAccount(['is_active' => false]);
        $recruitment = $this->makeAccount(['purpose' => MailAccount::PURPOSE_RECRUITMENT, 'from_email' => 'rec@altima.co.id']);

        foreach ([$inactive->id, $recruitment->id, (string) Str::uuid(), 'bukan-uuid'] as $accountId) {
            $this->postJson('/api/salary-slips/send', ['ids' => [$slip->id], 'mail_account_id' => $accountId])
                ->assertStatus(422)
                ->assertJsonValidationErrors('mail_account_id');
        }

        Queue::assertNothingPushed();
    }

    public function test_send_accepts_active_salary_slip_account(): void
    {
        $this->actingAsAdmin();
        $slip = $this->makeSlip();
        $account = $this->makeAccount();

        $this->postJson('/api/salary-slips/send', ['ids' => [$slip->id], 'mail_account_id' => $account->id])
            ->assertStatus(200)
            ->assertJsonPath('data.dispatched', 1);
    }

    public function test_send_validates_ids(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/salary-slips/send', ['ids' => ['bukan-uuid']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ids.0');

        $tooMany = array_map(fn () => (string) Str::uuid(), range(1, 501));
        $this->postJson('/api/salary-slips/send', ['ids' => $tooMany])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ids');
    }
}
