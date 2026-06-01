<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class UserManagementController extends Controller
{
    #[OA\Get(
        path: '/api/user-management',
        operationId: 'getAllUsersManagement',
        summary: 'Get all users for management',
        description: 'Returns list of all users for user management. Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['User Management'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of users',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'name', type: 'string', example: 'Ahmad Fauzi'),
                                    new OA\Property(property: 'email', type: 'string', example: 'ahmad@example.com'),
                                    new OA\Property(property: 'role', type: 'string', example: 'alumni'),
                                    new OA\Property(property: 'status', type: 'string', example: 'active'),
                                    new OA\Property(property: 'created_at', type: 'string', example: '2026-01-01T00:00:00.000000Z'),
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
    public function index(): JsonResponse
    {
        $users = User::query()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (User $user) => $this->formatUser($user));

        return response()->json([
            'data' => $users,
        ]);
    }

    #[OA\Put(
        path: '/api/user-management/{id}',
        operationId: 'updateUserManagement',
        summary: 'Update user data',
        description: 'Updates user information including name, email, role, and status. Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['User Management'],
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
                required: ['name', 'email', 'role', 'status'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Ahmad Fauzi Updated'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'ahmad.new@example.com'),
                    new OA\Property(property: 'role', type: 'string', enum: ['admin', 'alumni', 'user'], example: 'alumni'),
                    new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive'], example: 'active'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'User updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'User berhasil diperbarui'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'Ahmad Fauzi Updated'),
                                new OA\Property(property: 'email', type: 'string', example: 'ahmad.new@example.com'),
                                new OA\Property(property: 'role', type: 'string', example: 'alumni'),
                                new OA\Property(property: 'status', type: 'string', example: 'active'),
                                new OA\Property(property: 'created_at', type: 'string', example: '2026-01-01T00:00:00.000000Z'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'User not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'User tidak ditemukan'),
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
    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        $validated = $request->validate([
            'name'   => ['required', 'string', 'max:255'],
            'email'  => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role'   => ['required', 'string', Rule::in(['admin', 'alumni', 'user'])],
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],
        ]);

        [$firstName, $lastName] = $this->splitName($validated['name']);

        $payload = [
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'email'      => $validated['email'],
            'role'       => $validated['role'] === 'user' ? 'alumni' : $validated['role'],
        ];

        if (Schema::hasColumn('users', 'status')) {
            $payload['status'] = $validated['status'];
        }

        $user->update($payload);

        \App\Models\ActivityLog::log('edit_user', 'Admin updated user details for: ' . $user->email);

        return response()->json([
            'message' => 'User berhasil diperbarui',
            'data'    => $this->formatUser($user->fresh(), $validated['status']),
        ]);
    }

    #[OA\Delete(
        path: '/api/user-management/{id}',
        operationId: 'deleteUserManagement',
        summary: 'Delete user',
        description: 'Permanently deletes a user and revokes all their tokens. Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['User Management'],
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
                        new OA\Property(property: 'message', type: 'string', example: 'User berhasil dihapus'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'User not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'User tidak ditemukan'),
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        $user->tokens()->delete();
        $user->delete();

        \App\Models\ActivityLog::log('delete_user', 'Admin deleted user: ' . $user->email);

        return response()->json([
            'message' => 'User berhasil dihapus',
        ]);
    }

    private function formatUser(User $user, ?string $fallbackStatus = null): array
    {
        $name = trim(sprintf('%s %s', $user->first_name, $user->last_name));

        return [
            'id'         => $user->id,
            'name'       => $name !== '' ? $name : $user->email,
            'email'      => $user->email,
            'role'       => $user->role,
            'status'     => $user->status ?? $fallbackStatus ?? 'active',
            'created_at' => $user->created_at,
        ];
    }

    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2);

        return [
            $parts[0],
            $parts[1] ?? null,
        ];
    }
}
