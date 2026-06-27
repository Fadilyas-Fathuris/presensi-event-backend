<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class EventCategoryController extends Controller
{
    #[OA\Get(
        path: '/api/admin/event-categories',
        operationId: 'adminGetEventCategories',
        summary: 'Get event categories',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Event Category Management'],
        responses: [
            new OA\Response(response: 200, description: 'List of event categories'),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->select(['id', 'category_name', 'description', 'created_at', 'updated_at'])
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => $categories,
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/admin/event-categories',
        operationId: 'adminCreateEventCategory',
        summary: 'Create an event category',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Event Category Management'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['category_name'],
                properties: [
                    new OA\Property(property: 'category_name', type: 'string', maxLength: 100, example: 'Seminar'),
                    new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Kategori untuk acara seminar alumni'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Event category created successfully'),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_name' => ['required', 'string', 'max:100', 'unique:categories,category_name'],
            'description' => ['nullable', 'string'],
        ]);

        $category = Category::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Event category created successfully',
            'data' => [
                'category' => $category,
            ],
        ], 201);
    }

    #[OA\Get(
        path: '/api/admin/event-categories/{id}',
        operationId: 'adminGetEventCategory',
        summary: 'Get an event category',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Event Category Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Event category detail'),
            new OA\Response(response: 404, description: 'Event category not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $category = Category::find($id);

        if (! $category) {
            return $this->notFoundResponse();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'category' => $category,
            ],
        ]);
    }

    #[OA\Put(
        path: '/api/admin/event-categories/{id}',
        operationId: 'adminUpdateEventCategory',
        summary: 'Update an event category',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Event Category Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['category_name'],
                properties: [
                    new OA\Property(property: 'category_name', type: 'string', maxLength: 100, example: 'Seminar Updated'),
                    new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Deskripsi diperbarui'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Event category updated successfully'),
            new OA\Response(response: 404, description: 'Event category not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        $category = Category::find($id);

        if (! $category) {
            return $this->notFoundResponse();
        }

        $validated = $request->validate([
            'category_name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categories', 'category_name')->ignore($category->id),
            ],
            'description' => ['nullable', 'string'],
        ]);

        $category->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Event category updated successfully',
            'data' => [
                'category' => $category->fresh(),
            ],
        ]);
    }

    #[OA\Delete(
        path: '/api/admin/event-categories/{id}',
        operationId: 'adminDeleteEventCategory',
        summary: 'Delete an event category',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Event Category Management'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Event category deleted successfully'),
            new OA\Response(response: 404, description: 'Event category not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Event category is still used by events', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $category = Category::find($id);

        if (! $category) {
            return $this->notFoundResponse();
        }

        if ($category->events()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Event category cannot be deleted because it is still used by events',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Event category deleted successfully',
        ]);
    }

    private function notFoundResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Event category not found',
        ], 404);
    }
}
