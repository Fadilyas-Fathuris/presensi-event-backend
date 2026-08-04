<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Send reminder notifications for events starting within ~1 hour
Schedule::command('notifications:upcoming-events')->everyTenMinutes();

// Purge expired Sanctum tokens every hour (auto-logout for closed browsers)
Schedule::command('sanctum:purge-expired')->hourly();

