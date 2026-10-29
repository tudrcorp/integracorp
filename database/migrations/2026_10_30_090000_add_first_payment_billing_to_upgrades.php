<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Upgrades que se cobran completos en el primer pago del año (Negocios, 29/10/2026).
 *
 * - `billing_mode` en cada asignación: PRIMER_PAGO para las nuevas; NULL son las de antes,
 *   repartidas en las cuotas, que se dejan como están.
 * - `charged_collection_id`: aviso por pagar al que se sumó el upgrade cuando se agregó a una
 *   afiliación ya en curso. Al quitarlo, se descuenta de ese aviso si sigue por pagar.
 * - `upgrades_amount` en los comprobantes: parte del primer pago que corresponde a upgrades,
 *   para que al aprobarlo las cuotas siguientes no la repitan.
 *
 * Idempotente: se aplica con --path y puede correrse dos veces sin daño.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['affiliate_upgrades', 'affiliate_corporate_upgrades'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (! Schema::hasColumn($table, 'billing_mode')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->string('billing_mode', 20)->nullable()->after('amount');
                });
            }

            if (! Schema::hasColumn($table, 'charged_collection_id')) {
                Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                    $blueprint->unsignedBigInteger('charged_collection_id')->nullable()->after('billing_mode');
                    $blueprint->index('charged_collection_id', $table === 'affiliate_upgrades' ? 'au_charged_collection_idx' : 'acu_charged_collection_idx');
                });
            }
        }

        foreach (['paid_memberships', 'paid_membership_corporates'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'upgrades_amount')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->decimal('upgrades_amount', 10, 2)->nullable()->after('total_amount');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['affiliate_upgrades', 'affiliate_corporate_upgrades'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'charged_collection_id')) {
                Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                    $blueprint->dropIndex($table === 'affiliate_upgrades' ? 'au_charged_collection_idx' : 'acu_charged_collection_idx');
                    $blueprint->dropColumn('charged_collection_id');
                });
            }

            if (Schema::hasTable($table) && Schema::hasColumn($table, 'billing_mode')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->dropColumn('billing_mode');
                });
            }
        }

        foreach (['paid_memberships', 'paid_membership_corporates'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'upgrades_amount')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->dropColumn('upgrades_amount');
                });
            }
        }
    }
};
