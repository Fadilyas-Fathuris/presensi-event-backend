<?php

namespace Tests\Unit;

use App\Services\WhatsappService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsappServiceTest extends TestCase
{
    public function test_it_returns_clear_error_when_fonnte_token_is_missing(): void
    {
        config([
            'services.fonnte.token' => null,
            'services.fonnte.url' => 'https://api.fonnte.com/send',
        ]);

        Http::fake();

        $result = (new WhatsappService)->sendBroadcast(['6281234567890'], 'Halo');

        $this->assertFalse($result['success']);
        $this->assertSame('FONNTE_TOKEN belum dikonfigurasi', $result['message']);
        Http::assertNothingSent();
    }

    public function test_it_sends_broadcast_to_fonnte_with_expected_payload(): void
    {
        config([
            'services.fonnte.token' => 'dummy-token',
            'services.fonnte.url' => 'https://api.fonnte.com/send',
        ]);

        Http::fake([
            'api.fonnte.com/send' => Http::response([
                'status' => true,
                'detail' => 'success',
            ]),
        ]);

        $result = (new WhatsappService)->sendBroadcast([
            '6281234567890',
            '6281234567890',
            '6289876543210',
        ], 'Halo alumni');

        $this->assertTrue($result['success']);
        $this->assertSame('success', $result['message']);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.fonnte.com/send'
                && $request->hasHeader('Authorization', 'dummy-token')
                && $request['target'] === '6281234567890,6289876543210'
                && $request['message'] === 'Halo alumni'
                && $request['schedule'] === 0
                && $request['delay'] === '2';
        });
    }
}
