<?php

namespace Tests\Feature\Security;

use App\Enums\Role;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // TrustProxies::at() menyimpan state statis; jangan bocor ke test lain.
        TrustProxies::flushState();

        parent::tearDown();
    }

    public function test_login_is_throttled_after_5_failed_attempts(): void
    {
        User::factory()->create(['email' => 'target@example.com', 'role' => Role::USER->value, 'is_active' => true]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'target@example.com', 'password' => 'salah'])
                ->assertStatus(401);
        }

        $this->postJson('/api/auth/login', ['email' => 'target@example.com', 'password' => 'salah'])
            ->assertStatus(429);
    }

    public function test_login_limit_is_per_email(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => 'salah']);
        }

        $this->postJson('/api/auth/login', ['email' => 'b@example.com', 'password' => 'salah'])
            ->assertStatus(401);
    }

    public function test_login_email_limit_is_case_insensitive(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'Target@Example.com', 'password' => 'salah']);
        }

        $this->postJson('/api/auth/login', ['email' => 'target@example.com', 'password' => 'salah'])
            ->assertStatus(429);
    }

    public function test_register_and_forgot_password_are_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/forgot-password', ['email' => 'lupa@example.com']);
        }
        $this->postJson('/api/auth/forgot-password', ['email' => 'lupa@example.com'])->assertStatus(429);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/register', ['email' => 'daftar@example.com']);
        }
        $this->postJson('/api/auth/register', ['email' => 'daftar@example.com'])->assertStatus(429);
    }

    public function test_quick_apply_is_throttled(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/applications/quick', [])->assertStatus(422);
        }

        $this->postJson('/api/applications/quick', [])->assertStatus(429);
    }

    public function test_public_token_endpoint_is_throttled(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->getJson('/api/offer/'.str_repeat('x', 64))->assertStatus(404);
        }

        $this->getJson('/api/offer/'.str_repeat('x', 64))->assertStatus(429);
    }

    public function test_throttle_keys_by_client_ip(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->postJson('/api/applications/quick', []);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->postJson('/api/applications/quick', [])
            ->assertStatus(422);
    }

    public function test_trusted_proxy_forwarded_ip_is_used(): void
    {
        config(['app.trusted_proxies' => '127.0.0.1']);
        $this->refreshApplicationTrustedProxies();

        Route::get('/_test/ip', fn (Request $request) => $request->ip());

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeader('X-Forwarded-For', '203.0.113.9')
            ->get('/_test/ip')
            ->assertSee('203.0.113.9');
    }

    private function refreshApplicationTrustedProxies(): void
    {
        (new AppServiceProvider($this->app))->boot();
    }
}
