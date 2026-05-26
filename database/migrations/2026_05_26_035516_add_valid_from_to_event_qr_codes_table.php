<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('event_qr_codes', function (Blueprint $table) {
            $table->dateTime('valid_from')->nullable()->after('qr_code_url');
        });

        DB::table('event_qr_codes')
            ->whereNull('valid_from')
            ->update([
                'valid_from' => DB::raw('created_at'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_qr_codes', function (Blueprint $table) {
            $table->dropColumn('valid_from');
        });
    }
};