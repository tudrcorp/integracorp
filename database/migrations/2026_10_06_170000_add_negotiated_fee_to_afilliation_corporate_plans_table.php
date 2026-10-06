<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tarifa negociada en las filas de plan de una afiliación corporativa.
 *
 * - `fee` pasa de entero a decimal(8,2), el mismo tipo que los subtotales y que
 *   `affiliate_corporates.fee`: un entero truncaba las tarifas con céntimos.
 *   Ampliar no pierde datos; se repiten sus atributos (NOT NULL, sin default).
 * - `fee_source`: ESTANDAR (precio de la tabla `fees`) o NEGOCIADA (monto que
 *   escribió el analista). Nulo = filas anteriores a este cambio.
 * - Motivo, autor y fecha dejan la traza de la negociación en la propia fila.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('afilliation_corporate_plans', function (Blueprint $table): void {
            $table->decimal('fee', 8, 2)->nullable(false)->change();
        });

        Schema::table('afilliation_corporate_plans', function (Blueprint $table): void {
            if (! Schema::hasColumn('afilliation_corporate_plans', 'fee_source')) {
                $table->string('fee_source', 20)->nullable()->after('fee');
            }

            if (! Schema::hasColumn('afilliation_corporate_plans', 'fee_negotiation_reason')) {
                $table->text('fee_negotiation_reason')->nullable()->after('fee_source');
            }

            if (! Schema::hasColumn('afilliation_corporate_plans', 'fee_negotiated_by')) {
                $table->unsignedBigInteger('fee_negotiated_by')->nullable()->after('fee_negotiation_reason');
            }

            if (! Schema::hasColumn('afilliation_corporate_plans', 'fee_negotiated_at')) {
                $table->timestamp('fee_negotiated_at')->nullable()->after('fee_negotiated_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('afilliation_corporate_plans', function (Blueprint $table): void {
            foreach (['fee_negotiated_at', 'fee_negotiated_by', 'fee_negotiation_reason', 'fee_source'] as $column) {
                if (Schema::hasColumn('afilliation_corporate_plans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('afilliation_corporate_plans', function (Blueprint $table): void {
            $table->integer('fee')->nullable(false)->change();
        });
    }
};
