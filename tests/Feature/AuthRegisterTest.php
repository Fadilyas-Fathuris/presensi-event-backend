<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_validation_fails_when_all_fields_are_empty(): void
    {
        $this->postJson('/api/auth/register', [])
            ->assertUnprocessable()
            ->assertJsonMissingPath('success')
            ->assertJsonPath('message', 'Ada isian yang belum sesuai.')
            ->assertJsonValidationErrors([
                'first_name',
                'last_name',
                'gender',
                'email',
                'phone',
                'graduation_year',
                'birth_date',
                'password',
                'password_confirmation',
            ])
            ->assertJsonPath('errors.first_name.0', 'Nama depan wajib diisi.')
            ->assertJsonPath('errors.email.0', 'Email wajib diisi.');
    }

    public function test_register_validation_fails_when_email_is_invalid(): void
    {
        $this->postJson('/api/auth/register', $this->validPayload([
            'email' => 'email-tidak-valid',
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Ada isian yang belum sesuai.')
            ->assertJsonPath('errors.email.0', 'Email tidak valid.');
    }

    public function test_register_validation_fails_when_email_already_exists(): void
    {
        User::factory()->create([
            'email' => 'alumni@example.com',
            'phone' => '081111111111',
        ]);

        $this->postJson('/api/auth/register', $this->validPayload([
            'email' => 'alumni@example.com',
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Ada isian yang belum sesuai.')
            ->assertJsonPath('errors.email.0', 'Email ini sudah terdaftar.');
    }

    public function test_register_validation_fails_when_phone_already_exists(): void
    {
        User::factory()->create([
            'email' => 'existing@example.com',
            'phone' => '081234567890',
        ]);

        $this->postJson('/api/auth/register', $this->validPayload([
            'phone' => '081234567890',
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Ada isian yang belum sesuai.')
            ->assertJsonPath('errors.phone.0', 'Nomor telepon ini sudah terdaftar.');
    }

    public function test_register_validation_fails_when_password_is_less_than_eight_characters(): void
    {
        $this->postJson('/api/auth/register', $this->validPayload([
            'password' => 'rahasia',
            'password_confirmation' => 'rahasia',
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Ada isian yang belum sesuai.')
            ->assertJsonPath('errors.password.0', 'Kata sandi minimal 8 karakter.');
    }

    public function test_register_validation_fails_when_password_confirmation_does_not_match(): void
    {
        $this->postJson('/api/auth/register', $this->validPayload([
            'password_confirmation' => 'beda-password',
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Ada isian yang belum sesuai.')
            ->assertJsonPath('errors.password_confirmation.0', 'Konfirmasi kata sandi tidak sama.');
    }

    public function test_register_validation_fails_when_birth_date_is_in_the_future(): void
    {
        $this->postJson('/api/auth/register', $this->validPayload([
            'birth_date' => now()->addDay()->toDateString(),
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Ada isian yang belum sesuai.')
            ->assertJsonPath('errors.birth_date.0', 'Tanggal lahir tidak boleh melebihi hari ini.');
    }

    public function test_register_succeeds_with_valid_payload(): void
    {
        $this->postJson('/api/auth/register', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Registration successful')
            ->assertJsonPath('data.user.email', 'alumni@example.com')
            ->assertJsonPath('data.user.role', 'alumni')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure([
                'data' => [
                    'user',
                    'access_token',
                    'token_type',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'first_name' => 'Ahmad',
            'last_name' => 'Fauzi',
            'gender' => 'Laki-laki',
            'email' => 'alumni@example.com',
            'phone' => '081234567890',
            'graduation_year' => '2015',
            'birth_date' => '2000-01-01',
            'role' => 'alumni',
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Ahmad',
            'last_name' => 'Fauzi',
            'gender' => 'Laki-laki',
            'email' => 'alumni@example.com',
            'phone' => '081234567890',
            'graduation_year' => 2015,
            'birth_date' => '2000-01-01',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }
}
