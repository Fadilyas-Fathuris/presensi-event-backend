<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_active_alumni_cannot_login(): void
    {
        $messages = [
            'pending' => 'Akun Anda masih menunggu persetujuan admin.',
            'rejected' => 'Pendaftaran akun Anda ditolak. Hubungi admin.',
            'inactive' => 'Akun Anda sedang dinonaktifkan. Hubungi admin.',
        ];

        foreach ($messages as $status => $message) {
            $user = $this->createUser("{$status}@example.com", 'alumni', $status);
            $user->createToken('old-token');

            $this->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'password123',
            ])
                ->assertForbidden()
                ->assertJson([
                    'success' => false,
                    'message' => $message,
                ]);

            $this->assertDatabaseMissing('personal_access_tokens', [
                'tokenable_id' => $user->id,
                'tokenable_type' => User::class,
            ]);
        }
    }

    public function test_active_alumni_and_admin_can_login(): void
    {
        $activeAlumni = $this->createUser('active@example.com', 'alumni', 'active');
        $inactiveAdmin = $this->createUser('admin@example.com', 'admin', 'inactive');

        $this->postJson('/api/auth/login', [
            'email' => $activeAlumni->email,
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.status', 'active')
            ->assertJsonPath('data.token_type', 'Bearer');

        $this->postJson('/api/auth/login', [
            'email' => $inactiveAdmin->email,
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.role', 'admin');
    }

    public function test_admin_can_approve_reject_and_deactivate_alumni(): void
    {
        $admin = $this->createUser('admin@example.com', 'admin', 'active');
        $pending = $this->createUser('pending@example.com', 'alumni', 'pending');
        $rejected = $this->createUser('rejected@example.com', 'alumni', 'pending');
        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/users/{$pending->id}/status", [
            'status' => 'active',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'User status updated successfully')
            ->assertJsonPath('data.user.status', 'active');

        $pending->createToken('active-token');

        $this->patchJson("/api/admin/users/{$pending->id}/status", [
            'status' => 'inactive',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.status', 'inactive');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $pending->id,
            'tokenable_type' => User::class,
        ]);

        $rejected->createToken('pending-token');

        $this->patchJson("/api/admin/users/{$rejected->id}/status", [
            'status' => 'rejected',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.status', 'rejected');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $rejected->id,
            'tokenable_type' => User::class,
        ]);
    }

    public function test_admin_cannot_change_own_or_another_admin_status(): void
    {
        $admin = $this->createUser('admin@example.com', 'admin', 'active');
        $otherAdmin = $this->createUser('other-admin@example.com', 'admin', 'active');
        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/users/{$admin->id}/status", [
            'status' => 'inactive',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Admin cannot change their own status');

        $this->patchJson("/api/admin/users/{$otherAdmin->id}/status", [
            'status' => 'rejected',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Admin user status cannot be changed');
    }

    public function test_admin_user_list_can_filter_by_status(): void
    {
        $admin = $this->createUser('admin@example.com', 'admin', 'active');
        $pending = $this->createUser('pending@example.com', 'alumni', 'pending');
        $this->createUser('active@example.com', 'alumni', 'active');
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/users?status=pending')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.users')
            ->assertJsonPath('data.users.0.id', $pending->id)
            ->assertJsonPath('data.users.0.status', 'pending');
    }

    public function test_compatibility_user_routes_require_authentication(): void
    {
        $this->getJson('/api/users')->assertUnauthorized();
        $this->putJson('/api/users/1', [])->assertUnauthorized();
        $this->deleteJson('/api/users/1')->assertUnauthorized();
    }

    private function createUser(string $email, string $role, string $status): User
    {
        return User::query()->create([
            'first_name' => ucfirst(strstr($email, '@', true)),
            'last_name' => 'User',
            'gender' => 'Laki-laki',
            'email' => $email,
            'password' => 'password123',
            'role' => $role,
            'status' => $status,
        ]);
    }
}
