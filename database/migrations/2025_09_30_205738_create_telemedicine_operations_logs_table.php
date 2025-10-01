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
        Schema::create('telemedicine_operations_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('telemedicine_patient_id');
            $table->integer('telemedicine_case_id');
            $table->integer('telemedicine_consultation_patient_id');
            $table->string('code_reference');
            $table->string('operation');
            $table->string('description');
            $table->string('status');
            $table->string('observations');
            $table->string('responsable');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telemedicine_operations_logs');
    }
};