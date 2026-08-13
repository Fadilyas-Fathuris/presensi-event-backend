<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminAccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'admin_level' => ['nullable', Rule::in(['super_admin', 'admin'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = User::query()->where('role', 'admin');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['admin_level'])) {
            $query->where('admin_level', $filters['admin_level']);
        }

        $admins = $query
            ->orderByRaw("CASE WHEN admin_level = 'super_admin' THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 10);

        return response()->json([
            'success' => true,
            'data' => [
                'admins' => array_map(fn (User $admin) => $this->formatAdmin($admin), $admins->items()),
                'total' => $admins->total(),
                'current_page' => $admins->currentPage(),
                'last_page' => $admins->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone')],
            'gender' => ['required', Rule::in(['Laki-laki', 'Perempuan'])],
            'admin_level' => ['nullable', Rule::in(['super_admin', 'admin'])],
        ]);

        $admin = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'] ?? null,
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'gender' => $validated['gender'],
            'role' => 'admin',
            'admin_level' => $validated['admin_level'] ?? 'admin',
            'status' => 'active',
        ]);

        ActivityLog::log(
            'create_admin',
            "Super admin created admin account for {$admin->email}"
        );

        return response()->json([
            'success' => true,
            'message' => 'Admin berhasil dibuat',
            'data' => [
                'admin' => $this->formatAdmin($admin),
            ],
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $admin = User::query()
            ->where('role', 'admin')
            ->find($id);

        if (! $admin) {
            return $this->notFoundResponse();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'admin' => $this->formatAdmin($admin),
            ],
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $admin = User::query()
            ->where('role', 'admin')
            ->find($id);

        if (! $admin) {
            return $this->notFoundResponse();
        }

        $validated = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($admin->id)],
            'password' => ['sometimes', 'string', 'min:8', 'confirmed'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($admin->id)],
            'gender' => ['sometimes', Rule::in(['Laki-laki', 'Perempuan'])],
            'admin_level' => ['sometimes', Rule::in(['super_admin', 'admin'])],
        ]);

        $updatedAdmin = DB::transaction(function () use ($admin, $validated): User {
            $admin->refresh();

            if (
                isset($validated['admin_level'])
                && $validated['admin_level'] !== 'super_admin'
                && $admin->admin_level === 'super_admin'
                && $this->isLastActiveSuperAdmin($admin)
            ) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Super admin terakhir tidak dapat diturunkan levelnya.',
                ], 403));
            }

            $payload = $validated;

            if (isset($payload['password'])) {
                $payload['password'] = Hash::make($payload['password']);
                $payload['password_changed_at'] = now();
            }

            $oldLevel = $admin->admin_level;
            $admin->update($payload);

            if (isset($validated['admin_level']) && $validated['admin_level'] !== $oldLevel) {
                ActivityLog::log(
                    $validated['admin_level'] === 'super_admin' ? 'promote_admin_level' : 'demote_admin_level',
                    "Super admin changed {$admin->email} level from {$oldLevel} to {$validated['admin_level']}"
                );
            }

            ActivityLog::log(
                'update_admin',
                "Super admin updated admin account for {$admin->email}"
            );

            return $admin->fresh();
        });

        return response()->json([
            'success' => true,
            'message' => 'Admin berhasil diperbarui',
            'data' => [
                'admin' => $this->formatAdmin($updatedAdmin),
            ],
        ]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $admin = User::query()
            ->where('role', 'admin')
            ->find($id);

        if (! $admin) {
            return $this->notFoundResponse();
        }

        if ($request->user()->id === $admin->id) {
            return response()->json([
                'success' => false,
                'message' => 'Super admin tidak dapat mengubah status dirinya sendiri.',
            ], 403);
        }

        $updatedAdmin = DB::transaction(function () use ($admin, $validated): User {
            $admin->refresh();

            if (
                $validated['status'] !== 'active'
                && $admin->admin_level === 'super_admin'
                && $this->isLastActiveSuperAdmin($admin)
            ) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Super admin terakhir tidak dapat dinonaktifkan.',
                ], 403));
            }

            $admin->update(['status' => $validated['status']]);

            if ($validated['status'] === 'inactive') {
                $admin->tokens()->delete();
            }

            ActivityLog::log(
                'update_admin_status',
                "Super admin changed admin {$admin->email} status to {$validated['status']}"
            );

            return $admin->fresh();
        });

        return response()->json([
            'success' => true,
            'message' => 'Status admin berhasil diperbarui',
            'data' => [
                'admin' => $this->formatAdmin($updatedAdmin),
            ],
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $admin = User::query()
            ->where('role', 'admin')
            ->find($id);

        if (! $admin) {
            return $this->notFoundResponse();
        }

        if ($request->user()->id === $admin->id) {
            return response()->json([
                'success' => false,
                'message' => 'Super admin tidak dapat menghapus dirinya sendiri.',
            ], 403);
        }

        DB::transaction(function () use ($admin): void {
            $admin->refresh();

            if ($admin->admin_level === 'super_admin' && $this->isLastActiveSuperAdmin($admin)) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Super admin terakhir tidak dapat dihapus.',
                ], 403));
            }

            $email = $admin->email;
            $admin->tokens()->delete();
            $admin->delete();

            ActivityLog::log(
                'delete_admin',
                "Super admin deleted admin account for {$email}"
            );
        });

        return response()->json([
            'success' => true,
            'message' => 'Admin berhasil dihapus',
        ]);
    }

    private function formatAdmin(User $admin): array
    {
        return [
            'id' => $admin->id,
            'first_name' => $admin->first_name,
            'last_name' => $admin->last_name,
            'email' => $admin->email,
            'phone' => $admin->phone,
            'gender' => $admin->gender,
            'role' => $admin->role,
            'admin_level' => $admin->admin_level,
            'status' => $admin->status,
            'created_at' => $admin->created_at,
            'updated_at' => $admin->updated_at,
        ];
    }

    private function isLastActiveSuperAdmin(User $admin): bool
    {
        if ($admin->admin_level !== 'super_admin' || $admin->status !== 'active') {
            return false;
        }

        return User::query()
            ->where('role', 'admin')
            ->where('admin_level', 'super_admin')
            ->where('status', 'active')
            ->count() <= 1;
    }

    private function notFoundResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Admin tidak ditemukan',
        ], 404);
    }
}
