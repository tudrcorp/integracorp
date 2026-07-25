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
        Schema::create('operation_inventory_product_stocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('operation_inventory_product_id');
            $table->unsignedBigInteger('operation_inventory_ubication_id');
            $table->unsignedInteger('existence')->default(0);
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('operation_inventory_product_id', 'oip_stocks_product_fk')
                ->references('id')
                ->on('operation_inventory_products')
                ->cascadeOnDelete();

            $table->foreign('operation_inventory_ubication_id', 'oip_stocks_ubication_fk')
                ->references('id')
                ->on('operation_inventory_ubications')
                ->cascadeOnDelete();

            $table->unique(
                ['operation_inventory_product_id', 'operation_inventory_ubication_id'],
                'oip_stocks_product_ubication_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operation_inventory_product_stocks');
    }
};
