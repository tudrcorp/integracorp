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
        Schema::create('telemedicine_doctors', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('nro_identificacion')->unique();
            $table->string('email')->unique();
            $table->string('code_cm')->unique()->nullable();
            $table->string('code_mpps')->unique()->nullable();
            $table->string('phone')->nullable();
            $table->string('specialty')->default('MÉDICO GENERAL');
            $table->string('address')->nullable();
            $table->string('image');
            $table->string('signature');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telemedicine_doctors');
    }
};