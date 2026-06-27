<?php

use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\EventCategoryController;
use App\Http\Controllers\Api\Admin\EventController;
use App\Http\Controllers\Api\Admin\EventQrCodeController;
use App\Http\Controllers\Api\Admin\BroadcastController;
use App\Http\Controllers\Api\Admin\AdminProfileController;
use App\Http\Controllers\Api\AlumniNotificationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PresensiController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\UserManagementController;
use App\Http\Controllers\Api\WhatsappSettingsController;
use App\Http\Controllers\Api\EventRecommendationController;
use Illuminate\Support\Facades\Route;


// ── Auth
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:6,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me',      [AuthController::class, 'me']);
        Route::put('/profile',         [AuthController::class, 'updateProfile']);
        Route::put('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/profile/avatar', [AuthController::class, 'uploadAvatar']);
        Route::delete('/profile/avatar', [AuthController::class, 'deleteAvatar']);
    });
});

// ── User Management (Frontend compatibility)
Route::middleware(['auth:sanctum', 'is_admin'])->group(function () {
    Route::get('/users',         [UserManagementController::class, 'index']);
    Route::put('/users/{id}',    [UserManagementController::class, 'update']);
    Route::patch('/users/{id}',  [UserManagementController::class, 'update']);
    Route::delete('/users/{id}', [UserManagementController::class, 'destroy']);
});

// ── Admin
Route::prefix('admin')
    ->middleware(['auth:sanctum', 'is_admin'])
    ->group(function () {
        Route::put('/change-password', [AdminProfileController::class, 'changePassword']);

        // Dashboard
        Route::get('/dashboard/attendance-chart', [DashboardController::class, 'attendanceChart']);

        // User management
        Route::get('/users',          [AdminController::class, 'getAllUsers']);
        Route::get('/users/{id}',     [AdminController::class, 'getUser']);
        Route::post('/users',         [AdminController::class, 'createUser']);
        Route::put('/users/{id}',     [AdminController::class, 'updateUser']);
        Route::patch('/users/{id}/status', [AdminController::class, 'updateUserStatus']);
        Route::delete('/users/{id}',  [AdminController::class, 'deleteUser']);

        // Event management
        Route::get('/event-categories',            [EventCategoryController::class, 'index']);
        Route::post('/event-categories',           [EventCategoryController::class, 'store']);
        Route::get('/event-categories/{id}',       [EventCategoryController::class, 'show']);
        Route::put('/event-categories/{id}',       [EventCategoryController::class, 'update']);
        Route::delete('/event-categories/{id}',    [EventCategoryController::class, 'destroy']);
        Route::get('/events',                      [EventController::class, 'index']);
        Route::get('/events/{id}',                 [EventController::class, 'show']);
        Route::post('/events',                     [EventController::class, 'store']);
        Route::put('/events/{id}',                 [EventController::class, 'update']);
        Route::post('/events/{id}',                [EventController::class, 'update']);
        Route::delete('/events/{id}',              [EventController::class, 'destroy']);
        Route::patch('/events/{id}/toggle',        [EventController::class, 'toggle']);
        Route::get('/events/{id}/attendances',     [EventController::class, 'attendances']);
        Route::get('/events/{id}/qr',              [EventQrCodeController::class, 'show']);
        Route::post('/events/{id}/qr/generate',    [EventQrCodeController::class, 'generate']);
        Route::get('/events/{id}/qr-image',        [EventQrCodeController::class, 'image']);
        Route::get('/events/{id}/registrations',   [EventController::class, 'registrations']);

        // Broadcast
        Route::post('/events/{id}/broadcast',         [BroadcastController::class, 'send']);
        Route::get('/events/{id}/broadcast/preview',  [BroadcastController::class, 'preview']);

        // Activity logs
        Route::get('/activity-logs', [AdminController::class, 'getActivityLogs']);
    });

// ── Settings
Route::prefix('settings')
    ->middleware(['auth:sanctum', 'is_admin'])
    ->group(function () {
        Route::get('/whatsapp', [WhatsappSettingsController::class, 'show']);
        Route::put('/whatsapp', [WhatsappSettingsController::class, 'update']);
        Route::post('/whatsapp/test', [WhatsappSettingsController::class, 'test'])
            ->middleware('throttle:6,1');
    });

// ── Alumni Notifications & Recommendations 
Route::middleware(['auth:sanctum', 'is_alumni'])->group(function () {
    Route::get('/alumni/notifications', [AlumniNotificationController::class, 'index']);
    Route::get('/alumni/notifications/unread-count', [AlumniNotificationController::class, 'unreadCount']);
    Route::put('/alumni/notifications/read-all', [AlumniNotificationController::class, 'markAllAsRead']);
    Route::put('/alumni/notifications/{id}/read', [AlumniNotificationController::class, 'markAsRead']);

    Route::get('/alumni/recommendations', [EventRecommendationController::class, 'index']);
});

// ── Events & Registration (Alumni) 
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/events',                  [RegistrationController::class, 'index']);
    Route::get('/events/{id}',             [RegistrationController::class, 'show']);
    Route::post('/events/{id}/register',   [RegistrationController::class, 'register']);
    Route::delete('/events/{id}/register', [RegistrationController::class, 'cancel']);
});

// ── Presensi (Alumni)
Route::prefix('presensi')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::post('/scan',    [PresensiController::class, 'scan']);
        Route::get('/history',  [PresensiController::class, 'history']);
    });
