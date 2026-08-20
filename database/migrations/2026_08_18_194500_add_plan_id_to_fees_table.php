<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * `fees` pasa a ser la tabla canónica del catálogo de tarifas: plan + cobertura
 * + rango de edad + precio en una sola fila. Hasta ahora el plan se deducía
 * indirectamente por `fees.age_range_id -> age_ranges.plan_id`, o por la pivote
 * `fee_plans`, que está desalineada (10 tarifas sin fila, una con dos planes).
 *
 * `age_ranges.plan_id` queda congelado: se conserva en la tabla —es la fuente
 * del backfill y no se toca nada que hoy dependa de él— pero el código nuevo
 * lee `fees.plan_id`.
 *
 * El backfill es conservador a propósito: solo asigna plan cuando la cadena
 * fee -> age_range -> plan resuelve completa contra `plans`. Las tarifas cuyo
 * plan no se puede determinar sin adivinar quedan en NULL y se listan al final
 * para revisarlas a mano. Al 2026-08-18 resuelven 57 de 60 y quedan tres:
 *
 *   fee 44 (TDEC-FA-00043) -> age_range_id 9, que no existe
 *   fee 63 (TDEC-FA-00063) -> plan 24, que no existe en `plans`
 *   fee 66 (TDEC-FA-00066) -> plan 25, que no existe en `plans`
 *
 * Caso aparte, que sí queda resuelto: la fee 1 (TDEC-TA-0001) toma plan 1
 * (Inicial) desde `age_ranges`, aunque `fee_plans` la asocie a los planes 1 y 7.
 * Se deja el 1, que es el que el código ya trata como caso especial
 * (AffiliationAffiliateFeeCalculator::INITIAL_PLAN_ID).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('fees', 'plan_id')) {
            Schema::table('fees', function (Blueprint $table) {
                $table->unsignedBigInteger('plan_id')->nullable()->after('code');
                $table->index('plan_id');
            });
        }

        // Solo rellena lo que esté vacío, para poder re-ejecutar la migración
        // sin pisar correcciones manuales hechas después del primer pase.
        $updated = DB::table('fees')
            ->join('age_ranges', 'age_ranges.id', '=', 'fees.age_range_id')
            ->join('plans', 'plans.id', '=', 'age_ranges.plan_id')
            ->whereNull('fees.plan_id')
            ->update(['fees.plan_id' => DB::raw('age_ranges.plan_id')]);

        $this->reportPending($updated);
    }

    public function down(): void
    {
        if (Schema::hasColumn('fees', 'plan_id')) {
            Schema::table('fees', function (Blueprint $table) {
                $table->dropIndex(['plan_id']);
                $table->dropColumn('plan_id');
            });
        }
    }

    /**
     * Deja constancia de las tarifas que quedaron sin plan, con el motivo, para
     * que se corrijan a mano. No falla la migración: son datos preexistentes.
     */
    private function reportPending(int $updated): void
    {
        $pending = DB::table('fees')
            ->leftJoin('age_ranges', 'age_ranges.id', '=', 'fees.age_range_id')
            ->leftJoin('plans', 'plans.id', '=', 'age_ranges.plan_id')
            ->whereNull('fees.plan_id')
            ->orderBy('fees.id')
            ->get([
                'fees.id',
                'fees.code',
                'fees.age_range_id',
                'fees.coverage_id',
                'fees.price',
                'age_ranges.id as age_range_exists',
                'age_ranges.plan_id as age_range_plan_id',
                'plans.id as plan_exists',
            ]);

        $total = DB::table('fees')->count();

        $this->line("fees.plan_id: {$updated} tarifas actualizadas de {$total}.");

        if ($pending->isEmpty()) {
            $this->line('fees.plan_id: todas las tarifas resolvieron plan.');

            return;
        }

        $this->line("fees.plan_id: {$pending->count()} tarifas quedaron en NULL y necesitan revisión manual:");

        foreach ($pending as $fee) {
            $motivo = match (true) {
                $fee->age_range_exists === null => "age_range_id {$fee->age_range_id} no existe",
                $fee->age_range_plan_id === null => 'el rango de edad no tiene plan_id',
                $fee->plan_exists === null => "el plan {$fee->age_range_plan_id} no existe en plans",
                default => 'motivo desconocido',
            };

            $this->line("  fee {$fee->id} ({$fee->code}) cobertura={$fee->coverage_id} precio={$fee->price} -> {$motivo}");
        }
    }

    private function line(string $message): void
    {
        Log::info($message);

        if (app()->runningInConsole()) {
            fwrite(STDOUT, $message.PHP_EOL);
        }
    }
};
