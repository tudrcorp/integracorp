<?php

declare(strict_types=1);

namespace App\Support\WhiteCompanies;

use App\Models\Fee;
use App\Models\Plan;
use App\Models\WhiteCompany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Habilitación de planes para una empresa aliada.
 *
 * Es el primer paso del circuito y está deliberadamente separado de la matriz de
 * negociación: acá se decide **qué planes puede cotizar** la aliada, y recién
 * después se pactan venta y neta de sus tarifas (`WhiteCompanyFee`). La matriz
 * solo ofrece tarifas de los planes que pasaron por acá.
 *
 * **Un plan sin beneficios no se puede asignar.** Los beneficios son lo que
 * describe qué cubre el plan en la propuesta al cliente; habilitarlo sin ellos
 * dejaría cotizar algo que no se puede explicar. Coberturas, rangos y tarifas
 * viajan con el plan y no se exigen acá: un plan puede quedar asignado y todavía
 * no ser cotizable hasta que sus tarifas tengan neta.
 */
final class WhiteCompanyPlanAssignment
{
    /**
     * Planes que se pueden habilitar: cualquiera con beneficios cargados. No se
     * filtra por empresa —el analista puede asignar cualquier plan a cualquier
     * aliada— pero sí se excluyen los que ya tiene.
     *
     * @return array<int, string>
     */
    public static function assignablePlans(WhiteCompany $company): array
    {
        $yaAsignados = $company->assignedPlans()->pluck('plan_id')->all();

        return self::plansWithBenefits()
            ->reject(fn (Plan $plan): bool => in_array($plan->id, $yaAsignados, false))
            ->mapWithKeys(fn (Plan $plan): array => [
                (int) $plan->id => self::planLabel($plan),
            ])
            ->all();
    }

    /**
     * @return Collection<int, Plan>
     */
    public static function plansWithBenefits(): Collection
    {
        return Plan::query()
            ->whereHas('benefitPlans')
            ->orderBy('description')
            ->get();
    }

    /**
     * Motivo por el que un plan no se puede asignar, o null si se puede. Sirve
     * para explicarle al analista por qué un plan no aparece en la lista.
     */
    public static function blockingReason(Plan $plan): ?string
    {
        if ($plan->benefitPlans()->count() === 0) {
            return 'El plan no tiene beneficios cargados.';
        }

        return null;
    }

    public static function planLabel(Plan $plan): string
    {
        $descripcion = filled($plan->description) ? (string) $plan->description : 'Plan '.$plan->id;
        $tarifas = Fee::query()->forPlan((int) $plan->id)->count();

        return $tarifas === 0
            ? $descripcion.' (sin tarifas en el catálogo)'
            : $descripcion;
    }

    /**
     * Habilita uno o varios planes para la empresa aliada.
     *
     * @param  list<int|string>  $planIds
     * @return array{asignados: int, ya_estaban: int}
     */
    public static function assign(WhiteCompany $company, array $planIds, ?string $createdBy): array
    {
        $planes = self::validatePlans($planIds);
        $yaAsignados = $company->assignedPlans()->pluck('plan_id')->all();

        return DB::transaction(function () use ($company, $planes, $yaAsignados, $createdBy): array {
            $resumen = ['asignados' => 0, 'ya_estaban' => 0];

            foreach ($planes as $plan) {
                if (in_array($plan->id, $yaAsignados, false)) {
                    $resumen['ya_estaban']++;

                    continue;
                }

                $company->assignedPlans()->create([
                    'plan_id' => $plan->id,
                    'status' => 'ACTIVO',
                    'created_by' => $createdBy,
                ]);

                $resumen['asignados']++;
            }

            return $resumen;
        });
    }

    /**
     * Quita un plan de la empresa aliada. Las netas ya pactadas de ese plan se
     * retiran junto con él: dejarlas sueltas haría que la aliada siguiera
     * cotizando un plan que ya no tiene habilitado.
     *
     * @return array{netas_retiradas: int}
     */
    public static function unassign(WhiteCompany $company, int $planId): array
    {
        return DB::transaction(function () use ($company, $planId): array {
            $feeIds = Fee::query()->forPlan($planId)->pluck('id');

            $netas = $company->negotiatedFees()
                ->whereIn('fee_id', $feeIds)
                ->delete();

            $company->assignedPlans()->where('plan_id', $planId)->delete();

            return ['netas_retiradas' => (int) $netas];
        });
    }

    /**
     * Ids de las tarifas que la matriz de negociación puede ofrecer: las de los
     * planes habilitados para esta aliada.
     *
     * @return list<int>
     */
    public static function assignedFeeIds(WhiteCompany $company): array
    {
        $planIds = $company->assignedPlans()
            ->where(function ($query): void {
                $query->whereNull('status')->orWhere('status', 'ACTIVO');
            })
            ->pluck('plan_id');

        if ($planIds->isEmpty()) {
            return [];
        }

        return Fee::query()
            ->whereIn('plan_id', $planIds)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @param  list<int|string>  $planIds
     * @return Collection<int, Plan>
     */
    public static function validatePlans(array $planIds): Collection
    {
        $ids = collect($planIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            throw ValidationException::withMessages([
                'plan_ids' => 'Debe seleccionar al menos un plan.',
            ]);
        }

        $planes = Plan::query()->whereIn('id', $ids)->get();

        if ($planes->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'plan_ids' => 'Alguno de los planes seleccionados no existe.',
            ]);
        }

        foreach ($planes as $plan) {
            $motivo = self::blockingReason($plan);

            if ($motivo !== null) {
                throw ValidationException::withMessages([
                    'plan_ids' => sprintf('%s: %s', $plan->description ?? 'Plan '.$plan->id, $motivo),
                ]);
            }
        }

        return $planes;
    }
}
