<?php

declare(strict_types=1);

use App\Enums\PlanPricingMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * `plans.pricing_mode` convierte en propiedad del plan algo que hasta ahora era
 * un número mágico: "plan sin coberturas" estaba clavado al plan 1
 * (`AffiliationAffiliateFeeCalculator::INITIAL_PLAN_ID`), de modo que cualquier
 * otro plan armado como paquete de beneficios no resolvía tarifa nunca.
 *
 * Dos modos:
 *
 *   COBERTURAS — el plan tiene coberturas propias y la tarifa se resuelve por
 *                (plan, cobertura, rango de edad). Es el caso de PLAN IDEAL y
 *                PLAN ESPECIAL.
 *   PAQUETE    — el plan agrupa beneficios como un todo, sin coberturas, y la
 *                tarifa se resuelve por (plan, rango de edad) con
 *                `fees.coverage_id` nulo. Es el caso de PLAN INICIAL.
 *
 * El backfill es deliberadamente conservador: solo marca PAQUETE cuando no hay
 * ninguna evidencia de que el plan cobre por cobertura. Basta una tarifa con
 * `coverage_id`, o una cobertura propia, para dejarlo en COBERTURAS. Esto
 * importa porque hay planes sin coberturas propias cuyas tarifas apuntan a
 * coberturas de otros planes (HESPERIA usa la cobertura 10, que es del PLAN
 * ESPECIAL): marcarlos PAQUETE los dejaría sin tarifa.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('plans', 'pricing_mode')) {
            Schema::table('plans', function (Blueprint $table): void {
                $table->string('pricing_mode', 20)
                    ->default(PlanPricingMode::Coberturas->value)
                    ->after('type');
                $table->index('pricing_mode');
            });
        }

        $this->backfill();
    }

    public function down(): void
    {
        if (Schema::hasColumn('plans', 'pricing_mode')) {
            Schema::table('plans', function (Blueprint $table): void {
                $table->dropIndex(['pricing_mode']);
                $table->dropColumn('pricing_mode');
            });
        }
    }

    /**
     * Un plan queda en PAQUETE solo si no tiene ninguna tarifa con cobertura ni
     * coberturas propias. Ante la duda, COBERTURAS: es el comportamiento que ya
     * tenían todos los planes distintos del 1.
     */
    private function backfill(): void
    {
        $paquete = DB::table('plans')
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('fees')
                    ->whereColumn('fees.plan_id', 'plans.id')
                    ->whereNotNull('fees.coverage_id');
            })
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('coverages')
                    ->whereColumn('coverages.plan_id', 'plans.id');
            })
            ->pluck('description', 'id');

        DB::table('plans')->update(['pricing_mode' => PlanPricingMode::Coberturas->value]);

        if ($paquete->isNotEmpty()) {
            DB::table('plans')
                ->whereIn('id', $paquete->keys()->all())
                ->update(['pricing_mode' => PlanPricingMode::Paquete->value]);
        }

        $this->line("plans.pricing_mode: {$paquete->count()} plan(es) marcados como PAQUETE:");

        foreach ($paquete as $id => $description) {
            $this->line("  plan {$id} — {$description}");
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
