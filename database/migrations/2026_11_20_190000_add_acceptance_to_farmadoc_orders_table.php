<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nota de entrega y fecha de aceptación que informa Farmadoc al aceptar un pedido.
 * Nunca montos. Quién aceptó y el detalle por medicamento llegan en
 * 2026_11_20_210000_add_acceptance_detail_to_farmadoc_orders_table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('farmadoc_orders')) {
            return;
        }

        Schema::table('farmadoc_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('farmadoc_orders', 'delivery_note_number')) {
                $table->string('delivery_note_number', 100)->nullable()->after('farmadoc_updated_at');
            }

            if (! Schema::hasColumn('farmadoc_orders', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable()->after('delivery_note_number');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmadoc_orders')) {
            return;
        }

        Schema::table('farmadoc_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('farmadoc_orders', 'accepted_at')) {
                $table->dropColumn('accepted_at');
            }

            if (Schema::hasColumn('farmadoc_orders', 'delivery_note_number')) {
                $table->dropColumn('delivery_note_number');
            }
        });
    }
};
