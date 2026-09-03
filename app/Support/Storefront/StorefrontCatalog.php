<?php

declare(strict_types=1);

namespace App\Support\Storefront;

use App\Models\Fee;
use App\Models\Plan;
use App\Support\Plans\PlanQuotability;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Catálogo de la PWA: solo planes individuales BÁSICOS activos.
 * Dress Tylor y planes inactivos no se venden aquí.
 */
final class StorefrontCatalog
{
    /**
     * Plan Inicial, Ideal y Especial: la línea básica individual
     * que se vende al público. El resto de BÁSICOS del catálogo
     * interno no entra a la PWA.
     *
     * @var list<int>
     */
    public const INDIVIDUAL_BASIC_IDS = [1, 2, 3];

    /**
     * @return Builder<Plan>
     */
    public static function query(): Builder
    {
        return Plan::query()
            ->whereIn('id', self::INDIVIDUAL_BASIC_IDS)
            ->where('type', PlanQuotability::TYPE_BASICO)
            ->where('status', 'ACTIVO')
            ->orderBy('id');
    }

    public static function findActiveBasic(int $planId): ?Plan
    {
        if ($planId <= 0) {
            return null;
        }

        $plan = self::query()->whereKey($planId)->first();

        return $plan instanceof Plan ? $plan : null;
    }

    /**
     * @return Collection<int, array{
     *     plan: Plan,
     *     narrative: array<string, mixed>,
     *     desde: float|null,
     *     people_label: string
     * }>
     */
    public static function cards(): Collection
    {
        $plans = self::query()->get();

        if ($plans->isEmpty()) {
            return collect();
        }

        $startingPrices = Fee::query()
            ->whereIn('plan_id', $plans->modelKeys())
            ->whereNotNull('price')
            ->selectRaw('plan_id, MIN(price) as desde')
            ->groupBy('plan_id')
            ->pluck('desde', 'plan_id');

        return $plans->map(static function (Plan $plan) use ($startingPrices): array {
            $desde = $startingPrices->get($plan->getKey());

            return [
                'plan' => $plan,
                'narrative' => StorefrontPlanNarrative::for($plan),
                'desde' => is_numeric($desde) ? (float) $desde : null,
                'people_label' => 'Por persona / año',
            ];
        });
    }
}
