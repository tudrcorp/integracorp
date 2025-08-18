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
        Schema::create('telemedicine_laboratories', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->integer('telemedicine_patient_id');
            $table->integer('telemedicine_doctor_id');
            $table->integer('telemedicine_consultation_id');
            $table->string('code_reference');
            $table->string('laboratory');
            $table->string('observations');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telemedicine_laboratories');
    }
};