<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsappSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WhatsappSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_store_and_read_masked_whatsapp_setting(): void
    {
        Sanctum::actingAs($this->admin());

        $response = $this->putJson('/api/settings/whatsapp', [
            'provider' => 'fonnte',
            'api_url' => 'https://api.fonnte.com/send',
            'api_token' => 'FONNTE_SECRET_TOKEN',
            'sender_number' => '628123456789',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.provider', 'fonnte')
            ->assertJsonPath('data.api_url', 'https://api.fonnte.com/send')
            ->assertJsonPath('data.sender_number', '628123456789')
            ->assertJsonPath('data.is_configured', true);

        $this->assertNotSame(
            'FONNTE_SECRET_TOKEN',
            DB::table('whatsapp_settings')->value('api_token')
        );

        $this->getJson('/api/settings/whatsapp')
            ->assertOk()
            ->assertJsonPath('provider', 'fonnte')
            ->assertJsonPath('sender_number', '628123456789')
            ->assertJsonPath('is_configured', true)
            ->assertJsonMissing(['api_token' => 'FONNTE_SECRET_TOKEN']);
    }

    public function test_test_connection_uses_stored_configuration_and_sends_only_to_sender_number(): void
    {
        Sanctum::actingAs($this->admin());

        WhatsappSetting::query()->create([
            'provider' => 'fonnte',
            'api_url' => 'https://api.fonnte.com/send',
            'api_token' => 'stored-token',
            'sender_number' => '628123456789',
        ]);

        Http::fake([
            'api.fonnte.com/send' => Http::response([
                'status' => true,
                'detail' => 'success',
            ]),
        ]);

        $this->postJson('/api/settings/whatsapp/test')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'connected')
            ->assertJsonPath('sender_status', 'active');

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.fonnte.com/send'
                && $request->hasHeader('Authorization', 'stored-token')
                && $request['target'] === '628123456789'
                && $request['message'] === 'Test koneksi WhatsApp Broadcast';
        });
    }

    public function test_first_configuration_requires_token(): void
    {
        Sanctum::actingAs($this->admin());

        $this->putJson('/api/settings/whatsapp', [
            'provider' => 'fonnte',
            'api_url' => 'https://api.fonnte.com/send',
            'sender_number' => '628123456789',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['api_token']);
    }

    private function admin(): User
    {
        return User::query()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'gender' => 'Laki-laki',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);
    }
}
