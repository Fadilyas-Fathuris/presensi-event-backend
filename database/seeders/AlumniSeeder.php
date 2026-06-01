<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AlumniSeeder extends Seeder
{
    public function run(): void
    {
        $alumni = [
            [
                'email' => 'ahmad@gmail.com',
                'first_name' => 'Ahmad',
                'last_name' => 'Fauzi',
                'password' => Hash::make('password123'),
                'phone' => '081234567890',
                'graduation_year' => '2018',
                'birth_date' => '2000-05-15',
                'gender' => 'Laki-laki',
                'role' => 'alumni',
                'status' => 'active',
            ],
            [
                'email' => 'siti@gmail.com',
                'first_name' => 'Siti',
                'last_name' => 'Aminah',
                'password' => Hash::make('password123'),
                'phone' => '082345678901',
                'graduation_year' => '2019',
                'birth_date' => '2001-08-20',
                'gender' => 'Perempuan',
                'role' => 'alumni',
                'status' => 'active',
            ],
            [
                'email' => 'rizky@gmail.com',
                'first_name' => 'Rizky',
                'last_name' => 'Pratama',
                'password' => Hash::make('password123'),
                'phone' => '083456789012',
                'graduation_year' => '2020',
                'birth_date' => '2002-11-10',
                'gender' => 'Laki-laki',
                'role' => 'alumni',
                'status' => 'active',
            ],
            [
                'email' => 'fatimah@gmail.com',
                'first_name' => 'Fatimah',
                'last_name' => 'Azzahra',
                'password' => Hash::make('password123'),
                'phone' => '084567890123',
                'graduation_year' => '2021',
                'birth_date' => '2003-03-05',
                'gender' => 'Perempuan',
                'role' => 'alumni',
                'status' => 'active',
            ],
        ];

        foreach ($alumni as $data) {
            User::updateOrCreate(
                ['email' => $data['email']],
                $data
            );
        }

        $this->command->info('Alumni users seeded successfully!');
    }
}
