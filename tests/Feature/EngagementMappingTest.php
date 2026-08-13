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

class EngagementMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_get_attendance_based_engagement_mapping(): void
    {
        $admin = $this->admin();
        $category = Category::query()->create([
            'category_name' => 'Seminar',
            'description' => 'Seminar alumni',
        ]);

        $events = collect(range(1, 10))->map(function (int $number) use ($admin, $category) {
            return Event::query()->create([
                'category_id' => $category->id,
                'created_by' => $admin->id,
                'event_title' => "Event {$number}",
                'description' => 'Event engagement',
                'location' => 'Aula',
                'event_date' => now()->subDays(11 - $number)->toDateString(),
                'start_time' => '08:00',
                'end_time' => '10:00',
                'qr_token' => Str::uuid()->toString(),
                'status_event' => 'active',
            ]);
        });

        Event::query()->create([
            'category_id' => $category->id,
            'created_by' => $admin->id,
            'event_title' => 'Future Event',
            'description' => 'Excluded event',
            'location' => 'Aula',
            'event_date' => now()->addDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'qr_token' => Str::uuid()->toString(),
            'status_event' => 'active',
        ]);

        $muqorrobun = $this->alumni('muqorrobun@example.com');
        $mutawasithun = $this->alumni('mutawasithun@example.com');
        $mubtadi = $this->alumni('mubtadi@example.com');
        $ghoir = $this->alumni('ghoir@example.com');

        $this->createAttendances($muqorrobun, $events->take(7));
        $this->createAttendances($mutawasithun, $events->take(4));
        $this->createAttendances($mubtadi, $events->take(1));

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/engagement/attendance-mapping?per_page=20')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.calculation.eligible_events', 10)
            ->assertJsonPath('data.total', 4);

        $itemsByEmail = collect($response->json('data.items'))->keyBy('user.email');

        $this->assertSame('Al-Muqorrobun', $itemsByEmail['muqorrobun@example.com']['segment']);
        $this->assertSame(70, $itemsByEmail['muqorrobun@example.com']['attendance']['percentage']);

        $this->assertSame('Al-Mutawasithun', $itemsByEmail['mutawasithun@example.com']['segment']);
        $this->assertSame(40, $itemsByEmail['mutawasithun@example.com']['attendance']['percentage']);

        $this->assertSame("Al-Mubtadi'un", $itemsByEmail['mubtadi@example.com']['segment']);
        $this->assertSame(10, $itemsByEmail['mubtadi@example.com']['attendance']['percentage']);

        $this->assertSame('Ghoir Mukayyad', $itemsByEmail['ghoir@example.com']['segment']);
        $this->assertSame(0, $itemsByEmail['ghoir@example.com']['attendance']['percentage']);

        $this->getJson('/api/admin/engagement/attendance-mapping?segment=Al-Muqorrobun')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.user.email', 'muqorrobun@example.com')
            ->assertJsonPath('data.items.0.segment', 'Al-Muqorrobun');
    }

    public function test_alumni_cannot_access_engagement_mapping(): void
    {
        Sanctum::actingAs($this->alumni('alumni@example.com'));

        $this->getJson('/api/admin/engagement/attendance-mapping')
            ->assertForbidden();
    }

    public function test_alumni_can_get_personal_engagement_summary(): void
    {
        $admin = $this->admin();
        $alumni = $this->alumni('alumni@example.com');
        $otherAlumni = $this->alumni('other@example.com');
        $category = Category::query()->create([
            'category_name' => 'Kajian',
            'description' => 'Kajian alumni',
        ]);

        $events = collect(range(1, 5))->map(function (int $number) use ($admin, $category) {
            return Event::query()->create([
                'category_id' => $category->id,
                'created_by' => $admin->id,
                'event_title' => "Kajian {$number}",
                'description' => 'Kajian engagement',
                'location' => 'Masjid',
                'event_date' => now()->subDays(6 - $number)->toDateString(),
                'start_time' => '08:00',
                'end_time' => '10:00',
                'qr_token' => Str::uuid()->toString(),
                'status_event' => 'active',
            ]);
        });

        $this->createAttendances($alumni, $events->take(2));
        $this->createAttendances($otherAlumni, $events);

        Sanctum::actingAs($alumni);

        $this->getJson('/api/alumni/engagement/summary')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.attendance.total_events', 5)
            ->assertJsonPath('data.attendance.attended_events', 2)
            ->assertJsonPath('data.attendance.percentage', 40)
            ->assertJsonPath('data.segment', 'Al-Mutawasithun')
            ->assertJsonPath('data.next_segment', 'Al-Muqorrobun')
            ->assertJsonPath('data.remaining_attendances_to_next_segment', 2)
            ->assertJsonCount(2, 'data.recent_attendances');
    }

    private function createAttendances(User $user, $events): void
    {
        foreach ($events as $event) {
            Presensi::query()->create([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'status' => 'hadir',
                'scanned_at' => now(),
            ]);
        }
    }

    private function alumni(string $email): User
    {
        return User::query()->create([
            'first_name' => ucfirst(strstr($email, '@', true)),
            'last_name' => 'User',
            'gender' => 'Laki-laki',
            'email' => $email,
            'password' => 'password123',
            'role' => 'alumni',
            'status' => 'active',
        ]);
    }

    private function admin(): User
    {
        return User::query()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'gender' => 'Laki-laki',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'role' => 'admin',
            'admin_level' => 'admin',
            'status' => 'active',
        ]);
    }
}
