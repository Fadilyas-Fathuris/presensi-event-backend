<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class EventController extends Controller
{
    #[OA\Get(
        path: '/api/admin/event-categories',
        operationId: 'adminGetEventCategories',
        summary: 'Get event categories',
        description: 'Returns list of event categories for admin event form. Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Event Management'],
        responses: [
            new OA\Response(response: 200, description: 'List of event categories',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object',
                            properties: [
                                new OA\Property(property: 'categories', type: 'array', items: new OA\Items(
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                        new OA\Property(property: 'category_name', type: 'string', example: 'Reuni'),
                                        new OA\Property(property: 'description', type: 'string', example: 'Event reuni alumni'),
                                    ],
                                    type: 'object'
                                )),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function eventCategories(): JsonResponse
    {
        $categories = Category::query()
            ->select(['id', 'category_name', 'description'])
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => $categories,
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/admin/events',
        operationId: 'adminGetAllEvents',
        summary: 'Get all events',
        description: 'Returns paginated list of all events. Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Event Management'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['active', 'inactive'])),
            new OA\Parameter(name: 'category_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 10)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'List of events',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object',
                            properties: [
                                new OA\Property(property: 'events', type: 'array', items: new OA\Items(ref: '#/components/schemas/Event')),
                                new OA\Property(property: 'total', type: 'integer', example: 20),
                                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                new OA\Property(property: 'last_page', type: 'integer', example: 2),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Event::with(['category', 'createdBy']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('event_title', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status_event', $request->status);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $perPage = $request->get('per_page', 10);
        $events = $query->orderBy('event_date', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'events' => $events->items(),
                'total' => $events->total(),
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/admin/events/{id}',
        operationId: 'adminGetEvent',
        summary: 'Get event detail',
        description: 'Returns detail of a specific event including attendance count.',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Event Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Event detail',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object',
                            properties: [
                                new OA\Property(property: 'event', ref: '#/components/schemas/Event'),
                                new OA\Property(property: 'attendance_count', type: 'integer', example: 25),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Event not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $event = Event::with(['category', 'createdBy'])->find($id);

        if (! $event) {
            return response()->json(['success' => false, 'message' => 'Event not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'event' => $event,
                'attendance_count' => $event->presensis()->count(),
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/admin/events',
        operationId: 'adminCreateEvent',
        summary: 'Create a new event',
        description: 'Creates a new event and auto-generates a QR code. Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Event Management'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['category_id', 'event_title', 'location', 'event_date', 'start_time', 'end_time'],
                properties: [
                    new OA\Property(property: 'category_id', type: 'integer', example: 1),
                    new OA\Property(property: 'event_title', type: 'string', example: 'Reuni Akbar 2025'),
                    new OA\Property(property: 'description', type: 'string', example: 'Reuni alumni angkatan 2010-2015'),
                    new OA\Property(property: 'location', type: 'string', example: 'Aula Pesantren'),
                    new OA\Property(property: 'event_date', type: 'string', format: 'date', example: '2025-12-01'),
                    new OA\Property(property: 'start_time', type: 'string', format: 'time', example: '08:00'),
                    new OA\Property(property: 'end_time', type: 'string', format: 'time', example: '17:00'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Event created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Event created successfully'),
                        new OA\Property(property: 'data', type: 'object',
                            properties: [
                                new OA\Property(property: 'event', ref: '#/components/schemas/Event'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'event_title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'location' => 'required|string|max:255',
            'event_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'quota' => 'nullable|integer|min:1',
        ]);

        // Generate unique QR token
        $qrToken = Str::uuid()->toString();

        // Generate QR code image and save to storage
        $qrImage = QrCode::format('svg')->size(400)->generate($qrToken);
        $imagePath = "qrcodes/{$qrToken}.svg";
        Storage::disk('public')->put($imagePath, $qrImage);

        $event = Event::create([
            ...$validated,
            'created_by' => $request->user()->id,
            'qr_token' => $qrToken,
            'qr_code_image' => $imagePath,
            'status_event' => 'active',
        ]);

        $event->load(['category', 'createdBy']);

        // Notify all alumni about the new event
        $alumniUserIds = \App\Models\User::where('role', 'alumni')->pluck('id');
        $category = $event->category;

        if ($alumniUserIds->isNotEmpty()) {
            $notifications = $alumniUserIds->map(fn($userId) => [
                'user_id' => $userId,
                'title' => 'Event Baru: ' . $event->event_title,
                'body' => 'Event "' . $event->event_title . '" telah dijadwalkan pada ' . $event->event_date . ' di ' . $event->location,
                'type' => 'upcoming_event',
                'priority' => 'normal',
                'data' => json_encode([
                    'event_id' => $event->id,
                    'event_title' => $event->event_title,
                    'location' => $event->location,
                    'starts_at' => $event->event_date,
                    'start_time' => $event->start_time,
                    'end_time' => $event->end_time,
                    'category' => $category?->id,
                    'category_name' => $category?->category_name,
                ]),
                'is_read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ])->toArray();

            \App\Models\AlumniNotification::insert($notifications);
        }

        return response()->json([
            'success' => true,
            'message' => 'Event created successfully',
            'data' => ['event' => $event],
        ], 201);
    }

    #[OA\Put(
        path: '/api/admin/events/{id}',
        operationId: 'adminUpdateEvent',
        summary: 'Update an event',
        description: 'Updates event data. QR token is not affected. Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Event Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'category_id', type: 'integer', example: 2),
                    new OA\Property(property: 'event_title', type: 'string', example: 'Reuni Akbar 2025 Updated'),
                    new OA\Property(property: 'description', type: 'string', example: 'Deskripsi diperbarui'),
                    new OA\Property(property: 'location', type: 'string', example: 'Gedung Serbaguna'),
                    new OA\Property(property: 'event_date', type: 'string', format: 'date', example: '2025-12-05'),
                    new OA\Property(property: 'start_time', type: 'string', format: 'time', example: '09:00'),
                    new OA\Property(property: 'end_time', type: 'string', format: 'time', example: '16:00'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Event updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Event updated successfully'),
                        new OA\Property(property: 'data', type: 'object',
                            properties: [
                                new OA\Property(property: 'event', ref: '#/components/schemas/Event'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Event not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        $event = Event::find($id);

        if (! $event) {
            return response()->json(['success' => false, 'message' => 'Event not found'], 404);
        }

        $validated = $request->validate([
            'category_id' => 'sometimes|exists:categories,id',
            'event_title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'location' => 'sometimes|string|max:255',
            'event_date' => 'sometimes|date',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i|after:start_time',
            'quota' => 'nullable|integer|min:1',
        ]);

        $event->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Event updated successfully',
            'data' => ['event' => $event->fresh()->load(['category', 'createdBy'])],
        ]);
    }

    #[OA\Delete(
        path: '/api/admin/events/{id}',
        operationId: 'adminDeleteEvent',
        summary: 'Delete an event',
        description: 'Deletes an event and its QR code image. Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Event Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Event deleted successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Event deleted successfully'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Event not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $event = Event::find($id);

        if (! $event) {
            return response()->json(['success' => false, 'message' => 'Event not found'], 404);
        }

        // Delete QR code image from storage
        if ($event->qr_code_image && Storage::disk('public')->exists($event->qr_code_image)) {
            Storage::disk('public')->delete($event->qr_code_image);
        }

        $event->delete();

        return response()->json([
            'success' => true,
            'message' => 'Event deleted successfully',
        ]);
    }

    #[OA\Patch(
        path: '/api/admin/events/{id}/toggle',
        operationId: 'adminToggleEvent',
        summary: 'Toggle event status',
        description: 'Toggles event status between active and inactive. Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Event Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Status toggled successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Event status updated to inactive'),
                        new OA\Property(property: 'data', type: 'object',
                            properties: [
                                new OA\Property(property: 'status_event', type: 'string', example: 'inactive'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Event not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function toggle(int $id): JsonResponse
    {
        $event = Event::find($id);

        if (! $event) {
            return response()->json(['success' => false, 'message' => 'Event not found'], 404);
        }

        $newStatus = $event->status_event === 'active' ? 'inactive' : 'active';
        $event->update(['status_event' => $newStatus]);

        return response()->json([
            'success' => true,
            'message' => "Event status updated to {$newStatus}",
            'data' => ['status_event' => $newStatus],
        ]);
    }

    #[OA\Get(
        path: '/api/admin/events/{id}/attendances',
        operationId: 'adminGetEventAttendances',
        summary: 'Get event attendance list',
        description: 'Returns list of alumni who attended a specific event. Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Event Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 10)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Attendance list',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object',
                            properties: [
                                new OA\Property(property: 'event', ref: '#/components/schemas/Event'),
                                new OA\Property(property: 'attendances', type: 'array', items: new OA\Items(ref: '#/components/schemas/Presensi')),
                                new OA\Property(property: 'total', type: 'integer', example: 30),
                                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                new OA\Property(property: 'last_page', type: 'integer', example: 3),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Event not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function attendances(Request $request, int $id): JsonResponse
    {
        $event = Event::find($id);

        if (! $event) {
            return response()->json(['success' => false, 'message' => 'Event not found'], 404);
        }

        $perPage = $request->get('per_page', 10);
        $attendances = $event->presensis()
            ->with('user:id,name,email,angkatan')
            ->orderBy('scanned_at', 'asc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'event' => $event->only(['id', 'event_title', 'event_date', 'location']),
                'attendances' => $attendances->items(),
                'total' => $attendances->total(),
                'current_page' => $attendances->currentPage(),
                'last_page' => $attendances->lastPage(),
            ],
        ]);
    }

    public function qrImage(int $id): mixed
    {
        $event = Event::find($id);

        if (! $event) {
            return response()->json(['success' => false, 'message' => 'Event not found'], 404);
        }

        if (! $event->qr_code_image || ! Storage::disk('public')->exists($event->qr_code_image)) {
            return response()->json(['success' => false, 'message' => 'QR Code image not found'], 404);
        }

        $svgContent = Storage::disk('public')->get($event->qr_code_image);

        return response($svgContent, 200)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'public, max-age=86400');
    }

    #[OA\Get(
        path: '/api/admin/events/{id}/registrations',
        operationId: 'adminGetEventRegistrations',
        summary: 'Get event registration list',
        description: 'Returns list of alumni who registered for a specific event. Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Event Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['registered', 'attended', 'absent'])),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 10)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Registration list',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object',
                            properties: [
                                new OA\Property(property: 'event', ref: '#/components/schemas/Event'),
                                new OA\Property(property: 'summary', type: 'object',
                                    properties: [
                                        new OA\Property(property: 'total_registered', type: 'integer', example: 80),
                                        new OA\Property(property: 'total_attended', type: 'integer', example: 60),
                                        new OA\Property(property: 'total_absent', type: 'integer', example: 20),
                                        new OA\Property(property: 'quota', type: 'integer', example: 100),
                                        new OA\Property(property: 'remaining_quota', type: 'integer', example: 20),
                                    ]
                                ),
                                new OA\Property(property: 'registrations', type: 'array', items: new OA\Items(ref: '#/components/schemas/EventRegistration')),
                                new OA\Property(property: 'total', type: 'integer', example: 80),
                                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                new OA\Property(property: 'last_page', type: 'integer', example: 8),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Event not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function registrations(Request $request, int $id): JsonResponse
    {
        $event = Event::find($id);

        if (! $event) {
            return response()->json(['success' => false, 'message' => 'Event not found'], 404);
        }

        $query = $event->registrations()->with('user:id,name,email,phone,angkatan');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', 10);
        $registrations = $query->orderBy('registered_at', 'asc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'event' => $event->only(['id', 'event_title', 'event_date', 'location', 'quota']),
                'summary' => [
                    'total_registered' => $event->registrations()->count(),
                    'total_attended' => $event->registrations()->where('status', 'attended')->count(),
                    'total_absent' => $event->registrations()->where('status', 'absent')->count(),
                    'quota' => $event->quota,
                    'remaining_quota' => $event->remainingQuota(),
                ],
                'registrations' => $registrations->items(),
                'total' => $registrations->total(),
                'current_page' => $registrations->currentPage(),
                'last_page' => $registrations->lastPage(),
            ],
        ]);
    }
}
