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
        Schema::create('telemedicine_medical_reports', function (Blueprint $table) {
            $table->id();
            $table->integer('telemedicine_patient_id')->nullable();
            $table->integer('telemedicine_case_id')->nullable();
            $table->integer('telemedicine_doctor_id')->nullable();
            $table->integer('telemedicine_consultation_patient_id')->nullable();
            $table->integer('operation_coordination_service_id')->nullable();

            // Informacion de paciente
            $table->decimal('pa', 10, 2)->nullable()->comment('Presión arterial');
            $table->decimal('fc', 10, 2)->nullable()->comment('Frecuencia cardiaca');
            $table->decimal('fr', 10, 2)->nullable()->comment('Frecuencia respiratoria');
            $table->decimal('temp', 10, 2)->nullable()->comment('Temperatura');
            $table->decimal('saturacion', 10, 2)->nullable()->comment('Saturación de oxígeno');
            $table->decimal('peso', 10, 2)->nullable()->comment('Peso');
            $table->decimal('estatura', 10, 2)->nullable()->comment('Estatura');
            $table->decimal('imc', 10, 2)->nullable()->comment('Índice de masa corporal');

            // Informacion de la consulta

            $table->longText('reason_consultation')->nullable()->comment('Motivo de la consulta');
            $table->longText('actual_phatology')->nullable()->comment('Enfermedad actual');
            $table->longText('background')->nullable()->comment('Antecedentes');
            $table->longText('diagnostic_impression')->nullable()->comment('Impresión diagnóstica');
            $table->longText('observations')->nullable()->comment('Observaciones');

            $table->string('type_service')->nullable()->comment('Tipo de servicio');
            $table->string('priority_service')->nullable()->comment('Prioridad de la consulta');

            $table->integer('created_by')->nullable()->comment('Creado por');
            $table->integer('updated_by')->nullable()->comment('Actualizado por');
            $table->string('status')->nullable()->comment('Estado');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telemedicine_medical_reports');
    }
};
