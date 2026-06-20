<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_reset_link_for_registered_email(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'alumni@example.com',
        ]);

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'alumni@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Jika email terdaftar, link reset password akan dikirim.');

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            fn (ResetPasswordNotification $notification) => str_starts_with(
                $notification->toMail($user)->actionUrl,
                config('app.frontend_url') . '/reset-password?'
            )
        );
    }

    public function test_forgot_password_does_not_reveal_unknown_email(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'unknown@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Jika email terdaftar, link reset password akan dikirim.');

        Notification::assertNothingSent();
    }

    public function test_reset_password_updates_password_and_revokes_existing_tokens(): void
    {
        $user = User::factory()->create([
            'email' => 'alumni@example.com',
            'password' => Hash::make('passwordlama'),
        ]);

        $token = Password::broker()->createToken($user);
        $user->createToken('auth_token');

        $this->postJson('/api/auth/reset-password', [
            'email' => 'alumni@example.com',
            'token' => $token,
            'password' => 'passwordbaru123',
            'password_confirmation' => 'passwordbaru123',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Password berhasil direset. Silakan login dengan password baru.');

        $user->refresh();

        $this->assertTrue(Hash::check('passwordbaru123', $user->password));
        $this->assertNotNull($user->password_changed_at);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseHas('alumni_notifications', [
            'user_id' => $user->id,
            'type' => 'password_reset',
            'priority' => 'high',
        ]);
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        User::factory()->create([
            'email' => 'alumni@example.com',
        ]);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'alumni@example.com',
            'token' => 'token-salah',
            'password' => 'passwordbaru123',
            'password_confirmation' => 'passwordbaru123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }
}
