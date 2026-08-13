<?php

namespace Tests\Feature;

use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DomicileTest extends TestCase
{
    use RefreshDatabase;

    public function test_region_dropdown_endpoints_return_hierarchical_data(): void
    {
        $this->seedRegions();

        $this->getJson('/api/regions/provinces')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.code', '32')
            ->assertJsonPath('data.0.name', 'Jawa Barat');

        $this->getJson('/api/regions/cities?province_code=32')
            ->assertOk()
            ->assertJsonPath('data.0.code', '32.73')
            ->assertJsonPath('data.0.name', 'Kota Bandung');

        $this->getJson('/api/regions/districts?city_code=32.73')
            ->assertOk()
            ->assertJsonPath('data.0.code', '32.73.01');

        $this->getJson('/api/regions/villages?district_code=32.73.01')
            ->assertOk()
            ->assertJsonPath('data.0.code', '32.73.01.1001')
            ->assertJsonPath('data.0.postal_code', '40154');
    }

    public function test_register_with_valid_domicile_creates_user_domicile(): void
    {
        $this->seedRegions();

        $this->postJson('/api/auth/register', $this->registerPayload([
            'domicile_province_code' => '32',
            'domicile_city_code' => '32.73',
            'domicile_district_code' => '32.73.01',
            'domicile_village_code' => '32.73.01.1001',
            'domicile_address' => 'Jl. Contoh No. 10',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.user.domicile.province.name', 'Jawa Barat')
            ->assertJsonPath('data.user.domicile.city.name', 'Kota Bandung')
            ->assertJsonPath('data.user.domicile.postal_code', '40154');

        $this->assertDatabaseHas('user_domiciles', [
            'province_code' => '32',
            'city_code' => '32.73',
            'district_code' => '32.73.01',
            'village_code' => '32.73.01.1001',
            'postal_code' => '40154',
            'address' => 'Jl. Contoh No. 10',
        ]);
    }

    public function test_register_rejects_invalid_domicile_hierarchy(): void
    {
        $this->seedRegions();

        $this->postJson('/api/auth/register', $this->registerPayload([
            'domicile_province_code' => '32',
            'domicile_city_code' => '33.02',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('domicile_city_code');
    }

    public function test_alumni_can_update_profile_domicile(): void
    {
        $this->seedRegions();
        $user = $this->alumni();
        Sanctum::actingAs($user);

        $this->putJson('/api/auth/profile', [
            'domicile_province_code' => '32',
            'domicile_city_code' => '32.73',
            'domicile_district_code' => '32.73.01',
            'domicile_village_code' => '32.73.01.1001',
            'domicile_address' => 'Jl. Baru No. 2',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.domicile.village.name', 'Isola')
            ->assertJsonPath('data.user.domicile.address', 'Jl. Baru No. 2');

        $this->assertDatabaseHas('user_domiciles', [
            'user_id' => $user->id,
            'village_code' => '32.73.01.1001',
            'address' => 'Jl. Baru No. 2',
        ]);
    }

    public function test_admin_can_filter_alumni_by_domicile_city(): void
    {
        $this->seedRegions();
        $admin = $this->admin();
        $bandungAlumni = $this->alumni('bandung@example.com');
        $semarangAlumni = $this->alumni('semarang@example.com');

        $bandungAlumni->domicile()->create([
            'province_code' => '32',
            'province_name' => 'Jawa Barat',
            'city_code' => '32.73',
            'city_name' => 'Kota Bandung',
        ]);

        $semarangAlumni->domicile()->create([
            'province_code' => '33',
            'province_name' => 'Jawa Tengah',
            'city_code' => '33.02',
            'city_name' => 'Kabupaten Semarang',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/users?domicile_city_code=32.73')
            ->assertOk()
            ->assertJsonCount(1, 'data.users')
            ->assertJsonPath('data.users.0.id', $bandungAlumni->id)
            ->assertJsonPath('data.users.0.domicile.city.name', 'Kota Bandung');
    }

    private function seedRegions(): void
    {
        Region::query()->insert([
            ['code' => '32', 'name' => 'Jawa Barat', 'type' => 'province', 'parent_code' => null, 'postal_code' => null, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '32.73', 'name' => 'Kota Bandung', 'type' => 'city', 'parent_code' => '32', 'postal_code' => null, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '32.73.01', 'name' => 'Sukasari', 'type' => 'district', 'parent_code' => '32.73', 'postal_code' => null, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '32.73.01.1001', 'name' => 'Isola', 'type' => 'village', 'parent_code' => '32.73.01', 'postal_code' => '40154', 'created_at' => now(), 'updated_at' => now()],
            ['code' => '33', 'name' => 'Jawa Tengah', 'type' => 'province', 'parent_code' => null, 'postal_code' => null, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '33.02', 'name' => 'Kabupaten Semarang', 'type' => 'city', 'parent_code' => '33', 'postal_code' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function registerPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Ahmad',
            'last_name' => 'Fauzi',
            'gender' => 'Laki-laki',
            'email' => 'ahmad@example.com',
            'phone' => '6281234567890',
            'graduation_year' => 2020,
            'birth_date' => '2000-01-01',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    private function alumni(string $email = 'alumni@example.com'): User
    {
        return User::query()->create([
            'first_name' => 'Alumni',
            'last_name' => 'User',
            'gender' => 'Laki-laki',
            'email' => $email,
            'password' => 'password123',
            'role' => 'alumni',
            'status' => 'active',
        ]);
    }

    private function admin(): User
    {
        return User::query()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'gender' => 'Laki-laki',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'role' => 'admin',
            'admin_level' => 'admin',
            'status' => 'active',
        ]);
    }
}
