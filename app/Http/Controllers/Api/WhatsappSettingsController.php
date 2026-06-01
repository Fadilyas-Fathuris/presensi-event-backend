<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsappSetting;
use App\Services\WhatsappService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class WhatsappSettingsController extends Controller
{
    public function __construct(private WhatsappService $whatsapp) {}

    public function show(): JsonResponse
    {
        $setting = WhatsappSetting::current();
        $config = $this->whatsapp->configuration();
        $hasToken = filled($config['api_token'] ?? null);

        return response()->json([
            'provider' => $config['provider'] ?? WhatsappService::DEFAULT_PROVIDER,
            'api_url' => $config['api_url'] ?? WhatsappService::DEFAULT_URL,
            'api_token' => $hasToken ? WhatsappService::maskToken($config['api_token']) : null,
            'sender_number' => $config['sender_number'],
            'sender_status' => $config['sender_status'] ?? 'unknown',
            'blocked_reason' => $config['blocked_reason'] ?? null,
            'is_configured' => $hasToken && filled($config['sender_number']),
            'connected' => ($config['sender_status'] ?? null) === 'active',
            'can_edit' => true,
            'last_tested_at' => $setting?->last_tested_at?->toISOString(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $setting = WhatsappSetting::current();

        $validator = Validator::make($request->all(), [
            'provider' => ['required', Rule::in([WhatsappService::DEFAULT_PROVIDER])],
            'api_url' => ['nullable', 'url'],
            'api_token' => ['nullable', 'string'],
            'sender_number' => ['required', 'regex:/^62[0-9]{8,13}$/'],
        ], [
            'provider.in' => 'Provider WhatsApp hanya boleh fonnte.',
            'sender_number.regex' => 'sender_number harus angka format Indonesia dan diawali 62.',
        ]);

        $validator->after(function ($validator) use ($request, $setting) {
            $incomingToken = $request->input('api_token');
            $keepsExistingMaskedToken = is_string($incomingToken) && str_contains($incomingToken, '*') && filled($setting?->api_token);
            $incomingTokenIsUsable = filled($incomingToken) && ! str_contains((string) $incomingToken, '*');

            if (blank($setting?->api_token) && ! $incomingTokenIsUsable) {
                $validator->errors()->add('api_token', 'api_token wajib saat pertama kali konfigurasi.');
            }

            if ($keepsExistingMaskedToken) {
                return;
            }
        });

        $validated = $validator->validate();
        $incomingToken = $validated['api_token'] ?? null;
        $tokenShouldBeUpdated = filled($incomingToken) && ! str_contains((string) $incomingToken, '*');

        $payload = [
            'provider' => WhatsappService::DEFAULT_PROVIDER,
            'api_url' => $validated['api_url'] ?? WhatsappService::DEFAULT_URL,
            'sender_number' => $validated['sender_number'],
            'sender_status' => $setting?->sender_status ?? 'unknown',
        ];

        if ($tokenShouldBeUpdated) {
            $payload['api_token'] = $incomingToken;
            $payload['sender_status'] = 'unknown';
            $payload['blocked_reason'] = null;
        }

        if ($setting) {
            $setting->update($payload);
            $setting->refresh();
        } else {
            $setting = WhatsappSetting::query()->create($payload);
        }

        return response()->json([
            'success' => true,
            'message' => 'Konfigurasi WhatsApp berhasil disimpan',
            'data' => [
                'provider' => $setting->provider,
                'api_url' => $setting->api_url,
                'api_token' => WhatsappService::maskToken($setting->api_token),
                'sender_number' => $setting->sender_number,
                'sender_status' => $setting->sender_status,
                'blocked_reason' => $setting->blocked_reason,
                'is_configured' => filled($setting->api_token) && filled($setting->sender_number),
                'connected' => $setting->sender_status === 'active',
                'can_edit' => true,
            ],
        ]);
    }

    public function test(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => ['sometimes', Rule::in([WhatsappService::DEFAULT_PROVIDER])],
            'api_url' => ['sometimes', 'url'],
            'api_token' => ['sometimes', 'nullable', 'string'],
            'sender_number' => ['sometimes', 'regex:/^62[0-9]{8,13}$/'],
        ], [
            'provider.in' => 'Provider WhatsApp hanya boleh fonnte.',
            'sender_number.regex' => 'sender_number harus angka format Indonesia dan diawali 62.',
        ]);

        $validated = $validator->validate();
        $setting = WhatsappSetting::current();

        if (isset($validated['api_token']) && is_string($validated['api_token']) && str_contains($validated['api_token'], '*')) {
            unset($validated['api_token']);
        }

        $result = $this->whatsapp->testConnection($validated);

        if ($setting && ! array_key_exists('api_token', $validated)) {
            $setting->refresh();
        }

        $httpStatus = $result['http_status'] ?? ($result['success'] ? 200 : 400);
        unset($result['http_status']);

        return response()->json($result, $httpStatus);
    }
}
