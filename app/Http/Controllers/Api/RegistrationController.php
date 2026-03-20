<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class RegistrationController extends Controller
{
    #[OA\Get(
        path: '/api/events',
        operationId: 'getPublicEvents',
        summary: 'Get list of active events',
        description: 'Returns list of active events available for registration.',
        security: [['bearerAuth' => []]],
        tags: ['Registration'],
        parameters: [
            new OA\Parameter(name: 'search',      in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'category_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page',    in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 10)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of events',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object',
                            properties: [
                                new OA\Property(property: 'events',       type: 'array', items: new OA\Items(ref: '#/components/schemas/Event')),
                                new OA\Property(property: 'total',        type: 'integer', example: 10),
                                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                new OA\Property(property: 'last_page',    type: 'integer', example: 1),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Event::with('category')
            ->where('status_event', 'active')
            ->where('event_date', '>=', today());

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('event_title', 'like', "%{$search}%")
                  ->orWhere('location',   'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $perPage = $request->get('per_page', 10);
        $events  = $query->orderBy('event_date', 'asc')->paginate($perPage);

        // Tambahkan info kuota & status registrasi untuk alumni ini
        $userId      = $request->user()->id;
        $eventItems  = collect($events->items())->map(function ($event) use ($userId) {
            $event->remaining_quota  = $event->remainingQuota();
            $event->is_registered    = $event->registrations()
                ->where('user_id', $userId)->exists();
            return $event;
        });

        return response()->json([
            'success' => true,
            'data'    => [
                'events'       => $eventItems,
                'total'        => $events->total(),
                'current_page' => $events->currentPage(),
                'last_page'    => $events->lastPage(),
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/events/{id}',
        operationId: 'getEventDetail',
        summary: 'Get event detail',
        description: 'Returns detail of a specific event including quota and registration status.',
        security: [['bearerAuth' => []]],
        tags: ['Registration'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Event detail',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object',
                            properties: [
                                new OA\Property(property: 'event',           ref: '#/components/schemas/Event'),
                                new OA\Property(property: 'remaining_quota', type: 'integer', example: 20),
                                new OA\Property(property: 'is_registered',   type: 'boolean', example: false),
                                new OA\Property(property: 'registration',    ref: '#/components/schemas/EventRegistration', nullable: true),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Event not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function show(Request $request, int $id): JsonResponse
    {
        $event = Event::with('category')->find($id);

        if (! $event) {
            return response()->json(['success' => false, 'message' => 'Event not found'], 404);
        }

        $registration = EventRegistration::where('event_id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        return response()->json([
            'success' => true,
            'data'    => [
                'event'           => $event,
                'remaining_quota' => $event->remainingQuota(),
                'is_registered'   => ! is_null($registration),
                'registration'    => $registration,
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/events/{id}/register',
        operationId: 'registerEvent',
        summary: 'Register for an event',
        description: 'Alumni registers for an event. Checks quota availability and prevents duplicate registration.',
        security: [['bearerAuth' => []]],
        tags: ['Registration'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Registration successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Pendaftaran berhasil!'),
                        new OA\Property(property: 'data', type: 'object',
                            properties: [
                                new OA\Property(property: 'registration', ref: '#/components/schemas/EventRegistration'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Already registered or quota full', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Event not found',                  content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated',                  content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function register(Request $request, int $id): JsonResponse
    {
        $event = Event::find($id);

        if (! $event) {
            return response()->json(['success' => false, 'message' => 'Event not found'], 404);
        }

        // Cek event masih aktif
        if ($event->status_event !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Event ini sudah tidak aktif',
            ], 400);
        }

        // Cek event belum lewat
        if ($event->event_date->isPast() && ! $event->event_date->isToday()) {
            return response()->json([
                'success' => false,
                'message' => 'Pendaftaran event ini sudah ditutup',
            ], 400);
        }

        // Cek sudah pernah daftar
        $alreadyRegistered = EventRegistration::where('event_id', $id)
            ->where('user_id', $request->user()->id)
            ->exists();

        if ($alreadyRegistered) {
            return response()->json([
                'success' => false,
                'message' => 'Kamu sudah terdaftar di event ini',
            ], 400);
        }

        // Cek kuota
        if (! $event->isQuotaAvailable()) {
            return response()->json([
                'success' => false,
                'message' => 'Maaf, kuota event ini sudah penuh',
            ], 400);
        }

        // Daftarkan alumni
        $registration = EventRegistration::create([
            'event_id'      => $id,
            'user_id'       => $request->user()->id,
            'status'        => 'registered',
            'registered_at' => now(),
        ]);

        $registration->load('event:id,event_title,event_date,location');

        return response()->json([
            'success' => true,
            'message' => 'Pendaftaran berhasil! Sampai jumpa di event 🎉',
            'data'    => ['registration' => $registration],
        ], 201);
    }

    #[OA\Delete(
        path: '/api/events/{id}/register',
        operationId: 'cancelRegistration',
        summary: 'Cancel event registration',
        description: 'Alumni cancels their registration for an event.',
        security: [['bearerAuth' => []]],
        tags: ['Registration'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Registration cancelled',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Pendaftaran berhasil dibatalkan'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Not registered or already attended', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Event not found',                    content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function cancel(Request $request, int $id): JsonResponse
    {
        $event = Event::find($id);

        if (! $event) {
            return response()->json(['success' => false, 'message' => 'Event not found'], 404);
        }

        $registration = EventRegistration::where('event_id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $registration) {
            return response()->json([
                'success' => false,
                'message' => 'Kamu belum terdaftar di event ini',
            ], 400);
        }

        // Tidak bisa cancel jika sudah hadir
        if ($registration->status === 'attended') {
            return response()->json([
                'success' => false,
                'message' => 'Tidak bisa membatalkan pendaftaran karena kamu sudah tercatat hadir',
            ], 400);
        }

        $registration->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pendaftaran berhasil dibatalkan',
        ]);
    }
}