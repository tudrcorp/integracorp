<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Porcentaje de utilidad de la categoría, aplicado sobre el costo del producto.
     * NULL = sin definir (el producto no muestra precio final).
     */
    public function up(): void
    {
        if (! Schema::hasTable('operation_inventory_product_categories')
            || Schema::hasColumn('operation_inventory_product_categories', 'profit_percentage')) {
            return;
        }

        Schema::table('operation_inventory_product_categories', function (Blueprint $table): void {
            $table->decimal('profit_percentage', 8, 2)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('operation_inventory_product_categories', 'profit_percentage')) {
            return;
        }

        Schema::table('operation_inventory_product_categories', function (Blueprint $table): void {
            $table->dropColumn('profit_percentage');
        });
    }
};
