<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class UserManagementController extends Controller
{
    private const ADMIN_PROTECTED_MESSAGE = 'User dengan role admin tidak dapat diubah atau dihapus';

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
                                    new OA\Property(property: 'phone', type: 'string', nullable: true, example: '6281234567890'),
                                    new OA\Property(property: 'gender', type: 'string', nullable: true, example: 'Laki-laki'),
                                    new OA\Property(property: 'graduation_year', type: 'string', nullable: true, example: '2020'),
                                    new OA\Property(property: 'birth_date', type: 'string', nullable: true, example: '2000-01-01'),
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
            ->where('role', '!=', 'admin')
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
                    new OA\Property(property: 'phone', type: 'string', nullable: true, example: '6281234567890'),
                    new OA\Property(property: 'gender', type: 'string', nullable: true, enum: ['Laki-laki', 'Perempuan'], example: 'Laki-laki'),
                    new OA\Property(property: 'graduation_year', type: 'string', nullable: true, example: '2020'),
                    new OA\Property(property: 'birth_date', type: 'string', nullable: true, format: 'date', example: '2000-01-01'),
                    new OA\Property(property: 'role', type: 'string', enum: ['admin', 'alumni', 'user'], example: 'alumni'),
                    new OA\Property(property: 'status', type: 'string', enum: ['pending', 'active', 'inactive', 'rejected'], example: 'active'),
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
                                new OA\Property(property: 'phone', type: 'string', nullable: true, example: '6281234567890'),
                                new OA\Property(property: 'gender', type: 'string', nullable: true, example: 'Laki-laki'),
                                new OA\Property(property: 'graduation_year', type: 'string', nullable: true, example: '2020'),
                                new OA\Property(property: 'birth_date', type: 'string', nullable: true, example: '2000-01-01'),
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

        if ($user->role === 'admin') {
            return $this->adminProtectedResponse();
        }

        if ($request->input('role') === 'admin') {
            return response()->json([
                'message' => 'Role user tidak dapat diubah menjadi admin melalui endpoint kelola user',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', Rule::in(['Laki-laki', 'Perempuan'])],
            'graduation_year' => ['nullable', 'string', 'max:10'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'role' => ['required', 'string', Rule::in(['alumni', 'user'])],
            'status' => ['required', 'string', Rule::in(['pending', 'active', 'inactive', 'rejected'])],
        ]);

        [$firstName, $lastName] = $this->splitName($validated['name']);

        $payload = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $validated['email'],
            'role' => $validated['role'] === 'user' ? 'alumni' : $validated['role'],
        ];

        if (array_key_exists('phone', $validated) && $phoneColumn = $this->resolvePhoneColumn()) {
            $payload[$phoneColumn] = $validated['phone'];
        }

        foreach (['gender', 'graduation_year', 'birth_date'] as $field) {
            if (array_key_exists($field, $validated) && Schema::hasColumn('users', $field)) {
                $payload[$field] = $validated[$field];
            }
        }

        if (Schema::hasColumn('users', 'status')) {
            $payload['status'] = $validated['status'];
        }

        $user->update($payload);

        if (in_array($payload['status'] ?? null, ['pending', 'inactive', 'rejected'], true)) {
            $user->tokens()->delete();
        }

        ActivityLog::log('edit_user', 'Admin updated user details for: '.$user->email);

        return response()->json([
            'message' => 'User berhasil diperbarui',
            'data' => $this->formatUser($user->fresh(), $validated['status']),
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

        if ($user->role === 'admin') {
            return $this->adminProtectedResponse();
        }

        $user->tokens()->delete();
        $user->delete();

        ActivityLog::log('delete_user', 'Admin deleted user: '.$user->email);

        return response()->json([
            'message' => 'User berhasil dihapus',
        ]);
    }

    private function formatUser(User $user, ?string $fallbackStatus = null): array
    {
        $name = trim(sprintf('%s %s', $user->first_name, $user->last_name));

        return [
            'id' => $user->id,
            'name' => $name !== '' ? $name : $user->email,
            'email' => $user->email,
            'phone' => $this->resolvePhone($user),
            'gender' => $user->gender,
            'graduation_year' => $user->graduation_year,
            'birth_date' => $user->birth_date,
            'role' => $user->role,
            'status' => $user->status ?? $fallbackStatus ?? 'active',
            'created_at' => $user->created_at,
        ];
    }

    private function resolvePhone(User $user): ?string
    {
        return $user->phone
            ?? $user->phone_number
            ?? $user->no_telp
            ?? null;
    }

    private function resolvePhoneColumn(): ?string
    {
        foreach (['phone', 'phone_number', 'no_telp'] as $column) {
            if (Schema::hasColumn('users', $column)) {
                return $column;
            }
        }

        return null;
    }

    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2);

        return [
            $parts[0],
            $parts[1] ?? null,
        ];
    }

    private function adminProtectedResponse(): JsonResponse
    {
        return response()->json([
            'message' => self::ADMIN_PROTECTED_MESSAGE,
        ], 403);
    }
}
