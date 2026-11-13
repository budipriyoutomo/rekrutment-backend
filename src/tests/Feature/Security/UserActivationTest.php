<?php

namespace Tests\Feature\Security;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserActivationTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $email, string $password)
    {
        // Login tidak memakai token; header Authorization dari actingAs* tidak berpengaruh.
        return $this->postJson('/api/auth/login', ['email' => $email, 'password' => $password]);
    }

    public function test_user_created_from_settings_can_login(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/users', [
            'name' => 'Staf HR',
            'email' => 'staf@example.com',
            'password' => 'rahasia123',
            'role' => Role::USER->value,
        ])->assertStatus(200)->assertJsonPath('data.isActive', true);

        $this->login('staf@example.com', 'rahasia123')->assertStatus(200);
    }

    public function test_user_created_from_settings_can_be_created_inactive(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/users', [
            'name' => 'Staf HR',
            'email' => 'staf@example.com',
            'password' => 'rahasia123',
            'role' => Role::USER->value,
            'is_active' => false,
        ])->assertStatus(200)->assertJsonPath('data.isActive', false);

        $this->assertFalse(User::where('email', 'staf@example.com')->first()->is_active);
    }

    public function test_admin_can_activate_self_registered_user(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Pendaftar',
            'email' => 'pendaftar@example.com',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertSuccessful();

        $registered = User::where('email', 'pendaftar@example.com')->firstOrFail();
        $this->assertFalse($registered->is_active);
        $this->login('pendaftar@example.com', 'rahasia123')->assertStatus(401);

        $this->actingAsAdmin();
        $this->putJson("/api/users/{$registered->id}", ['is_active' => true])
            ->assertStatus(200)
            ->assertJsonPath('data.isActive', true);

        $this->login('pendaftar@example.com', 'rahasia123')->assertStatus(200);
    }

    public function test_admin_cannot_deactivate_self(): void
    {
        $admin = $this->actingAsAdmin();

        $this->putJson("/api/users/{$admin->id}", ['is_active' => false])
            ->assertStatus(422);

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_user_resource_exposes_is_active(): void
    {
        $this->actingAsAdmin();
        $pending = User::factory()->create(['role' => Role::USER->value, 'is_active' => false]);

        $this->getJson("/api/users/{$pending->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.isActive', false);
    }

    public function test_index_can_filter_pending_activation(): void
    {
        $this->actingAsAdmin();
        User::factory()->create(['role' => Role::USER->value, 'is_active' => false]);
        User::factory()->create(['role' => Role::USER->value, 'is_active' => true]);

        // Admin yang login juga aktif → total aktif 2, nonaktif 1.
        $this->getJson('/api/users?is_active=0')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.isActive', false);
    }
}
