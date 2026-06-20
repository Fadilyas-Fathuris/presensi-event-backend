<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@pesantren.com'],
            [
                'first_name' => 'Admin',
                'last_name' => 'Pesantren',
                'email' => 'admin@pesantren.com',
                'password' => Hash::make('admin123'),
                'phone' => '081234567890',
                'gender' => 'Laki-laki',
                'role' => 'admin',
                'status' => 'active',
            ]
        );

        $this->command->info('Admin seeded successfully!');
    }
}
