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
        Schema::create('telemedicine_consultation_patients', function (Blueprint $table) {
            $table->id();
            $table->integer('telemedicine_case_id');
            $table->integer('telemedicine_case_code');
            $table->integer('telemedicine_patient_id');
            $table->integer('telemedicine_doctor_id');
            $table->string('code_reference');
            $table->string('full_name');
            $table->string('nro_identificacion');
            $table->string('type_service');
            $table->string('reason_consultation');
            $table->string('actual_phatology');
            $table->string('vs_pa');
            $table->string('vs_fc');
            $table->string('vs_fr');
            $table->string('vs_temp');
            $table->string('vs_sat');
            $table->string('vs_weight');
            $table->string('background');
            $table->string('diagnostic_impression');
            $table->string('medicines');
            $table->string('indications');
            $table->json('labs');
            $table->json('studies');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telemedicine_consultation_patients');
    }
};