<?php

namespace Tests\Feature\Security;

use App\Domains\SalarySlip\Models\MailAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicEndpointExposureTest extends TestCase
{
    use RefreshDatabase;

    private function makeAccount(): MailAccount
    {
        return MailAccount::create([
            'label' => 'Rekrutmen',
            'driver' => 'smtp',
            'purpose' => MailAccount::PURPOSE_RECRUITMENT,
            'from_email' => 'rec@altima.co.id',
            'from_name' => 'Rekrutmen',
            'smtp_host' => 'smtp.altima.co.id',
            'smtp_port' => 587,
            'smtp_username' => 'rec@altima.co.id',
            'smtp_password_encrypted' => 'rahasia',
            'is_active' => true,
        ]);
    }

    public function test_analytics_requires_token(): void
    {
        $this->getJson('/api/analytics')->assertStatus(401);
    }

    public function test_analytics_allowed_for_dashboard_permission(): void
    {
        $this->actingAsUserWithPermissions(['dashboard']);

        $this->getJson('/api/analytics')->assertStatus(200);
    }

    public function test_analytics_allowed_for_analytics_permission(): void
    {
        $this->actingAsUserWithPermissions(['analytics']);

        $this->getJson('/api/analytics')->assertStatus(200);
    }

    public function test_analytics_forbidden_without_permission(): void
    {
        $this->actingAsUserWithPermissions(['vacancies']);

        $this->getJson('/api/analytics')->assertStatus(403);
    }

    public function test_mail_accounts_index_requires_token(): void
    {
        $this->getJson('/api/mail-accounts')->assertStatus(401);
    }

    public function test_mail_accounts_show_requires_token(): void
    {
        $account = $this->makeAccount();

        $this->getJson("/api/mail-accounts/{$account->id}")->assertStatus(401);
    }

    public function test_mail_accounts_index_forbidden_without_permission(): void
    {
        $this->actingAsUserWithPermissions(['vacancies']);

        $this->getJson('/api/mail-accounts')->assertStatus(403);
    }

    public function test_mail_accounts_index_allowed_for_salary_slip_permission(): void
    {
        $this->makeAccount();
        $this->actingAsUserWithPermissions(['salary-slips']);

        $this->getJson('/api/mail-accounts')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_include_env_ignored_for_non_admin(): void
    {
        $this->makeAccount();
        $this->actingAsUserWithPermissions(['settings']);

        $response = $this->getJson('/api/mail-accounts?include_env=true')->assertStatus(200);

        $this->assertNotContains(true, array_column($response->json('data'), 'isEnvDefault'));
        $response->assertJsonCount(1, 'data');
    }

    public function test_include_env_honoured_for_admin(): void
    {
        $this->makeAccount();
        $this->actingAsAdmin();

        $this->getJson('/api/mail-accounts?include_env=true')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.isEnvDefault', true);
    }
}
