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
        Schema::create('telemedicine_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->integer('telemedicine_patient_id');
            $table->integer('telemedicine_case_id');
            $table->integer('telemedicine_doctor_id');
            $table->integer('telemedicine_consultation_patient_id');
            $table->longText('cuestion_1');
            $table->longText('cuestion_2');
            $table->longText('cuestion_3');
            $table->longText('cuestion_4');
            $table->longText('cuestion_5');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telemedicine_follow_ups');
    }
};