<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Presensi;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DashboardController extends Controller
{
    private const MONTH_LABELS = [
        1 => 'Jan',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Apr',
        5 => 'Mei',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Agu',
        9 => 'Sep',
        10 => 'Okt',
        11 => 'Nov',
        12 => 'Des',
    ];

    public function attendanceChart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:1970', 'max:2100'],
            'months' => ['nullable', 'integer', 'min:1', 'max:12'],
            'event_id' => ['nullable'],
        ]);

        $year = (int) ($validated['year'] ?? now()->year);
        $months = (int) ($validated['months'] ?? 12);
        $eventId = $this->resolveEventId($request);
        $monthExpression = $this->monthExpression();

        $startDate = Carbon::create($year, 1, 1)->startOfDay();
        $endDate = Carbon::create($year, $months, 1)->endOfMonth()->endOfDay();

        $totalsByMonth = Presensi::query()
            ->selectRaw("{$monthExpression} as month, COUNT(*) as total")
            ->where('status', 'hadir')
            ->whereBetween('scanned_at', [$startDate, $endDate])
            ->when($eventId !== null, fn ($query) => $query->where('event_id', $eventId))
            ->groupBy(DB::raw($monthExpression))
            ->pluck('total', 'month')
            ->mapWithKeys(fn ($total, $month) => [(int) $month => (int) $total]);

        $monthly = collect(range(1, $months))
            ->map(fn (int $month) => [
                'month' => $month,
                'label' => self::MONTH_LABELS[$month],
                'total' => $totalsByMonth->get($month, 0),
            ])
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => [
                'year' => $year,
                'event_id' => $eventId,
                'total' => array_sum(array_column($monthly, 'total')),
                'monthly' => $monthly,
            ],
        ]);
    }

    private function resolveEventId(Request $request): ?int
    {
        $eventId = $request->query('event_id');

        if ($eventId === null || trim((string) $eventId) === '' || strtolower((string) $eventId) === 'all') {
            return null;
        }

        if (! ctype_digit((string) $eventId)) {
            throw ValidationException::withMessages([
                'event_id' => ['The event id must be a valid event ID or all.'],
            ]);
        }

        return (int) $eventId;
    }

    private function monthExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "CAST(strftime('%m', scanned_at) AS INTEGER)",
            'pgsql' => 'EXTRACT(MONTH FROM scanned_at)',
            default => 'MONTH(scanned_at)',
        };
    }
}
