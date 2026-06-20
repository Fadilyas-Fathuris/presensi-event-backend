<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Presensi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventRecommendationTest extends TestCase
{
    use RefreshDatabase;

    public function test_alumni_gets_upcoming_recommendations_from_categories_attended_at_least_twice(): void
    {
        $this->travelTo('2026-06-20 08:00:00');

        $admin = $this->createUser('admin@example.com', 'admin');
        $alumni = $this->createUser('alumni@example.com', 'alumni');
        $seminar = $this->createCategory('Seminar');
        $workshop = $this->createCategory('Workshop');

        $this->attend($alumni, $this->createEvent($admin, $seminar, 'Seminar Lama 1', '2026-06-01'));
        $this->attend($alumni, $this->createEvent($admin, $seminar, 'Seminar Lama 2', '2026-06-02'));
        $this->attend($alumni, $this->createEvent($admin, $workshop, 'Workshop Lama', '2026-06-03'));

        $laterToday = $this->createEvent($admin, $seminar, 'Seminar Nanti Sore', '2026-06-20', '15:00:00');
        $tomorrowMorning = $this->createEvent($admin, $seminar, 'Seminar Besok Pagi', '2026-06-21', '09:00:00');
        $tomorrowAfternoon = $this->createEvent($admin, $seminar, 'Seminar Besok Siang', '2026-06-21', '13:00:00');
        $nextWeek = $this->createEvent($admin, $seminar, 'Seminar Minggu Depan', '2026-06-27', '09:00:00');
        $anotherFuture = $this->createEvent($admin, $seminar, 'Seminar Lain', '2026-06-28', '09:00:00');
        $sixthFuture = $this->createEvent($admin, $seminar, 'Seminar Keenam', '2026-06-29', '09:00:00');

        $this->createEvent($admin, $seminar, 'Seminar Sudah Lewat Hari Ini', '2026-06-20', '07:00:00');
        $this->createEvent($admin, $seminar, 'Seminar Nonaktif', '2026-06-22', '09:00:00', 'inactive');
        $this->createEvent($admin, $workshop, 'Workshop Rekomendasi', '2026-06-22', '09:00:00');

        $registeredEvent = $this->createEvent($admin, $seminar, 'Seminar Sudah Daftar', '2026-06-23', '09:00:00');
        EventRegistration::query()->create([
            'event_id' => $registeredEvent->id,
            'user_id' => $alumni->id,
            'status' => 'registered',
            'registered_at' => now(),
        ]);

        $attendedFutureEvent = $this->createEvent($admin, $seminar, 'Seminar Sudah Presensi', '2026-06-24', '09:00:00');
        $this->attend($alumni, $attendedFutureEvent);

        Sanctum::actingAs($alumni);

        $this->getJson('/api/alumni/recommendations')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.id', $laterToday->id)
            ->assertJsonPath('data.0.event_title', 'Seminar Nanti Sore')
            ->assertJsonPath('data.0.event_datetime', '2026-06-20T15:00:00')
            ->assertJsonPath('data.0.event_date', '2026-06-20')
            ->assertJsonPath('data.0.start_time', '15:00:00')
            ->assertJsonPath('data.0.end_time', '12:00:00')
            ->assertJsonPath('data.0.location', 'Aula Utama')
            ->assertJsonPath('data.0.category.id', $seminar->id)
            ->assertJsonPath('data.0.category.category_name', 'Seminar')
            ->assertJsonPath('data.1.id', $tomorrowMorning->id)
            ->assertJsonPath('data.2.id', $tomorrowAfternoon->id)
            ->assertJsonPath('data.3.id', $nextWeek->id)
            ->assertJsonPath('data.4.id', $anotherFuture->id)
            ->assertJsonMissing(['id' => $sixthFuture->id])
            ->assertJsonMissing(['event_title' => 'Workshop Rekomendasi'])
            ->assertJsonMissing(['event_title' => 'Seminar Sudah Daftar'])
            ->assertJsonMissing(['event_title' => 'Seminar Sudah Presensi']);
    }

    public function test_recommendations_are_empty_when_no_category_reaches_two_attendances(): void
    {
        $this->travelTo('2026-06-20 08:00:00');

        $admin = $this->createUser('admin@example.com', 'admin');
        $alumni = $this->createUser('alumni@example.com', 'alumni');
        $seminar = $this->createCategory('Seminar');
        $workshop = $this->createCategory('Workshop');

        $this->attend($alumni, $this->createEvent($admin, $seminar, 'Seminar Lama', '2026-06-01'));
        $this->createEvent($admin, $seminar, 'Seminar Mendatang', '2026-06-21');
        $this->createEvent($admin, $workshop, 'Workshop Mendatang', '2026-06-21');

        Sanctum::actingAs($alumni);

        $this->getJson('/api/alumni/recommendations')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', []);
    }

    private function createUser(string $email, string $role): User
    {
        return User::query()->create([
            'first_name' => ucfirst($role),
            'gender' => 'Laki-laki',
            'email' => $email,
            'password' => 'password',
            'role' => $role,
        ]);
    }

    private function createCategory(string $name): Category
    {
        return Category::query()->create([
            'category_name' => $name,
            'description' => "Kategori {$name}",
        ]);
    }

    private function createEvent(
        User $admin,
        Category $category,
        string $title,
        string $date,
        string $startTime = '09:00:00',
        string $status = 'active'
    ): Event {
        return Event::query()->create([
            'category_id' => $category->id,
            'created_by' => $admin->id,
            'event_title' => $title,
            'description' => "Deskripsi {$title}",
            'location' => 'Aula Utama',
            'event_date' => $date,
            'start_time' => $startTime,
            'end_time' => '12:00:00',
            'qr_token' => Str::uuid()->toString(),
            'status_event' => $status,
            'quota' => 100,
        ]);
    }

    private function attend(User $alumni, Event $event): void
    {
        Presensi::query()->create([
            'event_id' => $event->id,
            'user_id' => $alumni->id,
            'scanned_at' => now(),
        ]);
    }
}
