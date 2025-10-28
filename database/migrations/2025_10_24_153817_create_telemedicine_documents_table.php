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
        Schema::create('telemedicine_documents', function (Blueprint $table) {
            $table->id();
            $table->integer('telemedicine_case_id');
            $table->integer('telemedicine_case_code');
            $table->integer('telemedicine_consultation_id');
            $table->integer('telemedicine_patient_id');
            $table->string('name');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telemedicine_documents');
    }
};