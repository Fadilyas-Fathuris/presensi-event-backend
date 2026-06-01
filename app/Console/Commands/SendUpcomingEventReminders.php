<?php

namespace App\Console\Commands;

use App\Models\AlumniNotification;
use App\Models\Event;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendUpcomingEventReminders extends Command
{
    protected $signature = 'notifications:upcoming-events';

    protected $description = 'Send reminder notifications for events starting within approximately 1 hour';

    public function handle(): int
    {
        $now = Carbon::now();
        $today = $now->toDateString();

        // Find active events happening today whose start_time is 50-70 minutes from now
        $from = $now->copy()->addMinutes(50)->format('H:i:s');
        $to   = $now->copy()->addMinutes(70)->format('H:i:s');

        $events = Event::where('status_event', 'active')
            ->whereDate('event_date', $today)
            ->whereTime('start_time', '>=', $from)
            ->whereTime('start_time', '<=', $to)
            ->with('category')
            ->get();

        if ($events->isEmpty()) {
            $this->info('No upcoming events found in the next ~1 hour window.');
            return self::SUCCESS;
        }

        $alumniUserIds = User::where('role', 'alumni')->pluck('id');

        if ($alumniUserIds->isEmpty()) {
            $this->info('No alumni users found.');
            return self::SUCCESS;
        }

        $sentCount = 0;

        foreach ($events as $event) {
            // Check if we already sent event_starting_soon for this event
            $alreadySent = AlumniNotification::where('type', 'event_starting_soon')
                ->whereJsonContains('data->event_id', $event->id)
                ->exists();

            if ($alreadySent) {
                $this->info("Reminder already sent for event: {$event->event_title} (ID: {$event->id}). Skipping.");
                continue;
            }

            $category = $event->category;

            $notifications = $alumniUserIds->map(fn($userId) => [
                'user_id'    => $userId,
                'title'      => 'Event Segera Dimulai: ' . $event->event_title,
                'body'       => 'Event "' . $event->event_title . '" akan dimulai dalam kurang dari 1 jam di ' . $event->location,
                'type'       => 'event_starting_soon',
                'priority'   => 'high',
                'data'       => json_encode([
                    'event_id'      => $event->id,
                    'event_title'   => $event->event_title,
                    'location'      => $event->location,
                    'starts_at'     => $event->event_date->toDateString(),
                    'start_time'    => $event->start_time,
                    'end_time'      => $event->end_time,
                    'category'      => $category?->id,
                    'category_name' => $category?->category_name,
                ]),
                'is_read'    => false,
                'created_at' => now(),
                'updated_at' => now(),
            ])->toArray();

            AlumniNotification::insert($notifications);
            $sentCount += count($notifications);

            $this->info("Sent {$alumniUserIds->count()} reminders for event: {$event->event_title} (starts at {$event->start_time})");
        }

        $this->info("Done. Total notifications sent: {$sentCount}");

        return self::SUCCESS;
    }
}
