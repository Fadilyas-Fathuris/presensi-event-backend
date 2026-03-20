<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Presensi;
use App\Models\EventRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            new OA\Response(response: 400, description: 'Scan failed (inactive, out of window, or double scan)',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
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
        $request->validate([
            'qr_token' => 'required|string',
        ]);

        // 1. Find event by QR token
        $event = Event::where('qr_token', $request->qr_token)->first();

        if (! $event) {
            return response()->json([
                'success' => false,
                'message' => 'QR Code tidak valid',
            ], 404);
        }

        // 2. Check event is active
        if ($event->status_event !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Event ini sudah tidak aktif',
            ], 400);
        }

        // 3. Check within attendance time window
        if (! $event->isWithinAttendanceWindow()) {
            return response()->json([
                'success' => false,
                'message' => 'Presensi hanya dapat dilakukan saat event berlangsung ' .
                             "({$event->start_time} - {$event->end_time})",
            ], 400);
        }

        // 4. Check double scan
        $alreadyScanned = Presensi::where('event_id', $event->id)
            ->where('user_id', $request->user()->id)
            ->exists();

        if ($alreadyScanned) {
            return response()->json([
                'success' => false,
                'message' => 'Kamu sudah melakukan presensi untuk event ini',
            ], 400);
        }

        // 5. Record attendance
        $presensi = Presensi::create([
            'event_id'   => $event->id,
            'user_id'    => $request->user()->id,
            'scanned_at' => now(),
        ]);

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