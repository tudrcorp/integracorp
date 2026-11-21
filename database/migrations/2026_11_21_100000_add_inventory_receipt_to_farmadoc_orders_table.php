<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ubicación de destino del pedido (DIAGNO-MOVIL o DIAGNO-CENTER) y la entrada a inventario:
 * quién y cuándo la registró, en qué ubicación, el número de la nota de recibido y el
 * detalle por medicamento (qué producto se actualizó o creó y los valores anteriores).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('farmadoc_orders')) {
            return;
        }

        Schema::table('farmadoc_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('farmadoc_orders', 'operation_inventory_ubication_id')) {
                $table->unsignedBigInteger('operation_inventory_ubication_id')->nullable()->after('operation_coordination_service_id')
                    ->index('fdo_ubication_idx');
            }

            if (! Schema::hasColumn('farmadoc_orders', 'receipt_number')) {
                $table->string('receipt_number', 30)->nullable()->after('acceptance_items')->unique('fdo_receipt_number_unique');
            }

            if (! Schema::hasColumn('farmadoc_orders', 'received_at')) {
                $table->timestamp('received_at')->nullable()->after('receipt_number');
            }

            if (! Schema::hasColumn('farmadoc_orders', 'received_by_user_id')) {
                $table->unsignedBigInteger('received_by_user_id')->nullable()->after('received_at');
            }

            if (! Schema::hasColumn('farmadoc_orders', 'received_by_name')) {
                $table->string('received_by_name')->nullable()->after('received_by_user_id');
            }

            if (! Schema::hasColumn('farmadoc_orders', 'receipt_ubication_id')) {
                $table->unsignedBigInteger('receipt_ubication_id')->nullable()->after('received_by_name');
            }

            if (! Schema::hasColumn('farmadoc_orders', 'receipt_items')) {
                $table->json('receipt_items')->nullable()->after('receipt_ubication_id');
            }

            if (! Schema::hasColumn('farmadoc_orders', 'receipt_notes')) {
                $table->text('receipt_notes')->nullable()->after('receipt_items');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmadoc_orders')) {
            return;
        }

        Schema::table('farmadoc_orders', function (Blueprint $table): void {
            foreach (['receipt_notes', 'receipt_items', 'receipt_ubication_id', 'received_by_name', 'received_by_user_id', 'received_at'] as $column) {
                if (Schema::hasColumn('farmadoc_orders', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('farmadoc_orders', 'receipt_number')) {
                $table->dropUnique('fdo_receipt_number_unique');
                $table->dropColumn('receipt_number');
            }

            if (Schema::hasColumn('farmadoc_orders', 'operation_inventory_ubication_id')) {
                $table->dropIndex('fdo_ubication_idx');
                $table->dropColumn('operation_inventory_ubication_id');
            }
        });
    }
};
