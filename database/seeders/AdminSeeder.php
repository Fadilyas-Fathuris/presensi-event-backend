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
                'name'     => 'Super Admin',
                'email'    => 'admin@pesantren.com',
                'password' => Hash::make('admin123'),
                'phone'    => '081234567890',
                'angkatan' => null,
                'role'     => 'admin',
            ]
        );

        $this->command->info('Admin seeded successfully!');
    }
}