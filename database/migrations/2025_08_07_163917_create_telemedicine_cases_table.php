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
        Schema::create('telemedicine_cases', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->integer('telemedicine_patient_id');
            $table->integer('telemedicine_doctor_id');
            $table->string('patient_name');
            $table->string('patient_age');
            $table->string('patient_sex');
            $table->string('patient_phone');
            $table->string('patient_address');
            $table->string('patient_country');
            $table->string('patient_state');
            $table->string('patient_city');
            $table->string('assigned_by');
            $table->string('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telemedicine_cases');
    }
};