<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * `benefit_coverages` es la matriz (plan, beneficio, cobertura) -> costo límite.
 *
 * La migración original (2025_10_16_001014) solo creaba benefit_id, coverage_id
 * y las columnas denormalizadas. `plan_id` y `limit` se agregaron después por
 * SQL directo contra la base, sin migración, así que existen en desarrollo pero
 * no necesariamente en el resto de los entornos. Esta migración las normaliza y
 * es idempotente: si ya están, solo ajusta lo que haga falta.
 *
 * Dos cambios de forma sobre `limit`:
 *
 *   - Pasa a NULL-able. NULL significa "este beneficio no tiene límite en esta
 *     cobertura", que es distinto de un límite de 0,00.
 *   - Pasa de decimal(8,2) a decimal(12,2), porque el tope anterior era
 *     999.999,99 y hay coberturas negociadas por encima de eso.
 *
 * El único `(plan_id, benefit_id, coverage_id)` es lo que permite que reeditar
 * un plan use updateOrCreate sin duplicar filas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('benefit_coverages', function (Blueprint $table): void {
            if (! Schema::hasColumn('benefit_coverages', 'plan_id')) {
                $table->unsignedBigInteger('plan_id')->nullable()->after('id');
            }

            if (! Schema::hasColumn('benefit_coverages', 'limit')) {
                $table->decimal('limit', 12, 2)->nullable()->after('coverage_id');
            }
        });

        // Se declara aparte del bloque anterior para que la columna exista
        // cuando `change()` la redefine en una base que venía sin ella.
        Schema::table('benefit_coverages', function (Blueprint $table): void {
            $table->decimal('limit', 12, 2)->nullable()->change();
        });

        $this->dropStaleDuplicates();

        if (! $this->hasIndex('benefit_coverages_plan_benefit_coverage_unique')) {
            Schema::table('benefit_coverages', function (Blueprint $table): void {
                $table->unique(
                    ['plan_id', 'benefit_id', 'coverage_id'],
                    'benefit_coverages_plan_benefit_coverage_unique',
                );
            });
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('benefit_coverages_plan_benefit_coverage_unique')) {
            Schema::table('benefit_coverages', function (Blueprint $table): void {
                $table->dropUnique('benefit_coverages_plan_benefit_coverage_unique');
            });
        }

        // `limit` vuelve a NOT NULL con el default original. Las filas en NULL
        // pasarían a 0.00, que es justamente la ambigüedad que esta migración
        // vino a resolver, así que se avisa antes de revertir.
        DB::table('benefit_coverages')->whereNull('limit')->update(['limit' => 0]);

        Schema::table('benefit_coverages', function (Blueprint $table): void {
            $table->decimal('limit', 8, 2)->default(0)->nullable(false)->change();
        });
    }

    /**
     * El único no se puede crear si ya hay combinaciones repetidas. Se conserva
     * la fila más reciente de cada grupo, que es la que refleja la última
     * edición del plan.
     */
    private function dropStaleDuplicates(): void
    {
        $duplicates = DB::table('benefit_coverages')
            ->select('plan_id', 'benefit_id', 'coverage_id', DB::raw('MAX(id) as keep_id'), DB::raw('COUNT(*) as total'))
            ->groupBy('plan_id', 'benefit_id', 'coverage_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isEmpty()) {
            return;
        }

        $removed = 0;

        foreach ($duplicates as $duplicate) {
            $removed += DB::table('benefit_coverages')
                ->where('benefit_id', $duplicate->benefit_id)
                ->where('coverage_id', $duplicate->coverage_id)
                ->when(
                    $duplicate->plan_id === null,
                    static fn ($query) => $query->whereNull('plan_id'),
                    static fn ($query) => $query->where('plan_id', $duplicate->plan_id),
                )
                ->where('id', '<', $duplicate->keep_id)
                ->delete();
        }

        $message = "benefit_coverages: {$removed} fila(s) duplicadas eliminadas antes de crear el único.";

        Log::info($message);

        if (app()->runningInConsole()) {
            fwrite(STDOUT, $message.PHP_EOL);
        }
    }

    /**
     * Se consulta con la introspección de Laravel y no contra
     * information_schema, para que la migración también corra sobre la sqlite
     * en memoria que usan los tests Feature.
     */
    private function hasIndex(string $name): bool
    {
        foreach (Schema::getIndexes('benefit_coverages') as $index) {
            if (($index['name'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }
};
