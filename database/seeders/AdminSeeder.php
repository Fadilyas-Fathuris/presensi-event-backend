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
            ['email' => 'admin@gmail.com'],
            [
                'first_name'    => 'Admin',              
                'email'         => 'admin@pesantren.com',
                'password'      => Hash::make('admin123'),
                'phone'         => '081234567890',
                'role'          => 'admin',
            ]
        );

        $this->command->info('Admin seeded successfully!');
    }
}