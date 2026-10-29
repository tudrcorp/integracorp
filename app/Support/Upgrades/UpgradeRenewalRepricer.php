<?php

declare(strict_types=1);

namespace App\Support\Upgrades;

use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\AffiliateCorporateUpgrade;
use App\Models\AffiliateUpgrade;
use App\Models\Upgrade;
use App\Models\UpgradeCoverageAgeRange;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Al renovar, cada upgrade activo se vuelve a cotizar con la edad del afiliado a
 * la fecha de renovación y el precio vigente del catálogo, igual que la tarifa
 * del plan. Fuera de la renovación el monto asignado queda congelado.
 *
 * Si ya no se puede cotizar (upgrade o cobertura inactivos, sin edad, o la edad
 * nueva no cae en ningún rango) se conserva el monto que tenía: una renovación
 * nunca deja a alguien sin un upgrade que ya paga sin que el analista lo decida.
 */
final class UpgradeRenewalRepricer
{
    public const REASON = 'RENOVACIÓN';

    /**
     * Suma que tendrían los upgrades activos a esa fecha. Solo lee.
     */
    public static function projectedTotal(Affiliate|AffiliateCorporate $affiliate, Carbon $asOf): float
    {
        if (! self::mayHaveUpgrades($affiliate)) {
            return 0.0;
        }

        $total = 0.0;

        foreach (self::activeAssignments($affiliate, lock: false) as $assignment) {
            $total += self::quote($assignment, $affiliate, $asOf)['amount'];
        }

        return round($total, 2);
    }

    /**
     * Vuelve a cotizar y deja escrito el resultado. Debe correr dentro de la
     * transacción de la renovación. Cada upgrade que cambia de precio o de rango
     * pasa a INACTIVO y lo reemplaza uno nuevo que apunta a él (`replaces_id`).
     *
     * @return float suma de upgrades activos después de recotizar
     */
    public static function reprice(Affiliate|AffiliateCorporate $affiliate, Carbon $asOf, ?string $actor = null): float
    {
        if (! self::mayHaveUpgrades($affiliate)) {
            return 0.0;
        }

        $actor = trim((string) ($actor ?? Auth::user()?->name ?? '')) ?: 'system';
        $total = 0.0;

        foreach (self::activeAssignments($affiliate, lock: true) as $assignment) {
            $quote = self::quote($assignment, $affiliate, $asOf);
            $total += $quote['amount'];

            if ($quote['range'] === null) {
                continue;
            }

            $samePrice = abs((float) $assignment->amount - $quote['amount']) < 0.005;
            $sameRange = (int) $assignment->upgrade_coverage_age_range_id === (int) $quote['range']->getKey();

            if ($samePrice && $sameRange) {
                continue;
            }

            $assignment->forceFill([
                'status' => Upgrade::STATUS_INACTIVE,
                'deactivated_at' => now(),
                'deactivated_by' => $actor,
                'deactivation_reason' => self::REASON,
                'updated_by' => $actor,
            ])->save();

            $replacement = $assignment->replicate(['status', 'deactivated_at', 'deactivated_by', 'deactivation_reason']);
            $replacement->forceFill([
                'upgrade_coverage_age_range_id' => (int) $quote['range']->getKey(),
                'age_at_assignment' => $quote['age'],
                'amount' => $quote['amount'],
                'status' => Upgrade::STATUS_ACTIVE,
                'replaces_id' => (int) $assignment->getKey(),
                'created_by' => $actor,
                'updated_by' => $actor,
            ])->save();
        }

        if ($affiliate->relationLoaded('activeUpgrades')) {
            $affiliate->unsetRelation('activeUpgrades');
        }

        return round($total, 2);
    }

    /**
     * @return array{amount: float, range: ?UpgradeCoverageAgeRange, age: ?int}
     */
    private static function quote(AffiliateCorporateUpgrade|AffiliateUpgrade $assignment, Affiliate|AffiliateCorporate $affiliate, Carbon $asOf): array
    {
        $frozen = ['amount' => round((float) $assignment->amount, 2), 'range' => null, 'age' => null];
        $coverage = $assignment->upgradeCoverage;

        if ($coverage === null
            || $coverage->status !== Upgrade::STATUS_ACTIVE
            || $coverage->upgrade === null
            || ! $coverage->upgrade->isActive()) {
            return $frozen;
        }

        $age = UpgradePricing::ageOf($affiliate, $asOf);

        if ($age === null) {
            return $frozen;
        }

        $range = UpgradePricing::rangeFor($coverage, $age);

        if ($range === null) {
            return $frozen;
        }

        return ['amount' => round((float) $range->price, 2), 'range' => $range, 'age' => $age];
    }

    /**
     * @return Collection<int, AffiliateCorporateUpgrade>|Collection<int, AffiliateUpgrade>
     */
    private static function activeAssignments(Affiliate|AffiliateCorporate $affiliate, bool $lock): Collection
    {
        $query = $affiliate->upgrades()
            ->where('status', Upgrade::STATUS_ACTIVE)
            ->with(['upgradeCoverage.upgrade', 'upgradeCoverage.activeAgeRanges'])
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    /**
     * Evita consultar a quien el agregado precargado ya dice que no tiene upgrades.
     */
    private static function mayHaveUpgrades(Affiliate|AffiliateCorporate $affiliate): bool
    {
        if (! $affiliate->exists) {
            return false;
        }

        if (array_key_exists('active_upgrades_total', $affiliate->getAttributes())) {
            return (float) $affiliate->getAttribute('active_upgrades_total') > 0;
        }

        return true;
    }
}
