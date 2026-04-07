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
        Schema::create('operation_service_order_items', function (Blueprint $table) {
            $table->id();
            $table->integer('operation_service_order_id');
            $table->string('item_name');
            $table->string('category');
            $table->longText('dosage_instruction')->nullable();
            $table->string('item_unit')->nullable();
            $table->integer('quantity')->default(0);
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('currency')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operation_service_order_items');
    }
};
