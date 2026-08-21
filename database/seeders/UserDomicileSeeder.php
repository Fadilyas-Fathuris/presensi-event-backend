<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Region;
use App\Models\UserDomicile;
use Illuminate\Database\Seeder;

class UserDomicileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::whereDoesntHave('domicile')->get();
        
        if ($users->isEmpty()) {
            $this->command->info('Semua pengguna sudah memiliki data domisili.');
            return;
        }

        $this->command->info("Memulai seeding domisili untuk {$users->count()} pengguna...");

        $successCount = 0;

        // Common street names for random addresses in Indonesia
        $streetNames = ['Jl. Merdeka', 'Jl. Sudirman', 'Jl. Gatot Subroto', 'Jl. Ahmad Yani', 'Jl. Diponegoro', 'Jl. Pahlawan', 'Jl. Gajah Mada', 'Jl. Hayam Wuruk', 'Jl. Mawar', 'Jl. Melati', 'Jl. Dahlia', 'Jl. Flamboyan'];

        foreach ($users as $user) {
            // Find a random province
            $province = Region::where('type', 'province')->inRandomOrder()->first();
            if (!$province) {
                continue;
            }

            // Find a random city in that province
            $city = Region::where('type', 'city')->where('parent_code', $province->code)->inRandomOrder()->first();
            if (!$city) {
                continue;
            }

            // Find a random district in that city
            $district = Region::where('type', 'district')->where('parent_code', $city->code)->inRandomOrder()->first();
            if (!$district) {
                continue;
            }

            // Find a random village in that district
            $village = Region::where('type', 'village')->where('parent_code', $district->code)->inRandomOrder()->first();
            
            $postalCode = $village?->postal_code ?? $district->postal_code ?? $city->postal_code ?? '40111';
            $street = $streetNames[array_rand($streetNames)] . ' No. ' . rand(1, 150) . ', RT ' . rand(1, 12) . '/RW ' . rand(1, 12);

            UserDomicile::create([
                'user_id' => $user->id,
                'province_code' => $province->code,
                'province_name' => $province->name,
                'city_code' => $city->code,
                'city_name' => $city->name,
                'district_code' => $district->code,
                'district_name' => $district->name,
                'village_code' => $village?->code,
                'village_name' => $village?->name,
                'postal_code' => $postalCode,
                'address' => $street,
            ]);

            $successCount++;
        }

        $this->command->info("Berhasil menambahkan data domisili untuk {$successCount} pengguna!");
    }
}
