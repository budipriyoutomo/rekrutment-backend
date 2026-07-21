<?php

namespace Tests;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

abstract class TestCase extends BaseTestCase
{
    /**
     * Login sebagai admin lalu pasang header Authorization untuk request berikutnya.
     *
     * Route HR dijaga PermissionMiddleware yang membaca token via
     * JWTAuth::parseToken(), jadi actingAs() saja tidak cukup — token JWT harus
     * benar-benar hadir di header. Role admin dipakai karena bypass seluruh
     * pengecekan izin menu.
     */
    protected function actingAsAdmin(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'role'      => Role::ADMIN->value,
            'is_active' => true,
        ], $attributes));

        $this->withHeader('Authorization', 'Bearer ' . JWTAuth::fromUser($user));

        return $user;
    }

    /**
     * Login sebagai user biasa yang hanya memegang izin menu tertentu.
     */
    protected function actingAsUserWithPermissions(array $permissions): User
    {
        return $this->actingAsAdmin([
            'role'        => Role::USER->value,
            'permissions' => $permissions,
        ]);
    }
}
