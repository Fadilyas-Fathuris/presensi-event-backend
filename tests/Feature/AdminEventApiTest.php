<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Presensi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertJsonPath('data.registrations.0.attendance.status', 'attended')
            ->assertJsonPath('data.summary.total_registered', 1)
            ->assertJsonPath('data.summary.total_attended', 1)
            ->assertJsonPath('data.summary.remaining_quota', 99);

        $this->getJson("/api/admin/events/{$event->id}/attendances")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.attendances.0.user.name', 'Ahmad Fauzi')
            ->assertJsonPath('data.attendances.0.user.angkatan', '2020')
            ->assertJsonPath('data.attendances.0.attendance.status', 'attended')
            ->assertJsonPath('data.summary.total_attended', 1);
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
}
