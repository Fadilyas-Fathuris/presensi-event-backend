<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Presensi;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first();
        if (!$admin) {
            $admin = User::create([
                'first_name' => 'Admin',
                'last_name' => 'Pesantren',
                'email' => 'admin@pesantren.com',
                'password' => bcrypt('admin123'),
                'role' => 'admin',
                'status' => 'active',
                'gender' => 'Laki-laki',
            ]);
        }

        $categories = Category::all()->pluck('id', 'category_name');

        if ($categories->isEmpty()) {
            $this->command->error('Categories must be seeded first! Run CategorySeeder.');
            return;
        }

        $eventsData = [
            [
                'event_title' => 'Reuni Akbar Pondok Pesantren 2026',
                'description' => 'Temu kangen alumni lintas angkatan pondok pesantren. Mari bernostalgia dan menjalin silaturahmi erat.',
                'location' => 'Aula Utama Pondok Pesantren',
                'event_date' => now()->addDays(7)->format('Y-m-d'),
                'start_time' => '08:00:00',
                'end_time' => '15:00:00',
                'category_id' => $categories['Reuni'] ?? 1,
                'quota' => 500,
            ],
            [
                'event_title' => 'Kajian Bulanan & Doa Bersama',
                'description' => 'Kajian keislaman rutin bulanan khusus alumni bersama jajaran pimpinan pondok pesantren.',
                'location' => 'Masjid Jami\' Pesantren',
                'event_date' => now()->format('Y-m-d'),
                'start_time' => '19:30:00',
                'end_time' => '21:30:00',
                'category_id' => $categories['Pengajian'] ?? 2,
                'quota' => 200,
            ],
            [
                'event_title' => 'Workshop Karir & Sharing Alumni',
                'description' => 'Sharing session dan workshop bimbingan karir oleh para alumni sukses untuk siswa aktif dan alumni muda.',
                'location' => 'Gedung Serbaguna Lt. 2',
                'event_date' => now()->addDay()->format('Y-m-d'),
                'start_time' => '09:00:00',
                'end_time' => '12:00:00',
                'category_id' => $categories['Seminar'] ?? 3,
                'quota' => 100,
            ],
            [
                'event_title' => 'Bakti Sosial & Pembagian Sembako',
                'description' => 'Kegiatan kepedulian sosial alumni pesantren berupa pembagian paket sembako kepada masyarakat sekitar pesantren.',
                'location' => 'Lapangan Desa Binaan',
                'event_date' => now()->addDays(3)->format('Y-m-d'),
                'start_time' => '08:00:00',
                'end_time' => '11:30:00',
                'category_id' => $categories['Bakti Sosial'] ?? 4,
                'quota' => 150,
            ],
            [
                'event_title' => 'Turnamen Futsal Lintas Angkatan',
                'description' => 'Turnamen futsal persahabatan antar angkatan alumni untuk mempererat tali persaudaraan dan kebersamaan.',
                'location' => 'GOR Futsal Pesantren',
                'event_date' => now()->addDays(5)->format('Y-m-d'),
                'start_time' => '13:00:00',
                'end_time' => '17:00:00',
                'category_id' => $categories['Olahraga'] ?? 5,
                'quota' => 80,
            ],
            [
                'event_title' => 'Halal Bihalal & Silaturahmi Syawal',
                'description' => 'Acara silaturahmi besar pasca perayaan Hari Raya Idul Fitri untuk menyambung ukhuwah islamiyah.',
                'location' => 'Aula Utama Pondok Pesantren',
                'event_date' => now()->subDays(10)->format('Y-m-d'),
                'start_time' => '09:00:00',
                'end_time' => '13:00:00',
                'category_id' => $categories['Reuni'] ?? 1,
                'quota' => 300,
            ],
        ];

        $alumniUsers = User::where('role', 'alumni')->get();

        foreach ($eventsData as $data) {
            $event = Event::updateOrCreate(
                ['event_title' => $data['event_title']],
                array_merge($data, [
                    'created_by' => $admin->id,
                    'qr_token' => Str::uuid()->toString(),
                    'status_event' => 'active',
                ])
            );

            // Seed registrations and presences for this event
            if ($alumniUsers->isNotEmpty()) {
                // Semua alumni terdaftar di Reuni Akbar & Kajian Bulanan
                if (Str::contains($event->event_title, ['Reuni Akbar', 'Kajian Bulanan', 'Workshop'])) {
                    foreach ($alumniUsers as $alumni) {
                        $isKajian = Str::contains($event->event_title, 'Kajian');
                        
                        EventRegistration::updateOrCreate(
                            ['event_id' => $event->id, 'user_id' => $alumni->id],
                            [
                                'status' => $isKajian ? 'attended' : 'registered',
                                'registered_at' => now()->subDays(2),
                            ]
                        );

                        // Kajian Bulanan hari ini otomatis tercatat hadir
                        if ($isKajian) {
                            Presensi::updateOrCreate(
                                ['event_id' => $event->id, 'user_id' => $alumni->id],
                                ['scanned_at' => now()->subHours(1)]
                            );
                        }
                    }
                }
            }
        }

        $this->command->info('Events, registrations, and presences seeded successfully!');
    }
}
