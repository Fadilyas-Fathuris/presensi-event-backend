<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BroadcastControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_broadcast_is_disabled_and_never_calls_the_fonnte_client(): void
    {
        Sanctum::actingAs($this->admin());
        $event = $this->event();

        Http::fake();

        $this->postJson("/api/admin/events/{$event->id}/broadcast", [
            'target' => 'custom',
            'numbers' => ['081234567890'],
        ])
            ->assertStatus(503)
            ->assertExactJson([
                'success' => false,
                'code' => 'WHATSAPP_AUTOMATIC_SEND_DISABLED',
                'message' => 'Pengiriman otomatis WhatsApp sedang dinonaktifkan. Gunakan kirim manual.',
            ]);

        Http::assertNothingSent();
    }

    public function test_preview_provides_normalized_target_numbers_for_manual_sending(): void
    {
        $admin = $this->admin();
        $event = $this->event();
        User::query()->create([
            'first_name' => 'Alumni',
            'gender' => 'Laki-laki',
            'email' => 'alumni@example.com',
            'password' => 'password',
            'phone' => '081234567890',
            'role' => 'alumni',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson("/api/admin/events/{$event->id}/broadcast/preview?target=all")
            ->assertOk()
            ->assertJsonPath('data.target_numbers', ['6281234567890'])
            ->assertJsonPath('data.total_targets', 1);
    }

    private function admin(): User
    {
        return User::query()->create([
            'first_name' => 'Admin',
            'gender' => 'Laki-laki',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);
    }

    private function event(): Event
    {
        $category = Category::query()->create([
            'category_name' => 'Seminar',
            'description' => 'Seminar alumni',
        ]);

        return Event::query()->create([
            'category_id' => $category->id,
            'created_by' => User::query()->where('role', 'admin')->value('id'),
            'event_title' => 'Event Broadcast',
            'location' => 'Aula',
            'event_date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'qr_token' => Str::uuid()->toString(),
            'status_event' => 'active',
        ]);
    }
}
