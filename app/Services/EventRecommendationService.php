<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Presensi;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EventRecommendationService
{
    public function getRecommendationsForAlumni(int $userId, int $limit = 5): Collection
    {
        $limit = max(1, min($limit, 5));

        $attendedEventIds = Presensi::where('user_id', $userId)
            ->pluck('event_id');

        $registeredEventIds = EventRegistration::where('user_id', $userId)
            ->pluck('event_id');

        $excludedEventIds = $attendedEventIds
            ->merge($registeredEventIds)
            ->unique()
            ->values();

        $interestedCategoryIds = Presensi::query()
            ->join('events', 'presensis.event_id', '=', 'events.id')
            ->where('presensis.user_id', $userId)
            ->whereNotNull('events.category_id')
            ->selectRaw('events.category_id, COUNT(*) as total')
            ->groupBy('events.category_id')
            ->havingRaw('COUNT(*) >= 2')
            ->orderByDesc('total')
            ->pluck('events.category_id');

        if ($interestedCategoryIds->isEmpty()) {
            return collect();
        }

        $now = Carbon::now();

        return Event::with('category:id,category_name')
            ->whereIn('category_id', $interestedCategoryIds)
            ->whereNotIn('id', $excludedEventIds)
            ->where('status_event', 'active')
            ->where(function ($query) use ($now) {
                $query->whereDate('event_date', '>', $now->toDateString())
                    ->orWhere(function ($query) use ($now) {
                        $query->whereDate('event_date', $now->toDateString())
                            ->whereTime('start_time', '>=', $now->format('H:i:s'));
                    });
            })
            ->orderBy('event_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->limit($limit)
            ->get()
            ->map(fn (Event $event): array => [
                'id' => $event->id,
                'event_title' => $event->event_title,
                'event_datetime' => $event->event_date->format('Y-m-d') . 'T' . Carbon::parse($event->start_time)->format('H:i:s'),
                'event_date' => $event->event_date->format('Y-m-d'),
                'start_time' => Carbon::parse($event->start_time)->format('H:i:s'),
                'end_time' => Carbon::parse($event->end_time)->format('H:i:s'),
                'location' => $event->location,
                'category' => [
                    'id' => $event->category->id,
                    'category_name' => $event->category->category_name,
                ],
            ]);
    }
}
