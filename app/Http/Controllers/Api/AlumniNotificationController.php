<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AlumniNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlumniNotificationController extends Controller
{
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
