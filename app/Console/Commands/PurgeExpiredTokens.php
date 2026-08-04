<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;

class PurgeExpiredTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sanctum:purge-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete expired Sanctum tokens and tokens inactive for too long';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $expirationMinutes = config('sanctum.expiration');

        if (! $expirationMinutes) {
            $this->warn('No Sanctum token expiration configured. Skipping purge.');
            return self::SUCCESS;
        }

        $expiredBefore = now()->subMinutes($expirationMinutes);

        // Delete tokens that were last used before the expiration threshold
        $deletedUsed = PersonalAccessToken::where('last_used_at', '<', $expiredBefore)->delete();

        // Also delete tokens that were NEVER used and created before the threshold
        // (e.g. tokens from a login where the user immediately closed the browser)
        $deletedUnused = PersonalAccessToken::whereNull('last_used_at')
            ->where('created_at', '<', $expiredBefore)
            ->delete();

        $total = $deletedUsed + $deletedUnused;

        $this->info("Purged {$total} expired token(s) ({$deletedUsed} stale, {$deletedUnused} never-used).");

        return self::SUCCESS;
    }
}
