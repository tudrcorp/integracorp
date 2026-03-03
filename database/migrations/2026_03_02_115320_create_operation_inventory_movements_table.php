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
        Schema::create('operation_inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->integer('operation_inventory_id');
            $table->integer('telemedicine_patient_id');
            $table->integer('telemedicine_case_id');
            $table->integer('telemedicine_consultation_id');
            $table->integer('telemedicine_doctor_id');
            $table->integer('business_unit_id');
            $table->integer('business_line_id');
            $table->integer('quantity');
            $table->string('unit');
            $table->string('type');
            $table->string('created_by');
            $table->string('status')->default('DESPACHADO');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operation_inventory_movements');
    }
};
