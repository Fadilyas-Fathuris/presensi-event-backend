<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EngagementMappingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlumniEngagementController extends Controller
{
    public function summary(Request $request, EngagementMappingService $service): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $service->summaryForUser($request->user()),
        ]);
    }
}
