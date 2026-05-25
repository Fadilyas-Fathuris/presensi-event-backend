<?php

use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\Admin\EventController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PresensiController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\Admin\BroadcastController;
use Illuminate\Support\Facades\Route;

// ── Auth ──────────────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me',      [AuthController::class, 'me']);
        Route::put('/profile',         [AuthController::class, 'updateProfile']);
        Route::post('/profile/avatar', [AuthController::class, 'uploadAvatar']);
    });
});

// ── Admin ─────────────────────────────────────────────────────────────────────
Route::prefix('admin')
    ->middleware(['auth:sanctum', 'is_admin'])
    ->group(function () {
        // User management
        Route::get('/users',          [AdminController::class, 'getAllUsers']);
        Route::get('/users/{id}',     [AdminController::class, 'getUser']);
        Route::post('/users',         [AdminController::class, 'createUser']);
        Route::put('/users/{id}',     [AdminController::class, 'updateUser']);
        Route::delete('/users/{id}',  [AdminController::class, 'deleteUser']);

        // Event management
        Route::get('/events',                      [EventController::class, 'index']);
        Route::get('/events/{id}',                 [EventController::class, 'show']);
        Route::post('/events',                     [EventController::class, 'store']);
        Route::put('/events/{id}',                 [EventController::class, 'update']);
        Route::delete('/events/{id}',              [EventController::class, 'destroy']);
        Route::patch('/events/{id}/toggle',        [EventController::class, 'toggle']);
        Route::get('/events/{id}/attendances',     [EventController::class, 'attendances']);
        Route::get('/events/{id}/qr-image',      [EventController::class, 'qrImage']);
        Route::get('/events/{id}/registrations', [EventController::class, 'registrations']);

        // Broadcast
        Route::post('/events/{id}/broadcast',         [BroadcastController::class, 'send']);
        Route::get('/events/{id}/broadcast/preview',  [BroadcastController::class, 'preview']);
    });

// ── Events & Registration (Alumni) ────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/events',                  [RegistrationController::class, 'index']);
    Route::get('/events/{id}',             [RegistrationController::class, 'show']);
    Route::post('/events/{id}/register',   [RegistrationController::class, 'register']);
    Route::delete('/events/{id}/register', [RegistrationController::class, 'cancel']);
});

// ── Presensi (Alumni) ─────────────────────────────────────────────────────────
Route::prefix('presensi')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::post('/scan',    [PresensiController::class, 'scan']);
        Route::get('/history',  [PresensiController::class, 'history']);
    });