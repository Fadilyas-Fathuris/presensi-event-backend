<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'Presensi Event Alumni Pesantren API',
    version: '1.0.0',
    description: 'API documentation for Presensi Event Alumni Pesantren system.',
    contact: new OA\Contact(email: 'admin@pesantren.com')
)]
#[OA\Server(
    url: 'http://localhost:8000',
    description: 'Local Development Server'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT'
)]

// ── Reusable Schemas ──────────────────────────────────────────────────────────

#[OA\Schema(
    schema: 'User',
    type: 'object',
    properties: [
        new OA\Property(property: 'id',         type: 'integer', example: 1),
        new OA\Property(property: 'name',        type: 'string',  example: 'Ahmad Fauzi'),
        new OA\Property(property: 'gender',      type: 'string',  example: 'L'),
        new OA\Property(property: 'status',      type: 'string',  example: 'alumni'),
        new OA\Property(property: 'email',       type: 'string',  example: 'ahmad@example.com'),
        new OA\Property(property: 'phone',       type: 'string',  example: '081234567890'),
        new OA\Property(property: 'angkatan',    type: 'string',  example: '2015'),
        new OA\Property(property: 'role',        type: 'string',  example: 'alumni'),
        new OA\Property(property: 'avatar_url',  type: 'string',  nullable: true, example: '/storage/avatars/avatar123.jpg'),
        new OA\Property(property: 'created_at',  type: 'string',  format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'ErrorResponse',
    type: 'object',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string',  example: 'Error message here'),
    ]
)]
#[OA\Schema(
    schema: 'ValidationError',
    type: 'object',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string',  example: 'Validation failed'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            example: ['email' => ['The email field is required.']]
        ),
    ]
)]
#[OA\Schema(
    schema: 'Event',
    type: 'object',
    properties: [
        new OA\Property(property: 'id',             type: 'integer', example: 1),
        new OA\Property(property: 'category_id',    type: 'integer', example: 1),
        new OA\Property(property: 'created_by',     type: 'integer', example: 1),
        new OA\Property(property: 'event_title',    type: 'string',  example: 'Reuni Akbar 2025'),
        new OA\Property(property: 'description',    type: 'string',  example: 'Reuni alumni angkatan 2010-2015'),
        new OA\Property(property: 'location',       type: 'string',  example: 'Aula Pesantren'),
        new OA\Property(property: 'event_date',     type: 'string',  format: 'date',     example: '2025-12-01'),
        new OA\Property(property: 'start_time',     type: 'string',  format: 'time',     example: '08:00'),
        new OA\Property(property: 'end_time',       type: 'string',  format: 'time',     example: '17:00'),
        new OA\Property(property: 'qr_token',       type: 'string',  example: '550e8400-e29b-41d4-a716'),
        new OA\Property(property: 'qr_code_image',  type: 'string',  example: 'qrcodes/550e8400.png'),
        new OA\Property(property: 'qr_code_url',    type: 'string',  example: 'http://localhost:8000/storage/qrcodes/550e8400.svg'),
        new OA\Property(property: 'poster_image',   type: 'string',  nullable: true, example: 'event-posters/poster123.jpg'),
        new OA\Property(property: 'poster_url',     type: 'string',  nullable: true, example: 'http://localhost:8000/storage/event-posters/poster123.jpg'),
        new OA\Property(property: 'status_event',   type: 'string',  example: 'active'),
        new OA\Property(property: 'quota',          type: 'integer', nullable: true, example: 100),
        new OA\Property(property: 'created_at',     type: 'string',  format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'Presensi',
    type: 'object',
    properties: [
        new OA\Property(property: 'id',          type: 'integer', example: 1),
        new OA\Property(property: 'event_id',    type: 'integer', example: 1),
        new OA\Property(property: 'user_id',     type: 'integer', example: 2),
        new OA\Property(property: 'scanned_at',  type: 'string',  format: 'date-time'),
        new OA\Property(property: 'event',       ref: '#/components/schemas/Event'),
        new OA\Property(property: 'user',        ref: '#/components/schemas/User'),
    ]
)]
#[OA\Schema(
    schema: 'EventRegistration',
    type: 'object',
    properties: [
        new OA\Property(property: 'id',            type: 'integer', example: 1),
        new OA\Property(property: 'event_id',      type: 'integer', example: 1),
        new OA\Property(property: 'user_id',       type: 'integer', example: 2),
        new OA\Property(property: 'status',        type: 'string',  example: 'registered'),
        new OA\Property(property: 'registered_at', type: 'string',  format: 'date-time'),
        new OA\Property(property: 'event',         ref: '#/components/schemas/Event'),
        new OA\Property(property: 'user',          ref: '#/components/schemas/User'),
    ]
)]
class SwaggerController extends Controller
{
    // intentionally empty — only holds global OpenAPI definitions
}
