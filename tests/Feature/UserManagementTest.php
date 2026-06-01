<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_users_for_kelola_user_page(): void
    {
        User::query()->create([
            'first_name' => 'Admin',
            'last_name'  => 'User',
            'gender'     => 'Laki-laki',
            'email'      => 'admin@example.com',
            'password'   => 'password',
            'role'       => 'admin',
            'status'     => 'active',
        ]);

        $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Admin User')
            ->assertJsonPath('data.0.email', 'admin@example.com')
            ->assertJsonPath('data.0.role', 'admin')
            ->assertJsonPath('data.0.status', 'active')
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'name',
                        'email',
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
            'last_name'  => 'Lama',
            'gender'     => 'Laki-laki',
            'email'      => 'lama@example.com',
            'password'   => 'password',
            'role'       => 'alumni',
            'status'     => 'active',
        ]);

        $this->putJson("/api/users/{$user->id}", [
            'name'   => 'Nama Baru',
            'email'  => 'emailbaru@example.com',
            'role'   => 'admin',
            'status' => 'inactive',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'User berhasil diperbarui')
            ->assertJsonPath('data.name', 'Nama Baru')
            ->assertJsonPath('data.email', 'emailbaru@example.com')
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('users', [
            'id'         => $user->id,
            'first_name' => 'Nama',
            'last_name'  => 'Baru',
            'email'      => 'emailbaru@example.com',
            'role'       => 'admin',
            'status'     => 'inactive',
        ]);
    }

    public function test_can_delete_user(): void
    {
        $user = User::query()->create([
            'first_name' => 'User',
            'last_name'  => 'Hapus',
            'gender'     => 'Perempuan',
            'email'      => 'hapus@example.com',
            'password'   => 'password',
            'role'       => 'alumni',
            'status'     => 'active',
        ]);

        $this->deleteJson("/api/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('message', 'User berhasil dihapus');

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }
}
