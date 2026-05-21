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
        Schema::create('operation_quote_generators', function (Blueprint $table) {
            $table->id();
            $table->integer('telemedicine_patient_id')->nullable();
            $table->integer('telemedicine_case_id')->nullable();
            $table->integer('operation_coordination_service_id')->nullable();
            $table->json('items');
            $table->string('type_service');
            $table->decimal('costo_dolares', 15, 2)->default(0);
            $table->decimal('costo_bolivares', 15, 2)->default(0);
            $table->decimal('porcentaje_ganancia', 8, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->string('created_by');
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operation_quote_generators');
    }
};
