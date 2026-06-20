<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EventRecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventRecommendationController extends Controller
{
    protected EventRecommendationService $recommendationService;

    public function __construct(EventRecommendationService $recommendationService)
    {
        $this->recommendationService = $recommendationService;
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($user->role !== 'alumni') {
            return response()->json([
                'success' => false,
                'message' => 'Akses hanya untuk alumni.',
            ], 403);
        }

        $recommendations = $this->recommendationService
            ->getRecommendationsForAlumni($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Rekomendasi event berhasil diambil.',
            'data' => $recommendations,
        ]);
    }
}