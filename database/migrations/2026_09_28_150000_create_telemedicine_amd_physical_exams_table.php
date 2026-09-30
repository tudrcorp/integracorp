<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Examen físico de la atención médica domiciliaria (AMD).
 *
 * Uno por consulta AMD (índice único). Mientras la consulta no se guarda, el
 * examen queda con `telemedicine_consultation_patient_id` nulo y se vincula
 * al guardarla, igual que el informe AMD.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('telemedicine_amd_physical_exams')) {
            return;
        }

        Schema::create('telemedicine_amd_physical_exams', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('telemedicine_patient_id');
            $table->unsignedBigInteger('telemedicine_case_id');
            $table->unsignedBigInteger('telemedicine_consultation_patient_id')->nullable();
            $table->unsignedBigInteger('telemedicine_doctor_id')->nullable();

            $table->string('heart_rate', 30)->nullable();
            $table->string('respiratory_rate', 30)->nullable();
            $table->string('blood_pressure', 30)->nullable();
            $table->string('pulse', 30)->nullable();
            $table->string('oxygen_saturation', 30)->nullable();

            $table->text('skin')->nullable();
            $table->text('head')->nullable();
            $table->text('ears')->nullable();
            $table->text('nose')->nullable();
            $table->text('mouth')->nullable();
            $table->text('neck')->nullable();
            $table->text('thorax')->nullable();
            $table->text('abdomen')->nullable();
            $table->text('genitourinary')->nullable();
            $table->text('extremities')->nullable();
            $table->text('nervous_system')->nullable();
            $table->text('mental_status')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('telemedicine_patient_id', 'amd_exams_patient_fk')
                ->references('id')->on('telemedicine_patients')->cascadeOnDelete();
            $table->foreign('telemedicine_case_id', 'amd_exams_case_fk')
                ->references('id')->on('telemedicine_cases')->cascadeOnDelete();
            $table->foreign('telemedicine_consultation_patient_id', 'amd_exams_consultation_fk')
                ->references('id')->on('telemedicine_consultation_patients')->nullOnDelete();
            $table->foreign('telemedicine_doctor_id', 'amd_exams_doctor_fk')
                ->references('id')->on('telemedicine_doctors')->nullOnDelete();
            $table->foreign('created_by', 'amd_exams_created_by_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by', 'amd_exams_updated_by_fk')
                ->references('id')->on('users')->nullOnDelete();

            $table->unique('telemedicine_consultation_patient_id', 'amd_exams_consultation_unique');
            $table->index(['telemedicine_case_id', 'telemedicine_consultation_patient_id'], 'amd_exams_case_consultation_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemedicine_amd_physical_exams');
    }
};
