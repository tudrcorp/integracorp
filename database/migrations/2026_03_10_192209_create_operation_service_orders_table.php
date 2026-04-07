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
        Schema::create('operation_service_orders', function (Blueprint $table) {
            $table->id();
            $table->integer('operation_coordination_service_id');
            $table->integer('supplier_id')->nullable();
            $table->integer('telemedicine_priority_id')->nullable();
            $table->string('order_number');
            $table->string('supplier_external')->nullable();
            $table->string('description');
            $table->string('service_type')->nullable();
            $table->string('service_item')->nullable();
            $table->string('currency')->nullable();
            $table->decimal('tasa_bcv', 10, 2)->nullable();
            $table->decimal('total_amount_usd', 10, 2)->nullable();
            $table->decimal('total_amount_ves', 10, 2)->nullable();
            $table->string('payment_method')->nullable();
            $table->string('status')->nullable();
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
        Schema::dropIfExists('operation_service_orders');
    }
};
