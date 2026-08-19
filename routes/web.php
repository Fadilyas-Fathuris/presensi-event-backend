<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Temporary debug route
Route::get('/debug-users', function () {
    $users = \App\Models\User::whereNotNull('google_id')
        ->orWhere('auth_provider', 'google')
        ->orderBy('created_at', 'desc')
        ->get(['id', 'first_name', 'last_name', 'email', 'google_id', 'auth_provider', 'status']);
    
    if ($users->isEmpty()) {
        $recent = \App\Models\User::orderBy('created_at', 'desc')
            ->take(3)
            ->get(['id', 'first_name', 'email', 'google_id', 'status']);
        return ['google_users' => [], 'recent_users' => $recent];
    }
    
    return ['google_users' => $users];
});
