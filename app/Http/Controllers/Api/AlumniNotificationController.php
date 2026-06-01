<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AlumniNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AlumniNotificationController extends Controller
{
    #[OA\Get(
        path: '/api/notifications',
        operationId: 'getNotifications',
        summary: 'Get alumni notifications',
        description: 'Returns list of notifications for the authenticated alumni user.',
        security: [['bearerAuth' => []]],
        tags: ['Notifications'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of notifications',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'notifications',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 1),
                                            new OA\Property(property: 'title', type: 'string', example: 'Event Baru: Reuni Akbar 2025'),
                                            new OA\Property(property: 'message', type: 'string', example: 'Event "Reuni Akbar 2025" telah dijadwalkan'),
                                            new OA\Property(property: 'body', type: 'string', example: 'Event "Reuni Akbar 2025" telah dijadwalkan'),
                                            new OA\Property(property: 'type', type: 'string', example: 'upcoming_event'),
                                            new OA\Property(property: 'priority', type: 'string', example: 'normal'),
                                            new OA\Property(property: 'created_at', type: 'string', example: '2026-06-01T10:00:00+00:00'),
                                            new OA\Property(property: 'is_read', type: 'boolean', example: false),
                                            new OA\Property(property: 'read_at', type: 'string', nullable: true, example: null),
                                            new OA\Property(property: 'data', type: 'object', nullable: true),
                                        ],
                                        type: 'object'
                                    )
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $notifications = AlumniNotification::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn (AlumniNotification $notification) => $this->formatNotification($notification));

        return response()->json([
            'success' => true,
            'data' => [
                'notifications' => $notifications,
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/notifications/unread-count',
        operationId: 'getUnreadNotificationCount',
        summary: 'Get unread notification count',
        description: 'Returns the count of unread notifications for the authenticated alumni user.',
        security: [['bearerAuth' => []]],
        tags: ['Notifications'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Unread notification count',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'unread_count', type: 'integer', example: 5),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function unreadCount(Request $request): JsonResponse
    {
        $unreadCount = AlumniNotification::query()
            ->where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'unread_count' => $unreadCount,
        ]);
    }

    #[OA\Patch(
        path: '/api/notifications/{id}/read',
        operationId: 'markNotificationAsRead',
        summary: 'Mark notification as read',
        description: 'Marks a specific notification as read for the authenticated alumni user.',
        security: [['bearerAuth' => []]],
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Notification ID',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Notification marked as read',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'notification',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                        new OA\Property(property: 'title', type: 'string', example: 'Event Baru'),
                                        new OA\Property(property: 'message', type: 'string', example: 'Event telah dijadwalkan'),
                                        new OA\Property(property: 'body', type: 'string', example: 'Event telah dijadwalkan'),
                                        new OA\Property(property: 'type', type: 'string', example: 'upcoming_event'),
                                        new OA\Property(property: 'priority', type: 'string', example: 'normal'),
                                        new OA\Property(property: 'created_at', type: 'string', example: '2026-06-01T10:00:00+00:00'),
                                        new OA\Property(property: 'is_read', type: 'boolean', example: true),
                                        new OA\Property(property: 'read_at', type: 'string', example: '2026-06-02T10:00:00+00:00'),
                                        new OA\Property(property: 'data', type: 'object', nullable: true),
                                    ]
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Notification not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $notification = AlumniNotification::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        if (! $notification->is_read) {
            $notification->forceFill([
                'is_read' => true,
                'read_at' => now(),
            ])->save();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'notification' => $this->formatNotification($notification->refresh()),
            ],
        ]);
    }

    #[OA\Patch(
        path: '/api/notifications/read-all',
        operationId: 'markAllNotificationsAsRead',
        summary: 'Mark all notifications as read',
        description: 'Marks all unread notifications as read for the authenticated alumni user.',
        security: [['bearerAuth' => []]],
        tags: ['Notifications'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'All notifications marked as read',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'updated_count', type: 'integer', example: 5),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function markAllAsRead(Request $request): JsonResponse
    {
        $updatedCount = AlumniNotification::query()
            ->where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'updated_count' => $updatedCount,
        ]);
    }

    private function formatNotification(AlumniNotification $notification): array
    {
        $body = $notification->body;

        return [
            'id' => $notification->id,
            'title' => $notification->title,
            'message' => $body,
            'body' => $body,
            'type' => $notification->type,
            'priority' => $notification->priority,
            'created_at' => $notification->created_at?->toIso8601String(),
            'is_read' => $notification->is_read,
            'read_at' => $notification->read_at?->toIso8601String(),
            'data' => $notification->data,
        ];
    }
}
