<?php

namespace App\Services;

use App\Models\Event;
use App\Models\WhatsappSetting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class WhatsappService
{
    public const DEFAULT_PROVIDER = 'fonnte';

    public const DEFAULT_URL = 'https://api.fonnte.com/send';

    /**
     * Send broadcast to multiple numbers
     *
     * @param  array  $numbers  Array of phone numbers e.g. ['6281234567890', '6289876543210']
     * @param  string  $message  Message text
     */
    public function sendBroadcast(array $numbers, string $message): array
    {
        $config = $this->configuration();

        if (($config['provider'] ?? null) !== self::DEFAULT_PROVIDER) {
            return [
                'success' => false,
                'message' => 'Provider WhatsApp harus fonnte',
                'sender_status' => 'invalid_provider',
            ];
        }

        if (blank($config['api_token'])) {
            return [
                'success' => false,
                'message' => 'FONNTE_TOKEN belum dikonfigurasi',
                'sender_status' => 'not_configured',
            ];
        }

        if (($config['sender_status'] ?? null) === 'blocked') {
            return [
                'success' => false,
                'message' => 'Nomor WhatsApp terindikasi terblokir atau tidak aktif',
                'sender_status' => 'blocked',
                'blocked_reason' => $config['blocked_reason'] ?? null,
            ];
        }

        if (empty($numbers)) {
            return [
                'success' => false,
                'message' => 'Tidak ada nomor tujuan yang valid',
            ];
        }

        // Fonnte accepts target as a comma-separated string.
        $targets = implode(',', array_values(array_unique($numbers)));

        try {
            $response = Http::withHeaders([
                'Authorization' => $config['api_token'],
            ])
                ->asForm()
                ->timeout(30)
                ->retry(2, 500)
                ->post($config['api_url'], [
                    'target' => $targets,
                    'message' => $message,
                    'schedule' => 0,
                    'typing' => false,
                    'delay' => '2',
                ]);

            $result = $this->safeFonntePayload($response->json() ?? []);
            $fonnteStatus = (bool) ($result['status'] ?? $result['Status'] ?? false);
            $success = $response->successful() && $fonnteStatus;
            $message = $result['detail']
                ?? $result['reason']
                ?? ($success ? 'Broadcast berhasil masuk antrean Fonnte' : 'Broadcast gagal dikirim ke Fonnte');

            Log::info('WhatsApp broadcast response received', [
                'total_numbers' => count($numbers),
                'http_status' => $response->status(),
                'success' => $success,
                'sender_number' => $config['sender_number'],
                'response' => $result,
            ]);

            $senderStatus = $this->senderStatusFromResponse($response, $result);
            if ($senderStatus === 'blocked') {
                $this->markCurrentSettingBlocked($message);
            } elseif ($success) {
                $this->markCurrentSettingActive();
            }

            return [
                'success' => $success,
                'message' => $message,
                'detail' => $result,
                'http_status' => $response->status(),
                'sender_status' => $senderStatus,
                'blocked_reason' => $senderStatus === 'blocked' ? $message : null,
            ];

        } catch (Throwable $e) {
            Log::error('WhatsApp broadcast failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Gagal mengirim broadcast: '.$e->getMessage(),
                'sender_status' => 'error',
            ];
        }
    }

    public function testConnection(array $overrides = []): array
    {
        $config = $this->configuration($overrides);
        $shouldPersistStatus = empty(array_intersect(array_keys($overrides), ['api_url', 'api_token', 'sender_number']));

        if (($config['provider'] ?? null) !== self::DEFAULT_PROVIDER) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => 'Provider WhatsApp harus fonnte',
                'sender_number' => $config['sender_number'] ?? null,
                'sender_status' => 'invalid_provider',
                'http_status' => 422,
                'fonnte' => [],
            ];
        }

        if (blank($config['api_token']) || blank($config['sender_number'])) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => 'api_token dan sender_number wajib dikonfigurasi untuk test koneksi',
                'sender_number' => $config['sender_number'] ?? null,
                'sender_status' => 'not_configured',
                'http_status' => 422,
                'fonnte' => [],
            ];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => $config['api_token'],
            ])
                ->asForm()
                ->timeout(20)
                ->post($config['api_url'], [
                    'target' => $config['sender_number'],
                    'message' => 'Test koneksi WhatsApp Broadcast',
                    'schedule' => 0,
                    'typing' => false,
                    'delay' => '1',
                ]);

            $result = $this->safeFonntePayload($response->json() ?? []);
            $senderStatus = $this->senderStatusFromResponse($response, $result);

            if ($response->successful() && (bool) ($result['status'] ?? $result['Status'] ?? false)) {
                if ($shouldPersistStatus) {
                    $this->markCurrentSettingActive();
                }

                return [
                    'success' => true,
                    'status' => 'connected',
                    'message' => 'Koneksi Fonnte berhasil',
                    'sender_number' => $config['sender_number'],
                    'sender_status' => 'active',
                    'http_status' => 200,
                    'fonnte' => $result,
                ];
            }

            $blockedReason = $result['detail'] ?? $result['reason'] ?? $response->body() ?: 'Koneksi Fonnte gagal';
            if ($senderStatus === 'blocked' && $shouldPersistStatus) {
                $this->markCurrentSettingBlocked($blockedReason);
            }

            return [
                'success' => false,
                'status' => $senderStatus === 'blocked' ? 'blocked' : 'error',
                'message' => $senderStatus === 'blocked'
                    ? 'Nomor WhatsApp terindikasi terblokir atau tidak aktif'
                    : 'Koneksi Fonnte gagal',
                'sender_number' => $config['sender_number'],
                'sender_status' => $senderStatus,
                'blocked_reason' => $senderStatus === 'blocked' ? $blockedReason : null,
                'http_status' => $response->status() >= 400 ? $response->status() : 400,
                'fonnte' => $result,
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => 'Gagal menghubungi Fonnte: '.$e->getMessage(),
                'sender_number' => $config['sender_number'] ?? null,
                'sender_status' => 'error',
                'http_status' => 502,
                'fonnte' => [],
            ];
        }
    }

    public function configuration(array $overrides = []): array
    {
        $setting = $this->currentSetting();

        return array_merge([
            'provider' => $setting?->provider ?? self::DEFAULT_PROVIDER,
            'api_url' => $setting?->api_url ?? config('services.fonnte.url', self::DEFAULT_URL),
            'api_token' => $setting?->api_token ?? config('services.fonnte.token'),
            'sender_number' => $setting?->sender_number,
            'sender_status' => $setting?->sender_status ?? 'unknown',
            'blocked_reason' => $setting?->blocked_reason,
        ], Arr::whereNotNull($overrides));
    }

    public static function maskToken(?string $token): ?string
    {
        if (blank($token)) {
            return null;
        }

        if (strlen($token) <= 10) {
            return substr($token, 0, 2).'******';
        }

        return substr($token, 0, 4).str_repeat('*', 8).substr($token, -4);
    }

    /**
     * Build event broadcast message
     */
    public function buildEventMessage(Event $event): string
    {
        $event->loadMissing('category');

        $eventDate = $event->event_date->translatedFormat('l, d F Y');
        $startTime = substr($event->start_time, 0, 5);
        $endTime = substr($event->end_time, 0, 5);
        $appName = config('app.name');
        $frontendUrl = rtrim(config('services.fonnte.frontend_url'), '/');
        $category = $event->category?->category_name ?? '-';
        $description = $event->description ?: '-';
        $quotaInfo = $event->quota
            ? "👥 Kuota    : {$event->quota} peserta (sisa: {$event->remainingQuota()})\n"
            : '';

        return "🕌 *{$appName}*\n\n"
            ."📢 *INFORMASI EVENT*\n\n"
            ."📌 *{$event->event_title}*\n"
            ."🏷️ Kategori  : {$category}\n"
            ."📝 Deskripsi : {$description}\n\n"
            ."📅 Tanggal   : {$eventDate}\n"
            ."⏰ Waktu     : {$startTime} - {$endTime} WIB\n"
            ."📍 Lokasi    : {$event->location}\n"
            .$quotaInfo
            ."\n📝 *Daftar sekarang sebelum kuota habis:*\n"
            ."{$frontendUrl}/events/{$event->id}/register\n\n"
            ."Presensi dilakukan dengan scan QR Code di lokasi event.\n\n"
            ."_Pesan ini dikirim otomatis oleh sistem {$appName}_";
    }

    /**
     * Pesan reminder untuk alumni yang sudah registrasi
     */
    public function buildReminderMessage(Event $event): string
    {
        $event->loadMissing('category');

        $eventDate = $event->event_date->translatedFormat('l, d F Y');
        $startTime = substr($event->start_time, 0, 5);
        $endTime = substr($event->end_time, 0, 5);
        $appName = config('app.name');

        return "🕌 *{$appName}*\n\n"
            ."⏰ *REMINDER EVENT*\n\n"
            ."Halo! Ini pengingat bahwa kamu telah terdaftar di:\n\n"
            ."📌 *{$event->event_title}*\n"
            ."📅 Tanggal : {$eventDate}\n"
            ."⏰ Waktu   : {$startTime} - {$endTime} WIB\n"
            ."📍 Lokasi  : {$event->location}\n\n"
            ."Jangan lupa hadir ya! Presensi dilakukan dengan\n"
            ."scan QR Code di lokasi event.\n\n"
            ."_Pesan ini dikirim otomatis oleh sistem {$appName}_";
    }

    private function currentSetting(): ?WhatsappSetting
    {
        try {
            if (! Schema::hasTable('whatsapp_settings')) {
                return null;
            }

            return WhatsappSetting::current();
        } catch (Throwable) {
            return null;
        }
    }

    private function markCurrentSettingBlocked(?string $reason): void
    {
        $setting = $this->currentSetting();

        if (! $setting) {
            return;
        }

        $setting->update([
            'sender_status' => 'blocked',
            'blocked_reason' => $reason,
            'last_tested_at' => now(),
        ]);
    }

    private function markCurrentSettingActive(): void
    {
        $setting = $this->currentSetting();

        if (! $setting) {
            return;
        }

        $setting->update([
            'sender_status' => 'active',
            'blocked_reason' => null,
            'last_tested_at' => now(),
        ]);
    }

    private function senderStatusFromResponse(Response $response, array $result): string
    {
        $text = strtolower(json_encode($result).' '.$response->body());

        foreach ([
            'block',
            'blokir',
            'banned',
            'disconnect',
            'not connected',
            'device',
            'invalid token',
            'token invalid',
            'unauthorized',
            'inactive',
            'tidak aktif',
        ] as $needle) {
            if (str_contains($text, $needle)) {
                return 'blocked';
            }
        }

        return $response->successful() && (bool) ($result['status'] ?? $result['Status'] ?? false)
            ? 'active'
            : 'error';
    }

    private function safeFonntePayload(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (in_array(strtolower((string) $key), ['token', 'api_token', 'authorization'], true)) {
                $payload[$key] = self::maskToken((string) $value);
                continue;
            }

            if (is_array($value)) {
                $payload[$key] = $this->safeFonntePayload($value);
            }
        }

        return $payload;
    }
}
