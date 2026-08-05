<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('operation_medical_appointments')) {
            return;
        }

        Schema::create('operation_medical_appointments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('operation_service_order_id')->unique();
            $table->unsignedBigInteger('telemedicine_patient_id')->nullable();
            $table->unsignedBigInteger('telemedicine_case_id')->nullable();
            $table->unsignedBigInteger('operation_coordination_service_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('supplier_external')->nullable();
            $table->string('supplier_notify_email')->nullable();
            $table->string('supplier_notify_phone')->nullable();
            $table->timestamp('appointment_at');
            $table->string('status')->default('SCHEDULED');
            $table->timestamp('previous_appointment_at')->nullable();
            $table->text('last_change_reason')->nullable();
            $table->timestamp('last_changed_at')->nullable();
            $table->string('last_changed_by')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('operation_service_order_id', 'oma_service_order_fk')
                ->references('id')
                ->on('operation_service_orders')
                ->cascadeOnDelete();
            $table->foreign('telemedicine_patient_id', 'oma_patient_fk')
                ->references('id')
                ->on('telemedicine_patients')
                ->nullOnDelete();
            $table->foreign('telemedicine_case_id', 'oma_case_fk')
                ->references('id')
                ->on('telemedicine_cases')
                ->nullOnDelete();
            $table->foreign('operation_coordination_service_id', 'oma_coordination_fk')
                ->references('id')
                ->on('operation_coordination_services')
                ->nullOnDelete();
            $table->foreign('supplier_id', 'oma_supplier_fk')
                ->references('id')
                ->on('suppliers')
                ->nullOnDelete();

            $table->index('appointment_at', 'oma_appointment_at_idx');
            $table->index('status', 'oma_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_medical_appointments');
    }
};
