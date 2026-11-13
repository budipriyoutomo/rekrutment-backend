<?php

namespace App\Domains\User\Policies;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Aturan manajemen user (Settings → Users).
 *
 *  - super_admin → boleh mengelola semua user & memberi role apa pun.
 *  - admin       → hanya mengelola user ber-role `user` (plus profil sendiri),
 *                  hanya boleh memberi role `user`.
 *  - user        → (butuh izin menu `settings`) hanya mengelola user ber-role `user`,
 *                  tidak boleh mengubah role siapa pun maupun izin menunya sendiri.
 *  - Tidak ada yang boleh mengubah role miliknya sendiri.
 */
class UserPolicy
{
    /**
     * @return list<string>
     */
    public static function assignableRoles(User $actor): array
    {
        return match ($actor->role) {
            Role::SUPER_ADMIN->value => array_column(Role::cases(), 'value'),
            default => [Role::USER->value],
        };
    }

    public function create(User $actor, string $role): Response
    {
        if (! in_array($role, self::assignableRoles($actor), true)) {
            return Response::deny('Anda tidak berhak membuat user dengan role tersebut.');
        }

        return Response::allow();
    }

    /**
     * @param  array<string, mixed>  $attributes  Data tervalidasi dari request.
     */
    public function update(User $actor, User $target, array $attributes = []): Response
    {
        if (! $this->canManage($actor, $target)) {
            return Response::deny('Anda tidak berhak mengubah user ini.');
        }

        $isSelf = $actor->is($target);

        if (array_key_exists('role', $attributes) && $attributes['role'] !== $target->role) {
            if ($isSelf) {
                return Response::deny('Tidak dapat mengubah role akun sendiri.');
            }

            if ($actor->role === Role::USER->value
                || ! in_array($attributes['role'], self::assignableRoles($actor), true)) {
                return Response::deny('Anda tidak berhak mengubah role user ini.');
            }
        }

        if ($isSelf
            && $actor->role === Role::USER->value
            && array_key_exists('permissions', $attributes)
            && ! $this->samePermissions($attributes['permissions'], $target->permissions)) {
            return Response::deny('Tidak dapat mengubah izin menu akun sendiri.');
        }

        return Response::allow();
    }

    public function delete(User $actor, User $target): Response
    {
        if (! $this->canManage($actor, $target)) {
            return Response::deny('Anda tidak berhak menghapus user ini.');
        }

        return Response::allow();
    }

    private function canManage(User $actor, User $target): bool
    {
        if ($actor->role === Role::SUPER_ADMIN->value || $actor->is($target)) {
            return true;
        }

        return $target->role === Role::USER->value;
    }

    private function samePermissions(?array $incoming, ?array $current): bool
    {
        $incoming = $incoming ?? [];
        $current = $current ?? [];
        sort($incoming);
        sort($current);

        return $incoming === $current;
    }
}
