<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\AlumniNotification;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
    path: '/api/auth/register',
    operationId: 'register',
    summary: 'Register a new alumni user',
    description: 'Creates a new alumni account and returns an access token.',
    tags: ['Authentication'],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['first_name', 'last_name', 'email', 'password', 'password_confirmation'],
            properties: [
                new OA\Property(property: 'first_name', type: 'string', example: 'Ahmad'),
                new OA\Property(property: 'last_name',  type: 'string', example: 'Fauzi'),
                new OA\Property(property: 'gender', type: 'string', enum: ["Laki-laki", "Perempuan"], example: 'Laki-laki'),
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'ahmad@gmail.com'),
                new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'password123'),
                new OA\Property(property: 'phone', type: 'string', example: '081234567890'),
                new OA\Property(property: 'graduation_year', type: 'string', example: '2015'),
                new OA\Property(property: 'birth_date', type: 'string', format: 'date', example: '2003-08-17'),
            ]
        )
    ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Registration successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Registration successful'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'user',         ref: '#/components/schemas/User'),
                                new OA\Property(property: 'access_token', type: 'string', example: '1|abc123...'),
                                new OA\Property(property: 'token_type',   type: 'string', example: 'Bearer'),
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
        ]
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'first_name'      => $request->first_name,
            'last_name'       => $request->last_name,
            'gender'          => $request->gender,
            'email'           => $request->email,
            'password'        => Hash::make($request->password),
            'phone'           => $request->phone,
            'graduation_year' => $request->graduation_year,
            'birth_date'      => $request->birth_date,
            'role'            => 'alumni',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'data'    => [
                'user'         => $user,
                'access_token' => $token,
                'token_type'   => 'Bearer',
            ],
        ], 201);
    }

    #[OA\Post(
        path: '/api/auth/login',
        operationId: 'login',
        summary: 'Login user',
        description: 'Authenticates user credentials and returns an access token.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email',    type: 'string', format: 'email',    example: 'ahmad@gmail.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Login successful'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'user',         ref: '#/components/schemas/User'),
                                new OA\Property(property: 'access_token', type: 'string', example: '2|xyz789...'),
                                new OA\Property(property: 'token_type',   type: 'string', example: 'Bearer'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Invalid credentials',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
            ),
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'email atau password salah',
            ], 401);
        }

        // Revoke all previous tokens (single session policy)
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        if ($user->role === 'admin') {
            \App\Models\ActivityLog::log('login', 'Admin logged in', $user->id);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data'    => [
                'user'         => $user,
                'access_token' => $token,
                'token_type'   => 'Bearer',
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/auth/forgot-password',
        operationId: 'forgotPassword',
        summary: 'Send password reset link',
        description: 'Sends a password reset link to the user email when the account exists.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'ahmad@gmail.com'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Reset link request accepted',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Jika email terdaftar, link reset password akan dikirim.'),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 429, description: 'Too many reset requests', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink($validated);

        if ($status === Password::RESET_THROTTLED) {
            return response()->json([
                'success' => false,
                'message' => 'Terlalu banyak permintaan reset password. Silakan coba lagi nanti.',
            ], 429);
        }

        return response()->json([
            'success' => true,
            'message' => 'Jika email terdaftar, link reset password akan dikirim.',
        ]);
    }

    #[OA\Post(
        path: '/api/auth/reset-password',
        operationId: 'resetPassword',
        summary: 'Reset password',
        description: 'Resets user password using the token sent to email.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'token', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'ahmad@gmail.com'),
                    new OA\Property(property: 'token', type: 'string', example: 'reset-token-from-email'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'passwordbaru123'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'passwordbaru123'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Password reset successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Password berhasil direset. Silakan login dengan password baru.'),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error or invalid token', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function resetPassword(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $credentials,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'password_changed_at' => now(),
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();

                event(new PasswordReset($user));

                AlumniNotification::create([
                    'user_id' => $user->id,
                    'title' => 'Password Berhasil Direset',
                    'body' => 'Password akun Anda berhasil direset pada ' . now()->translatedFormat('d F Y, H:i') . '. Jika Anda tidak melakukan perubahan ini, segera hubungi admin.',
                    'type' => 'password_reset',
                    'priority' => 'high',
                    'data' => [
                        'changed_at' => now()->toIso8601String(),
                    ],
                    'is_read' => false,
                ]);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => ['Token reset password tidak valid atau sudah kedaluwarsa.'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil direset. Silakan login dengan password baru.',
        ]);
    }

    #[OA\Post(
        path: '/api/auth/logout',
        operationId: 'logout',
        summary: 'Logout user',
        description: 'Revokes the current user access token.',
        security: [['bearerAuth' => []]],
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Logout successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Logged out successfully'),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    #[OA\Get(
        path: '/api/auth/me',
        operationId: 'me',
        summary: 'Get authenticated user profile',
        description: 'Returns the currently authenticated user data.',
        security: [['bearerAuth' => []]],
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User profile retrieved',
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
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'user' => $request->user(),
            ],
        ]);
    }

    // =========================================================================
    // ── PROFILE UPDATE ────────────────────────────────────────────────────────
    // =========================================================================

    #[OA\Put(
        path: '/api/auth/profile',
        operationId: 'updateProfile',
        summary: 'Update alumni profile',
        description: 'Updates the authenticated alumni profile data.',
        security: [['bearerAuth' => []]],
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'first_name',      type: 'string',  example: 'Ahmad'),
                    new OA\Property(property: 'last_name',       type: 'string',  example: 'Fauzi'),
                    new OA\Property(property: 'gender',          type: 'string',  enum: ['Laki-laki', 'Perempuan']),
                    new OA\Property(property: 'phone',           type: 'string',  example: '081234567890'),
                    new OA\Property(property: 'graduation_year', type: 'integer', example: 2020),
                    new OA\Property(property: 'birth_date',      type: 'string',  format: 'date', example: '2000-01-01'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profile updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string',  example: 'Profile updated successfully'),
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
            new OA\Response(response: 401, description: 'Unauthenticated',   content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error',  content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name'      => ['sometimes', 'string', 'max:100'],
            'last_name'       => ['sometimes', 'string', 'max:100'],
            'gender'          => ['sometimes', Rule::in(['Laki-laki', 'Perempuan'])],
            'phone'           => ['sometimes', 'string', 'max:20'],
            'graduation_year' => ['sometimes', 'digits:4', 'integer', 'min:1950', 'max:' . date('Y')],
            'birth_date'      => ['sometimes', 'date', 'before:today'],
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data'    => ['user' => $user->fresh()],
        ]);
    }

    #[OA\Put(
        path: '/api/auth/change-password',
        operationId: 'changePassword',
        summary: 'Change user password',
        description: 'Changes the password of the authenticated user. Revokes all other tokens except current one.',
        security: [['bearerAuth' => []]],
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['current_password', 'new_password', 'new_password_confirmation'],
                properties: [
                    new OA\Property(property: 'current_password', type: 'string', format: 'password', example: 'oldpassword123'),
                    new OA\Property(property: 'new_password', type: 'string', format: 'password', example: 'newpassword123'),
                    new OA\Property(property: 'new_password_confirmation', type: 'string', format: 'password', example: 'newpassword123'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Password changed successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Password berhasil diperbarui'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'password_changed_at', type: 'string', example: '2026-06-02T10:00:00.000000Z'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error or incorrect current password',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password saat ini tidak sesuai',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($validated['new_password']),
            'password_changed_at' => now(),
        ]);

        $currentTokenId = $request->user()->currentAccessToken()?->id;

        if ($currentTokenId) {
            $user->tokens()->where('id', '!=', $currentTokenId)->delete();
        }

        // Create password changed notification
        \App\Models\AlumniNotification::create([
            'user_id'  => $user->id,
            'title'    => 'Password Berhasil Diubah',
            'body'     => 'Password akun Anda berhasil diperbarui pada ' . now()->translatedFormat('d F Y, H:i') . '. Jika Anda tidak melakukan perubahan ini, segera hubungi admin.',
            'type'     => 'password_changed',
            'priority' => 'high',
            'data'     => json_encode([
                'changed_at' => now()->toIso8601String(),
            ]),
            'is_read'  => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diperbarui',
            'data' => [
                'password_changed_at' => $user->fresh()->password_changed_at,
            ],
        ]);
    }

    // =========================================================================
    // ── AVATAR UPLOAD ─────────────────────────────────────────────────────────
    // =========================================================================

    #[OA\Post(
        path: '/api/auth/profile/avatar',
        operationId: 'uploadAvatar',
        summary: 'Upload profile avatar',
        description: 'Uploads a new profile picture for the authenticated user.',
        security: [['bearerAuth' => []]],
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'avatar', type: 'string', format: 'binary'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Avatar uploaded successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'avatar_url', type: 'string', example: '/storage/avatars/abc.jpg'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated',  content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();

        // Hapus avatar lama jika ada
        if ($user->avatar_url) {
            $oldPath = str_replace('/storage/', '', $user->avatar_url);
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $url  = '/storage/' . $path;

        $user->update(['avatar_url' => $url]);

        return response()->json([
            'success' => true,
            'data'    => ['avatar_url' => $url],
        ]);
    }

    #[OA\Delete(
        path: '/api/auth/profile/avatar',
        operationId: 'deleteAvatar',
        summary: 'Delete profile avatar',
        description: 'Removes the profile picture of the authenticated user.',
        security: [['bearerAuth' => []]],
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Avatar deleted successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Avatar deleted successfully'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'No avatar to delete', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->avatar_url) {
            return response()->json([
                'success' => false,
                'message' => 'No avatar to delete',
            ], 404);
        }

        // Hapus file dari storage
        $oldPath = str_replace('/storage/', '', $user->avatar_url);
        if (Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        // Set avatar_url ke null
        $user->update(['avatar_url' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Avatar deleted successfully',
        ]);
    }
}
