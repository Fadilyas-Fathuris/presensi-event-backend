<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventQrCode;
use App\Models\Presensi;
use App\Models\EventRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
                                new OA\Property(property: 'presensi', ref: '#/components/schemas/Presensi'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Scan failed (inactive, expired, out of window, or double scan)',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 403, description: 'User not registered for event',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'QR Code tidak dikenali'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'QR token not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 401, description: 'Unauthenticated',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function scan(Request $request): JsonResponse
    {
        // Log incoming request for debugging
        Log::info('Presensi Scan Request', [
            'qr_token_received' => $request->input('qr_token'),
            'qr_token_length' => strlen($request->input('qr_token')),
            'qr_token_type' => gettype($request->input('qr_token')),
            'full_request' => $request->all(),
            'user_id' => $request->user()->id,
        ]);

        $request->validate([
            'qr_token' => 'required|string',
        ]);

        $event = null;
        $qrCodeRecord = null;

        // 1. Try to find event by NEW QR system (event_qr_codes table)
        $qrCodeRecord = EventQrCode::where('qr_token', $request->qr_token)
            ->where('is_active', true)
            ->first();

        if ($qrCodeRecord) {
            Log::info('QR Code found in NEW system', ['qr_code_id' => $qrCodeRecord->id]);

            // Check if QR code is still valid (not expired)
            if ($qrCodeRecord->is_expired) {
                return response()->json([
                    'success' => false,
                    'message' => 'QR Code sudah kadaluarsa. Silakan minta admin untuk generate QR code baru.',
                ], 400);
            }

            // Check if QR code is valid now (within valid_from and timeout)
            if (!$qrCodeRecord->is_valid_now) {
                return response()->json([
                    'success' => false,
                    'message' => 'QR Code belum aktif atau sudah tidak valid.',
                ], 400);
            }

            $event = $qrCodeRecord->event;
        } else {
            Log::info('QR Code not found in NEW system, checking OLD system');

            // 2. Fallback: Try OLD QR system (events.qr_token)
            $event = Event::where('qr_token', $request->qr_token)->first();

            if ($event) {
                Log::info('QR Code found in OLD system', ['event_id' => $event->id]);
            }
        }

        if (! $event) {
            Log::warning('QR Code not found in any system', [
                'qr_token_searched' => $request->qr_token,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'QR Code tidak valid',
            ], 404);
        }

        // 3. Check event is active
        if ($event->status_event !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Event ini sudah tidak aktif',
            ], 400);
        }

        // 4. Check if user is registered for this event
        $isRegistered = EventRegistration::where('event_id', $event->id)
            ->where('user_id', $request->user()->id)
            ->exists();

        if (!$isRegistered) {
            Log::warning('User not registered for event', [
                'user_id' => $request->user()->id,
                'event_id' => $event->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'QR Code tidak dikenali',
            ], 403);
        }

        // 5. Check double scan
        $alreadyScanned = Presensi::where('event_id', $event->id)
            ->where('user_id', $request->user()->id)
            ->exists();

        if ($alreadyScanned) {
            return response()->json([
                'success' => false,
                'message' => 'Kamu sudah melakukan presensi untuk event ini',
            ], 400);
        }

        // 6. Record attendance
        $presensi = Presensi::create([
            'event_id'   => $event->id,
            'user_id'    => $request->user()->id,
            'scanned_at' => now(),
        ]);

        // 7. Update registration status to attended
        EventRegistration::where('event_id', $event->id)
            ->where('user_id', $request->user()->id)
            ->update(['status' => 'attended']);

        $presensi->load('event:id,event_title,location,event_date');

        return response()->json([
            'success' => true,
            'message' => 'Presensi berhasil dicatat',
            'data'    => ['presensi' => $presensi],
        ], 201);
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
