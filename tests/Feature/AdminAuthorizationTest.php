<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_regular_admin(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $this->postJson('/api/admin/admins', [
            'first_name' => 'Regular',
            'last_name' => 'Admin',
            'email' => 'regular-admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '628111222333',
            'gender' => 'Laki-laki',
            'admin_level' => 'admin',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.admin.email', 'regular-admin@example.com')
            ->assertJsonPath('data.admin.role', 'admin')
            ->assertJsonPath('data.admin.admin_level', 'admin')
            ->assertJsonPath('data.admin.status', 'active');

        $this->assertDatabaseHas('users', [
            'email' => 'regular-admin@example.com',
            'role' => 'admin',
            'admin_level' => 'admin',
            'status' => 'active',
        ]);
    }

    public function test_regular_admin_cannot_create_admin(): void
    {
        Sanctum::actingAs($this->regularAdmin());

        $this->postJson('/api/admin/admins', [
            'first_name' => 'Blocked',
            'email' => 'blocked-admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'gender' => 'Perempuan',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden. Super admin access only.');

        $this->assertDatabaseMissing('users', [
            'email' => 'blocked-admin@example.com',
        ]);
    }

    public function test_inactive_admin_cannot_login(): void
    {
        User::query()->create([
            'first_name' => 'Inactive',
            'last_name' => 'Admin',
            'gender' => 'Laki-laki',
            'email' => 'inactive-admin@example.com',
            'password' => 'password123',
            'role' => 'admin',
            'admin_level' => 'admin',
            'status' => 'inactive',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'inactive-admin@example.com',
            'password' => 'password123',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Akun admin sedang dinonaktifkan. Hubungi super admin.');
    }

    public function test_super_admin_cannot_disable_self(): void
    {
        $superAdmin = $this->superAdmin();
        Sanctum::actingAs($superAdmin);

        $this->patchJson("/api/admin/admins/{$superAdmin->id}/status", [
            'status' => 'inactive',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Super admin tidak dapat mengubah status dirinya sendiri.');
    }

    public function test_last_active_super_admin_cannot_be_disabled(): void
    {
        $superAdmin = $this->superAdmin();
        $regularAdmin = $this->regularAdmin();
        Sanctum::actingAs($superAdmin);

        $this->patchJson("/api/admin/admins/{$regularAdmin->id}/status", [
            'status' => 'inactive',
        ])->assertOk();

        $anotherSuperAdmin = User::query()->create([
            'first_name' => 'Second',
            'last_name' => 'Super',
            'gender' => 'Perempuan',
            'email' => 'second-super@example.com',
            'password' => 'password123',
            'role' => 'admin',
            'admin_level' => 'super_admin',
            'status' => 'active',
        ]);

        $this->patchJson("/api/admin/admins/{$anotherSuperAdmin->id}/status", [
            'status' => 'inactive',
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $anotherSuperAdmin->id,
            'status' => 'inactive',
        ]);
    }

    public function test_last_active_super_admin_cannot_be_deleted(): void
    {
        $superAdmin = $this->superAdmin();
        $anotherSuperAdmin = User::query()->create([
            'first_name' => 'Second',
            'last_name' => 'Super',
            'gender' => 'Perempuan',
            'email' => 'second-super@example.com',
            'password' => 'password123',
            'role' => 'admin',
            'admin_level' => 'super_admin',
            'status' => 'active',
        ]);

        Sanctum::actingAs($superAdmin);

        $this->deleteJson("/api/admin/admins/{$anotherSuperAdmin->id}")
            ->assertOk();

        $this->deleteJson("/api/admin/admins/{$superAdmin->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Super admin tidak dapat menghapus dirinya sendiri.');
    }

    public function test_admin_user_management_uses_schema_fields_for_alumni_creation(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $this->postJson('/api/admin/users', [
            'first_name' => 'Ahmad',
            'last_name' => 'Fauzi',
            'gender' => 'Laki-laki',
            'email' => 'ahmad@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '6281234567890',
            'graduation_year' => 2018,
            'birth_date' => '2000-01-01',
            'status' => 'active',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'User berhasil dibuat')
            ->assertJsonPath('data.user.first_name', 'Ahmad')
            ->assertJsonPath('data.user.last_name', 'Fauzi')
            ->assertJsonPath('data.user.graduation_year', 2018)
            ->assertJsonPath('data.user.role', 'alumni')
            ->assertJsonPath('data.user.admin_level', null);

        $this->assertDatabaseHas('users', [
            'email' => 'ahmad@example.com',
            'first_name' => 'Ahmad',
            'last_name' => 'Fauzi',
            'graduation_year' => 2018,
            'role' => 'alumni',
            'admin_level' => null,
        ]);
    }

    public function test_admin_user_management_rejects_admin_role_escalation(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $this->postJson('/api/admin/users', [
            'first_name' => 'Escalated',
            'gender' => 'Laki-laki',
            'email' => 'escalated@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ])->assertUnprocessable();
    }

    private function superAdmin(): User
    {
        return User::query()->create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'gender' => 'Laki-laki',
            'email' => 'super-admin@example.com',
            'password' => 'password123',
            'role' => 'admin',
            'admin_level' => 'super_admin',
            'status' => 'active',
        ]);
    }

    private function regularAdmin(): User
    {
        return User::query()->create([
            'first_name' => 'Regular',
            'last_name' => 'Admin',
            'gender' => 'Perempuan',
            'email' => 'regular-admin@example.com',
            'password' => 'password123',
            'role' => 'admin',
            'admin_level' => 'admin',
            'status' => 'active',
        ]);
    }
}
