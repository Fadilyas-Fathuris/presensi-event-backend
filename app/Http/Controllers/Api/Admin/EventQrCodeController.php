<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventQrCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class EventQrCodeController extends Controller
{
    #[OA\Get(
        path: '/api/admin/events/{id}/qr',
        operationId: 'adminGetEventQrCode',
        summary: 'Get active event QR code',
        description: 'Returns active QR code data for a specific event. Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Event QR Management'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Active QR code data',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object',
                            properties: [
                                new OA\Property(property: 'qr_code', type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                        new OA\Property(property: 'event_id', type: 'integer', example: 1),
                                        new OA\Property(property: 'qr_token', type: 'string', example: '550e8400-e29b-41d4-a716-446655440000'),
                                        new OA\Property(property: 'qr_payload', type: 'string', example: '550e8400-e29b-41d4-a716-446655440000'),
                                        new OA\Property(property: 'qr_code_image', type: 'string', nullable: true, example: null),
                                        new OA\Property(property: 'qr_code_url', type: 'string', nullable: true, example: null),
                                        new OA\Property(property: 'duration_days', type: 'integer', example: 3),
                                        new OA\Property(property: 'is_active', type: 'boolean', example: true),
                                        new OA\Property(property: 'created_at', type: 'string', example: '2026-08-04T13:18:00.000000Z'),
                                        new OA\Property(property: 'valid_from_wib', type: 'string', example: '04 Agustus 2026, 13:18 WIB'),
                                        new OA\Property(property: 'expired_at_wib', type: 'string', example: '07 Agustus 2026, 13:18 WIB'),
                                        new OA\Property(property: 'is_expired', type: 'boolean', example: false),
                                    ]
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Event or QR code not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $event = Event::find($id);

        if (! $event) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found',
            ], 404);
        }

        $qrCode = $event->activeQrCode;

        if (! $qrCode) {
            return response()->json([
                'success' => false,
                'message' => 'QR code not found for this event',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'qr_code' => $this->serializeQrCode($qrCode),
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/admin/events/{id}/qr/generate',
        operationId: 'adminGenerateEventQrCode',
        summary: 'Generate event QR code',
        description: 'Generates a new QR code for a specific event. QR is immediately active from now and valid for the specified number of days. Previous active QR code will be deactivated. Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Event QR Management'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['duration_days'],
                properties: [
                    new OA\Property(
                        property: 'duration_days',
                        type: 'integer',
                        description: 'Durasi berlaku QR code dalam satuan hari (1-30)',
                        example: 3
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'QR code generated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'QR code berhasil di-generate, berlaku selama 3 hari'),
                        new OA\Property(property: 'data', type: 'object',
                            properties: [
                                new OA\Property(property: 'qr_code', type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                        new OA\Property(property: 'event_id', type: 'integer', example: 1),
                                        new OA\Property(property: 'qr_token', type: 'string', example: '550e8400-e29b-41d4-a716-446655440000'),
                                        new OA\Property(property: 'qr_payload', type: 'string', example: '550e8400-e29b-41d4-a716-446655440000'),
                                        new OA\Property(property: 'qr_code_image', type: 'string', nullable: true, example: null),
                                        new OA\Property(property: 'qr_code_url', type: 'string', nullable: true, example: null),
                                        new OA\Property(property: 'duration_days', type: 'integer', example: 3),
                                        new OA\Property(property: 'is_active', type: 'boolean', example: true),
                                        new OA\Property(property: 'valid_from_wib', type: 'string', example: '04 Agustus 2026, 13:18 WIB'),
                                        new OA\Property(property: 'expired_at_wib', type: 'string', example: '07 Agustus 2026, 13:18 WIB'),
                                        new OA\Property(property: 'created_at_wib', type: 'string', example: '04 Agustus 2026, 13:18 WIB'),
                                        new OA\Property(property: 'is_valid_now', type: 'boolean', example: true),
                                        new OA\Property(property: 'is_expired', type: 'boolean', example: false),
                                    ]
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Event not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function generate(Request $request, int $id): JsonResponse
    {
        $event = Event::find($id);

        if (! $event) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found',
            ], 404);
        }

        $validated = $request->validate([
            'duration_days' => 'required|integer|min:1|max:30',
        ], [
            'duration_days.required' => 'Durasi hari wajib diisi',
            'duration_days.integer'  => 'Durasi hari harus berupa angka',
            'duration_days.min'      => 'Durasi minimal 1 hari',
            'duration_days.max'      => 'Durasi maksimal 30 hari',
        ]);

        // QR is immediately active from now
        $validFrom = now();

        // Deactivate all previous QR codes for this event
        EventQrCode::where('event_id', $event->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $qrToken = Str::uuid()->toString();

        $qrCode = EventQrCode::create([
            'event_id' => $event->id,
            'qr_token' => $qrToken,
            'qr_code_image' => null,
            'qr_code_url' => null,
            'valid_from' => $validFrom,
            'duration_days' => $validated['duration_days'],
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        $durationDays = $validated['duration_days'];

        Log::info('QR Generate - Duration days', [
            'event_id' => $event->id,
            'duration_days' => $durationDays,
            'valid_from_wib' => $qrCode->valid_from_wib,
            'expired_at_wib' => $qrCode->expired_at_wib,
        ]);

        \App\Models\ActivityLog::log('generate_qr', 'Admin generated a new QR Code for event: ' . $event->event_title . ' (berlaku ' . $durationDays . ' hari)');

        return response()->json([
            'success' => true,
            'message' => "QR code berhasil di-generate, berlaku selama {$durationDays} hari",
            'data' => [
                'qr_code' => $this->serializeQrCode($qrCode),
            ],
        ], 201);
    }

    #[OA\Get(
        path: '/api/admin/events/{id}/qr-image',
        operationId: 'adminGetGeneratedEventQrImage',
        summary: 'Display active event QR Code image',
        description: 'Returns the active QR Code SVG image of a specific event directly in browser. Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Event QR Management'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'QR Code SVG image',
                content: new OA\MediaType(
                    mediaType: 'image/svg+xml',
                    schema: new OA\Schema(type: 'string', format: 'binary')
                )
            ),
            new OA\Response(response: 404, description: 'Event or QR code image not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function image(int $id): mixed
    {
        $event = Event::find($id);

        if (! $event) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found',
            ], 404);
        }

        $qrCode = $event->activeQrCode;

        if (! $qrCode) {
            return response()->json([
                'success' => false,
                'message' => 'QR code not found',
            ], 404);
        }

        if (! $qrCode->qr_code_image) {
            return response()->json([
                'success' => false,
                'message' => 'QR image is generated by frontend. Use data.qr_code.qr_payload from /api/admin/events/{id}/qr.',
                'data' => [
                    'qr_code' => $this->serializeQrCode($qrCode),
                ],
            ], 410);
        }

        if (! Storage::disk('public')->exists($qrCode->qr_code_image)) {
            return response()->json([
                'success' => false,
                'message' => 'QR code image file not found',
            ], 404);
        }

        $svgContent = Storage::disk('public')->get($qrCode->qr_code_image);

        return response($svgContent, 200)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'public, max-age=86400');
    }

    private function serializeQrCode(EventQrCode $qrCode): array
    {
        return [
            'id' => $qrCode->id,
            'event_id' => $qrCode->event_id,
            'qr_token' => $qrCode->qr_token,
            'qr_payload' => $qrCode->qr_payload,
            'qr_code_image' => $qrCode->qr_code_image,
            'qr_code_url' => $qrCode->qr_code_url,
            'duration_days' => $qrCode->duration_days,
            'valid_from' => $qrCode->valid_from,
            'expired_at' => $qrCode->expired_at,
            'valid_from_wib' => $qrCode->valid_from_wib,
            'expired_at_wib' => $qrCode->expired_at_wib,
            'created_at_wib' => $qrCode->created_at_wib,
            'is_active' => $qrCode->is_active,
            'created_at' => $qrCode->created_at,
            'is_valid_now' => $qrCode->is_valid_now,
            'is_expired' => $qrCode->is_expired,
        ];
    }
}
