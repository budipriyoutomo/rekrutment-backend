<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------ helpers

    private function activeUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'is_active' => true,
            'role'      => 'admin',
            'password'  => bcrypt('password123'),
        ], $attrs));
    }

    private function loginAndGetToken(string $email, string $password = 'password123'): string
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => $email,
            'password' => $password,
        ]);

        return $response->json('data.access_token') ?? '';
    }

    // ------------------------------------------------------------------ register

    public function test_register_creates_inactive_user(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Test User',
            'email'                 => 'newuser@example.com',
            'password'              => 'secret1234',
            'password_confirmation' => 'secret1234',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
    }

    public function test_register_requires_email_and_password(): void
    {
        $this->postJson('/api/auth/register', [])
            ->assertStatus(422);
    }

    // ------------------------------------------------------------------ login

    public function test_login_with_valid_credentials_returns_token(): void
    {
        $user = $this->activeUser(['email' => 'admin@example.com']);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'admin@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonStructure(['data' => ['access_token', 'token_type', 'user']]);
    }

    public function test_login_inactive_user_is_rejected(): void
    {
        User::factory()->create([
            'email'     => 'inactive@example.com',
            'password'  => bcrypt('password123'),
            'is_active' => false,
        ]);

        $this->postJson('/api/auth/login', [
            'email'    => 'inactive@example.com',
            'password' => 'password123',
        ])->assertStatus(401);
    }

    public function test_login_with_wrong_password_is_rejected(): void
    {
        $this->activeUser(['email' => 'user@example.com']);

        $this->postJson('/api/auth/login', [
            'email'    => 'user@example.com',
            'password' => 'wrongpassword',
        ])->assertStatus(401);
    }

    // ------------------------------------------------------------------ forgot-password

    public function test_forgot_password_returns_success_for_any_email(): void
    {
        $this->postJson('/api/auth/forgot-password', ['email' => 'anyone@example.com'])
            ->assertStatus(200)
            ->assertJsonPath('status', true);
    }

    public function test_forgot_password_requires_valid_email(): void
    {
        $this->postJson('/api/auth/forgot-password', ['email' => 'not-an-email'])
            ->assertStatus(422);
    }

    // ------------------------------------------------------------------ me

    public function test_me_returns_authenticated_user(): void
    {
        $user  = $this->activeUser(['email' => 'me@example.com']);
        $token = $this->loginAndGetToken('me@example.com');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('data.email', 'me@example.com');
    }

    public function test_me_without_token_returns_401(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401);
    }

    // ------------------------------------------------------------------ logout

    public function test_logout_invalidates_token(): void
    {
        $user  = $this->activeUser(['email' => 'logout@example.com']);
        $token = $this->loginAndGetToken('logout@example.com');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout')
            ->assertStatus(200)
            ->assertJsonPath('status', true);
    }

    // ------------------------------------------------------------------ refresh

    public function test_refresh_returns_new_token(): void
    {
        $user  = $this->activeUser(['email' => 'refresh@example.com']);
        $token = $this->loginAndGetToken('refresh@example.com');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/refresh')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['access_token']]);
    }
}
