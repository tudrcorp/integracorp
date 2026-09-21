<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajuste global de tarifas por columna del plan.
 *
 * `plan_generator_columns.rate_adjustment_percent` guarda el porcentaje vigente
 * (positivo aumenta, negativo descuenta) y
 * `plan_generator_rate_cells.base_rate_amount` congela la tarifa original de
 * cada celda. La tarifa que ve el cliente sigue viviendo en `rate_amount`: el
 * porcentaje es dato interno y no se imprime en la cotización.
 *
 * Con la base congelada el ajuste es idempotente y reversible: pasar de -10 % a
 * -15 % da -15 % del original —no -23,5 %— y volver a 0 % restaura el monto
 * exacto. Ambas columnas son nullable: las cotizaciones que ya existen no tienen
 * ajuste y su base se congela la primera vez que se aplique uno.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('plan_generator_columns', 'rate_adjustment_percent')) {
            Schema::table('plan_generator_columns', function (Blueprint $table): void {
                $table->decimal('rate_adjustment_percent', 7, 2)->nullable()->after('header_label');
            });
        }

        if (! Schema::hasColumn('plan_generator_rate_cells', 'base_rate_amount')) {
            Schema::table('plan_generator_rate_cells', function (Blueprint $table): void {
                $table->decimal('base_rate_amount', 12, 2)->nullable()->after('rate_amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('plan_generator_columns', 'rate_adjustment_percent')) {
            Schema::table('plan_generator_columns', function (Blueprint $table): void {
                $table->dropColumn('rate_adjustment_percent');
            });
        }

        if (Schema::hasColumn('plan_generator_rate_cells', 'base_rate_amount')) {
            Schema::table('plan_generator_rate_cells', function (Blueprint $table): void {
                $table->dropColumn('base_rate_amount');
            });
        }
    }
};
