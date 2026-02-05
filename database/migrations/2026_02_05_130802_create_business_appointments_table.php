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
        Schema::create('business_appointments', function (Blueprint $table) {
            $table->id();
            $table->string('legal_name');
            $table->string('phone');
            $table->string('email');
            $table->integer('country_id');
            $table->integer('state_id');
            $table->integer('city_id');
            $table->string('status')->default('PENDIENTE');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_appointments');
    }
};
