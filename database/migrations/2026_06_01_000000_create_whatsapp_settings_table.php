<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('fonnte');
            $table->string('api_url')->default('https://api.fonnte.com/send');
            $table->text('api_token')->nullable();
            $table->string('sender_number')->nullable();
            $table->string('sender_status')->default('unknown');
            $table->text('blocked_reason')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_settings');
    }
};
