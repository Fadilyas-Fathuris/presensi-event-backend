<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventQrCode;
use App\Models\Presensi;
use App\Models\EventRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class PresensiController extends Controller
{
    #[OA\Post(
        path: '/api/presensi/scan',
        operationId: 'scanPresensi',
        summary: 'Scan QR code for attendance',
        description: 'Alumni scans QR code to record attendance. Validates event status, time window, and prevents double scan.',
        security: [['bearerAuth' => []]],
        tags: ['Presensi'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['qr_token'],
                properties: [
                    new OA\Property(property: 'qr_token', type: 'string', example: '550e8400-e29b-41d4-a716-446655440000'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Attendance recorded successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Presensi berhasil dicatat'),
                        new OA\Property(property: 'data', type: 'object',
                            properties: [
                                new OA\Property(property: 'attendance', ref: '#/components/schemas/Presensi'),
                                new OA\Property(property: 'event', ref: '#/components/schemas/Event'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'QR token is invalid, inactive, or not yet valid',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 403, description: 'User not registered for event',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Kamu belum terdaftar di event ini'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'QR token not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 409, description: 'Attendance already recorded',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 410, description: 'QR token expired',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 401, description: 'Unauthenticated',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function scan(Request $request): JsonResponse
    {
        $rawQrToken = $request->input('qr_token');

        if (! is_string($rawQrToken) || trim($rawQrToken) === '') {
            return $this->errorResponse('QR token wajib diisi', 422);
        }

        if (strlen($rawQrToken) > 512) {
            return $this->errorResponse('QR token tidak valid', 422);
        }

        $qrToken = $this->normalizeQrToken($rawQrToken);

        Log::info('Presensi Scan Request', [
            'qr_token_normalized' => $qrToken,
            'qr_token_type' => gettype($rawQrToken),
            'user_id' => $request->user()->id,
        ]);

        if (! Str::isUuid($qrToken)) {
            Log::warning('Invalid QR token format', [
                'qr_token_received' => $rawQrToken,
                'qr_token_normalized' => $qrToken,
                'user_id' => $request->user()->id,
            ]);

            return $this->errorResponse('QR Code tidak valid', 422);
        }

        $event = null;

        // 1. Try to find event by NEW QR system (event_qr_codes table)
        $qrCodeRecord = EventQrCode::where('qr_token', $qrToken)->first();

        if ($qrCodeRecord) {
            Log::info('QR Code found in NEW system', ['qr_code_id' => $qrCodeRecord->id]);

            if (! $qrCodeRecord->is_active) {
                return $this->errorResponse('QR Code belum aktif atau sudah tidak valid.', 422);
            }

            // Check if QR code is still valid (not expired)
            if ($qrCodeRecord->is_expired) {
                return $this->errorResponse('QR Code sudah kadaluarsa. Silakan minta admin untuk generate QR code baru.', 410);
            }

            // Check if QR code is valid now (within valid_from and timeout)
            if (!$qrCodeRecord->is_valid_now) {
                return $this->errorResponse('QR Code belum aktif atau sudah tidak valid.', 422);
            }

            $event = $qrCodeRecord->event;
        } else {
            Log::info('QR Code not found in NEW system, checking OLD system');

            // 2. Fallback: Try OLD QR system (events.qr_token)
            $event = Event::where('qr_token', $qrToken)->first();

            if ($event) {
                Log::info('QR Code found in OLD system', ['event_id' => $event->id]);
            }
        }

        if (! $event) {
            Log::warning('QR Code not found in any system', [
                'qr_token_searched' => $qrToken,
            ]);

            return $this->errorResponse('QR Code tidak dikenali', 404);
        }

        // 3. Check event is active
        if ($event->status_event !== 'active') {
            return $this->errorResponse('Event ini sudah tidak aktif', 422);
        }

        $userId = $request->user()->id;

        $presensi = DB::transaction(function () use ($event, $userId) {
            // 4. Check if user is registered for this event
            $registration = EventRegistration::where('event_id', $event->id)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (! $registration) {
                Log::warning('User not registered for event', [
                    'user_id' => $userId,
                    'event_id' => $event->id,
                ]);

                abort($this->errorResponse('Kamu belum terdaftar di event ini', 403));
            }

            // 5. Check double scan
            $alreadyScanned = Presensi::where('event_id', $event->id)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->exists();

            if ($alreadyScanned) {
                abort($this->errorResponse('Kamu sudah melakukan presensi untuk event ini', 409));
            }

            // 6. Record attendance
            $presensi = Presensi::create([
                'event_id'   => $event->id,
                'user_id'    => $userId,
                'status'     => 'hadir',
                'scanned_at' => now(),
            ]);

            // 7. Update registration status to attended
            $registration->update(['status' => 'attended']);

            return $presensi;
        });

        $presensi->load('event:id,event_title,location,event_date', 'user:id,first_name,last_name,email,phone,graduation_year');

        return response()->json([
            'success' => true,
            'message' => 'Presensi berhasil dicatat',
            'data'    => [
                'attendance' => $this->serializeAttendance($presensi),
                'event' => $presensi->event,
            ],
        ], 201);
    }

    private function normalizeQrToken(string $rawToken): string
    {
        $token = trim($rawToken);

        if (preg_match('/[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}/', $token, $matches)) {
            return strtolower($matches[0]);
        }

        return strtolower($token);
    }

    private function errorResponse(string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }

    private function serializeAttendance(Presensi $presensi): array
    {
        return [
            'id' => $presensi->id,
            'event_id' => $presensi->event_id,
            'user_id' => $presensi->user_id,
            'status' => $presensi->status,
            'scanned_at' => $presensi->scanned_at,
        ];
    }

    #[OA\Get(
        path: '/api/presensi/history',
        operationId: 'presensiHistory',
        summary: 'Get alumni attendance history',
        description: 'Returns the attendance history of the currently logged-in alumni.',
        security: [['bearerAuth' => []]],
        tags: ['Presensi'],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 10)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Attendance history',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object',
                            properties: [
                                new OA\Property(property: 'history',      type: 'array', items: new OA\Items(ref: '#/components/schemas/Presensi')),
                                new OA\Property(property: 'total',        type: 'integer', example: 5),
                                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                new OA\Property(property: 'last_page',    type: 'integer', example: 1),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function history(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 10);

        $history = Presensi::where('user_id', $request->user()->id)
            ->with('event:id,event_title,location,event_date,start_time,end_time,status_event')
            ->orderBy('scanned_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => [
                'history'      => $history->items(),
                'total'        => $history->total(),
                'current_page' => $history->currentPage(),
                'last_page'    => $history->lastPage(),
            ],
        ]);
    }
}
