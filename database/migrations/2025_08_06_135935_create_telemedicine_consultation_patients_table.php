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
            $table->string('telemedicine_case_code', 10);
            $table->integer('telemedicine_patient_id');
            $table->integer('telemedicine_doctor_id');
            $table->integer('telemedicine_priority_id')->nullable();
            $table->integer('telemedicine_service_list_id')->nullable();
            $table->string('code_reference')->nullable();
            $table->string('full_name')->nullable();
            $table->string('nro_identificacion')->nullable();
            $table->longText('reason_consultation')->nullable();
            $table->longText('actual_phatology')->nullable();
            $table->longText('background')->nullable();
            $table->longText('diagnostic_impression')->nullable();
            $table->json('labs')->nullable();
            $table->json('studies')->nullable();
            $table->json('consult_specialist')->nullable();
            $table->json('other_labs')->nullable();
            $table->json('other_studies')->nullable();
            $table->json('other_specialist')->nullable();
            $table->timestamps();
            $table->string('status', 100)->nullable();
            $table->integer('assigned_by')->nullable();
            $table->longText('cuestion_1')->nullable();
            $table->longText('cuestion_2')->nullable();
            $table->longText('cuestion_3')->nullable();
            $table->longText('cuestion_4')->nullable();
            $table->longText('cuestion_5')->nullable();
            $table->boolean('feedbackOne')->nullable();
            $table->integer('duration')->nullable();
            $table->integer('priorityMonitoring')->nullable();
            $table->longText('observations')->nullable();
            $table->decimal('pa', 8, 2)->nullable();
            $table->decimal('fc', 8, 2)->nullable();
            $table->decimal('fr', 8, 2)->nullable();
            $table->decimal('temp', 8, 2)->nullable();
            $table->decimal('saturacion', 8, 2)->nullable();
            $table->decimal('peso', 8, 2)->nullable();
            $table->decimal('estatura', 8, 2)->nullable();
            $table->decimal('imc', 8, 2)->nullable();
            $table->unsignedInteger('telemedicine_service_list_drift_id')->nullable();
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
