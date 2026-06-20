<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\Presensi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_get_attendance_chart_for_all_events(): void
    {
        $admin = $this->createAdmin();
        $eventA = $this->createEvent($admin, 'Event A');
        $eventB = $this->createEvent($admin, 'Event B');

        $this->createPresence($eventA, '2026-01-10 08:00:00');
        $this->createPresence($eventA, '2026-01-11 08:00:00');
        $this->createPresence($eventB, '2026-02-12 08:00:00');
        $this->createPresence($eventB, '2026-02-13 08:00:00', 'batal');
        $this->createPresence($eventB, '2025-01-10 08:00:00');

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/dashboard/attendance-chart?year=2026&months=3&event_id=all');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.year', 2026)
            ->assertJsonPath('data.event_id', null)
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.monthly.0.month', 1)
            ->assertJsonPath('data.monthly.0.label', 'Jan')
            ->assertJsonPath('data.monthly.0.total', 2)
            ->assertJsonPath('data.monthly.1.total', 1)
            ->assertJsonPath('data.monthly.2.total', 0);
    }

    public function test_admin_can_filter_attendance_chart_by_event(): void
    {
        $admin = $this->createAdmin();
        $eventA = $this->createEvent($admin, 'Event A');
        $eventB = $this->createEvent($admin, 'Event B');

        $this->createPresence($eventA, '2026-01-10 08:00:00');
        $this->createPresence($eventB, '2026-01-11 08:00:00');

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/admin/dashboard/attendance-chart?year=2026&months=12&event_id={$eventA->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.event_id', $eventA->id)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.monthly.0.total', 1)
            ->assertJsonPath('data.monthly.1.total', 0)
            ->assertJsonCount(12, 'data.monthly');
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

    private function createEvent(User $admin, string $title): Event
    {
        $category = Category::query()->create([
            'category_name' => $title,
            'description' => 'Kategori dashboard',
        ]);

        return Event::query()->create([
            'category_id' => $category->id,
            'created_by' => $admin->id,
            'event_title' => $title,
            'description' => 'Event dashboard.',
            'location' => 'Aula',
            'event_date' => '2026-01-01',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'qr_token' => Str::uuid()->toString(),
            'status_event' => 'active',
            'quota' => 100,
        ]);
    }

    private function createPresence(Event $event, string $scannedAt, string $status = 'hadir'): void
    {
        $user = User::query()->create([
            'first_name' => Str::random(8),
            'gender' => 'Laki-laki',
            'email' => Str::random(8) . '@example.com',
            'password' => 'password',
            'role' => 'alumni',
        ]);

        Presensi::query()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => $status,
            'scanned_at' => $scannedAt,
        ]);
    }
}
