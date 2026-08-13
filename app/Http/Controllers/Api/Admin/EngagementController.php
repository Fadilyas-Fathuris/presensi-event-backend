<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EngagementMappingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EngagementController extends Controller
{
    public function attendanceMapping(Request $request, EngagementMappingService $service): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'graduation_year' => ['nullable', 'digits:4'],
            'segment' => ['nullable', Rule::in([
                'Al-Muqorrobun',
                'Al-Mutawasithun',
                "Al-Mubtadi'un",
                'Ghoir Mukayyad',
            ])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $mapping = $service->paginatedMapping($filters);
        $segmentCounts = $service->segmentCounts();

        $items = collect($mapping->items())
            ->map(function (User $user) use (&$segmentCounts): array {
                $segment = (string) $user->engagement_segment;
                $segmentCounts[$segment] = ($segmentCounts[$segment] ?? 0) + 1;

                return [
                    'user' => [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'graduation_year' => $user->graduation_year,
                        'status' => $user->status,
                        'domicile' => $this->formatDomicile($user),
                    ],
                    'attendance' => [
                        'total_events' => $user->engagement_total_events,
                        'attended_events' => $user->engagement_attended_events,
                        'percentage' => $user->engagement_attendance_percentage,
                    ],
                    'segment' => $segment,
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'summary' => [
                    'total_alumni' => $mapping->total(),
                    'segment_counts_current_page' => $segmentCounts,
                    'calculation' => [
                        'basis' => 'attended_events / eligible_events * 100',
                        'eligible_events' => $service->eligibleEventCount(),
                    ],
                ],
                'total' => $mapping->total(),
                'current_page' => $mapping->currentPage(),
                'last_page' => $mapping->lastPage(),
            ],
        ]);
    }

    private function formatDomicile(User $user): ?array
    {
        if (! $user->domicile) {
            return null;
        }

        return [
            'province' => [
                'code' => $user->domicile->province_code,
                'name' => $user->domicile->province_name,
            ],
            'city' => [
                'code' => $user->domicile->city_code,
                'name' => $user->domicile->city_name,
            ],
        ];
    }
}
