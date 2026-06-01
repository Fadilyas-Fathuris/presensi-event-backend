<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsappSetting;
use App\Services\WhatsappService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class WhatsappSettingsController extends Controller
{
    public function __construct(private WhatsappService $whatsapp) {}

    #[OA\Get(
        path: '/api/whatsapp-settings',
        operationId: 'getWhatsappSettings',
        summary: 'Get WhatsApp configuration',
        description: 'Returns current WhatsApp API configuration including provider, API URL, sender number, and connection status. Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['WhatsApp Settings'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'WhatsApp configuration',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'provider', type: 'string', example: 'fonnte'),
                        new OA\Property(property: 'api_url', type: 'string', example: 'https://api.fonnte.com/send'),
                        new OA\Property(property: 'api_token', type: 'string', nullable: true, example: 'abc***xyz'),
                        new OA\Property(property: 'sender_number', type: 'string', nullable: true, example: '6281234567890'),
                        new OA\Property(property: 'sender_status', type: 'string', example: 'active'),
                        new OA\Property(property: 'blocked_reason', type: 'string', nullable: true, example: null),
                        new OA\Property(property: 'is_configured', type: 'boolean', example: true),
                        new OA\Property(property: 'connected', type: 'boolean', example: true),
                        new OA\Property(property: 'can_edit', type: 'boolean', example: true),
                        new OA\Property(property: 'last_tested_at', type: 'string', nullable: true, example: '2026-06-01T10:00:00+00:00'),
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

    #[OA\Put(
        path: '/api/whatsapp-settings',
        operationId: 'updateWhatsappSettings',
        summary: 'Update WhatsApp configuration',
        description: 'Updates WhatsApp API configuration. If api_token contains masked value (***), existing token is kept. Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['WhatsApp Settings'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['provider', 'sender_number'],
                properties: [
                    new OA\Property(property: 'provider', type: 'string', enum: ['fonnte'], example: 'fonnte'),
                    new OA\Property(property: 'api_url', type: 'string', format: 'url', nullable: true, example: 'https://api.fonnte.com/send'),
                    new OA\Property(property: 'api_token', type: 'string', nullable: true, example: 'your-api-token-here'),
                    new OA\Property(property: 'sender_number', type: 'string', example: '6281234567890'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Configuration updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Konfigurasi WhatsApp berhasil disimpan'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'provider', type: 'string', example: 'fonnte'),
                                new OA\Property(property: 'api_url', type: 'string', example: 'https://api.fonnte.com/send'),
                                new OA\Property(property: 'api_token', type: 'string', example: 'abc***xyz'),
                                new OA\Property(property: 'sender_number', type: 'string', example: '6281234567890'),
                                new OA\Property(property: 'sender_status', type: 'string', example: 'unknown'),
                                new OA\Property(property: 'blocked_reason', type: 'string', nullable: true, example: null),
                                new OA\Property(property: 'is_configured', type: 'boolean', example: true),
                                new OA\Property(property: 'connected', type: 'boolean', example: false),
                                new OA\Property(property: 'can_edit', type: 'boolean', example: true),
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

    #[OA\Post(
        path: '/api/whatsapp-settings/test',
        operationId: 'testWhatsappConnection',
        summary: 'Test WhatsApp connection',
        description: 'Tests WhatsApp API connection with provided or saved configuration. Can test with temporary config without saving. Admin only.',
        security: [['bearerAuth' => []]],
        tags: ['WhatsApp Settings'],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'provider', type: 'string', enum: ['fonnte'], example: 'fonnte'),
                    new OA\Property(property: 'api_url', type: 'string', format: 'url', example: 'https://api.fonnte.com/send'),
                    new OA\Property(property: 'api_token', type: 'string', nullable: true, example: 'your-api-token-here'),
                    new OA\Property(property: 'sender_number', type: 'string', example: '6281234567890'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Connection test successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Koneksi WhatsApp berhasil'),
                        new OA\Property(property: 'sender_status', type: 'string', example: 'active'),
                        new OA\Property(property: 'blocked_reason', type: 'string', nullable: true, example: null),
                        new OA\Property(property: 'detail', type: 'object', nullable: true),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Connection test failed',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Koneksi WhatsApp gagal'),
                        new OA\Property(property: 'sender_status', type: 'string', example: 'blocked'),
                        new OA\Property(property: 'blocked_reason', type: 'string', nullable: true, example: 'Invalid API token'),
                        new OA\Property(property: 'detail', type: 'object', nullable: true),
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
