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
        Schema::table('operation_inventories', function (Blueprint $table) {
            if (! Schema::hasColumn('operation_inventories', 'operation_inventory_product_id')) {
                $table->unsignedBigInteger('operation_inventory_product_id')->nullable()->after('id');
            }

            if (! Schema::hasColumn('operation_inventories', 'operation_inventory_ubication_id')) {
                $table->unsignedBigInteger('operation_inventory_ubication_id')->nullable()->after('operation_inventory_product_id');
            }
        });

        $inventoryForeignKeys = collect(Schema::getForeignKeys('operation_inventories'))->pluck('name')->all();

        Schema::table('operation_inventories', function (Blueprint $table) use ($inventoryForeignKeys) {
            if (! in_array('oi_product_fk', $inventoryForeignKeys, true)) {
                $table->foreign('operation_inventory_product_id', 'oi_product_fk')
                    ->references('id')
                    ->on('operation_inventory_products')
                    ->nullOnDelete();
            }

            if (! in_array('oi_ubication_fk', $inventoryForeignKeys, true)) {
                $table->foreign('operation_inventory_ubication_id', 'oi_ubication_fk')
                    ->references('id')
                    ->on('operation_inventory_ubications')
                    ->nullOnDelete();
            }
        });

        $inventoryIndexes = collect(Schema::getIndexes('operation_inventories'))->pluck('name')->all();

        if (! in_array('oi_product_ubication_unique', $inventoryIndexes, true)) {
            Schema::table('operation_inventories', function (Blueprint $table) {
                $table->unique(
                    ['operation_inventory_product_id', 'operation_inventory_ubication_id'],
                    'oi_product_ubication_unique'
                );
            });
        }

        Schema::table('operation_inventory_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('operation_inventory_entries', 'operation_inventory_product_id')) {
                $table->unsignedBigInteger('operation_inventory_product_id')->nullable()->after('operation_inventory_id');
            }

            if (! Schema::hasColumn('operation_inventory_entries', 'operation_inventory_ubication_id')) {
                $table->unsignedBigInteger('operation_inventory_ubication_id')->nullable()->after('operation_inventory_product_id');
            }
        });

        $entryForeignKeys = collect(Schema::getForeignKeys('operation_inventory_entries'))->pluck('name')->all();

        Schema::table('operation_inventory_entries', function (Blueprint $table) use ($entryForeignKeys) {
            if (! in_array('oie_product_fk', $entryForeignKeys, true)) {
                $table->foreign('operation_inventory_product_id', 'oie_product_fk')
                    ->references('id')
                    ->on('operation_inventory_products')
                    ->nullOnDelete();
            }

            if (! in_array('oie_ubication_fk', $entryForeignKeys, true)) {
                $table->foreign('operation_inventory_ubication_id', 'oie_ubication_fk')
                    ->references('id')
                    ->on('operation_inventory_ubications')
                    ->nullOnDelete();
            }
        });

        Schema::table('operation_inventory_outflows', function (Blueprint $table) {
            if (! Schema::hasColumn('operation_inventory_outflows', 'operation_inventory_product_id')) {
                $table->unsignedBigInteger('operation_inventory_product_id')->nullable()->after('operation_inventory_id');
            }

            if (! Schema::hasColumn('operation_inventory_outflows', 'operation_inventory_ubication_id')) {
                $table->unsignedBigInteger('operation_inventory_ubication_id')->nullable()->after('operation_inventory_product_id');
            }
        });

        $outflowForeignKeys = collect(Schema::getForeignKeys('operation_inventory_outflows'))->pluck('name')->all();

        Schema::table('operation_inventory_outflows', function (Blueprint $table) use ($outflowForeignKeys) {
            if (! in_array('oio_product_fk', $outflowForeignKeys, true)) {
                $table->foreign('operation_inventory_product_id', 'oio_product_fk')
                    ->references('id')
                    ->on('operation_inventory_products')
                    ->nullOnDelete();
            }

            if (! in_array('oio_ubication_fk', $outflowForeignKeys, true)) {
                $table->foreign('operation_inventory_ubication_id', 'oio_ubication_fk')
                    ->references('id')
                    ->on('operation_inventory_ubications')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operation_inventory_outflows', function (Blueprint $table) {
            $foreignKeys = collect(Schema::getForeignKeys('operation_inventory_outflows'))->pluck('name')->all();

            if (in_array('oio_product_fk', $foreignKeys, true)) {
                $table->dropForeign('oio_product_fk');
            }

            if (in_array('oio_ubication_fk', $foreignKeys, true)) {
                $table->dropForeign('oio_ubication_fk');
            }

            if (Schema::hasColumn('operation_inventory_outflows', 'operation_inventory_product_id')) {
                $table->dropColumn('operation_inventory_product_id');
            }

            if (Schema::hasColumn('operation_inventory_outflows', 'operation_inventory_ubication_id')) {
                $table->dropColumn('operation_inventory_ubication_id');
            }
        });

        Schema::table('operation_inventory_entries', function (Blueprint $table) {
            $foreignKeys = collect(Schema::getForeignKeys('operation_inventory_entries'))->pluck('name')->all();

            if (in_array('oie_product_fk', $foreignKeys, true)) {
                $table->dropForeign('oie_product_fk');
            }

            if (in_array('oie_ubication_fk', $foreignKeys, true)) {
                $table->dropForeign('oie_ubication_fk');
            }

            if (Schema::hasColumn('operation_inventory_entries', 'operation_inventory_product_id')) {
                $table->dropColumn('operation_inventory_product_id');
            }

            if (Schema::hasColumn('operation_inventory_entries', 'operation_inventory_ubication_id')) {
                $table->dropColumn('operation_inventory_ubication_id');
            }
        });

        Schema::table('operation_inventories', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('operation_inventories'))->pluck('name')->all();
            $foreignKeys = collect(Schema::getForeignKeys('operation_inventories'))->pluck('name')->all();

            if (in_array('oi_product_ubication_unique', $indexes, true)) {
                $table->dropUnique('oi_product_ubication_unique');
            }

            if (in_array('oi_product_fk', $foreignKeys, true)) {
                $table->dropForeign('oi_product_fk');
            }

            if (in_array('oi_ubication_fk', $foreignKeys, true)) {
                $table->dropForeign('oi_ubication_fk');
            }

            if (Schema::hasColumn('operation_inventories', 'operation_inventory_product_id')) {
                $table->dropColumn('operation_inventory_product_id');
            }

            if (Schema::hasColumn('operation_inventories', 'operation_inventory_ubication_id')) {
                $table->dropColumn('operation_inventory_ubication_id');
            }
        });
    }
};
