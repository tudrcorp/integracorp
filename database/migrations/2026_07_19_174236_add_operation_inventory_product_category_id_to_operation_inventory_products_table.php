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
        if (! Schema::hasColumn('operation_inventory_products', 'operation_inventory_product_category_id')) {
            Schema::table('operation_inventory_products', function (Blueprint $table) {
                $table->unsignedBigInteger('operation_inventory_product_category_id')
                    ->nullable()
                    ->after('id');
            });
        }

        $foreignKeys = collect(Schema::getForeignKeys('operation_inventory_products'))
            ->pluck('name')
            ->all();

        if (! in_array('oip_category_id_foreign', $foreignKeys, true)) {
            Schema::table('operation_inventory_products', function (Blueprint $table) {
                $table->foreign('operation_inventory_product_category_id', 'oip_category_id_foreign')
                    ->references('id')
                    ->on('operation_inventory_product_categories')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $foreignKeys = collect(Schema::getForeignKeys('operation_inventory_products'))
            ->pluck('name')
            ->all();

        Schema::table('operation_inventory_products', function (Blueprint $table) use ($foreignKeys) {
            if (in_array('oip_category_id_foreign', $foreignKeys, true)) {
                $table->dropForeign('oip_category_id_foreign');
            }
        });

        if (Schema::hasColumn('operation_inventory_products', 'operation_inventory_product_category_id')) {
            Schema::table('operation_inventory_products', function (Blueprint $table) {
                $table->dropColumn('operation_inventory_product_category_id');
            });
        }
    }
};
