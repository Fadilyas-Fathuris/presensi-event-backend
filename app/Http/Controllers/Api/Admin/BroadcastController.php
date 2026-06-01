<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BroadcastLog;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\WhatsappService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class BroadcastController extends Controller
{
    public function __construct(private WhatsappService $whatsapp) {}

    #[OA\Post(
        path: '/api/admin/events/{id}/broadcast',
        operationId: 'broadcastEvent',
        summary: 'Broadcast event info via WhatsApp',
        description: 'Sends WhatsApp broadcast to alumni. Target options: "all" = semua alumni, "registered" = yang sudah daftar event ini, "custom" = nomor pilihan manual.',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Broadcast'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Event ID',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    // ✅ Gunakan string langsung
                    new OA\Property(
                        property: 'target',
                        type: 'string',
                        enum: ['all', 'registered', 'custom'],
                        example: 'all',
                        description: 'all = semua alumni | registered = yang sudah daftar event ini | custom = input nomor manual'
                    ),
                    new OA\Property(
                        property: 'numbers',
                        type: 'array',
                        items: new OA\Items(type: 'string', example: '6281234567890'),
                        description: 'Wajib diisi jika target = custom'
                    ),
                    new OA\Property(
                        property: 'custom_message',
                        type: 'string',
                        nullable: true,
                        description: 'Opsional — override pesan default',
                        example: null
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Broadcast sent successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Broadcast berhasil dikirim ke 45 alumni'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'target', type: 'string', example: 'registered'),
                                new OA\Property(property: 'total_sent', type: 'integer', example: 45),
                                new OA\Property(property: 'event', ref: '#/components/schemas/Event'),
                                new OA\Property(
                                    property: 'fonnte',
                                    type: 'object',
                                    nullable: true,
                                    example: ['status' => true, 'detail' => 'success']
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Broadcast failed atau tidak ada penerima', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Event not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function send(Request $request, int $id): JsonResponse
    {
        $event = Event::with('category')->find($id);

        if (! $event) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found',
            ], 404);
        }

        $validated = $request->validate([
            'target' => 'sometimes|string|in:all,registered,custom',
            'numbers' => 'required_if:target,custom|array|min:1',
            'numbers.*' => 'required|string',
            'custom_message' => 'nullable|string|max:60000',
        ]);

        $target = $validated['target'] ?? 'all';

        // ── Tentukan penerima berdasarkan target ──────────────────────────────
        $numbers = match ($target) {

            // Semua alumni yang punya nomor HP
            'all' => User::where('role', 'alumni')
                ->whereNotNull('phone')
                ->pluck('phone')
                ->map(fn ($phone) => $this->formatPhoneNumber($phone))
                ->filter()
                ->unique()
                ->values()
                ->toArray(),

            // Hanya alumni yang sudah registrasi event ini
            'registered' => EventRegistration::where('event_id', $event->id)
                ->with('user:id,phone')
                ->get()
                ->pluck('user.phone')
                ->filter()
                ->map(fn ($phone) => $this->formatPhoneNumber($phone))
                ->filter()
                ->unique()
                ->values()
                ->toArray(),

            // Input nomor manual dari request
            'custom' => collect($validated['numbers'] ?? [])
                ->map(fn ($phone) => $this->formatPhoneNumber($phone))
                ->filter()
                ->unique()
                ->values()
                ->toArray(),
        };

        if (empty($numbers)) {
            return response()->json([
                'success' => false,
                'message' => match ($target) {
                    'all' => 'Tidak ada alumni dengan nomor HP terdaftar',
                    'registered' => 'Belum ada alumni yang mendaftar event ini',
                    'custom' => 'Tidak ada nomor tujuan yang valid',
                },
            ], 400);
        }

        // ── Build & kirim pesan ───────────────────────────────────────────────
        $message = $validated['custom_message']
            ?? $this->buildMessageByTarget($event, $target);

        $result = $this->whatsapp->sendBroadcast($numbers, $message);

        if (! $result['success']) {
            BroadcastLog::query()->create([
                'event_id' => $event->id,
                'target' => $target,
                'total_targets' => count($numbers),
                'status' => ($result['sender_status'] ?? null) === 'blocked' ? 'blocked' : 'failed',
                'sender_status' => $result['sender_status'] ?? null,
                'message' => $result['message'],
                'blocked_reason' => $result['blocked_reason'] ?? null,
                'fonnte' => $result['detail'] ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'sender_status' => $result['sender_status'] ?? null,
                'blocked_reason' => $result['blocked_reason'] ?? null,
                'data' => [
                    'target' => $target,
                    'total_targets' => count($numbers),
                    'fonnte' => $result['detail'] ?? null,
                    'sender_status' => $result['sender_status'] ?? null,
                    'blocked_reason' => $result['blocked_reason'] ?? null,
                ],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Broadcast berhasil dikirim ke '.count($numbers).' alumni',
            'data' => [
                'target' => $target,
                'total_sent' => count($numbers),
                'event' => $event,
                'fonnte' => $result['detail'] ?? null,
                'sender_status' => $result['sender_status'] ?? null,
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/admin/events/{id}/broadcast/preview',
        operationId: 'previewBroadcast',
        summary: 'Preview broadcast message',
        description: 'Returns preview pesan WA dan jumlah penerima berdasarkan target.',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Broadcast'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
            new OA\Parameter(
                name: 'target',
                in: 'query',
                required: false,
                description: 'all | registered | custom',
                schema: new OA\Schema(type: 'string', enum: ['all', 'registered', 'custom'], example: 'all')
            ),
            new OA\Parameter(
                name: 'numbers[]',
                in: 'query',
                required: false,
                description: 'Nomor tujuan untuk preview target custom. Bisa dikirim berulang, contoh numbers[]=081234567890&numbers[]=628987654321.',
                schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string'))
            ),
            new OA\Parameter(
                name: 'custom_message',
                in: 'query',
                required: false,
                description: 'Opsional untuk preview pesan custom',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Preview pesan broadcast',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'message', type: 'string', example: '🕌 *Presensi Event Alumni Pesantren*...'),
                                new OA\Property(property: 'target', type: 'string', example: 'registered'),
                                new OA\Property(property: 'total_targets', type: 'integer', example: 45),
                                new OA\Property(
                                    property: 'breakdown',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'total_all', type: 'integer', example: 120),
                                        new OA\Property(property: 'total_registered', type: 'integer', example: 45),
                                        new OA\Property(property: 'total_custom', type: 'integer', example: 2),
                                    ]
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Event not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function preview(Request $request, int $id): JsonResponse
    {
        $event = Event::with('category')->find($id);

        if (! $event) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found',
            ], 404);
        }

        $validated = $request->validate([
            'target' => 'sometimes|string|in:all,registered,custom',
            'numbers' => 'sometimes|array',
            'numbers.*' => 'required|string',
            'custom_message' => 'nullable|string|max:60000',
        ]);

        $target = $validated['target'] ?? 'all';
        $message = $validated['custom_message'] ?? $this->buildMessageByTarget($event, $target);

        $totalAll = User::where('role', 'alumni')
            ->whereNotNull('phone')
            ->pluck('phone')
            ->map(fn ($phone) => $this->formatPhoneNumber($phone))
            ->filter()
            ->unique()
            ->count();

        $totalRegistered = EventRegistration::where('event_id', $event->id)
            ->with('user:id,phone')
            ->get()
            ->pluck('user.phone')
            ->filter()
            ->map(fn ($phone) => $this->formatPhoneNumber($phone))
            ->filter()
            ->unique()
            ->count();

        $totalCustom = collect($validated['numbers'] ?? [])
            ->map(fn ($phone) => $this->formatPhoneNumber($phone))
            ->filter()
            ->unique()
            ->count();

        $totalTargets = match ($target) {
            'registered' => $totalRegistered,
            'custom' => $totalCustom,
            default => $totalAll,
        };

        return response()->json([
            'success' => true,
            'data' => [
                'message' => $message,
                'target' => $target,
                'total_targets' => $totalTargets,
                'breakdown' => [
                    'total_all' => $totalAll,
                    'total_registered' => $totalRegistered,
                    'total_custom' => $totalCustom,
                ],
            ],
        ]);
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    /**
     * Build pesan sesuai target — registered dapat pesan reminder,
     * all/custom dapat pesan undangan biasa
     */
    private function buildMessageByTarget(Event $event, string $target): string
    {
        if ($target === 'registered') {
            return $this->whatsapp->buildReminderMessage($event);
        }

        return $this->whatsapp->buildEventMessage($event);
    }

    /**
     * Format nomor HP ke format internasional 62xxx
     */
    private function formatPhoneNumber(string $phone): ?string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        }

        if (! str_starts_with($phone, '62')) {
            $phone = '62'.$phone;
        }

        $withoutCode = substr($phone, 2);
        if (strlen($withoutCode) < 9 || strlen($withoutCode) > 13) {
            return null;
        }

        return $phone;
    }
}
