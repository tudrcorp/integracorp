<?php

declare(strict_types=1);

namespace App\Support\Business\CapitationPlans;

use App\Models\AffiliateCapitationOverride;
use App\Models\CapitationPlan;
use App\Models\Supplier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Planes de capitado aplicables, agrupados por alias del proveedor Outsourcing, y ajustes
 * negociados por afiliado.
 *
 * Se arma con tres consultas y vive una sola vez por request o job (binding `scoped`): una
 * tabla de 50 afiliados no consulta 50 veces, y un worker de colas no arrastra datos viejos
 * de un job al siguiente.
 */
final class CapitationCostIndex
{
    /**
     * @param  array<string, list<array{capitation_plan_id: int, plan: string, provider: string, cost: float, frequency: string, plan_ids: list<int>, plan_model: CapitationPlan}>>  $byAlias
     */
    /**
     * @param  array<string, array{amount: float, reason: string, by: string, at: string, plan_amount: float|null}>  $overrides  por clave `tipo:id`
     */
    private function __construct(private readonly array $byAlias, private readonly array $overrides = []) {}

    public static function load(): self
    {
        $plans = CapitationPlan::query()
            ->where('status', CapitationPlan::STATUS_ACTIVE)
            ->where('provider_type', CapitationPlan::PROVIDER_SUPPLIER)
            ->with([
                'supplier:id,name,integracorp_alias,is_outsourcing_medical_department,deleted_at',
                'coveredPlans:id',
            ])
            ->get();

        $byAlias = [];

        foreach ($plans as $plan) {
            $supplier = $plan->supplier;

            /** Proveedor eliminado, fuera de Outsourcing o sin alias: ningún afiliado puede apuntarle. */
            if (! $supplier instanceof Supplier || $supplier->trashed() || ! $supplier->is_outsourcing_medical_department) {
                continue;
            }

            $alias = self::normalize((string) $supplier->integracorp_alias);

            if ($alias === '') {
                continue;
            }

            $byAlias[$alias][] = [
                'capitation_plan_id' => (int) $plan->getKey(),
                'plan' => (string) $plan->name,
                'provider' => (string) $supplier->name,
                'cost' => round((float) $plan->cost_per_affiliate_usd, 2),
                'frequency' => $plan->payment_frequency->label(),
                'plan_ids' => $plan->coveredPlans->modelKeys(),
                'plan_model' => $plan,
            ];
        }

        return new self($byAlias, self::loadOverrides());
    }

    /**
     * Los ajustes negociados son pocos (uno por afiliado con negociación directa): se cargan
     * todos en una consulta en lugar de una por fila.
     *
     * @return array<string, array{amount: float, reason: string, by: string, at: string, plan_amount: float|null}>
     */
    private static function loadOverrides(): array
    {
        try {
            $rows = AffiliateCapitationOverride::query()
                ->get(['subject_type', 'subject_id', 'amount_usd', 'reason', 'plan_amount_usd', 'created_by_name', 'updated_at']);
        } catch (QueryException $exception) {
            /** Despliegue a medias (migración pendiente): se muestra el monto del plan, no se rompe la tabla. */
            Log::warning('CapitationCostIndex: no se pudieron leer los ajustes de capitación', ['error' => $exception->getMessage()]);

            return [];
        }

        $overrides = [];

        foreach ($rows as $row) {
            $overrides[$row->subject_type.':'.$row->subject_id] = [
                'amount' => round((float) $row->amount_usd, 2),
                'reason' => (string) $row->reason,
                'by' => (string) ($row->created_by_name ?? '—'),
                'at' => (string) $row->updated_at?->format('d/m/Y h:i A'),
                'plan_amount' => $row->plan_amount_usd !== null ? round((float) $row->plan_amount_usd, 2) : null,
            ];
        }

        return $overrides;
    }

    /**
     * @return array{amount: float, reason: string, by: string, at: string, plan_amount: float|null}|null
     */
    public function overrideFor(?string $subjectKey): ?array
    {
        return $subjectKey !== null ? ($this->overrides[$subjectKey] ?? null) : null;
    }

    public static function normalize(string $alias): string
    {
        return Str::upper(Str::squish($alias));
    }

    public function hasAlias(string $alias): bool
    {
        return isset($this->byAlias[$alias]);
    }

    /**
     * @return list<array{capitation_plan_id: int, plan: string, provider: string, cost: float, frequency: string, plan_ids: list<int>, plan_model: CapitationPlan}>
     */
    public function plansFor(string $alias): array
    {
        return $this->byAlias[$alias] ?? [];
    }
}
