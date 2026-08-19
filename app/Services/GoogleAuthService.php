<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

class GoogleAuthService
{
    private const STATE_PREFIX = 'oauth_state';
    private const TEMP_TOKEN_PREFIX = 'oauth_temp_token';
    private const STATE_TTL = 1800; // 30 minutes in seconds (increased from 10)
    private const TEMP_TOKEN_TTL = 900; // 15 minutes in seconds

    /**
     * Generate Google OAuth authorization URL with state parameter
     *
     * @param string $flow OAuth flow type: 'register', 'login', or 'link'
     * @param int|null $userId User ID for link flow (required for link flow)
     * @return string Google authorization URL
     */
    public function generateAuthorizationUrl(string $flow, ?int $userId = null): string
    {
        // Generate cryptographically secure state token
        $state = bin2hex(random_bytes(32));

        // Store state in cache with flow type and optional user ID
        $cacheKey = $this->getStateCacheKey($flow, $state);
        Cache::put($cacheKey, [
            'flow' => $flow,
            'user_id' => $userId,
            'created_at' => now()->toDateTimeString(),
        ], self::STATE_TTL);

        // DEBUG: Verify state was stored
        \Log::info('OAuth state generated', [
            'flow' => $flow,
            'state' => substr($state, 0, 16) . '...',
            'cache_key' => $cacheKey,
            'stored' => Cache::has($cacheKey),
            'ttl_seconds' => self::STATE_TTL,
        ]);

        // Get redirect URI for this flow
        $redirectUri = $this->getRedirectUri($flow);

        // Generate Socialite authorization URL with state and custom redirect_uri
        return Socialite::driver('google')
            ->redirectUrl($redirectUri)
            ->stateless()
            ->with(['state' => $state])
            ->redirect()
            ->getTargetUrl();
    }

    /**
     * Handle OAuth callback: validate state, exchange code for token, fetch user data
     *
     * @param string $code Authorization code from Google
     * @param string $state State parameter from Google
     * @param string $flow Expected OAuth flow type
     * @return array Array containing: google_user (SocialiteUser)
     * @throws \Exception If state validation fails or OAuth exchange fails
     */
    public function handleCallback(string $code, string $state, string $flow): array
    {
        // Validate state token (CSRF protection)
        $stateData = $this->validateState($state, $flow);

        try {
            // Get redirect URI for this flow (must match the one used in authorization)
            $redirectUri = $this->getRedirectUri($flow);

            // Exchange code for user data with the same redirect_uri
            $googleUser = Socialite::driver('google')
                ->redirectUrl($redirectUri)
                ->stateless()
                ->user();
        } catch (\Laravel\Socialite\Two\InvalidStateException $e) {
            throw new \Exception('Invalid state parameter. Possible CSRF attack or expired session.');
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            // Handle specific Google OAuth errors
            $response = $e->getResponse();
            $body = json_decode($response->getBody()->getContents(), true);

            if (isset($body['error'])) {
                $error = $body['error'];

                if ($error === 'access_denied') {
                    throw new \Exception('Autentikasi Google dibatalkan. Silakan coba lagi.');
                }

                if ($error === 'invalid_grant' || $error === 'invalid_code') {
                    throw new \Exception('Kode autentikasi tidak valid atau sudah kedaluwarsa. Silakan mulai ulang proses login.');
                }

                throw new \Exception('Terjadi kesalahan pada layanan Google. Silakan coba lagi nanti.');
            }

            throw new \Exception('Terjadi kesalahan saat berkomunikasi dengan Google. Silakan coba lagi.');
        } catch (\Exception $e) {
            // Generic error handling
            if (str_contains($e->getMessage(), 'cURL error')) {
                throw new \Exception('Koneksi ke Google gagal. Periksa koneksi internet Anda dan coba lagi.');
            }

            throw $e;
        }

        // Verify email is verified with Google
        if (!($googleUser->user['email_verified'] ?? false)) {
            throw new \Exception('Email Google Anda belum diverifikasi. Silakan verifikasi email di akun Google Anda terlebih dahulu.');
        }

        // Delete state token (single-use enforcement)
        $this->deleteState($flow, $state);

        return [
            'google_user' => $googleUser,
            'state_data' => $stateData,
        ];
    }

    /**
     * Validate state parameter and return associated data
     *
     * @param string $state State token to validate
     * @param string $expectedFlow Expected flow type
     * @return array State data from cache
     * @throws \Exception If state is invalid or expired
     */
    public function validateState(string $state, string $expectedFlow): array
    {
        $cacheKey = $this->getStateCacheKey($expectedFlow, $state);
        $stateData = Cache::get($cacheKey);

        // DEBUG: Log validation attempt
        \Log::info('OAuth state validation', [
            'flow' => $expectedFlow,
            'state' => substr($state, 0, 16) . '...',
            'cache_key' => $cacheKey,
            'found' => $stateData !== null,
        ]);

        if (!$stateData) {
            // Try to find any state for this flow (debugging)
            $allStates = $this->findStatesForFlow($expectedFlow);

            \Log::warning('OAuth state not found in cache', [
                'flow' => $expectedFlow,
                'state' => $state,
                'cache_key' => $cacheKey,
                'available_states_count' => count($allStates),
                'available_states' => array_map(fn($s) => substr($s, 0, 16) . '...', $allStates),
            ]);

            throw new \Exception('Invalid state parameter. Possible CSRF attack or expired session.');
        }

        if ($stateData['flow'] !== $expectedFlow) {
            \Log::warning('OAuth state flow mismatch', [
                'expected' => $expectedFlow,
                'actual' => $stateData['flow'],
            ]);
            throw new \Exception('State flow mismatch. Expected: ' . $expectedFlow . ', got: ' . $stateData['flow']);
        }

        \Log::info('OAuth state validated successfully', [
            'flow' => $expectedFlow,
            'created_at' => $stateData['created_at'] ?? 'unknown',
        ]);

        return $stateData;
    }

