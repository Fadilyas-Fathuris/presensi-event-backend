<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class AdminController extends Controller
{
    #[OA\Get(
        path: '/api/admin/users',
        operationId: 'adminGetAllUsers',
        summary: 'Get all alumni users',
        description: 'Returns a paginated list of all alumni users. Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['Admin - User Management'],
        parameters: [
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                description: 'Search by name, email, or angkatan',
                schema: new OA\Schema(type: 'string', example: 'Ahmad')
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                required: false,
                description: 'Number of results per page',
                schema: new OA\Schema(type: 'integer', example: 10)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of alumni users',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'users',
                                    type: 'array',
                                    items: new OA\Items(ref: '#/components/schemas/User')
                                ),
                                new OA\Property(property: 'total',        type: 'integer', example: 50),
                                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                new OA\Property(property: 'last_page',    type: 'integer', example: 5),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function getAllUsers(Request $request): JsonResponse
    {
        $query = User::where('role', 'alumni');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name',     'like', "%{$search}%")
                  ->orWhere('email',    'like', "%{$search}%")
                  ->orWhere('angkatan', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 10);
        $users   = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => [
                'users'        => $users->items(),
                'total'        => $users->total(),
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/admin/users/{id}',
        operationId: 'adminGetUser',
        summary: 'Get a specific alumni user',
        description: 'Returns detail of a specific alumni user by ID. Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['Admin - User Management'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'User ID',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User detail',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'User not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function getUser(int $id): JsonResponse
    {
        $user = User::where('role', 'alumni')->find($id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => ['user' => $user],
        ]);
    }

    #[OA\Post(
        path: '/api/admin/users',
        operationId: 'adminCreateUser',
        summary: 'Create a new alumni user',
        description: 'Creates a new alumni account manually by admin.',
        security: [['bearerAuth' => []]],
        tags: ['Admin - User Management'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'name',                  type: 'string',  example: 'Budi Santoso'),
                    new OA\Property(property: 'email',                 type: 'string',  format: 'email', example: 'budi@example.com'),
                    new OA\Property(property: 'password',              type: 'string',  format: 'password', example: 'password123'),
                    new OA\Property(property: 'password_confirmation', type: 'string',  format: 'password', example: 'password123'),
                    new OA\Property(property: 'phone',                 type: 'string',  example: '081298765432'),
                    new OA\Property(property: 'angkatan',              type: 'string',  example: '2018'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'User created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'User created successfully'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function createUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone'    => 'nullable|string|max:20',
            'angkatan' => 'nullable|string|max:10',
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone'    => $validated['phone']    ?? null,
            'angkatan' => $validated['angkatan'] ?? null,
            'role'     => 'alumni',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data'    => ['user' => $user],
        ], 201);
    }

    #[OA\Put(
        path: '/api/admin/users/{id}',
        operationId: 'adminUpdateUser',
        summary: 'Update an alumni user',
        description: 'Updates data of a specific alumni user. Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['Admin - User Management'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'User ID',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name',     type: 'string', example: 'Budi Santoso Updated'),
                    new OA\Property(property: 'email',    type: 'string', format: 'email', example: 'budi.new@example.com'),
                    new OA\Property(property: 'phone',    type: 'string', example: '089876543210'),
                    new OA\Property(property: 'angkatan', type: 'string', example: '2019'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'newpassword123'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'newpassword123'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'User updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'User updated successfully'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'User not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function updateUser(Request $request, int $id): JsonResponse
    {
        $user = User::where('role', 'alumni')->find($id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $validated = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'email'    => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'sometimes|string|min:8|confirmed',
            'phone'    => 'nullable|string|max:20',
            'angkatan' => 'nullable|string|max:10',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
            $validated['password_changed_at'] = now();
        }

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data'    => ['user' => $user->fresh()],
        ]);
    }

    #[OA\Delete(
        path: '/api/admin/users/{id}',
        operationId: 'adminDeleteUser',
        summary: 'Delete an alumni user',
        description: 'Permanently deletes an alumni user by ID. Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['Admin - User Management'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'User ID',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User deleted successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'User deleted successfully'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'User not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function deleteUser(int $id): JsonResponse
    {
        $user = User::where('role', 'alumni')->find($id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        // Revoke all tokens before deleting
        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully',
        ]);
    }

    #[OA\Get(
        path: '/api/admin/activity-logs',
        operationId: 'adminGetActivityLogs',
        summary: 'Get admin activity logs',
        description: 'Returns list of all admin activity logs including user actions. Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Activity Logs'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of activity logs',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'user_id', type: 'integer', example: 1),
                                    new OA\Property(property: 'action', type: 'string', example: 'login'),
                                    new OA\Property(property: 'description', type: 'string', example: 'Admin logged in'),
                                    new OA\Property(property: 'created_at', type: 'string', example: '2026-06-01T10:00:00.000000Z'),
                                    new OA\Property(
                                        property: 'user',
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 1),
                                            new OA\Property(property: 'first_name', type: 'string', example: 'Admin'),
                                            new OA\Property(property: 'last_name', type: 'string', example: 'User'),
                                            new OA\Property(property: 'email', type: 'string', example: 'admin@example.com'),
                                        ]
                                    ),
                                ],
                                type: 'object'
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function getActivityLogs(): JsonResponse
    {
        $logs = \App\Models\ActivityLog::with('user:id,first_name,last_name,email')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $logs,
        ]);
    }
}
