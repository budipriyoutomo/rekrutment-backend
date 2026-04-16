<?php

namespace App\Domains\Auth\Services;

use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AuthService
{
    protected string $guard = 'api';

    public function login(string $email, string $password): array
    {
        $credentials = [
            'email' => $email,
            'password' => $password,
        ];

        if (!$token = Auth::guard($this->guard)->attempt($credentials)) {
            throw new UnauthorizedHttpException('', 'Email atau password salah');
        }

        return $this->respondWithToken($token);
    }

    public function logout(): void
    {
        Auth::guard($this->guard)->logout();
    }

    public function refresh(): array
    {
        $token = Auth::guard($this->guard)->refresh();

        return $this->respondWithToken($token);
    }

    public function me()
    {
        return Auth::guard($this->guard)->user();
    }

    protected function respondWithToken(string $token): array
    {
        $user = Auth::guard($this->guard)->user();

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => Auth::guard($this->guard)->factory()->getTTL() * 60,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role ?? null,
            ]
        ];
    }
}