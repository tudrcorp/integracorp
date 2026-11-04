<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Forma de cobro de los upgrades, elegida por afiliación (y sugerida desde el generador):
 * - `ANUAL`: completos en el primer pago del año (lo de siempre);
 * - la misma frecuencia de la afiliación: repartidos en cada cuota.
 *
 * NULL en afiliaciones existentes = ANUAL (comportamiento previo); no se reescribe nada.
 *
 * `upgrade_installment_charges` guarda el reparto de un upgrade agregado a mitad de año entre
 * los avisos pendientes, para poder descontarlo exacto si se quita y para que los procesos que
 * anulan y regeneran avisos lo arrastren.
 *
 * Aditiva e idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['affiliations', 'affiliation_corporates', 'plan_generators'] as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'upgrade_billing_frequency')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->string('upgrade_billing_frequency', 20)->nullable();
                });
            }
        }

        if (! Schema::hasTable('upgrade_installment_charges')) {
            Schema::create('upgrade_installment_charges', function (Blueprint $table): void {
                $table->id();
                $table->string('upgrade_table', 40);
                $table->unsignedBigInteger('upgrade_id');
                $table->unsignedBigInteger('collection_id');
                $table->decimal('amount', 12, 2);
                $table->string('status', 20)->default('ACTIVO');
                $table->timestamps();

                $table->index(['upgrade_table', 'upgrade_id'], 'upg_inst_charge_upgrade_idx');
                $table->index(['collection_id', 'status'], 'upg_inst_charge_collection_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('upgrade_installment_charges');

        foreach (['affiliations', 'affiliation_corporates', 'plan_generators'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'upgrade_billing_frequency')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->dropColumn('upgrade_billing_frequency');
                });
            }
        }
    }
};
