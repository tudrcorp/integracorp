<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quién aceptó el pedido en Farmadoc y el detalle por medicamento (aceptado o rechazado,
 * con el motivo), para el aviso a los analistas. Nunca montos. Índice en `accepted_at`
 * para buscar las aceptaciones recientes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('farmadoc_orders')) {
            return;
        }

        Schema::table('farmadoc_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('farmadoc_orders', 'accepted_by')) {
                $table->string('accepted_by')->nullable()->after('accepted_at');
            }

            if (! Schema::hasColumn('farmadoc_orders', 'acceptance_items')) {
                $table->json('acceptance_items')->nullable()->after('accepted_by');
            }

            if (Schema::hasColumn('farmadoc_orders', 'accepted_at') && ! Schema::hasIndex('farmadoc_orders', 'fdo_accepted_at_idx')) {
                $table->index('accepted_at', 'fdo_accepted_at_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmadoc_orders')) {
            return;
        }

        Schema::table('farmadoc_orders', function (Blueprint $table): void {
            if (Schema::hasIndex('farmadoc_orders', 'fdo_accepted_at_idx')) {
                $table->dropIndex('fdo_accepted_at_idx');
            }

            if (Schema::hasColumn('farmadoc_orders', 'acceptance_items')) {
                $table->dropColumn('acceptance_items');
            }

            if (Schema::hasColumn('farmadoc_orders', 'accepted_by')) {
                $table->dropColumn('accepted_by');
            }
        });
    }
};
