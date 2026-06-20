<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\EventQrCode;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QrCodeFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_generates_qr_payload_without_backend_image_file(): void
    {
        Storage::fake('public');

        $admin = $this->createUser('admin@example.com', 'admin');
        $event = $this->createEvent($admin);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/admin/events/{$event->id}/qr/generate", [
            'valid_from' => now()->subMinute()->toDateTimeString(),
            'timeout_minutes' => 60,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.qr_code.qr_code_image', null)
            ->assertJsonPath('data.qr_code.qr_code_url', null);

        $qrToken = $response->json('data.qr_code.qr_token');

        $this->assertTrue(Str::isUuid($qrToken));
        $this->assertSame($qrToken, $response->json('data.qr_code.qr_payload'));
        Storage::disk('public')->assertMissing("qrcodes/{$qrToken}.svg");
    }

    public function test_presensi_scan_normalizes_uuid_from_scanner_url(): void
    {
        $admin = $this->createUser('admin@example.com', 'admin');
        $alumni = $this->createUser('alumni@example.com', 'alumni');
        $event = $this->createEvent($admin);
        $qrToken = Str::uuid()->toString();

        EventQrCode::query()->create([
            'event_id' => $event->id,
            'qr_token' => $qrToken,
            'valid_from' => now()->subMinute(),
            'timeout_minutes' => 60,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        EventRegistration::query()->create([
            'event_id' => $event->id,
            'user_id' => $alumni->id,
            'status' => 'registered',
            'registered_at' => now(),
        ]);

        Sanctum::actingAs($alumni);

        $this->postJson('/api/presensi/scan', [
            'qr_token' => " https://example.test/presensi/scan?qr_token={$qrToken}\n",
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Presensi berhasil dicatat')
            ->assertJsonPath('data.attendance.status', 'hadir');

        $this->assertDatabaseHas('presensis', [
            'event_id' => $event->id,
            'user_id' => $alumni->id,
            'status' => 'hadir',
        ]);
        $this->assertDatabaseHas('event_registrations', [
            'event_id' => $event->id,
            'user_id' => $alumni->id,
            'status' => 'attended',
        ]);
    }

    public function test_presensi_scan_errors_do_not_return_http_200(): void
    {
        $admin = $this->createUser('admin@example.com', 'admin');
        $alumni = $this->createUser('alumni@example.com', 'alumni');
        $event = $this->createEvent($admin);
        $qrToken = Str::uuid()->toString();

        EventQrCode::query()->create([
            'event_id' => $event->id,
            'qr_token' => $qrToken,
            'valid_from' => now()->subMinute(),
            'timeout_minutes' => 60,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        EventRegistration::query()->create([
            'event_id' => $event->id,
            'user_id' => $alumni->id,
            'status' => 'registered',
            'registered_at' => now(),
        ]);

        Sanctum::actingAs($alumni);

        $this->postJson('/api/presensi/scan', ['qr_token' => ''])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->postJson('/api/presensi/scan', ['qr_token' => 'not-a-uuid'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->postJson('/api/presensi/scan', ['qr_token' => Str::uuid()->toString()])
            ->assertNotFound()
            ->assertJsonPath('success', false);

        $this->postJson('/api/presensi/scan', ['qr_token' => $qrToken])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->postJson('/api/presensi/scan', ['qr_token' => $qrToken])
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Kamu sudah melakukan presensi untuk event ini');
    }

    public function test_registration_is_rejected_when_quota_is_full(): void
    {
        $admin = $this->createUser('admin@example.com', 'admin');
        $firstAlumni = $this->createUser('first@example.com', 'alumni');
        $secondAlumni = $this->createUser('second@example.com', 'alumni');
        $event = $this->createEvent($admin, quota: 1);

        EventRegistration::query()->create([
            'event_id' => $event->id,
            'user_id' => $firstAlumni->id,
            'status' => 'registered',
            'registered_at' => now(),
        ]);

        Sanctum::actingAs($secondAlumni);

        $this->postJson("/api/events/{$event->id}/register")
            ->assertBadRequest()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Kuota penuh, segera hubungi penyelenggara');
    }

    public function test_event_list_marks_events_registered_by_authenticated_alumni(): void
    {
        $admin = $this->createUser('admin@example.com', 'admin');
        $alumni = $this->createUser('alumni@example.com', 'alumni');
        $registeredEvent = $this->createEvent($admin);
        $unregisteredEvent = $this->createEvent($admin);

        EventRegistration::query()->create([
            'event_id' => $registeredEvent->id,
            'user_id' => $alumni->id,
            'status' => 'registered',
            'registered_at' => now(),
        ]);

        Sanctum::actingAs($alumni);

        $response = $this->getJson('/api/events?per_page=10')
            ->assertOk()
            ->assertJsonPath('success', true);

        $eventsById = collect($response->json('data.events'))->keyBy('id');

        $this->assertTrue($eventsById[$registeredEvent->id]['is_registered']);
        $this->assertFalse($eventsById[$unregisteredEvent->id]['is_registered']);
    }

    public function test_event_detail_marks_event_registered_by_authenticated_alumni(): void
    {
        $admin = $this->createUser('admin@example.com', 'admin');
        $alumni = $this->createUser('alumni@example.com', 'alumni');
        $registeredEvent = $this->createEvent($admin);
        $unregisteredEvent = $this->createEvent($admin);

        EventRegistration::query()->create([
            'event_id' => $registeredEvent->id,
            'user_id' => $alumni->id,
            'status' => 'registered',
            'registered_at' => now(),
        ]);

        Sanctum::actingAs($alumni);

        $this->getJson("/api/events/{$registeredEvent->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.event.is_registered', true)
            ->assertJsonPath('data.is_registered', true);

        $this->getJson("/api/events/{$unregisteredEvent->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.event.is_registered', false)
            ->assertJsonPath('data.is_registered', false);
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

    private function createEvent(User $admin, int $quota = 100): Event
    {
        $category = Category::query()->create([
            'category_name' => 'Seminar',
            'description' => 'Event seminar',
        ]);

        return Event::query()->create([
            'category_id' => $category->id,
            'created_by' => $admin->id,
            'event_title' => 'Event QR Stabil',
            'description' => 'Event untuk testing QR.',
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
