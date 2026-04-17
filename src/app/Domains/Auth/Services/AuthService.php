<?php

namespace App\Domains\Auth\Services;

use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AuthService
{
    /**
     * Login user & generate token
     */
    public function login(string $email, string $password): array
    {
        $credentials = [
            'email' => $email,
            'password' => $password,
        ];

        if (!$token = JWTAuth::attempt($credentials)) {
            throw new UnauthorizedHttpException('', 'Email atau password salah');
        }

        $user = JWTAuth::user();

        // ✅ CHECK AKTIVASI
        if (!$user->is_active) {
            throw new UnauthorizedHttpException('', 'Akun belum diaktifkan admin');
        }

        // ✅ REGENERATE TOKEN DENGAN CLAIM ROLE
        $token = JWTAuth::claims([
            'role' => $user->role,
        ])->fromUser($user);

        return $this->respondWithToken($token);
    }

    /**
     * Logout (invalidate token)
     */
    public function logout(): void
    {
        try {
            JWTAuth::parseToken()->invalidate();
        } catch (\Exception $e) {
            // silent fail (optional logging)
        }
    }

    /**
     * Refresh token
     */
    public function refresh(): array
    {
        try {
            $newToken = JWTAuth::parseToken()->refresh();

            return $this->respondWithToken($newToken);

        } catch (\PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException $e) {
            throw new UnauthorizedHttpException('', 'Token expired');

        } catch (\PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException $e) {
            throw new UnauthorizedHttpException('', 'Token invalid');
        }
    }

    /**
     * Get authenticated user
     */
    public function me(): array
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                throw new UnauthorizedHttpException('', 'User tidak ditemukan');
            }

            return $this->transformUser($user);

        } catch (\Exception $e) {
            throw new UnauthorizedHttpException('', 'Unauthorized');
        }
    }

    /**
     * Build token response
     */
    protected function respondWithToken(string $token): array
    {
        $user = JWTAuth::setToken($token)->toUser();

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $this->getTTL(),
            'user' => $this->transformUser($user),
        ];
    }

    /**
     * Get TTL in seconds
     */
    protected function getTTL(): int
    {
        return JWTAuth::factory()->getTTL() * 60;
    }

    /**
     * Transform user data (safe output)
     */
    protected function transformUser($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role ?? null,
        ];
    }
}