<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class GoogleOAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_login_callback_links_existing_user_by_email_automatically(): void
    {
        // 1. Create a user with email but no google_id
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'google_id' => null,
            'role' => 'alumni',
            'status' => 'active',
            'auth_provider' => 'email',
        ]);

        // 2. Put state in cache to bypass CSRF state validation
        Cache::put('oauth_state:login:valid_state_token', [
            'flow' => 'login',
            'user_id' => null,
            'created_at' => now()->toDateTimeString(),
        ], 600);

        // 3. Mock Socialite to return Google user data matching the email
        $googleUser = Mockery::mock('Laravel\Socialite\Two\User');
        $googleUser->shouldReceive('getId')->andReturn('google-id-123');
        $googleUser->shouldReceive('getEmail')->andReturn('user@example.com');
        $googleUser->shouldReceive('getName')->andReturn('John Doe');
        $googleUser->user = ['email_verified' => true];

        $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        // 4. Hit the login callback API endpoint
        $response = $this->getJson('/api/auth/google/login/callback?code=valid_code&state=valid_state_token');

        // 5. Assert successful login response and that the database was updated
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Login berhasil.')
            ->assertJsonStructure([
                'data' => [
                    'user',
                    'access_token',
                    'token_type',
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'google_id' => 'google-id-123',
            'auth_provider' => 'google',
        ]);

        $this->assertDatabaseHas('alumni_notifications', [
            'user_id' => $user->id,
            'type' => 'google_account_linked',
        ]);
    }

    public function test_login_callback_returns_404_if_user_does_not_exist_at_all(): void
    {
        // 1. Put state in cache
        Cache::put('oauth_state:login:valid_state_token', [
            'flow' => 'login',
            'user_id' => null,
            'created_at' => now()->toDateTimeString(),
        ], 600);

        // 2. Mock Socialite
        $googleUser = Mockery::mock('Laravel\Socialite\Two\User');
        $googleUser->shouldReceive('getId')->andReturn('google-id-999');
        $googleUser->shouldReceive('getEmail')->andReturn('nonexistent@example.com');
        $googleUser->shouldReceive('getName')->andReturn('Unknown User');
        $googleUser->user = ['email_verified' => true];

        $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        // 3. Hit the login callback
        $response = $this->getJson('/api/auth/google/login/callback?code=valid_code&state=valid_state_token');

        // 4. Assert 404 response
        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Akun Google ini belum terdaftar. Silakan registrasi terlebih dahulu.');
    }

    public function test_login_callback_returns_403_if_matching_user_is_admin(): void
    {
        // 1. Create an admin user with matching email
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'google_id' => null,
            'role' => 'admin',
            'status' => 'active',
            'auth_provider' => 'email',
        ]);

        // 2. Put state in cache
        Cache::put('oauth_state:login:valid_state_token', [
            'flow' => 'login',
            'user_id' => null,
            'created_at' => now()->toDateTimeString(),
        ], 600);

        // 3. Mock Socialite
        $googleUser = Mockery::mock('Laravel\Socialite\Two\User');
        $googleUser->shouldReceive('getId')->andReturn('google-id-888');
        $googleUser->shouldReceive('getEmail')->andReturn('admin@example.com');
        $googleUser->shouldReceive('getName')->andReturn('Admin User');
        $googleUser->user = ['email_verified' => true];

        $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        // 4. Hit the login callback
        $response = $this->getJson('/api/auth/google/login/callback?code=valid_code&state=valid_state_token');

        // 5. Assert 403 response
        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Login dengan Google hanya tersedia untuk alumni. Admin harus login dengan email dan password.');
    }
}
