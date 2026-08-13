<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_domiciles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('province_code', 20)->nullable();
            $table->string('province_name')->nullable();
            $table->string('city_code', 20)->nullable();
            $table->string('city_name')->nullable();
            $table->string('district_code', 20)->nullable();
            $table->string('district_name')->nullable();
            $table->string('village_code', 20)->nullable();
            $table->string('village_name')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->text('address')->nullable();
            $table->timestamps();

            $table->index('province_code');
            $table->index('city_code');
            $table->index('district_code');
            $table->index('village_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_domiciles');
    }
};
