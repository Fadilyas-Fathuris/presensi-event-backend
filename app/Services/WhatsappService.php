<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappService
{
    private string $token;
    private string $url;

    public function __construct()
    {
        $this->token = config('services.fonnte.token');
        $this->url   = config('services.fonnte.url');
    }

    /**
     * Send broadcast to multiple numbers
     *
     * @param array  $numbers  Array of phone numbers e.g. ['6281234567890', '6289876543210']
     * @param string $message  Message text
     * @return array
     */
    public function sendBroadcast(array $numbers, string $message): array
    {
        // Fonnte accepts numbers as comma-separated string
        $targets = implode(',', $numbers);

        Log::debug('Fonnte token debug', [
            'token_value'  => $this->token,
            'token_length' => strlen($this->token ?? ''),
            'token_is_null' => is_null($this->token),
        ]);

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->token,
            ])->asForm()->post($this->url, [
                'target'   => $targets,
                'message'  => $message,
                'schedule' => 0,        // 0 = kirim sekarang
                'typing'   => false,    // simulasi typing
                'delay'    => 2,        // delay antar pesan (detik)
            ]);

            $result = $response->json();

            Log::info('WhatsApp broadcast sent', [
                'total_numbers' => count($numbers),
                'response'      => $result,
            ]);

            return [
                'success' => $response->successful() && ($result['status'] ?? false),
                'message' => $result['reason'] ?? 'Broadcast sent',
                'detail'  => $result,
            ];

        } catch (\Exception $e) {
            Log::error('WhatsApp broadcast failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Gagal mengirim broadcast: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Build event broadcast message
     */
    public function buildEventMessage(Event $event): string
{
    $event->loadMissing('category');

    $eventDate   = $event->event_date->translatedFormat('l, d F Y');
    $startTime   = substr($event->start_time, 0, 5);
    $endTime     = substr($event->end_time,   0, 5);
    $appName     = config('app.name');
    $frontendUrl = rtrim(config('services.fonnte.frontend_url'), '/');
    $quotaInfo   = $event->quota
        ? "👥 Kuota    : {$event->quota} peserta (sisa: {$event->remainingQuota()})\n"
        : "";

    return "🕌 *{$appName}*\n\n"
        . "📢 *INFORMASI EVENT*\n\n"
        . "📌 *{$event->event_title}*\n"
        . "🏷️ Kategori  : {$event->category->category_name}\n"
        . "📝 Deskripsi : {$event->description}\n\n"
        . "📅 Tanggal   : {$eventDate}\n"
        . "⏰ Waktu     : {$startTime} - {$endTime} WIB\n"
        . "📍 Lokasi    : {$event->location}\n"
        . $quotaInfo
        . "\n📝 *Daftar sekarang sebelum kuota habis:*\n"
        . "{$frontendUrl}/events/{$event->id}/register\n\n"  // 👈 link pendaftaran
        . "Presensi dilakukan dengan scan QR Code di lokasi event.\n\n"
        . "_Pesan ini dikirim otomatis oleh sistem {$appName}_";
}

/**
 * Pesan reminder untuk alumni yang sudah registrasi
 */
public function buildReminderMessage(Event $event): string
{
    $event->loadMissing('category');

    $eventDate   = $event->event_date->translatedFormat('l, d F Y');
    $startTime   = substr($event->start_time, 0, 5);
    $endTime     = substr($event->end_time,   0, 5);
    $appName     = config('app.name');

    return "🕌 *{$appName}*\n\n"
        . "⏰ *REMINDER EVENT*\n\n"
        . "Halo! Ini pengingat bahwa kamu telah terdaftar di:\n\n"
        . "📌 *{$event->event_title}*\n"
        . "📅 Tanggal : {$eventDate}\n"
        . "⏰ Waktu   : {$startTime} - {$endTime} WIB\n"
        . "📍 Lokasi  : {$event->location}\n\n"
        . "Jangan lupa hadir ya! Presensi dilakukan dengan\n"
        . "scan QR Code di lokasi event.\n\n"
        . "_Pesan ini dikirim otomatis oleh sistem {$appName}_";
}
}