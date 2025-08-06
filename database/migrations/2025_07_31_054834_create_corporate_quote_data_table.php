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
        Schema::create('corporate_quote_data', function (Blueprint $table) {
            $table->id();
            $table->integer('corporate_quote_id');
            $table->string('last_name');
            $table->string('first_name');
            $table->string('nro_identificacion');
            $table->string('birth_date')->nullable();
            $table->string('age')->nullable();
            $table->string('sex')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('condition_medical')->nullable();
            $table->string('initial_date')->nullable();
            $table->string('position_company')->nullable();
            $table->string('address')->nullable();
            $table->string('full_name_emergency')->nullable();
            $table->string('phone_emergency')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('corporate_quote_data');
    }
};