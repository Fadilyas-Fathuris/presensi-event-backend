<?php

namespace Tests\Feature;

use App\Models\AlumniNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AlumniNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_alumni_can_only_list_their_own_notifications(): void
    {
        $alumni = $this->createAlumni('alumni@example.com');
        $otherAlumni = $this->createAlumni('other@example.com');

        $ownNotification = AlumniNotification::query()->create([
            'user_id' => $alumni->id,
            'title' => 'Event baru',
            'body' => 'Ada event baru untuk Anda.',
            'type' => 'event',
            'priority' => 'normal',
            'data' => ['event_id' => 10],
        ]);

        AlumniNotification::query()->create([
            'user_id' => $otherAlumni->id,
            'title' => 'Milik user lain',
            'body' => 'Tidak boleh terlihat.',
            'type' => 'event',
            'priority' => 'high',
        ]);

        Sanctum::actingAs($alumni);

        $this->getJson('/api/alumni/notifications')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.notifications')
            ->assertJsonPath('data.notifications.0.id', $ownNotification->id)
            ->assertJsonPath('data.notifications.0.title', 'Event baru')
            ->assertJsonPath('data.notifications.0.message', 'Ada event baru untuk Anda.')
            ->assertJsonPath('data.notifications.0.body', 'Ada event baru untuk Anda.')
            ->assertJsonPath('data.notifications.0.type', 'event')
            ->assertJsonPath('data.notifications.0.priority', 'normal')
            ->assertJsonPath('data.notifications.0.is_read', false)
            ->assertJsonPath('data.notifications.0.data.event_id', 10)
            ->assertJsonMissing(['title' => 'Milik user lain']);
    }

    public function test_unread_count_only_counts_authenticated_alumni_notifications(): void
    {
        $alumni = $this->createAlumni('alumni@example.com');
        $otherAlumni = $this->createAlumni('other@example.com');

        AlumniNotification::query()->create([
            'user_id' => $alumni->id,
            'title' => 'Unread 1',
            'body' => 'Belum dibaca.',
        ]);

        AlumniNotification::query()->create([
            'user_id' => $alumni->id,
            'title' => 'Read',
            'body' => 'Sudah dibaca.',
            'is_read' => true,
            'read_at' => now(),
        ]);

        AlumniNotification::query()->create([
            'user_id' => $otherAlumni->id,
            'title' => 'Unread user lain',
            'body' => 'Tidak dihitung.',
        ]);

        Sanctum::actingAs($alumni);

        $this->getJson('/api/alumni/notifications/unread-count')
            ->assertOk()
            ->assertExactJson([
                'unread_count' => 1,
            ]);
    }

    public function test_alumni_can_mark_their_notification_as_read_idempotently(): void
    {
        $alumni = $this->createAlumni('alumni@example.com');

        $notification = AlumniNotification::query()->create([
            'user_id' => $alumni->id,
            'title' => 'Reminder',
            'body' => 'Jangan lupa hadir.',
        ]);

        Sanctum::actingAs($alumni);

        $this->putJson("/api/alumni/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.notification.is_read', true);

        $this->assertDatabaseHas('alumni_notifications', [
            'id' => $notification->id,
            'is_read' => true,
        ]);

        $this->assertNotNull($notification->refresh()->read_at);

        $readAt = $notification->read_at;

        $this->putJson("/api/alumni/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.notification.is_read', true);

        $this->assertTrue($readAt->equalTo($notification->refresh()->read_at));
    }

    public function test_alumni_cannot_mark_another_alumni_notification_as_read(): void
    {
        $alumni = $this->createAlumni('alumni@example.com');
        $otherAlumni = $this->createAlumni('other@example.com');

        $otherNotification = AlumniNotification::query()->create([
            'user_id' => $otherAlumni->id,
            'title' => 'Private',
            'body' => 'Milik alumni lain.',
        ]);

        Sanctum::actingAs($alumni);

        $this->putJson("/api/alumni/notifications/{$otherNotification->id}/read")
            ->assertNotFound();

        $this->assertDatabaseHas('alumni_notifications', [
            'id' => $otherNotification->id,
            'is_read' => false,
            'read_at' => null,
        ]);
    }

    public function test_alumni_can_mark_all_their_unread_notifications_as_read(): void
    {
        $alumni = $this->createAlumni('alumni@example.com');
        $otherAlumni = $this->createAlumni('other@example.com');

        $first = AlumniNotification::query()->create([
            'user_id' => $alumni->id,
            'title' => 'Unread 1',
            'body' => 'Belum dibaca.',
        ]);

        $second = AlumniNotification::query()->create([
            'user_id' => $alumni->id,
            'title' => 'Unread 2',
            'body' => 'Belum dibaca juga.',
        ]);

        $alreadyRead = AlumniNotification::query()->create([
            'user_id' => $alumni->id,
            'title' => 'Read',
            'body' => 'Sudah dibaca.',
            'is_read' => true,
            'read_at' => now()->subDay(),
        ]);

        $otherNotification = AlumniNotification::query()->create([
            'user_id' => $otherAlumni->id,
            'title' => 'Other',
            'body' => 'Milik user lain.',
        ]);

        Sanctum::actingAs($alumni);

        $this->putJson('/api/alumni/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('updated_count', 2);

        $this->assertTrue($first->refresh()->is_read);
        $this->assertTrue($second->refresh()->is_read);
        $this->assertTrue($alreadyRead->refresh()->is_read);
        $this->assertFalse($otherNotification->refresh()->is_read);
        $this->assertNotNull($first->read_at);
        $this->assertNotNull($second->read_at);
    }

    public function test_admin_cannot_access_alumni_notification_endpoints(): void
    {
        $admin = User::query()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'gender' => 'Perempuan',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/alumni/notifications')
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden. Alumni access only.');
    }

    private function createAlumni(string $email): User
    {
        return User::query()->create([
            'first_name' => 'Alumni',
            'last_name' => 'User',
            'gender' => 'Laki-laki',
            'email' => $email,
            'password' => 'password',
            'role' => 'alumni',
        ]);
    }
}
