<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AlumniSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password123');

        $alumni = [
            [
                'email' => 'ahmad@gmail.com',
                'first_name' => 'Ahmad',
                'last_name' => 'Fauzi',
                'password' => $password,
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
                'password' => $password,
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
                'password' => $password,
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
                'password' => $password,
                'phone' => '084567890123',
                'graduation_year' => '2021',
                'birth_date' => '2003-03-05',
                'gender' => 'Perempuan',
                'role' => 'alumni',
                'status' => 'active',
            ],
        ];

        $generatedAlumni = [
            ['Nurul', 'Hidayah', 'Perempuan', '2012'],
            ['Muhammad', 'Ilham', 'Laki-laki', '2013'],
            ['Dewi', 'Lestari', 'Perempuan', '2014'],
            ['Fajar', 'Ramadhan', 'Laki-laki', '2015'],
            ['Aisyah', 'Putri', 'Perempuan', '2016'],
            ['Bagus', 'Setiawan', 'Laki-laki', '2017'],
            ['Nadia', 'Rahmawati', 'Perempuan', '2018'],
            ['Hendra', 'Saputra', 'Laki-laki', '2019'],
            ['Maya', 'Salsabila', 'Perempuan', '2020'],
            ['Yusuf', 'Hidayat', 'Laki-laki', '2021'],
            ['Zahra', 'Nabila', 'Perempuan', '2022'],
            ['Farhan', 'Hakim', 'Laki-laki', '2023'],
            ['Laila', 'Khairunnisa', 'Perempuan', '2011'],
            ['Rafi', 'Maulana', 'Laki-laki', '2012'],
            ['Anisa', 'Fitriani', 'Perempuan', '2013'],
            ['Dimas', 'Ardiansyah', 'Laki-laki', '2014'],
            ['Salma', 'Kamila', 'Perempuan', '2015'],
            ['Irfan', 'Syahputra', 'Laki-laki', '2016'],
            ['Hanifah', 'Mubarokah', 'Perempuan', '2017'],
            ['Taufik', 'Ridwan', 'Laki-laki', '2018'],
            ['Citra', 'Permata', 'Perempuan', '2019'],
            ['Galih', 'Prakoso', 'Laki-laki', '2020'],
            ['Aulia', 'Safitri', 'Perempuan', '2021'],
            ['Rangga', 'Prayoga', 'Laki-laki', '2022'],
            ['Indah', 'Puspita', 'Perempuan', '2023'],
            ['Bima', 'Nugroho', 'Laki-laki', '2010'],
            ['Rina', 'Marlina', 'Perempuan', '2011'],
            ['Arif', 'Wibowo', 'Laki-laki', '2012'],
            ['Miftah', 'Jannah', 'Perempuan', '2013'],
            ['Sulaiman', 'Akbar', 'Laki-laki', '2014'],
            ['Nisa', 'Amalia', 'Perempuan', '2015'],
            ['Wildan', 'Fikri', 'Laki-laki', '2016'],
            ['Khadijah', 'Amani', 'Perempuan', '2017'],
            ['Reza', 'Pahlevi', 'Laki-laki', '2018'],
            ['Vina', 'Oktaviani', 'Perempuan', '2019'],
            ['Hamzah', 'Alfarizi', 'Laki-laki', '2020'],
        ];

        foreach ($generatedAlumni as $index => [$firstName, $lastName, $gender, $graduationYear]) {
            $sequence = $index + 1;
            $alumni[] = [
                'email' => sprintf('alumni%02d@pesantren.test', $sequence),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'password' => $password,
                'phone' => '0857'.str_pad((string) $sequence, 8, '0', STR_PAD_LEFT),
                'graduation_year' => $graduationYear,
                'birth_date' => sprintf('%d-%02d-%02d', 1992 + ($sequence % 11), ($sequence % 12) + 1, ($sequence % 27) + 1),
                'gender' => $gender,
                'role' => 'alumni',
                'status' => $sequence % 13 === 0 ? 'inactive' : 'active',
            ];
        }

        foreach ($alumni as $data) {
            User::updateOrCreate(
                ['email' => $data['email']],
                $data
            );
        }

        $this->command->info('Alumni users seeded successfully!');
    }
}
