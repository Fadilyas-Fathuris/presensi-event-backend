<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Presensi;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class EngagementMappingService
{
    public function paginatedMapping(array $filters): LengthAwarePaginator
    {
        $eligibleEventIds = $this->eligibleEventIds();
        $totalEvents = count($eligibleEventIds);

        $query = User::query()
            ->where('role', 'alumni')
            ->with('domicile')
            ->withCount([
                'presensis as attended_events_count' => function (Builder $query) use ($eligibleEventIds): void {
                    if ($eligibleEventIds === []) {
                        $query->whereRaw('1 = 0');
                        return;
                    }

                    $query->whereIn('event_id', $eligibleEventIds);
                },
            ]);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search): void {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('graduation_year', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['graduation_year'])) {
            $query->where('graduation_year', $filters['graduation_year']);
        }

        $users = $query
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $mappedUsers = $users->map(function (User $user) use ($totalEvents) {
            $attendedCount = (int) $user->attended_events_count;
            $percentage = $this->attendancePercentage($attendedCount, $totalEvents);

            $user->setAttribute('engagement_total_events', $totalEvents);
            $user->setAttribute('engagement_attended_events', $attendedCount);
            $user->setAttribute('engagement_attendance_percentage', $percentage);
            $user->setAttribute('engagement_segment', $this->segmentFor($percentage));

            return $user;
        });

        if (! empty($filters['segment'])) {
            $mappedUsers = $mappedUsers
                ->filter(fn (User $user) => $user->engagement_segment === $filters['segment'])
                ->values();
        }

        $perPage = $filters['per_page'] ?? 10;
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $mappedUsers->forPage($page, $perPage)->values(),
            $mappedUsers->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }

    public function segmentCounts(): array
    {
        return [
            'Al-Muqorrobun' => 0,
            'Al-Mutawasithun' => 0,
            "Al-Mubtadi'un" => 0,
            'Ghoir Mukayyad' => 0,
        ];
    }

    public function eligibleEventCount(): int
    {
        return count($this->eligibleEventIds());
    }

    public function summaryForUser(User $user): array
    {
        $eligibleEventIds = $this->eligibleEventIds();
        $totalEvents = count($eligibleEventIds);

        $attendedEvents = $eligibleEventIds === []
            ? 0
            : Presensi::query()
                ->where('user_id', $user->id)
                ->whereIn('event_id', $eligibleEventIds)
                ->count();

        $percentage = $this->attendancePercentage($attendedEvents, $totalEvents);
        $segment = $this->segmentFor($percentage);

        return [
            'attendance' => [
                'total_events' => $totalEvents,
                'attended_events' => $attendedEvents,
                'percentage' => $percentage,
            ],
            'segment' => $segment,
            'next_segment' => $this->nextSegmentFor($percentage),
            'remaining_attendances_to_next_segment' => $this->remainingAttendancesToNextSegment(
                $attendedEvents,
                $totalEvents,
                $percentage
            ),
            'recent_attendances' => $this->recentAttendancesFor($user, $eligibleEventIds),
        ];
    }

    public function segmentFor(float $percentage): string
    {
        if ($percentage >= 70) {
            return 'Al-Muqorrobun';
        }

        if ($percentage >= 40) {
            return 'Al-Mutawasithun';
        }

        if ($percentage > 0) {
            return "Al-Mubtadi'un";
        }

        return 'Ghoir Mukayyad';
    }

    private function eligibleEventIds(): array
    {
        return Event::query()
            ->whereDate('event_date', '<=', now()->toDateString())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function attendancePercentage(int $attendedCount, int $totalEvents): float
    {
        if ($totalEvents === 0) {
            return 0.0;
        }

        return round(($attendedCount / $totalEvents) * 100, 2);
    }

    private function nextSegmentFor(float $percentage): ?string
    {
        if ($percentage >= 70) {
            return null;
        }

        if ($percentage >= 40) {
            return 'Al-Muqorrobun';
        }

        if ($percentage > 0) {
            return 'Al-Mutawasithun';
        }

        return "Al-Mubtadi'un";
    }

    private function remainingAttendancesToNextSegment(int $attendedCount, int $totalEvents, float $percentage): int
    {
        if ($totalEvents === 0 || $percentage >= 70) {
            return 0;
        }

        $targetPercentage = $percentage >= 40
            ? 70
            : ($percentage > 0 ? 40 : 1);

        return max(0, (int) ceil(($targetPercentage / 100) * $totalEvents) - $attendedCount);
    }

    private function recentAttendancesFor(User $user, array $eligibleEventIds): array
    {
        if ($eligibleEventIds === []) {
            return [];
        }

        return Presensi::query()
            ->where('user_id', $user->id)
            ->whereIn('event_id', $eligibleEventIds)
            ->with('event:id,event_title,location,event_date,start_time,end_time')
            ->orderBy('scanned_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn (Presensi $presensi) => [
                'id' => $presensi->id,
                'status' => $presensi->status,
                'scanned_at' => $presensi->scanned_at,
                'event' => $presensi->event ? [
                    'id' => $presensi->event->id,
                    'event_title' => $presensi->event->event_title,
                    'location' => $presensi->event->location,
                    'event_date' => $presensi->event->event_date,
                    'start_time' => $presensi->event->start_time,
                    'end_time' => $presensi->event->end_time,
                ] : null,
            ])
            ->values()
            ->all();
    }
}
