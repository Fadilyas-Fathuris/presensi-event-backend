<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Google OAuth ID - nullable because existing users don't have it
            $table->string('google_id', 255)->nullable()->unique()->after('email');
            
            // Auth provider - defaults to 'email' for existing users
            $table->enum('auth_provider', ['email', 'google'])->default('email')->after('google_id');
            
            // Add indexes for performance
            $table->index('google_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['google_id']);
            
            // Drop columns
            $table->dropColumn(['google_id', 'auth_provider']);
        });
    }
};

