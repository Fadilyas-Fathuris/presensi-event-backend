<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Presensi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first();
        if (! $admin) {
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
                'registered_count' => 34,
                'attended_count' => 0,
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
                'registered_count' => 36,
                'attended_count' => 30,
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
                'registered_count' => 28,
                'attended_count' => 0,
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
                'registered_count' => 22,
                'attended_count' => 0,
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
                'registered_count' => 24,
                'attended_count' => 0,
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
                'registered_count' => 32,
                'attended_count' => 26,
            ],
        ];

        $year = now()->year;
        $chartEvents = [
            ['Silaturahmi Awal Tahun Alumni', 'Reuni', 1, 18, 24, 'Aula Utama Pondok Pesantren'],
            ['Daurah Tahsin Alumni', 'Pengajian', 2, 22, 27, 'Masjid Jami\' Pesantren'],
            ['Seminar Wirausaha Alumni', 'Seminar', 3, 15, 21, 'Gedung Serbaguna Lt. 2'],
            ['Gerakan Wakaf Buku Alumni', 'Bakti Sosial', 4, 27, 31, 'Perpustakaan Pesantren'],
            ['Liga Persahabatan Alumni', 'Olahraga', 5, 12, 18, 'GOR Futsal Pesantren'],
            ['Temu Kangen Angkatan Muda', 'Reuni', 6, 34, 38, 'Aula Utama Pondok Pesantren'],
            ['Kajian Keluarga Sakinah', 'Pengajian', 7, 29, 35, 'Masjid Jami\' Pesantren'],
            ['Pelatihan Digital Marketing', 'Seminar', 8, 21, 28, 'Laboratorium Komputer'],
            ['Donor Darah Alumni Peduli', 'Bakti Sosial', 9, 31, 36, 'Klinik Pesantren'],
            ['Fun Run Alumni Sehat', 'Olahraga', 10, 25, 30, 'Lapangan Pesantren'],
            ['Forum Mentoring Santri Akhir', 'Seminar', 11, 37, 40, 'Gedung Serbaguna Lt. 2'],
            ['Muhasabah Akhir Tahun Alumni', 'Pengajian', 12, 33, 39, 'Masjid Jami\' Pesantren'],
        ];

        foreach ($chartEvents as [$title, $categoryName, $month, $attendedCount, $registeredCount, $location]) {
            $date = Carbon::create($year, $month, min(12 + $month, 28));

            $eventsData[] = [
                'event_title' => $title,
                'description' => 'Data demo untuk memperkaya grafik presensi dashboard admin sepanjang tahun.',
                'location' => $location,
                'event_date' => $date->format('Y-m-d'),
                'start_time' => '08:00:00',
                'end_time' => '12:00:00',
                'category_id' => $categories[$categoryName] ?? $categories->first(),
                'quota' => 100,
                'registered_count' => $registeredCount,
                'attended_count' => $attendedCount,
            ];
        }

        $alumniUsers = User::where('role', 'alumni')
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        foreach ($eventsData as $data) {
            $registeredCount = $data['registered_count'] ?? 0;
            $attendedCount = $data['attended_count'] ?? 0;
            unset($data['registered_count'], $data['attended_count']);

            $event = Event::updateOrCreate(
                ['event_title' => $data['event_title']],
                array_merge($data, [
                    'created_by' => $admin->id,
                    'qr_token' => Str::uuid()->toString(),
                    'status_event' => 'active',
                ])
            );

            if ($alumniUsers->isNotEmpty()) {
                $this->seedEventAudience($event, $alumniUsers, $registeredCount, $attendedCount);
            }
        }

        $this->command->info('Events, registrations, and presences seeded successfully!');
    }

    private function seedEventAudience(Event $event, Collection $alumniUsers, int $registeredCount, int $attendedCount): void
    {
        $registeredCount = min($registeredCount, $alumniUsers->count());
        $attendedCount = min($attendedCount, $registeredCount);
        $registeredAt = Carbon::parse($event->event_date)->subDays(5)->setTime(9, 0);
        $scannedAt = Carbon::parse($event->event_date)->setTimeFromTimeString($event->start_time)->addMinutes(45);

        $participants = $alumniUsers->take($registeredCount)->values();

        foreach ($participants as $index => $alumni) {
            $isAttended = $index < $attendedCount;

            EventRegistration::updateOrCreate(
                ['event_id' => $event->id, 'user_id' => $alumni->id],
                [
                    'status' => $isAttended ? 'attended' : 'registered',
                    'registered_at' => $registeredAt->copy()->addMinutes($index * 7),
                ]
            );

            if ($isAttended) {
                Presensi::updateOrCreate(
                    ['event_id' => $event->id, 'user_id' => $alumni->id],
                    [
                        'status' => 'hadir',
                        'scanned_at' => $scannedAt->copy()->addMinutes($index * 3),
                    ]
                );
            }
        }
    }
}