    /**
     * Find user by Google ID
     *
     * @param string $googleId Google user ID
     * @return User|null User model or null if not found
     */
    public function findUserByGoogleId(string $googleId): ?User
    {
        return User::where('google_id', $googleId)->first();
    }

    /**
     * Create temporary registration token and store Google user data
     *
     * @param array $googleData Google user data (google_id, email, first_name, last_name)
     * @return string Temporary token
     */
    public function createTemporaryToken(array $googleData): string
    {
        // Generate cryptographically secure temporary token
        $token = bin2hex(random_bytes(32));

        // Store Google user data in cache
        $cacheKey = $this->getTempTokenCacheKey($token);
        Cache::put($cacheKey, $googleData, self::TEMP_TOKEN_TTL);

        return $token;
    }

    /**
     * Validate temporary token and return Google user data
     *
     * @param string $token Temporary token to validate
     * @return array|null Google user data or null if invalid/expired
     */
    public function validateTemporaryToken(string $token): ?array
    {
        $cacheKey = $this->getTempTokenCacheKey($token);
        $data = Cache::get($cacheKey);

        if ($data) {
            // Delete token (single-use enforcement)
            Cache::forget($cacheKey);
        }

        return $data;
    }

    /**
     * Link Google account to existing user
     *
     * @param User $user User to link
     * @param array $googleData Google user data
     * @return bool Success status
     */
    public function linkGoogleAccount(User $user, array $googleData): bool
    {
        return $user->update([
            'google_id' => $googleData['google_id'],
            'auth_provider' => 'google',
        ]);
    }

    /**
     * Unlink Google account from user
     *
     * @param User $user User to unlink
     * @return bool Success status
     */
    public function unlinkGoogleAccount(User $user): bool
    {
        return $user->update([
            'google_id' => null,
            'auth_provider' => 'email',
        ]);
    }

    /**
     * Extract and format Google user data from Socialite user object
     *
     * @param SocialiteUser $googleUser Socialite user object
     * @return array Formatted user data
     */
    public function extractGoogleUserData(SocialiteUser $googleUser): array
    {
        $name = $googleUser->getName() ?? '';
        $nameParts = explode(' ', trim($name), 2);

        return [
            'google_id' => $googleUser->getId(),
            'email' => $googleUser->getEmail(),
            'first_name' => $nameParts[0] ?? '',
            'last_name' => $nameParts[1] ?? '',
        ];
    }

    /**
     * Get redirect URI for a specific OAuth flow
     *
     * @param string $flow OAuth flow type: 'register', 'login', or 'link'
     * @return string Full redirect URI
     */
    private function getRedirectUri(string $flow): string
    {
        // Base URL from config should be frontend URL (e.g., http://localhost:3000/alumni)
        $baseUrl = rtrim(config('services.google.redirect'), '/');

        // Map flow to frontend route path
        $pathMap = [
            'register' => '/register',
            'login' => '/login',
            'link' => '/profile',
        ];

        return $baseUrl . ($pathMap[$flow] ?? '/login');
    }

    /**
     * Find all cached states for a flow (debugging helper)
     *
     * @param string $flow OAuth flow type
     * @return array Array of state tokens
     */
    private function findStatesForFlow(string $flow): array
    {
        // This is for debugging only - finds all cached states for a flow
        try {
            $prefix = self::STATE_PREFIX . ':' . $flow . ':';

            // For database cache driver
            $states = \DB::table('cache')
                ->where('key', 'like', $prefix . '%')
                ->where('expiration', '>', time())
                ->pluck('key')
                ->map(fn($key) => str_replace($prefix, '', $key))
                ->toArray();

            return $states;
        } catch (\Exception $e) {
            \Log::error('Error finding states for flow', [
                'flow' => $flow,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Delete state token from cache
     *
     * @param string $flow OAuth flow type
     * @param string $state State token
     * @return void
     */
    private function deleteState(string $flow, string $state): void
    {
        $cacheKey = $this->getStateCacheKey($flow, $state);
        Cache::forget($cacheKey);
    }

    /**
     * Get cache key for state token
     *
     * @param string $flow OAuth flow type
     * @param string $state State token
     * @return string Cache key
     */
    private function getStateCacheKey(string $flow, string $state): string
    {
        return self::STATE_PREFIX . ':' . $flow . ':' . $state;
    }

    /**
     * Get cache key for temporary token
     *
     * @param string $token Temporary token
     * @return string Cache key
     */
    private function getTempTokenCacheKey(string $token): string
    {
        return self::TEMP_TOKEN_PREFIX . ':' . $token;
    }
}
