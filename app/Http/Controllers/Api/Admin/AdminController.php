<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\DomicileResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            new OA\Parameter(
                name: 'status',
                in: 'query',
                required: false,
                description: 'Filter users by approval status',
                schema: new OA\Schema(type: 'string', enum: ['pending', 'active', 'inactive', 'rejected'])
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
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['pending', 'active', 'inactive', 'rejected'])],
            'graduation_year' => ['nullable', 'string', 'max:10'],
            'angkatan' => ['nullable', 'string', 'max:10'],
            'year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'domicile_province_code' => ['nullable', 'string', 'max:20'],
            'domicile_city_code' => ['nullable', 'string', 'max:20'],
            'domicile_district_code' => ['nullable', 'string', 'max:20'],
            'domicile_village_code' => ['nullable', 'string', 'max:20'],
            'sort_by' => ['nullable', 'string', Rule::in(['name', 'first_name', 'last_name', 'email', 'phone', 'graduation_year', 'angkatan', 'role', 'status', 'created_at'])],
            'sort_dir' => ['nullable', 'string', Rule::in(['asc', 'desc', 'ASC', 'DESC'])],
            'order' => ['nullable', 'string', Rule::in(['asc', 'desc', 'ASC', 'DESC'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $graduationYear = $filters['graduation_year'] ?? $filters['angkatan'] ?? null;
        $sortDir = strtolower($filters['sort_dir'] ?? $filters['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query = User::with('domicile')->where('role', 'alumni');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('graduation_year', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($graduationYear)) {
            $query->where('graduation_year', $graduationYear);
        }

        if (! empty($filters['year'])) {
            $query->whereYear('created_at', $filters['year']);
        }

        if (! empty($filters['event_id'])) {
            $eventId = $filters['event_id'];
            $query->whereHas('eventRegistrations', fn ($q) => $q->where('event_id', $eventId));
        }

        foreach ([
            'domicile_province_code' => 'province_code',
            'domicile_city_code' => 'city_code',
            'domicile_district_code' => 'district_code',
            'domicile_village_code' => 'village_code',
        ] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->whereHas('domicile', fn ($q) => $q->where($column, $filters[$filter]));
            }
        }

        $allowedSorts = [
            'name' => 'first_name',
            'first_name' => 'first_name',
            'last_name' => 'last_name',
            'email' => 'email',
            'phone' => 'phone',
            'graduation_year' => 'graduation_year',
            'angkatan' => 'graduation_year',
            'role' => 'role',
            'status' => 'status',
            'created_at' => 'created_at',
        ];
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortColumn = $allowedSorts[$sortBy] ?? 'created_at';

        $perPage = $filters['per_page'] ?? 10;

        if ($sortBy === 'name') {
            $query->orderBy('first_name', $sortDir)->orderBy('last_name', $sortDir);
        } else {
            $query->orderBy($sortColumn, $sortDir);
        }

        $users = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => [
                'users'        => collect($users->items())
                    ->map(fn (User $user) => (new UserResource($user))->resolve())
                    ->all(),
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
        $user = User::with('domicile')->where('role', 'alumni')->find($id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => ['user' => new UserResource($user)],
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
    public function createUser(Request $request, DomicileResolver $domicileResolver): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required_without:name', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'name' => ['required_without:first_name', 'string', 'max:255'],
            'gender' => ['required', Rule::in(['Laki-laki', 'Perempuan'])],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:20'],
            'graduation_year' => ['nullable', 'digits:4', 'integer', 'min:1950', 'max:' . date('Y')],
            'angkatan' => ['nullable', 'digits:4', 'integer', 'min:1950', 'max:' . date('Y')],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'status' => ['nullable', Rule::in(['pending', 'active', 'inactive', 'rejected'])],
            'role' => ['prohibited'],
            'admin_level' => ['prohibited'],
            'domicile_province_code' => ['nullable', 'string', 'max:20'],
            'domicile_city_code' => ['nullable', 'string', 'max:20'],
            'domicile_district_code' => ['nullable', 'string', 'max:20'],
            'domicile_village_code' => ['nullable', 'string', 'max:20'],
            'domicile_postal_code' => ['nullable', 'string', 'max:10'],
            'domicile_address' => ['nullable', 'string', 'max:1000'],
        ]);

        [$firstName, $lastName] = $this->resolveNameParts($validated);
        $domicile = $domicileResolver->resolve($validated);

        $user = DB::transaction(function () use ($validated, $firstName, $lastName, $domicile): User {
            $user = User::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'gender' => $validated['gender'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'phone' => $validated['phone'] ?? null,
                'graduation_year' => $validated['graduation_year'] ?? $validated['angkatan'] ?? null,
                'birth_date' => $validated['birth_date'] ?? null,
                'role' => 'alumni',
                'admin_level' => null,
                'status' => $validated['status'] ?? 'active',
            ]);

            if ($domicile !== null) {
                $user->domicile()->create($domicile);
            }

            return $user->fresh('domicile');
        });

        \App\Models\ActivityLog::log(
            'create_alumni_by_admin',
            "Admin created alumni account for {$user->email}"
        );

        return response()->json([
            'success' => true,
            'message' => 'User berhasil dibuat',
            'data'    => ['user' => new UserResource($user)],
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
    public function updateUser(Request $request, int $id, DomicileResolver $domicileResolver): JsonResponse
    {
        $user = User::where('role', 'alumni')->find($id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $validated = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'name' => ['sometimes', 'string', 'max:255'],
            'gender' => ['sometimes', Rule::in(['Laki-laki', 'Perempuan'])],
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['sometimes', 'string', 'min:8', 'confirmed'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'graduation_year' => ['sometimes', 'nullable', 'digits:4', 'integer', 'min:1950', 'max:' . date('Y')],
            'angkatan' => ['sometimes', 'nullable', 'digits:4', 'integer', 'min:1950', 'max:' . date('Y')],
            'birth_date' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'status' => ['sometimes', Rule::in(['pending', 'active', 'inactive', 'rejected'])],
            'status_reason' => ['sometimes', 'nullable', 'string', 'max:255'],
            'role' => ['prohibited'],
            'admin_level' => ['prohibited'],
            'domicile_province_code' => ['nullable', 'string', 'max:20'],
            'domicile_city_code' => ['nullable', 'string', 'max:20'],
            'domicile_district_code' => ['nullable', 'string', 'max:20'],
            'domicile_village_code' => ['nullable', 'string', 'max:20'],
            'domicile_postal_code' => ['nullable', 'string', 'max:10'],
            'domicile_address' => ['nullable', 'string', 'max:1000'],
        ]);

        $domicile = $domicileResolver->resolve($validated);

        $payload = collect($validated)
            ->except([
                'name',
                'angkatan',
                'domicile_province_code',
                'domicile_city_code',
                'domicile_district_code',
                'domicile_village_code',
                'domicile_postal_code',
                'domicile_address',
            ])
            ->all();

        if (isset($payload['status']) && $payload['status'] === 'active') {
            $payload['status_reason'] = null;
        }

        if (isset($validated['name']) && ! isset($validated['first_name'])) {
            [$firstName, $lastName] = $this->resolveNameParts($validated);
            $payload['first_name'] = $firstName;
            $payload['last_name'] = $lastName;
        }

        if (isset($validated['angkatan']) && ! isset($validated['graduation_year'])) {
            $payload['graduation_year'] = $validated['angkatan'];
        }

        if (isset($payload['password'])) {
            $payload['password'] = Hash::make($payload['password']);
            $payload['password_changed_at'] = now();
        }

        DB::transaction(function () use ($user, $payload, $domicile): void {
            $user->update($payload);

            if ($domicile !== null) {
                $user->domicile()->updateOrCreate(
                    ['user_id' => $user->id],
                    $domicile
                );
            }
        });

        if (in_array($payload['status'] ?? null, ['pending', 'inactive', 'rejected'], true)) {
            $user->tokens()->delete();
        }

        \App\Models\ActivityLog::log(
            'update_alumni_by_admin',
            "Admin updated alumni account for {$user->email}"
        );

        return response()->json([
            'success' => true,
            'message' => 'User berhasil diperbarui',
            'data'    => ['user' => new UserResource($user->fresh('domicile'))],
        ]);
    }

    #[OA\Patch(
        path: '/api/admin/users/{id}/status',
        operationId: 'adminUpdateUserStatus',
        summary: 'Update alumni user approval status',
        description: 'Approves, rejects, or deactivates an alumni user. Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['Admin - User Management'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 2)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(
                        property: 'status',
                        type: 'string',
                        enum: ['active', 'inactive', 'rejected'],
                        example: 'active'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'User status updated successfully'),
            new OA\Response(response: 403, description: 'Admin user status cannot be changed', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'User not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function updateUserStatus(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        if ($request->user()->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Admin cannot change their own status',
            ], 403);
        }

        if ($user->role === 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Admin user status cannot be changed',
            ], 403);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive', 'rejected'])],
            'reason' => ['required_if:status,inactive,rejected', 'nullable', 'string', 'max:255'],
        ]);

        $user->update([
            'status' => $validated['status'],
            'status_reason' => $validated['status'] === 'active' ? null : ($validated['reason'] ?? null),
        ]);

        if (in_array($validated['status'], ['inactive', 'rejected'], true)) {
            $user->tokens()->delete();
        }

        \App\Models\ActivityLog::log(
            'update_user_status',
            sprintf(
                "Admin updated user status for %s to %s. Reason: %s",
                $user->email,
                $validated['status'],
                $validated['reason'] ?? '-'
            )
        );

        return response()->json([
            'success' => true,
            'message' => 'User status updated successfully',
            'data' => [
                'user' => $user->fresh(),
            ],
        ]);
    }

    #[OA\Patch(
        path: '/api/admin/users/bulk-status',
        operationId: 'adminBulkUpdateUserStatus',
        summary: 'Bulk update user status',
        description: 'Bulk updates the status of selected non-admin users. Admin users and the authenticated admin cannot be updated through this endpoint.',
        security: [['bearerAuth' => []]],
        tags: ['Admin - User Management'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_ids', 'status'],
                properties: [
                    new OA\Property(
                        property: 'user_ids',
                        type: 'array',
                        description: 'IDs of users to update. Every ID must exist in users.id.',
                        items: new OA\Items(type: 'integer'),
                        minItems: 1,
                        example: [1, 2, 3]
                    ),
                    new OA\Property(
                        property: 'status',
                        type: 'string',
                        description: 'Target user status. Pending is not allowed for bulk update.',
                        enum: ['active', 'inactive', 'rejected'],
                        example: 'active'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Bulk user status updated successfully',
                content: new OA\JsonContent(
                    required: ['success', 'message', 'data'],
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Bulk user status updated successfully'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'updated_count', type: 'integer', example: 3),
                                new OA\Property(property: 'skipped_count', type: 'integer', example: 1),
                                new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive', 'rejected'], example: 'active'),
                                new OA\Property(
                                    property: 'updated_user_ids',
                                    type: 'array',
                                    items: new OA\Items(type: 'integer'),
                                    example: [1, 2, 3]
                                ),
                                new OA\Property(
                                    property: 'skipped_user_ids',
                                    type: 'array',
                                    items: new OA\Items(type: 'integer'),
                                    example: [4]
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(
                response: 422,
                description: 'Validation error or no eligible users. user_ids is required and must be an array with at least one existing users.id. status must be active, inactive, or rejected.',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(
                            required: ['success', 'message', 'data'],
                            properties: [
                                new OA\Property(property: 'success', type: 'boolean', example: false),
                                new OA\Property(property: 'message', type: 'string', example: 'No eligible users to update'),
                                new OA\Property(
                                    property: 'data',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'updated_count', type: 'integer', example: 0),
                                        new OA\Property(property: 'skipped_count', type: 'integer', example: 3),
                                        new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive', 'rejected'], example: 'active'),
                                        new OA\Property(
                                            property: 'updated_user_ids',
                                            type: 'array',
                                            items: new OA\Items(type: 'integer'),
                                            example: []
                                        ),
                                        new OA\Property(
                                            property: 'skipped_user_ids',
                                            type: 'array',
                                            items: new OA\Items(type: 'integer'),
                                            example: [1, 2, 3]
                                        ),
                                    ]
                                ),
                            ],
                            type: 'object'
                        ),
                        new OA\Schema(ref: '#/components/schemas/ValidationError'),
                    ]
                )
            ),
        ]
    )]
    public function bulkUpdateUserStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'integer', 'distinct', Rule::exists('users', 'id')],
            'status' => ['required', Rule::in(['active', 'inactive', 'rejected'])],
            'reason' => ['required_if:status,inactive,rejected', 'nullable', 'string', 'max:255'],
        ]);

        $requestedUserIds = array_values(array_map('intval', $validated['user_ids']));
        $currentAdminId = (int) $request->user()->id;
        $status = $validated['status'];
        $reason = $validated['reason'] ?? null;

        $result = DB::transaction(function () use ($requestedUserIds, $currentAdminId, $status, $reason): array {
            $usersById = User::query()
                ->whereIn('id', $requestedUserIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $eligibleUsers = collect();
            $skippedUserIds = [];

            foreach ($requestedUserIds as $userId) {
                $user = $usersById->get($userId);

                if (! $user || $user->id === $currentAdminId || $user->role === 'admin') {
                    $skippedUserIds[] = $userId;
                    continue;
                }

                $eligibleUsers->push($user);
            }

            $updatedUserIds = $eligibleUsers
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            if ($updatedUserIds !== []) {
                $updatePayload = [
                    'status' => $status,
                    'status_reason' => $status === 'active' ? null : $reason,
                    'updated_at' => now()
                ];
                User::query()
                    ->whereIn('id', $updatedUserIds)
                    ->update($updatePayload);

                if (in_array($status, ['inactive', 'rejected'], true)) {
                    $eligibleUsers->each(fn (User $user) => $user->tokens()->delete());
                }

                \App\Models\ActivityLog::log(
                    'bulk_update_user_status',
                    sprintf(
                        'Admin bulk updated %d user status(es) to %s. Reason: %s',
                        count($updatedUserIds),
                        $status,
                        $reason ?? '-'
                    )
                );
            }

            return [
                'updated_user_ids' => $updatedUserIds,
                'skipped_user_ids' => $skippedUserIds,
            ];
        });

        $updatedCount = count($result['updated_user_ids']);
        $skippedCount = count($result['skipped_user_ids']);

        if ($updatedCount === 0) {
            return response()->json([
                'success' => false,
                'message' => 'No eligible users to update',
                'data' => [
                    'updated_count' => 0,
                    'skipped_count' => $skippedCount,
                    'skipped_user_ids' => $result['skipped_user_ids'],
                ],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Bulk user status updated successfully',
            'data' => [
                'updated_count' => $updatedCount,
                'skipped_count' => $skippedCount,
                'status' => $status,
                'updated_user_ids' => $result['updated_user_ids'],
                'skipped_user_ids' => $result['skipped_user_ids'],
            ],
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

        \App\Models\ActivityLog::log(
            'delete_alumni_by_admin',
            "Admin deleted alumni account for {$user->email}"
        );

        return response()->json([
            'success' => true,
            'message' => 'User berhasil dihapus',
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

    private function resolveNameParts(array $validated): array
    {
        if (! empty($validated['first_name'])) {
            return [
                $validated['first_name'],
                $validated['last_name'] ?? null,
            ];
        }

        $parts = preg_split('/\s+/', trim($validated['name']), 2);

        return [
            $parts[0],
            $parts[1] ?? ($validated['last_name'] ?? null),
        ];
    }
}
