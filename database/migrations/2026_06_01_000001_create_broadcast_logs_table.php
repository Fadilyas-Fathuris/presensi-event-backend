<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('target')->nullable();
            $table->unsignedInteger('total_targets')->default(0);
            $table->string('status');
            $table->string('sender_status')->nullable();
            $table->text('message')->nullable();
            $table->text('blocked_reason')->nullable();
            $table->json('fonnte')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_logs');
    }
};
