<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
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

        return response()->json([
            'message' => 'User berhasil diperbarui',
            'data'    => $this->formatUser($user->fresh(), $validated['status']),
        ]);
    }

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
