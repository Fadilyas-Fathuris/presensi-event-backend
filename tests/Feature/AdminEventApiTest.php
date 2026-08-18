<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Presensi;
use App\Models\User;
use App\Models\UserDomicile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminEventApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_event_even_when_notification_table_is_unavailable(): void
    {
        Storage::fake('public');

        $admin = $this->createAdmin();
        $category = Category::query()->create([
            'category_name' => 'Seminar',
            'description' => 'Event seminar',
        ]);

        User::query()->create([
            'first_name' => 'Alumni',
            'gender' => 'Laki-laki',
            'email' => 'alumni@example.com',
            'password' => 'password',
            'role' => 'alumni',
        ]);

        Schema::dropIfExists('alumni_notifications');
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/events', [
            'category_id' => $category->id,
            'event_title' => 'Workshop API Stabil',
            'description' => 'Testing event create tanpa notifikasi.',
            'location' => 'Aula',
            'event_date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'quota' => 100,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.event.event_title', 'Workshop API Stabil')
            ->assertJsonPath('data.event.start_time', '08:00');

        $this->assertDatabaseHas('events', [
            'event_title' => 'Workshop API Stabil',
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);
    }

    public function test_admin_post_update_validates_end_time_against_existing_start_time(): void
    {
        $admin = $this->createAdmin();
        $event = $this->createEvent($admin);

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/events/{$event->id}", [
            'end_time' => '07:30',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('end_time');

        $this->postJson("/api/admin/events/{$event->id}", [
            'end_time' => '11:30:00',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.event.end_time', '11:30');

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'end_time' => '11:30',
        ]);
    }

    public function test_admin_can_list_event_registrations_and_attendances_with_user_aliases(): void
    {
        $admin = $this->createAdmin();
        $event = $this->createEvent($admin);
        $alumni = User::query()->create([
            'first_name' => 'Ahmad',
            'last_name' => 'Fauzi',
            'gender' => 'Laki-laki',
            'email' => 'ahmad@example.com',
            'password' => 'password',
            'phone' => '08123456789',
            'graduation_year' => '2020',
            'role' => 'alumni',
        ]);

        EventRegistration::query()->create([
            'event_id' => $event->id,
            'user_id' => $alumni->id,
            'status' => 'attended',
            'registered_at' => now(),
        ]);

        Presensi::query()->create([
            'event_id' => $event->id,
            'user_id' => $alumni->id,
            'scanned_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $this->getJson("/api/admin/events/{$event->id}/registrations")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.registrations.0.user.name', 'Ahmad Fauzi')
            ->assertJsonPath('data.registrations.0.user.angkatan', '2020')
            ->assertJsonPath('data.registrations.0.attendance.status', 'hadir')
            ->assertJsonPath('data.summary.total_registered', 1)
            ->assertJsonPath('data.summary.total_attended', 1)
            ->assertJsonPath('data.summary.remaining_quota', 99);

        $this->getJson("/api/admin/events/{$event->id}/attendances")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.attendances.0.user.name', 'Ahmad Fauzi')
            ->assertJsonPath('data.attendances.0.user.angkatan', '2020')
            ->assertJsonPath('data.attendances.0.attendance.status', 'hadir')
            ->assertJsonPath('data.summary.total_attended', 1);
    }

    public function test_attendance_breakdown_uses_all_event_attendances_and_includes_domicile(): void
    {
        $admin = $this->createAdmin();
        $event = $this->createEvent($admin);
        $bandung = [
            'province_code' => '32',
            'province_name' => 'Jawa Barat',
            'city_code' => '3273',
            'city_name' => 'Kota Bandung',
        ];
        $bekasi = [
            'province_code' => '32',
            'province_name' => 'Jawa Barat',
            'city_code' => '3275',
            'city_name' => 'Kota Bekasi',
        ];

        $this->createAttendanceAlumni($event, 'Ahmad', '2021', $bandung, 1);
        $this->createAttendanceAlumni($event, 'Budi', '2021', $bandung, 2);
        $this->createAttendanceAlumni($event, 'Citra', '2021', $bandung, 3);
        $this->createAttendanceAlumni($event, 'Dedi', '2020', $bekasi, 4);
        $this->createAttendanceAlumni($event, 'Eka', '2020', $bekasi, 5);
        $this->createAttendanceAlumni($event, 'Fajar', null, null, 6);

        Sanctum::actingAs($admin);

        $pageOne = $this->getJson("/api/admin/events/{$event->id}/attendances?per_page=2&page=1")
            ->assertOk()
            ->assertJsonCount(2, 'data.attendances')
            ->assertJsonPath('data.total', 6)
            ->assertJsonPath('data.attendances.0.user.domicile.city.name', 'Kota Bandung')
            ->json('data');

        $expectedByAngkatan = [
            ['angkatan' => '2021', 'total' => 3],
            ['angkatan' => '2020', 'total' => 2],
            ['angkatan' => 'Tidak diketahui', 'total' => 1],
        ];
        $expectedByDomicile = [
            [
                'city_code' => '3273',
                'city_name' => 'Kota Bandung',
                'province_code' => '32',
                'province_name' => 'Jawa Barat',
                'total' => 3,
            ],
            [
                'city_code' => '3275',
                'city_name' => 'Kota Bekasi',
                'province_code' => '32',
                'province_name' => 'Jawa Barat',
                'total' => 2,
            ],
            [
                'city_code' => null,
                'city_name' => 'Tidak diketahui',
                'province_code' => null,
                'province_name' => null,
                'total' => 1,
            ],
        ];

        $this->assertSame($expectedByAngkatan, $pageOne['breakdown']['by_angkatan']);
        $this->assertSame($expectedByDomicile, $pageOne['breakdown']['by_domicile']);
        $this->assertSame(6, array_sum(array_column($pageOne['breakdown']['by_angkatan'], 'total')));
        $this->assertSame(6, array_sum(array_column($pageOne['breakdown']['by_domicile'], 'total')));

        $pageTwo = $this->getJson("/api/admin/events/{$event->id}/attendances?per_page=2&page=2")
            ->assertOk()
            ->assertJsonCount(2, 'data.attendances')
            ->json('data');
        $this->assertSame($pageOne['breakdown'], $pageTwo['breakdown']);

        $this->getJson("/api/admin/events/{$event->id}/attendances?per_page=2&page=3")
            ->assertOk()
            ->assertJsonPath('data.attendances.1.user.domicile', null);

        $filtered = $this->getJson(
            "/api/admin/events/{$event->id}/attendances?angkatan=2021&domicile_city_code=3273&status=attended&per_page=100"
        )
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->json('data');
        $this->assertSame($pageOne['breakdown'], $filtered['breakdown']);
    }

    public function test_attendances_can_be_sorted_by_angkatan_and_domicile_with_unknown_city_last(): void
    {
        $admin = $this->createAdmin();
        $event = $this->createEvent($admin);
        $bandung = [
            'province_code' => '32',
            'province_name' => 'Jawa Barat',
            'city_code' => '3273',
            'city_name' => 'Kota Bandung',
        ];
        $bekasi = [
            'province_code' => '32',
            'province_name' => 'Jawa Barat',
            'city_code' => '3275',
            'city_name' => 'Kota Bekasi',
        ];

        $this->createAttendanceAlumni($event, 'Zaki', '2021', $bandung, 1);
        $this->createAttendanceAlumni($event, 'Bima', '2019', $bekasi, 2);
        $this->createAttendanceAlumni($event, 'Agus', '2020', null, 3);

        Sanctum::actingAs($admin);

        $angkatanAsc = $this->getJson(
            "/api/admin/events/{$event->id}/attendances?sort_by=angkatan&sort_dir=asc&per_page=100"
        )->assertOk()->json('data.attendances');
        $angkatanDesc = $this->getJson(
            "/api/admin/events/{$event->id}/attendances?sort_by=angkatan&sort_dir=desc&per_page=100"
        )->assertOk()->json('data.attendances');

        $this->assertSame(['2019', '2020', '2021'], array_column(array_column($angkatanAsc, 'user'), 'angkatan'));
        $this->assertSame(['2021', '2020', '2019'], array_column(array_column($angkatanDesc, 'user'), 'angkatan'));

        $domicileAsc = $this->getJson(
            "/api/admin/events/{$event->id}/attendances?sort_by=domicile&sort_dir=asc&per_page=100"
        )->assertOk()->json('data.attendances');
        $domicileDesc = $this->getJson(
            "/api/admin/events/{$event->id}/attendances?sort_by=domicile&sort_dir=desc&per_page=100"
        )->assertOk()->json('data.attendances');

        $this->assertSame(['Zaki', 'Bima', 'Agus'], array_column(array_column($domicileAsc, 'user'), 'first_name'));
        $this->assertSame(['Bima', 'Zaki', 'Agus'], array_column(array_column($domicileDesc, 'user'), 'first_name'));
        $this->assertNull($domicileAsc[2]['user']['domicile']);
        $this->assertNull($domicileDesc[2]['user']['domicile']);
    }

    public function test_attendance_breakdown_orders_angkatan_in_php_without_sql_order_by(): void
    {
        $admin = $this->createAdmin();
        $event = $this->createEvent($admin);

        foreach ([
            ['Year2020', '2020'],
            ['Year2022', '2022'],
            ['Year2021', '2021'],
            ['YearNull', null],
            ['YearEmpty', ''],
        ] as $index => [$name, $graduationYear]) {
            $this->createAttendanceAlumni(
                $event,
                $name,
                $graduationYear,
                null,
                $index + 1,
            );
        }

        $executedSql = [];
        DB::listen(function ($query) use (&$executedSql): void {
            $executedSql[] = strtolower($query->sql);
        });
        Sanctum::actingAs($admin);

        $breakdown = $this->getJson("/api/admin/events/{$event->id}/attendances?per_page=1")
            ->assertOk()
            ->json('data.breakdown');

        $this->assertSame([
            ['angkatan' => '2022', 'total' => 1],
            ['angkatan' => '2021', 'total' => 1],
            ['angkatan' => '2020', 'total' => 1],
            ['angkatan' => 'Tidak diketahui', 'total' => 2],
        ], $breakdown['by_angkatan']);

        $aggregationQueries = collect($executedSql)
            ->filter(fn (string $sql) => str_contains($sql, 'presensis') && str_contains($sql, 'group by'))
            ->values();

        $this->assertCount(2, $aggregationQueries);
        $aggregationQueries->each(function (string $sql): void {
            $this->assertStringNotContainsString('order by', $sql);
        });
    }

    public function test_attendance_breakdown_orders_domicile_buckets_by_total_then_city_with_unknown_last(): void
    {
        $admin = $this->createAdmin();
        $event = $this->createEvent($admin);
        $cities = [
            'Bandung' => [
                'province_code' => '32',
                'province_name' => 'Jawa Barat',
                'city_code' => '3273',
                'city_name' => 'Kota Bandung',
                'total' => 2,
            ],
            'Bekasi' => [
                'province_code' => '32',
                'province_name' => 'Jawa Barat',
                'city_code' => '3275',
                'city_name' => 'Kota Bekasi',
                'total' => 4,
            ],
            'Depok' => [
                'province_code' => '32',
                'province_name' => 'Jawa Barat',
                'city_code' => '3276',
                'city_name' => 'Kota Depok',
                'total' => 2,
            ],
        ];
        $scanOrder = 1;

        foreach ($cities as $label => $city) {
            for ($index = 1; $index <= $city['total']; $index++) {
                $domicile = $city;
                unset($domicile['total']);
                $this->createAttendanceAlumni(
                    $event,
                    "{$label}{$index}",
                    '2021',
                    $domicile,
                    $scanOrder++,
                );
            }
        }

        for ($index = 1; $index <= 5; $index++) {
            $this->createAttendanceAlumni(
                $event,
                "Unknown{$index}",
                '2021',
                null,
                $scanOrder++,
            );
        }

        Sanctum::actingAs($admin);

        $byDomicile = $this->getJson("/api/admin/events/{$event->id}/attendances?per_page=1")
            ->assertOk()
            ->json('data.breakdown.by_domicile');

        $this->assertSame([
            [
                'city_code' => '3275',
                'city_name' => 'Kota Bekasi',
                'province_code' => '32',
                'province_name' => 'Jawa Barat',
                'total' => 4,
            ],
            [
                'city_code' => '3273',
                'city_name' => 'Kota Bandung',
                'province_code' => '32',
                'province_name' => 'Jawa Barat',
                'total' => 2,
            ],
            [
                'city_code' => '3276',
                'city_name' => 'Kota Depok',
                'province_code' => '32',
                'province_name' => 'Jawa Barat',
                'total' => 2,
            ],
            [
                'city_code' => null,
                'city_name' => 'Tidak diketahui',
                'province_code' => null,
                'province_name' => null,
                'total' => 5,
            ],
        ], $byDomicile);
    }

    public function test_admin_event_list_exposes_realtime_quota_status(): void
    {
        $admin = $this->createAdmin();
        $event = $this->createEvent($admin, quota: 1);
        $alumni = User::query()->create([
            'first_name' => 'Siti',
            'gender' => 'Perempuan',
            'email' => 'siti@example.com',
            'password' => 'password',
            'role' => 'alumni',
        ]);

        EventRegistration::query()->create([
            'event_id' => $event->id,
            'user_id' => $alumni->id,
            'status' => 'registered',
            'registered_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/events')
            ->assertOk()
            ->assertJsonPath('data.events.0.quota', 1)
            ->assertJsonPath('data.events.0.quota_used', 1)
            ->assertJsonPath('data.events.0.remaining_quota', 0)
            ->assertJsonPath('data.events.0.is_quota_full', true)
            ->assertJsonPath('data.events.0.quota_message', 'Kuota penuh, segera hubungi penyelenggara');
    }

    public function test_event_date_before_today_is_rejected_for_create_and_both_update_routes(): void
    {
        $admin = $this->createAdmin();
        $event = $this->createEvent($admin);
        $pastDate = now()->subDay()->format('Y-m-d');
        $message = 'Tanggal event tidak boleh lebih awal dari hari ini.';

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/events', [
            'category_id' => $event->category_id,
            'event_title' => 'Event Tanggal Lampau',
            'location' => 'Aula',
            'event_date' => $pastDate,
            'start_time' => '08:00',
            'end_time' => '10:00',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.event_date.0', $message);

        $this->putJson("/api/admin/events/{$event->id}", [
            'event_date' => $pastDate,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.event_date.0', $message);

        $this->postJson("/api/admin/events/{$event->id}", [
            'event_date' => $pastDate,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.event_date.0', $message);
    }

    public function test_event_date_today_is_accepted_for_create_and_both_update_routes(): void
    {
        $admin = $this->createAdmin();
        $event = $this->createEvent($admin);
        $today = now()->format('Y-m-d');

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/events', [
            'category_id' => $event->category_id,
            'event_title' => 'Event Hari Ini',
            'location' => 'Aula',
            'event_date' => $today,
            'start_time' => '08:00',
            'end_time' => '10:00',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $createdEvent = Event::query()
            ->where('event_title', 'Event Hari Ini')
            ->firstOrFail();
        $this->assertSame($today, $createdEvent->event_date->format('Y-m-d'));

        $this->putJson("/api/admin/events/{$event->id}", [
            'event_date' => $today,
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->postJson("/api/admin/events/{$event->id}", [
            'event_date' => $today,
        ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    private function createAdmin(): User
    {
        return User::query()->create([
            'first_name' => 'Admin',
            'gender' => 'Laki-laki',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);
    }

    private function createEvent(User $admin, int $quota = 100): Event
    {
        $category = Category::query()->create([
            'category_name' => 'Reuni',
            'description' => 'Event reuni',
        ]);

        return Event::query()->create([
            'category_id' => $category->id,
            'created_by' => $admin->id,
            'event_title' => 'Event Existing',
            'description' => 'Event untuk update.',
            'location' => 'Aula',
            'event_date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'qr_token' => Str::uuid()->toString(),
            'status_event' => 'active',
            'quota' => $quota,
        ]);
    }

    private function createAttendanceAlumni(
        Event $event,
        string $firstName,
        ?string $graduationYear,
        ?array $domicile,
        int $scanOrder
    ): User {
        $user = User::query()->create([
            'first_name' => $firstName,
            'gender' => 'Laki-laki',
            'email' => strtolower($firstName)."-{$event->id}@example.com",
            'password' => 'password',
            'phone' => '08123'.str_pad((string) $scanOrder, 6, '0', STR_PAD_LEFT),
            'graduation_year' => $graduationYear,
            'role' => 'alumni',
        ]);

        if ($domicile !== null) {
            UserDomicile::query()->create([
                'user_id' => $user->id,
                ...$domicile,
                'district_code' => '3273010',
                'district_name' => 'Kecamatan Contoh',
                'village_code' => '3273010001',
                'village_name' => 'Kelurahan Contoh',
                'postal_code' => '40111',
                'address' => "Alamat {$firstName}",
            ]);
        }

        Presensi::query()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => 'hadir',
            'scanned_at' => now()->addSeconds($scanOrder),
        ]);

        return $user;
    }
}
