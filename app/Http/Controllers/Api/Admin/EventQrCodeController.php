<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventQrCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

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
                                        new OA\Property(property: 'qr_code_image', type: 'string', example: 'qrcodes/550e8400.svg'),
                                        new OA\Property(property: 'qr_code_url', type: 'string', example: 'http://localhost:8000/storage/qrcodes/550e8400.svg'),
                                        new OA\Property(property: 'timeout_minutes', type: 'integer', example: 60),
                                        new OA\Property(property: 'is_active', type: 'boolean', example: true),
                                        new OA\Property(property: 'created_at', type: 'string', example: '2026-05-25T13:56:00.000000Z'),
                                        new OA\Property(property: 'expired_at', type: 'string', example: '2026-05-25T14:56:00.000000Z'),
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
                'qr_code' => [
                    'id' => $qrCode->id,
                    'event_id' => $qrCode->event_id,
                    'qr_token' => $qrCode->qr_token,
                    'qr_code_image' => $qrCode->qr_code_image,
                    'qr_code_url' => $qrCode->qr_code_url,
                    'valid_from' => $qrCode->valid_from,
                    'timeout_minutes' => $qrCode->timeout_minutes,
                    'is_active' => $qrCode->is_active,
                    'created_at' => $qrCode->created_at,
                    'expired_at' => $qrCode->expired_at,
                    'is_valid_now' => $qrCode->is_valid_now,
                    'is_expired' => $qrCode->is_expired,
                ],
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/admin/events/{id}/qr/generate',
        operationId: 'adminGenerateEventQrCode',
        summary: 'Generate event QR code',
        description: 'Generates a new QR code for a specific event. Previous active QR code will be deactivated. Admin only.',
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
                required: ['timeout_minutes'],
                properties: [
                    new OA\Property(property: 'timeout_minutes', type: 'integer', example: 60),
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
                        new OA\Property(property: 'message', type: 'string', example: 'QR code generated successfully'),
                        new OA\Property(property: 'data', type: 'object',
                            properties: [
                                new OA\Property(property: 'qr_code', type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                        new OA\Property(property: 'event_id', type: 'integer', example: 1),
                                        new OA\Property(property: 'qr_token', type: 'string', example: '550e8400-e29b-41d4-a716-446655440000'),
                                        new OA\Property(property: 'qr_code_image', type: 'string', example: 'qrcodes/550e8400.svg'),
                                        new OA\Property(property: 'qr_code_url', type: 'string', example: 'http://localhost:8000/storage/qrcodes/550e8400.svg'),
                                        new OA\Property(property: 'valid_from', type: 'string', example: '2026-05-25T13:56:00.000000Z'),
                                        new OA\Property(property: 'timeout_minutes', type: 'integer', example: 60),
                                        new OA\Property(property: 'is_active', type: 'boolean', example: true),
                                        new OA\Property(property: 'created_at', type: 'string', example: '2026-05-25T13:56:00.000000Z'),
                                        new OA\Property(property: 'expired_at', type: 'string', example: '2026-05-25T14:56:00.000000Z'),
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
            'valid_from' => 'required|date',
            'timeout_minutes' => 'required|integer|min:1|max:1440',
        ]);

        EventQrCode::where('event_id', $event->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $qrToken = Str::uuid()->toString();

        $qrContent = url("/api/attendance/scan/{$qrToken}");

        $qrImage = QrCode::format('svg')
            ->size(400)
            ->generate($qrContent);

        $imagePath = "qrcodes/{$qrToken}.svg";

        Storage::disk('public')->put($imagePath, $qrImage);

        $qrCode = EventQrCode::create([
            'event_id' => $event->id,
            'qr_token' => $qrToken,
            'qr_code_image' => $imagePath,
            'qr_code_url' => Storage::disk('public')->url($imagePath),
            'valid_from' => $validated['valid_from'],
            'timeout_minutes' => $validated['timeout_minutes'],
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        \App\Models\ActivityLog::log('generate_qr', 'Admin generated a new QR Code for event: ' . $event->event_title);

        return response()->json([
            'success' => true,
            'message' => 'QR code generated successfully',
            'data' => [
                'qr_code' => [
                    'id' => $qrCode->id,
                    'event_id' => $qrCode->event_id,
                    'qr_token' => $qrCode->qr_token,
                    'qr_code_image' => $qrCode->qr_code_image,
                    'qr_code_url' => $qrCode->qr_code_url,
                    'valid_from' => $qrCode->valid_from,
                    'timeout_minutes' => $qrCode->timeout_minutes,
                    'is_active' => $qrCode->is_active,
                    'created_at' => $qrCode->created_at,
                    'expired_at' => $qrCode->expired_at,
                    'is_valid_now' => $qrCode->is_valid_now,
                    'is_expired' => $qrCode->is_expired,
                ],
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

        if (! $qrCode || ! $qrCode->qr_code_image) {
            return response()->json([
                'success' => false,
                'message' => 'QR code image not found',
            ], 404);
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
}