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
            $table->enum('admin_level', ['super_admin', 'admin'])
                ->nullable()
                ->after('role');

            $table->index(['role', 'admin_level', 'status']);
        });

        DB::table('users')
            ->where('role', 'admin')
            ->whereNull('admin_level')
            ->update(['admin_level' => 'super_admin']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'admin_level', 'status']);
            $table->dropColumn('admin_level');
        });
    }
};
