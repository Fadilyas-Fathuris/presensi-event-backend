<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Converts QR duration from minutes to days:
     *  - Renames column `timeout_minutes` → `duration_days`
     *  - Converts existing minute values to day values (rounded up, minimum 1)
     *  - Sets default to 1 day
     */
    public function up(): void
    {
        Schema::table('event_qr_codes', function (Blueprint $table) {
            $table->renameColumn('timeout_minutes', 'duration_days');
        });

        Schema::table('event_qr_codes', function (Blueprint $table) {
            $table->unsignedInteger('duration_days')->default(1)->change();
        });

        // Convert existing minute values to days (ceiling, min 1)
        if (DB::getDriverName() === 'sqlite') {
            DB::table('event_qr_codes')->get()->each(function ($record) {
                $days = max((int) ceil(($record->duration_days ?? 0) / 1440), 1);
                DB::table('event_qr_codes')->where('id', $record->id)->update(['duration_days' => $days]);
            });
        } else {
            DB::table('event_qr_codes')->update([
                'duration_days' => DB::raw('GREATEST(CEIL(duration_days / 1440), 1)'),
            ]);
        }

        // Auto-fill valid_from with created_at for rows that already have valid_from
        // (no change needed, valid_from is already populated)
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Convert days back to minutes
        DB::table('event_qr_codes')->update([
            'duration_days' => DB::raw('duration_days * 1440'),
        ]);

        Schema::table('event_qr_codes', function (Blueprint $table) {
            $table->unsignedInteger('duration_days')->default(60)->change();
        });

        Schema::table('event_qr_codes', function (Blueprint $table) {
            $table->renameColumn('duration_days', 'timeout_minutes');
        });
    }
};
