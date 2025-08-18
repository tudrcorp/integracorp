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
        Schema::create('telemedicine_representatives', function (Blueprint $table) {
            $table->id();
            $table->integer('full_name');
            $table->string('telemedicine_patient_id')->nullable();
            $table->string('email')->unique()->nullable();
            $table->string('nro_identificacion');
            $table->string('phone')->nullable();
            $table->string('relationship');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telemedicine_representatives');
    }
};