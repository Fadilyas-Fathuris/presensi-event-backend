<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Region;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class RegionController extends Controller
{
    public function provinces(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->cachedRegions('province'),
        ]);
    }

    public function cities(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'province_code' => ['required', 'string', Rule::exists('regions', 'code')->where('type', 'province')],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->cachedRegions('city', $validated['province_code']),
        ]);
    }

    public function districts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city_code' => ['required', 'string', Rule::exists('regions', 'code')->where('type', 'city')],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->cachedRegions('district', $validated['city_code']),
        ]);
    }

    public function villages(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'district_code' => ['required', 'string', Rule::exists('regions', 'code')->where('type', 'district')],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->cachedRegions('village', $validated['district_code'], true),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'type' => ['nullable', Rule::in(['province', 'city', 'district', 'village'])],
        ]);

        $query = Region::query()
            ->where('name', 'like', '%' . $validated['q'] . '%');

        if (! empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        $regions = $query
            ->orderBy('type')
            ->orderBy('name')
            ->limit(25)
            ->get()
            ->map(fn (Region $region) => $this->formatRegion($region, true))
            ->values();

        return response()->json([
            'success' => true,
            'data' => $regions,
        ]);
    }

    private function cachedRegions(string $type, ?string $parentCode = null, bool $includeDetails = false)
    {
        $cacheKey = 'regions:' . $type . ':' . ($parentCode ?? 'root') . ':' . (int) $includeDetails;

        return Cache::remember($cacheKey, now()->addDays(7), function () use ($type, $parentCode, $includeDetails) {
            return Region::query()
                ->where('type', $type)
                ->when(
                    $parentCode === null,
                    fn ($query) => $query->whereNull('parent_code'),
                    fn ($query) => $query->where('parent_code', $parentCode)
                )
                ->orderBy('name')
                ->get()
                ->map(fn (Region $region) => $this->formatRegion($region, $includeDetails))
                ->values();
        });
    }

    private function formatRegion(Region $region, bool $includeDetails = false): array
    {
        $data = [
            'code' => $region->code,
            'name' => $region->name,
        ];

        if ($includeDetails) {
            $data['type'] = $region->type;
            $data['parent_code'] = $region->parent_code;
            $data['postal_code'] = $region->postal_code;
        }

        return $data;
    }
}
