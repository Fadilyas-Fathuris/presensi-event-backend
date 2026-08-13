<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->enum('type', ['province', 'city', 'district', 'village']);
            $table->string('parent_code', 20)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->timestamps();

            $table->index(['type', 'parent_code']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};
