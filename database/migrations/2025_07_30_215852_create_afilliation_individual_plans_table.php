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
        Schema::create('afilliation_individual_plans', function (Blueprint $table) {
            $table->id();
            $table->integer('afilliation_id');
            $table->string('code_affiliation');
            $table->integer('plan_id');
            $table->integer('coverage_id');
            $table->integer('age_range_id');
            $table->integer('fee');
            $table->decimal('subtotal_anual');
            $table->decimal('subtotal_quarterly');
            $table->decimal('subtotal_biannual');
            $table->decimal('subtotal_monthly');
            $table->string('status');
            $table->string('created_by');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('afilliation_individual_plans');
    }
};