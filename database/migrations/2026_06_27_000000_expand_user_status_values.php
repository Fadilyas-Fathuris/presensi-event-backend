<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('status', ['pending', 'active', 'inactive', 'rejected'])
                ->default('active')
                ->change();
        });
    }

    public function down(): void
    {
        DB::table('users')
            ->whereIn('status', ['pending', 'rejected'])
            ->update(['status' => 'inactive']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('status', ['active', 'inactive'])
                ->default('active')
                ->change();
        });
    }
};
