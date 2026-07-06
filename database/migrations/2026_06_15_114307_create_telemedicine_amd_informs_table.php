<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telemedicine_amd_informs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('telemedicine_patient_id');
            $table->unsignedBigInteger('telemedicine_case_id');
            $table->unsignedBigInteger('telemedicine_consultation_patient_id');
            $table->unsignedBigInteger('telemedicine_doctor_id');
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->text('reason_consultation')->nullable();
            $table->text('actual_phatology')->nullable();
            $table->text('background')->nullable();
            $table->text('diagnostic_impression')->nullable();
            $table->string('pa')->nullable();
            $table->string('fc')->nullable();
            $table->string('fr')->nullable();
            $table->string('temp')->nullable();
            $table->string('saturacion')->nullable();
            $table->decimal('peso', 8, 2)->nullable();
            $table->decimal('estatura', 8, 2)->nullable();
            $table->decimal('imc', 8, 2)->nullable();
            $table->string('pdf_document_name')->nullable();
            $table->string('pdf_file_path')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('telemedicine_patient_id', 'amd_informs_patient_fk')
                ->references('id')->on('telemedicine_patients')->cascadeOnDelete();
            $table->foreign('telemedicine_case_id', 'amd_informs_case_fk')
                ->references('id')->on('telemedicine_cases')->cascadeOnDelete();
            $table->foreign('telemedicine_consultation_patient_id', 'amd_informs_consultation_fk')
                ->references('id')->on('telemedicine_consultation_patients')->cascadeOnDelete();
            $table->foreign('telemedicine_doctor_id', 'amd_informs_doctor_fk')
                ->references('id')->on('telemedicine_doctors')->cascadeOnDelete();
            $table->foreign('supplier_id', 'amd_informs_supplier_fk')
                ->references('id')->on('suppliers')->nullOnDelete();
            $table->foreign('created_by', 'amd_informs_created_by_fk')
                ->references('id')->on('users')->nullOnDelete();

            $table->index(['telemedicine_case_id', 'telemedicine_consultation_patient_id'], 'amd_informs_case_consultation_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemedicine_amd_informs');
    }
};
