<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Presensi;
use Carbon\Carbon;

class EventRecommendationService
{
    public function getRecommendationsForAlumni(int $userId)
    {
        // Ambil event yang sudah pernah dihadiri alumni
        $attendedEventIds = Presensi::where('user_id', $userId)
            ->pluck('event_id');

        // Hitung kategori event yang paling sering diikuti alumni
        $favoriteCategoryIds = Presensi::query()
            ->join('events', 'presensis.event_id', '=', 'events.id')
            ->where('presensis.user_id', $userId)
            ->selectRaw('events.category_id, COUNT(*) as total')
            ->groupBy('events.category_id')
            ->orderByDesc('total')
            ->limit(3)
            ->pluck('events.category_id');

        // Jika alumni belum punya riwayat presensi
        if ($favoriteCategoryIds->isEmpty()) {
            return $this->getFallbackUpcomingEvents($attendedEventIds);
        }

        // Ambil event aktif dan mendatang berdasarkan kategori favorit
        $recommendations = Event::with('category')
            ->withCount('registrations')
            ->whereIn('category_id', $favoriteCategoryIds)
            ->whereNotIn('id', $attendedEventIds)
            ->where('status_event', 'active')
            ->whereDate('event_date', '>=', Carbon::today())
            ->orderBy('event_date', 'asc')
            ->limit(5)
            ->get();

        // Jika tidak ada event dari kategori favorit, tampilkan event terdekat
        if ($recommendations->isEmpty()) {
            return $this->getFallbackUpcomingEvents($attendedEventIds);
        }

        return $recommendations;
    }

    private function getFallbackUpcomingEvents($attendedEventIds)
    {
        return Event::with('category')
            ->withCount('registrations')
            ->whereNotIn('id', $attendedEventIds)
            ->where('status_event', 'active')
            ->whereDate('event_date', '>=', Carbon::today())
            ->orderBy('event_date', 'asc')
            ->limit(5)
            ->get();
    }
}