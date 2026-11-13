<?php

namespace App\Providers;

use App\Domains\User\Policies\UserPolicy;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);

        $this->configureTrustedProxies();
        $this->configureRateLimiting();
    }

    /**
     * Di belakang Traefik/nginx, IP klien asli ada di X-Forwarded-For. Tanpa ini
     * semua request terbaca ber-IP proxy sehingga limiter per-IP memblokir semua orang.
     */
    private function configureTrustedProxies(): void
    {
        $proxies = config('app.trusted_proxies');

        if (filled($proxies)) {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
    }

    private function configureRateLimiting(): void
    {
        // Brute force login/registrasi/lupa password: per email+IP dan per IP.
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);

        // Form lamaran publik (submit, quick apply, upload).
        RateLimiter::for('public-submit', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        // Endpoint publik bertoken (assessment, offer, pre-boarding, profile completion).
        RateLimiter::for('public-token', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
    }
}
