<?php

namespace Tests\Feature\Security;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class InactiveUserAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function mailAccountPayload(): array
    {
        return [
            'label' => 'HR Team',
            'driver' => 'smtp',
            'purpose' => 'salary_slip',
            'from_email' => 'hr@altima.co.id',
            'from_name' => 'HR Altima',
            'smtp_host' => 'smtp.altima.co.id',
            'smtp_port' => 587,
            'smtp_username' => 'hr@altima.co.id',
            'smtp_password' => 'secret',
            'smtp_encryption' => 'tls',
        ];
    }

    private function loginToken(User $user): string
    {
        return $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertStatus(200)
            ->json('data.access_token');
    }

    public function test_deactivated_user_gets_401_on_permission_route(): void
    {
        $user = $this->actingAsUserWithPermissions(['job-requests']);
        $this->getJson('/api/job-requests')->assertStatus(200);

        $user->update(['is_active' => false]);

        $this->getJson('/api/job-requests')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Akun dinonaktifkan');
    }

    public function test_deactivated_admin_gets_401_on_role_route(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['is_active' => false]);

        $this->postJson('/api/mail-accounts', $this->mailAccountPayload())
            ->assertStatus(401)
            ->assertJsonPath('message', 'Akun dinonaktifkan');
    }

    public function test_deactivated_user_cannot_refresh_token(): void
    {
        $user = User::factory()->create(['role' => Role::USER->value, 'is_active' => true]);
        $token = $this->loginToken($user);

        $user->update(['is_active' => false]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/refresh')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Akun dinonaktifkan');
    }

    public function test_deactivated_user_cannot_fetch_me(): void
    {
        $user = User::factory()->create(['role' => Role::USER->value, 'is_active' => true]);
        $token = $this->loginToken($user);

        $user->update(['is_active' => false]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Akun dinonaktifkan');
    }

    public function test_refreshed_token_carries_current_role(): void
    {
        $user = User::factory()->create(['role' => Role::USER->value, 'is_active' => true]);
        $token = $this->loginToken($user);

        $user->update(['role' => Role::ADMIN->value]);

        $newToken = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/refresh')
            ->assertStatus(200)
            ->json('data.access_token');

        $this->assertSame(Role::ADMIN->value, JWTAuth::setToken($newToken)->getPayload()->get('role'));
    }

    public function test_demoted_admin_loses_admin_route_immediately(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['role' => Role::USER->value]);

        $this->postJson('/api/mail-accounts', $this->mailAccountPayload())
            ->assertStatus(403);
    }

    public function test_promoted_user_gains_admin_route_without_relogin(): void
    {
        $user = $this->actingAsUserWithPermissions(['settings']);
        $user->update(['role' => Role::ADMIN->value]);

        $this->postJson('/api/mail-accounts', $this->mailAccountPayload())
            ->assertStatus(200);
    }

    public function test_unauthorized_response_does_not_leak_exception_message(): void
    {
        $user = $this->actingAsUserWithPermissions(['job-requests']);
        $user->delete();

        $this->getJson('/api/job-requests')
            ->assertStatus(401)
            ->assertJsonMissingPath('error');

        $this->postJson('/api/mail-accounts', $this->mailAccountPayload())
            ->assertStatus(401)
            ->assertJsonMissingPath('error');
    }
}
