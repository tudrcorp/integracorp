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
        Schema::create('telemedicine_patients', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->integer('plan_id')->nullable();
            $table->integer('afilliation_id')->nullable();
            $table->integer('afilliation_corporate_id')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('nro_identificacion');
            $table->string('date_birth');
            $table->string('sex');
            $table->string('phone');
            $table->string('email')->unique();
            $table->string('address');
            $table->string('city_id');
            $table->string('country_id');
            $table->string('region_id');
            $table->string('state_id');
            $table->string('phone_contact')->nullable();
            $table->string('email_contact')->nullable();
            $table->string('type_affiliation')->nullable();
            $table->string('date_affiliation')->nullable();
            $table->string('status_affiliation')->nullable();
            $table->string('observations');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telemedicine_patients');
    }
};