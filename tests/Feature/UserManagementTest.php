<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_non_admin_users_for_kelola_user_page(): void
    {
        User::query()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'gender' => 'Laki-laki',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
            'status' => 'active',
        ]);

        User::query()->create([
            'first_name' => 'Alumni',
            'last_name' => 'User',
            'gender' => 'Perempuan',
            'email' => 'alumni@example.com',
            'password' => 'password',
            'phone' => '6281234567890',
            'graduation_year' => '2020',
            'birth_date' => '2000-01-01',
            'role' => 'alumni',
            'status' => 'active',
        ]);

        $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Alumni User')
            ->assertJsonPath('data.0.email', 'alumni@example.com')
            ->assertJsonPath('data.0.phone', '6281234567890')
            ->assertJsonPath('data.0.gender', 'Perempuan')
            ->assertJsonPath('data.0.graduation_year', '2020')
            ->assertJsonPath('data.0.birth_date', '2000-01-01')
            ->assertJsonPath('data.0.role', 'alumni')
            ->assertJsonPath('data.0.status', 'active')
            ->assertJsonMissingPath('data.0.password')
            ->assertJsonMissingPath('data.0.remember_token')
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'name',
                        'email',
                        'phone',
                        'gender',
                        'graduation_year',
                        'birth_date',
                        'role',
                        'status',
                        'created_at',
                    ],
                ],
            ]);
    }

    public function test_can_update_user_without_password(): void
    {
        $user = User::query()->create([
            'first_name' => 'Nama',
            'last_name' => 'Lama',
            'gender' => 'Laki-laki',
            'email' => 'lama@example.com',
            'password' => 'password',
            'phone' => '6289876543210',
            'graduation_year' => '2019',
            'birth_date' => '1999-01-01',
            'role' => 'alumni',
            'status' => 'active',
        ]);

        $this->putJson("/api/users/{$user->id}", [
            'name' => 'Nama Baru',
            'email' => 'emailbaru@example.com',
            'phone' => '628111222333',
            'gender' => 'Perempuan',
            'graduation_year' => '2021',
            'birth_date' => '2001-02-03',
            'role' => 'alumni',
            'status' => 'inactive',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'User berhasil diperbarui')
            ->assertJsonPath('data.name', 'Nama Baru')
            ->assertJsonPath('data.email', 'emailbaru@example.com')
            ->assertJsonPath('data.phone', '628111222333')
            ->assertJsonPath('data.gender', 'Perempuan')
            ->assertJsonPath('data.graduation_year', '2021')
            ->assertJsonPath('data.birth_date', '2001-02-03')
            ->assertJsonPath('data.role', 'alumni')
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'first_name' => 'Nama',
            'last_name' => 'Baru',
            'email' => 'emailbaru@example.com',
            'phone' => '628111222333',
            'gender' => 'Perempuan',
            'graduation_year' => '2021',
            'birth_date' => '2001-02-03',
            'role' => 'alumni',
            'status' => 'inactive',
        ]);
    }

    public function test_cannot_update_admin_user(): void
    {
        $admin = User::query()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'gender' => 'Laki-laki',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->putJson("/api/users/{$admin->id}", [
            'name' => 'Admin Updated',
            'email' => 'admin.updated@example.com',
            'role' => 'alumni',
            'status' => 'inactive',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'User dengan role admin tidak dapat diubah atau dihapus');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'email' => 'admin@example.com',
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    public function test_cannot_change_user_role_to_admin(): void
    {
        $user = User::query()->create([
            'first_name' => 'Nama',
            'last_name' => 'Lama',
            'gender' => 'Laki-laki',
            'email' => 'lama@example.com',
            'password' => 'password',
            'role' => 'alumni',
            'status' => 'active',
        ]);

        $this->putJson("/api/users/{$user->id}", [
            'name' => 'Nama Baru',
            'email' => 'emailbaru@example.com',
            'role' => 'admin',
            'status' => 'inactive',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Role user tidak dapat diubah menjadi admin melalui endpoint kelola user');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'lama@example.com',
            'role' => 'alumni',
            'status' => 'active',
        ]);
    }

    public function test_can_delete_user(): void
    {
        $user = User::query()->create([
            'first_name' => 'User',
            'last_name' => 'Hapus',
            'gender' => 'Perempuan',
            'email' => 'hapus@example.com',
            'password' => 'password',
            'role' => 'alumni',
            'status' => 'active',
        ]);

        $this->deleteJson("/api/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('message', 'User berhasil dihapus');

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }

    public function test_cannot_delete_admin_user(): void
    {
        $admin = User::query()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'gender' => 'Laki-laki',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->deleteJson("/api/users/{$admin->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'User dengan role admin tidak dapat diubah atau dihapus');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]);
    }
}
