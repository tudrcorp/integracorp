<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fallas de medicamentos: medicamentos recetados que no están en el inventario Diagnomóvil
 * (o lo que hay no corresponde, o no alcanza) y que la empresa debe adquirir.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('operation_medication_failures')) {
            return;
        }

        Schema::create('operation_medication_failures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('telemedicine_patient_medication_id')->nullable()->index('omf_medication_idx');
            $table->foreignId('operation_coordination_service_id')->nullable()->index('omf_coordination_idx');
            $table->foreignId('telemedicine_patient_id')->nullable()->index('omf_patient_idx');
            $table->foreignId('telemedicine_case_id')->nullable()->index('omf_case_idx');
            $table->string('medicine_name');
            $table->text('indications')->nullable();
            $table->string('patient_name')->nullable();
            $table->string('case_code', 50)->nullable();
            $table->string('reason', 30);
            $table->text('notes')->nullable();
            $table->string('status', 30)->index('omf_status_idx');
            $table->foreignId('reported_by_user_id')->nullable();
            $table->string('reported_by_name')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_by_name')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('operation_inventory_product_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_medication_failures');
    }
};
