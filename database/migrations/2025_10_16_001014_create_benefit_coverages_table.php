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
        Schema::create('benefit_coverages', function (Blueprint $table) {
            $table->id();
            $table->integer('benefit_id');
            $table->integer('coverage_id');
            $table->string('benefit_description');
            $table->string('coverage_price');
            $table->string('price');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('benefit_coverages');
    }
};
