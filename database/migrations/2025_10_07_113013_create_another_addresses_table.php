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
        Schema::create('another_addresses', function (Blueprint $table) {
            $table->id();
            $table->integer('telemedicine_patient_id');
            $table->longText('address');
            $table->integer('city_id');
            $table->integer('country_id');
            $table->integer('state_id');
            $table->string('phone_1');
            $table->string('phone_2');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('another_addresses');
    }
};