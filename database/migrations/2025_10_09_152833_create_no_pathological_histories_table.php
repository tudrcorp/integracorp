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
        Schema::create('no_pathological_histories', function (Blueprint $table) {
            $table->id();
            $table->integer('telemedicine_patient_id');
            $table->integer('telemedicine_history_patient_id');
            $table->string('observations');
            $table->string('created_by');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('no_pathological_histories');
    }
};