<?php

namespace Tests\Feature\Security;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleEscalationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function newUserPayload(string $role): array
    {
        return [
            'name' => 'Pengguna Baru',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'rahasia123',
            'role' => $role,
        ];
    }

    private function makeUser(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'is_active' => true,
        ], $attributes));
    }

    public function test_user_with_settings_permission_cannot_promote_self_to_super_admin(): void
    {
        $actor = $this->actingAsUserWithPermissions(['settings']);

        $this->putJson("/api/users/{$actor->id}", ['role' => Role::SUPER_ADMIN->value])
            ->assertStatus(403);

        $this->assertSame(Role::USER->value, $actor->fresh()->role);
    }

    public function test_user_with_settings_permission_cannot_promote_other_user(): void
    {
        $this->actingAsUserWithPermissions(['settings']);
        $target = $this->makeUser(Role::USER->value);

        $this->putJson("/api/users/{$target->id}", ['role' => Role::ADMIN->value])
            ->assertStatus(403);

        $this->assertSame(Role::USER->value, $target->fresh()->role);
    }

    public function test_user_with_settings_permission_cannot_grant_self_extra_permissions(): void
    {
        $actor = $this->actingAsUserWithPermissions(['settings']);

        $this->putJson("/api/users/{$actor->id}", ['permissions' => ['settings', 'salary-slips']])
            ->assertStatus(403);

        $this->assertSame(['settings'], $actor->fresh()->permissions);
    }

    public function test_user_with_settings_permission_can_edit_regular_user_with_unchanged_role(): void
    {
        $this->actingAsUserWithPermissions(['settings']);
        $target = $this->makeUser(Role::USER->value);

        $this->putJson("/api/users/{$target->id}", [
            'name' => 'Nama Baru',
            'role' => Role::USER->value,
            'permissions' => ['vacancies'],
        ])->assertStatus(200);

        $this->assertSame('Nama Baru', $target->fresh()->name);
    }

    public function test_user_with_settings_permission_cannot_create_admin(): void
    {
        $this->actingAsUserWithPermissions(['settings']);

        $this->postJson('/api/users', $this->newUserPayload(Role::ADMIN->value))
            ->assertStatus(403);

        $this->assertDatabaseMissing('users', ['role' => Role::ADMIN->value]);
    }

    public function test_user_with_settings_permission_cannot_update_admin_account(): void
    {
        $this->actingAsUserWithPermissions(['settings']);
        $admin = $this->makeUser(Role::ADMIN->value);

        $this->putJson("/api/users/{$admin->id}", ['name' => 'Diretas'])
            ->assertStatus(403);

        $this->assertNotSame('Diretas', $admin->fresh()->name);
    }

    public function test_user_with_settings_permission_cannot_delete_admin_account(): void
    {
        $this->actingAsUserWithPermissions(['settings']);
        $admin = $this->makeUser(Role::ADMIN->value);

        $this->deleteJson("/api/users/{$admin->id}")->assertStatus(403);

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_cannot_create_super_admin(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/users', $this->newUserPayload(Role::SUPER_ADMIN->value))
            ->assertStatus(403);

        $this->assertDatabaseMissing('users', ['role' => Role::SUPER_ADMIN->value]);
    }

    public function test_admin_cannot_create_another_admin(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/users', $this->newUserPayload(Role::ADMIN->value))
            ->assertStatus(403);
    }

    public function test_admin_can_create_regular_user(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/users', $this->newUserPayload(Role::USER->value))
            ->assertStatus(200)
            ->assertJsonPath('data.role', Role::USER->value);
    }

    public function test_admin_cannot_update_super_admin_account(): void
    {
        $this->actingAsAdmin();
        $superAdmin = $this->makeUser(Role::SUPER_ADMIN->value);

        $this->putJson("/api/users/{$superAdmin->id}", ['password' => 'gantipass123'])
            ->assertStatus(403);
    }

    public function test_admin_cannot_promote_user_to_admin(): void
    {
        $this->actingAsAdmin();
        $target = $this->makeUser(Role::USER->value);

        $this->putJson("/api/users/{$target->id}", ['role' => Role::ADMIN->value])
            ->assertStatus(403);

        $this->assertSame(Role::USER->value, $target->fresh()->role);
    }

    public function test_admin_cannot_change_own_role(): void
    {
        $admin = $this->actingAsAdmin();

        $this->putJson("/api/users/{$admin->id}", ['role' => Role::USER->value])
            ->assertStatus(403);

        $this->assertSame(Role::ADMIN->value, $admin->fresh()->role);
    }

    public function test_admin_can_edit_own_profile(): void
    {
        $admin = $this->actingAsAdmin();

        $this->putJson("/api/users/{$admin->id}", ['name' => 'Admin Baru', 'role' => Role::ADMIN->value])
            ->assertStatus(200);

        $this->assertSame('Admin Baru', $admin->fresh()->name);
    }

    public function test_super_admin_can_create_admin(): void
    {
        $this->actingAsAdmin(['role' => Role::SUPER_ADMIN->value]);

        $this->postJson('/api/users', $this->newUserPayload(Role::ADMIN->value))
            ->assertStatus(200)
            ->assertJsonPath('data.role', Role::ADMIN->value);
    }

    public function test_super_admin_can_promote_user_to_admin(): void
    {
        $this->actingAsAdmin(['role' => Role::SUPER_ADMIN->value]);
        $target = $this->makeUser(Role::USER->value);

        $this->putJson("/api/users/{$target->id}", ['role' => Role::ADMIN->value])
            ->assertStatus(200);

        $this->assertSame(Role::ADMIN->value, $target->fresh()->role);
    }

    public function test_super_admin_cannot_change_own_role(): void
    {
        $superAdmin = $this->actingAsAdmin(['role' => Role::SUPER_ADMIN->value]);

        $this->putJson("/api/users/{$superAdmin->id}", ['role' => Role::ADMIN->value])
            ->assertStatus(403);

        $this->assertSame(Role::SUPER_ADMIN->value, $superAdmin->fresh()->role);
    }
}
