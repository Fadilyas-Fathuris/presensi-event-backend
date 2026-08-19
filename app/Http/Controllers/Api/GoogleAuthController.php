<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\GoogleRegisterCompleteRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\DomicileResolver;
use App\Services\GoogleAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class GoogleAuthController extends Controller
{
    public function __construct(
        private readonly GoogleAuthService $googleAuthService,
        private readonly DomicileResolver $domicileResolver
    ) {}

    /**
     * Initiate Google OAuth registration flow
     */
    #[OA\Get(
        path: '/api/auth/google/register/redirect',
        operationId: 'googleRegisterRedirect',
        summary: 'Initiate Google OAuth registration',
        description: 'Returns Google authorization URL for new user registration via OAuth.',
        tags: ['Google OAuth'],
        responses: [
            new OA\Response(response: 200, description: 'Authorization URL generated'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function registerRedirect(): JsonResponse
    {
        try {
            $authorizationUrl = $this->googleAuthService->generateAuthorizationUrl('register');

            return response()->json([
                'success' => true,
                'data' => [
                    'authorization_url' => $authorizationUrl,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membuat URL autentikasi Google.',
            ], 500);
        }
    }

    /**
     * Handle Google OAuth registration callback
     */
    #[OA\Get(
        path: '/api/auth/google/register/callback',
        operationId: 'googleRegisterCallback',
        summary: 'Handle Google OAuth registration callback',
        description: 'Processes callback and returns temporary token.',
        tags: ['Google OAuth'],
        parameters: [
            new OA\Parameter(name: 'code', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'state', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Callback processed'),
            new OA\Response(response: 400, description: 'Invalid state'),
            new OA\Response(response: 409, description: 'Account exists'),
        ]
    )]
    public function registerCallback(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
            'state' => 'required|string',
        ]);

        try {
            $callbackData = $this->googleAuthService->handleCallback(
                $request->query('code'),
                $request->query('state'),
                'register'
            );

            $googleUser = $callbackData['google_user'];
            $googleData = $this->googleAuthService->extractGoogleUserData($googleUser);

            // Check duplicates
            if ($this->googleAuthService->findUserByGoogleId($googleData['google_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun Google ini sudah terdaftar. Silakan login menggunakan Google.',
                ], 409);
            }

            if (User::where('email', $googleData['email'])->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email sudah terdaftar. Silakan login atau hubungkan akun Google Anda di halaman profil.',
                ], 409);
            }

            $tempToken = $this->googleAuthService->createTemporaryToken($googleData);

            return response()->json([
                'success' => true,
                'data' => [
                    'temp_token' => $tempToken,
                    'user_data' => [
                        'email' => $googleData['email'],
                        'first_name' => $googleData['first_name'],
                        'last_name' => $googleData['last_name'],
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::warning('Google OAuth registration callback error', [
                'error' => $e->getMessage(),
                'state' => $request->query('state'),
            ]);

            if (str_contains($e->getMessage(), 'Invalid state') || str_contains($e->getMessage(), 'CSRF')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid state parameter. Possible CSRF attack or expired session.',
                ], 400);
            }

            if (str_contains($e->getMessage(), 'Email') && str_contains($e->getMessage(), 'verified')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 400);
            }

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memproses autentikasi Google. Silakan coba lagi.',
            ], 500);
        }
    }

    /**
     * Complete Google OAuth registration with additional user data
     */
    #[OA\Post(
        path: '/api/auth/google/register/complete',
        operationId: 'googleRegisterComplete',
        summary: 'Complete Google OAuth registration',
        description: 'Completes registration by creating user with additional required data. Returns pending status.',
        tags: ['Google OAuth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['temp_token', 'phone', 'graduation_year', 'birth_date', 'gender'],
                properties: [
                    new OA\Property(property: 'temp_token', type: 'string', example: 'abc123...'),
                    new OA\Property(property: 'phone', type: 'string', example: '081234567890'),
                    new OA\Property(property: 'graduation_year', type: 'integer', example: 2020),
                    new OA\Property(property: 'birth_date', type: 'string', format: 'date', example: '2000-01-01'),
                    new OA\Property(property: 'gender', type: 'string', enum: ['Laki-laki', 'Perempuan'], example: 'Laki-laki'),
                    new OA\Property(property: 'domicile_province_code', type: 'string', nullable: true),
                    new OA\Property(property: 'domicile_city_code', type: 'string', nullable: true),
                    new OA\Property(property: 'domicile_district_code', type: 'string', nullable: true),
                    new OA\Property(property: 'domicile_village_code', type: 'string', nullable: true),
                    new OA\Property(property: 'domicile_postal_code', type: 'string', nullable: true),
                    new OA\Property(property: 'domicile_address', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Registration completed successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Registrasi berhasil. Akun Anda menunggu persetujuan admin.'),
                        new OA\Property(property: 'data', type: 'object', properties: [
                            new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                            new OA\Property(property: 'access_token', type: 'null', example: null),
                            new OA\Property(property: 'token_type', type: 'null', example: null),
                        ]),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid or expired token',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Token registrasi tidak valid atau sudah kedaluwarsa.'),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function registerComplete(GoogleRegisterCompleteRequest $request): JsonResponse
    {
        // LOG: Incoming request
        Log::info('Google OAuth registration complete request', [
            'has_temp_token' => !empty($request->temp_token),
            'temp_token_preview' => $request->temp_token ? substr($request->temp_token, 0, 16) . '...' : 'MISSING',
            'email' => $request->email ?? 'not provided',
        ]);

        // Validate temporary token and retrieve Google data
        $googleData = $this->googleAuthService->validateTemporaryToken($request->temp_token);

        // LOG: Token validation result
        Log::info('Temp token validation result', [
            'valid' => $googleData !== null,
            'google_data' => $googleData ? [
                'email' => $googleData['email'] ?? 'missing',
                'google_id' => $googleData['google_id'] ?? 'missing',
                'first_name' => $googleData['first_name'] ?? 'missing',
                'last_name' => $googleData['last_name'] ?? 'missing',
            ] : 'NULL',
        ]);

        if (!$googleData) {
            Log::warning('Registration failed: invalid temp token');
            return response()->json([
                'success' => false,
                'message' => 'Token registrasi tidak valid atau sudah kedaluwarsa. Silakan mulai ulang proses registrasi.',
            ], 400);
        }

        try {
            // Resolve domicile data
            $domicile = $this->domicileResolver->resolve($request->all());

            // Create user in transaction
            $user = DB::transaction(function () use ($request, $googleData, $domicile): User {
                // Generate random password for Google-only users
                $randomPassword = bin2hex(random_bytes(16)); // 32 characters

                // LOG: About to create user
                Log::info('Creating user with Google OAuth data', [
                    'email' => $googleData['email'],
                    'google_id' => $googleData['google_id'] ?? 'MISSING',
                    'auth_provider' => 'google',
                ]);

                $user = User::create([
                    'first_name' => $googleData['first_name'],
                    'last_name' => $googleData['last_name'],
                    'email' => $googleData['email'],
                    'google_id' => $googleData['google_id'],
                    'auth_provider' => 'google',
                    'password' => Hash::make($randomPassword),
                    'phone' => $request->phone,
                    'graduation_year' => $request->graduation_year,
                    'birth_date' => $request->birth_date,
                    'gender' => $request->gender,
                    'role' => 'alumni',
                    'status' => 'pending',
                ]);

                // LOG: User created
                Log::info('User created successfully', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'google_id' => $user->google_id ?? 'NULL',
                    'auth_provider' => $user->auth_provider,
                ]);

                // Create domicile if provided
                if ($domicile !== null) {
                    $user->domicile()->create($domicile);
                }

                return $user->fresh('domicile');
            });

            return response()->json([
                'success' => true,
                'message' => 'Registrasi berhasil. Akun Anda menunggu persetujuan admin.',
                'data' => [
                    'user' => new UserResource($user),
                    'access_token' => null,
                    'token_type' => null,
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Google OAuth registration completion error', [
                'error' => $e->getMessage(),
                'email' => $googleData['email'] ?? 'unknown',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyelesaikan registrasi. Silakan coba lagi.',
            ], 500);
        }
    }

    /**
     * Initiate Google OAuth login flow
     */
    #[OA\Get(
        path: '/api/auth/google/login/redirect',
        operationId: 'googleLoginRedirect',
        summary: 'Initiate Google OAuth login',
        description: 'Returns Google authorization URL for existing user login via OAuth.',
        tags: ['Google OAuth'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Authorization URL generated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object', properties: [
                            new OA\Property(property: 'authorization_url', type: 'string', example: 'https://accounts.google.com/o/oauth2/v2/auth?...'),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function loginRedirect(): JsonResponse
    {
        try {
            $authorizationUrl = $this->googleAuthService->generateAuthorizationUrl('login');

            return response()->json([
                'success' => true,
                'data' => [
                    'authorization_url' => $authorizationUrl,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membuat URL autentikasi Google.',
            ], 500);
        }
    }

    /**
     * Handle Google OAuth login callback
     */
    #[OA\Get(
        path: '/api/auth/google/login/callback',
        operationId: 'googleLoginCallback',
        summary: 'Handle Google OAuth login callback',
        description: 'Processes login callback, validates user status and role, and returns Sanctum token. Implements single session policy (revokes all previous tokens).',
        tags: ['Google OAuth'],
        parameters: [
            new OA\Parameter(
                name: 'code',
                in: 'query',
                required: true,
                description: 'Authorization code from Google',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'state',
                in: 'query',
                required: true,
                description: 'State parameter for CSRF protection',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Login berhasil.'),
                        new OA\Property(property: 'data', type: 'object', properties: [
                            new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                            new OA\Property(property: 'access_token', type: 'string', example: '1|abc123...'),
                            new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                        ]),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid state or unverified email',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Invalid state parameter.'),
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Access denied - pending/rejected/inactive status or admin role',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Akun Anda masih menunggu persetujuan admin.'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'User not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Akun Google ini belum terdaftar.'),
                    ]
                )
            ),
        ]
    )]
    public function loginCallback(Request $request): JsonResponse
    {
        // Validate required parameters
        $request->validate([
            'code' => 'required|string',
            'state' => 'required|string',
        ]);

        try {
            // Handle OAuth callback and get Google user data
            $callbackData = $this->googleAuthService->handleCallback(
                $request->query('code'),
                $request->query('state'),
                'login'
            );

            $googleUser = $callbackData['google_user'];
            $googleData = $this->googleAuthService->extractGoogleUserData($googleUser);

            // Find user by Google ID
            $user = $this->googleAuthService->findUserByGoogleId($googleData['google_id']);

            if (!$user) {
                // Try to find user by email since they might be registered by admin/email-password
                $user = User::where('email', $googleData['email'])->first();
                if ($user) {
                    // Check user role (alumni only)
                    if ($user->role === 'admin') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Login dengan Google hanya tersedia untuk alumni. Admin harus login dengan email dan password.',
                        ], 403);
                    }

                    // Link the Google account automatically since the email is verified by Google
                    $user->update([
                        'google_id' => $googleData['google_id'],
                        'auth_provider' => 'google',
                    ]);

                    // Create notification
                    $user->alumniNotifications()->create([
                        'type' => 'google_account_linked',
                        'title' => 'Akun Google Berhasil Dihubungkan',
                        'body' => sprintf(
                            'Akun Google Anda (%s) berhasil dihubungkan dengan akun alumni pada %s. Anda sekarang dapat login menggunakan Google atau email/password.',
                            $googleData['email'],
                            now()->timezone('Asia/Jakarta')->format('d M Y H:i')
                        ),
                        'priority' => 'medium',
                        'is_read' => false,
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Akun Google ini belum terdaftar. Silakan registrasi terlebih dahulu.',
                    ], 404);
                }
            }

            // Check user status
            if ($user->status === 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun Anda masih menunggu persetujuan admin. Silakan coba lagi nanti.',
                ], 403);
            }

            if ($user->status === 'rejected') {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun Anda ditolak oleh admin. Silakan hubungi administrator untuk informasi lebih lanjut.',
                ], 403);
            }

            if ($user->status === 'inactive') {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun Anda tidak aktif. Silakan hubungi administrator.',
                ], 403);
            }

            // Check user role (alumni only)
            if ($user->role === 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Login dengan Google hanya tersedia untuk alumni. Admin harus login dengan email dan password.',
                ], 403);
            }

            // Single session policy: revoke all existing tokens
            $user->tokens()->delete();

            // Generate new Sanctum token
            $token = $user->createToken('google-oauth-token')->plainTextToken;

            // Load relationships for response
            $user->load('domicile');

            return response()->json([
                'success' => true,
                'message' => 'Login berhasil.',
                'data' => [
                    'user' => new UserResource($user),
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                ],
            ]);
        } catch (\Exception $e) {
            // Log the error for debugging
            Log::warning('Google OAuth login callback error', [
                'error' => $e->getMessage(),
                'state' => $request->query('state'),
            ]);

            // Check if it's a state validation error
            if (str_contains($e->getMessage(), 'Invalid state') || str_contains($e->getMessage(), 'CSRF')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid state parameter. Possible CSRF attack or expired session.',
                ], 400);
            }

            // Check if it's an email verification error
            if (str_contains($e->getMessage(), 'Email') && str_contains($e->getMessage(), 'verified')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 400);
            }

            // Generic error response
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memproses autentikasi Google. Silakan coba lagi.',
            ], 500);
        }
    }

    /**
     * Initiate Google account linking flow
     */
    #[OA\Get(
        path: '/api/auth/google/link/redirect',
        operationId: 'googleLinkRedirect',
        summary: 'Initiate Google account linking',
        description: 'Returns Google authorization URL to link Google account to existing user. Requires authentication.',
        security: [['sanctum' => []]],
        tags: ['Google OAuth'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Authorization URL generated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object', properties: [
                            new OA\Property(property: 'authorization_url', type: 'string', example: 'https://accounts.google.com/o/oauth2/v2/auth?...'),
                        ]),
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'User not active or not alumni',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Hanya alumni dengan status aktif yang dapat menghubungkan akun Google.'),
                    ]
                )
            ),
            new OA\Response(
                response: 409,
                description: 'Google account already linked',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Akun Google sudah terhubung.'),
                    ]
                )
            ),
        ]
    )]
    public function linkRedirect(Request $request): JsonResponse
    {
        $user = $request->user();

        // Validate user status
        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya alumni dengan status aktif yang dapat menghubungkan akun Google.',
            ], 403);
        }

        // Validate user role
        if ($user->role !== 'alumni') {
            return response()->json([
                'success' => false,
                'message' => 'Fitur ini hanya tersedia untuk alumni.',
            ], 403);
        }

        // Check if already linked
        if (!empty($user->google_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Google sudah terhubung. Silakan lepas hubungan terlebih dahulu jika ingin menghubungkan akun Google lain.',
            ], 409);
        }

        try {
            $authorizationUrl = $this->googleAuthService->generateAuthorizationUrl('link', $user->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'authorization_url' => $authorizationUrl,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membuat URL autentikasi Google.',
            ], 500);
        }
    }

    /**
     * Handle Google account linking callback
     */
    #[OA\Get(
        path: '/api/auth/google/link/callback',
        operationId: 'googleLinkCallback',
        summary: 'Handle Google account linking callback',
        description: 'Processes callback and links Google account to authenticated user. Creates notification.',
        security: [['sanctum' => []]],
        tags: ['Google OAuth'],
        parameters: [
            new OA\Parameter(
                name: 'code',
                in: 'query',
                required: true,
                description: 'Authorization code from Google',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'state',
                in: 'query',
                required: true,
                description: 'State parameter for CSRF protection',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Account linked successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Akun Google berhasil dihubungkan.'),
                        new OA\Property(property: 'data', type: 'object', properties: [
                            new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                        ]),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid state or expired session',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Invalid state parameter.'),
                    ]
                )
            ),
            new OA\Response(
                response: 409,
                description: 'Google ID already used by another account',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Akun Google ini sudah terhubung dengan akun lain.'),
                    ]
                )
            ),
        ]
    )]
    public function linkCallback(Request $request): JsonResponse
    {
        $user = $request->user();

        // Validate required parameters
        $request->validate([
            'code' => 'required|string',
            'state' => 'required|string',
        ]);

        try {
            // Handle OAuth callback and get Google user data
            $callbackData = $this->googleAuthService->handleCallback(
                $request->query('code'),
                $request->query('state'),
                'link'
            );

            $googleUser = $callbackData['google_user'];
            $googleData = $this->googleAuthService->extractGoogleUserData($googleUser);

            // Check if google_id is already used by another user
            $existingUser = $this->googleAuthService->findUserByGoogleId($googleData['google_id']);
            if ($existingUser && $existingUser->id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun Google ini sudah terhubung dengan akun lain. Silakan gunakan akun Google yang berbeda.',
                ], 409);
            }

            // Link Google account
            $success = $this->googleAuthService->linkGoogleAccount($user, $googleData);

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal menghubungkan akun Google. Silakan coba lagi.',
                ], 500);
            }

            // Refresh user data
            $user->refresh();
            $user->load('domicile');

            // Create notification
            $user->alumniNotifications()->create([
                'type' => 'google_account_linked',
                'title' => 'Akun Google Berhasil Dihubungkan',
                'body' => sprintf(
                    'Akun Google Anda (%s) berhasil dihubungkan dengan akun alumni pada %s. Anda sekarang dapat login menggunakan Google atau email/password.',
                    $googleData['email'],
                    now()->timezone('Asia/Jakarta')->format('d M Y H:i')
                ),
                'priority' => 'medium',
                'is_read' => false,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Akun Google berhasil dihubungkan. Anda sekarang dapat login dengan Google.',
                'data' => [
                    'user' => new UserResource($user),
                ],
            ]);
        } catch (\Exception $e) {
            // Log the error for debugging
            Log::warning('Google account linking error', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'state' => $request->query('state'),
            ]);

            // Check if it's a state validation error
            if (str_contains($e->getMessage(), 'Invalid state') || str_contains($e->getMessage(), 'CSRF')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid state parameter. Possible CSRF attack or expired session.',
                ], 400);
            }

            // Check if it's an email verification error
            if (str_contains($e->getMessage(), 'Email') && str_contains($e->getMessage(), 'verified')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 400);
            }

            // Generic error response
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memproses autentikasi Google. Silakan coba lagi.',
            ], 500);
        }
    }

    /**
     * Unlink Google account from authenticated user
     */
    #[OA\Delete(
        path: '/api/auth/google/unlink',
        operationId: 'googleUnlink',
        summary: 'Unlink Google account',
        description: 'Removes Google account link from authenticated user. Requires password to be set to prevent account lockout.',
        security: [['sanctum' => []]],
        tags: ['Google OAuth'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Google account unlinked successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Akun Google berhasil dilepas.'),
                        new OA\Property(property: 'data', type: 'object', properties: [
                            new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                        ]),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'No Google account linked',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Tidak ada akun Google yang terhubung.'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Password not set - cannot unlink',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Anda harus mengatur password terlebih dahulu sebelum melepas akun Google.'),
                    ]
                )
            ),
        ]
    )]
    public function unlink(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if Google account is linked
        if (empty($user->google_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada akun Google yang terhubung dengan akun Anda.',
            ], 404);
        }

        // Check if user has password set (prevent account lockout)
        if (empty($user->password) || !Hash::check('dummy', $user->password) === false) {
            // More reliable check: try to verify if password hash exists and is valid format
            if (empty($user->password) || strlen($user->password) < 60) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda harus mengatur password terlebih dahulu sebelum melepas akun Google. Silakan ke halaman Ubah Password untuk mengatur password Anda.',
                ], 422);
            }
        }

        try {
            // Store google email before unlinking for notification
            $googleEmail = $user->email;

            // Unlink Google account
            $success = $this->googleAuthService->unlinkGoogleAccount($user);

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal melepas akun Google. Silakan coba lagi.',
                ], 500);
            }

            // Refresh user data
            $user->refresh();
            $user->load('domicile');

            // Create high-priority notification
            $user->alumniNotifications()->create([
                'type' => 'google_account_unlinked',
                'title' => 'Akun Google Berhasil Dilepas',
                'body' => sprintf(
                    'Akun Google Anda (%s) telah dilepas dari akun alumni pada %s. Anda sekarang hanya dapat login menggunakan email dan password. Jika ini bukan tindakan Anda, segera hubungi administrator.',
                    $googleEmail,
                    now()->timezone('Asia/Jakarta')->format('d M Y H:i')
                ),
                'priority' => 'high',
                'is_read' => false,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Akun Google berhasil dilepas. Anda sekarang hanya dapat login dengan email dan password.',
                'data' => [
                    'user' => new UserResource($user),
                ],
            ]);
        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error('Google account unlinking error', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat melepas akun Google. Silakan coba lagi.',
            ], 500);
        }
    }
}
