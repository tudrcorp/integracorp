<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates;

use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\AfilliationCorporatePlan;
use App\Models\AgeRange;
use App\Services\CorporateAffiliatePlanSyncService;
use App\Services\CorporateAffiliateRemovalService;
use App\Support\AffiliationAffiliateBusinessContextSynchronizer;
use App\Support\SecurityAudit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Alinea a cada afiliado corporativo con lo contratado por su empresa: toma la
 * fila de `afilliation_corporate_plans` cuyo rango de edad contiene la edad del
 * afiliado y le copia plan, cobertura y tarifa, además de la unidad de negocio y
 * la línea de servicio de la afiliación.
 *
 * A diferencia de «Reasignar plan», aquí no se elige nada a mano: el plan sale de
 * la afiliación y el rango de edad se deduce de la edad de cada persona.
 */
final class CorporateAffiliatePlanSynchronizer
{
    public const REASON_NO_AGE = 'sin_edad';

    public const REASON_NO_PLAN_ROW = 'sin_plan_para_la_edad';

    public const REASON_AMBIGUOUS = 'varios_planes_aplican';

    /**
     * Estados que forman parte de la población facturable.
     *
     * La mayoría de la cartera corporativa vive en PRE-APROBADA: contar solo
     * ACTIVO al recalcular dejaba los totales de la afiliación en cero.
     *
     * @var list<string>
     */
    public const COUNTABLE_STATUSES = ['ACTIVO', 'PRE-APROBADA'];

    /**
     * Filas de plan contratadas por la afiliación, con su rango de edad resuelto.
     *
     * @return Collection<int, AfilliationCorporatePlan>
     */
    public static function planRowsFor(AffiliationCorporate $owner): Collection
    {
        return AfilliationCorporatePlan::query()
            ->where('affiliation_corporate_id', $owner->getKey())
            ->with('ageRange')
            ->get()
            ->filter(fn (AfilliationCorporatePlan $row): bool => $row->ageRange instanceof AgeRange)
            ->values();
    }

    public static function ageOf(AffiliateCorporate $affiliate): ?int
    {
        $age = trim((string) ($affiliate->age ?? ''));

        if ($age === '' || ! is_numeric($age)) {
            return null;
        }

        return (int) $age;
    }

    /**
     * Fila de plan que le corresponde al afiliado por su edad.
     *
     * Cuando varias filas cubren la misma edad se respeta el plan que el afiliado
     * ya tenga asignado; si aun así hay más de una opción distinta, se considera
     * ambiguo y no se toca el registro en lugar de adivinar.
     *
     * @param  Collection<int, AfilliationCorporatePlan>|null  $planRows
     * @return array{row: AfilliationCorporatePlan|null, reason: string|null}
     */
    public static function resolvePlanRowForAffiliate(
        AffiliationCorporate $owner,
        AffiliateCorporate $affiliate,
        ?Collection $planRows = null,
    ): array {
        $age = self::ageOf($affiliate);

        if ($age === null) {
            return ['row' => null, 'reason' => self::REASON_NO_AGE];
        }

        $planRows ??= self::planRowsFor($owner);

        $candidates = $planRows->filter(function (AfilliationCorporatePlan $row) use ($age): bool {
            $ageRange = $row->ageRange;

            return $age >= (int) $ageRange->age_init && $age <= (int) $ageRange->age_end;
        })->values();

        if ($candidates->isEmpty()) {
            return ['row' => null, 'reason' => self::REASON_NO_PLAN_ROW];
        }

        if ($candidates->count() === 1) {
            return ['row' => $candidates->first(), 'reason' => null];
        }

        if ($affiliate->plan_id !== null) {
            $samePlan = $candidates->where('plan_id', (int) $affiliate->plan_id);

            if ($samePlan->count() === 1) {
                return ['row' => $samePlan->first(), 'reason' => null];
            }
        }

        $distinct = $candidates
            ->map(fn (AfilliationCorporatePlan $row): string => implode('|', [
                (int) $row->plan_id,
                (int) $row->coverage_id,
                number_format((float) $row->fee, 2, '.', ''),
            ]))
            ->unique();

        if ($distinct->count() === 1) {
            return ['row' => $candidates->first(), 'reason' => null];
        }

        return ['row' => null, 'reason' => self::REASON_AMBIGUOUS];
    }

    public static function isSynced(
        AffiliationCorporate $owner,
        AffiliateCorporate $affiliate,
        ?AfilliationCorporatePlan $planRow = null,
    ): bool {
        if (! self::businessContextIsSynced($owner, $affiliate)) {
            return false;
        }

        $planRow ??= self::resolvePlanRowForAffiliate($owner, $affiliate)['row'];

        if ($planRow === null) {
            return false;
        }

        return (int) $affiliate->plan_id === (int) $planRow->plan_id
            && (int) $affiliate->coverage_id === (int) $planRow->coverage_id
            && abs((float) $affiliate->fee - (float) $planRow->fee) < 0.01;
    }

    public static function businessContextIsSynced(AffiliationCorporate $owner, AffiliateCorporate $affiliate): bool
    {
        if (blank($owner->business_unit_id) || blank($owner->business_line_id)) {
            return blank($affiliate->business_unit_id)
                && blank($affiliate->business_line_id)
                && self::specificBusinessUnitMatches($owner, $affiliate);
        }

        return (int) $affiliate->business_unit_id === (int) $owner->business_unit_id
            && (int) $affiliate->business_line_id === (int) $owner->business_line_id
            && self::specificBusinessUnitMatches($owner, $affiliate);
    }

    private static function specificBusinessUnitMatches(AffiliationCorporate $owner, AffiliateCorporate $affiliate): bool
    {
        $ownerValue = AffiliationAffiliateBusinessContextSynchronizer::normalizeSpecificBusinessUnit(
            $owner->specific_business_unit,
        );
        $affiliateValue = AffiliationAffiliateBusinessContextSynchronizer::normalizeSpecificBusinessUnit(
            $affiliate->specific_business_unit,
        );

        return $ownerValue === $affiliateValue;
    }

    /**
     * Sincroniza los afiliados indicados.
     *
     * Nunca cambia el `status`: un afiliado dado de baja no debe reactivarse por
     * sincronizar sus montos.
     *
     * @param  iterable<int, AffiliateCorporate>  $affiliates
     * @return array{updated: int, unchanged: int, skipped: list<array{name: string, reason: string}>}
     */
    public static function sync(AffiliationCorporate $owner, iterable $affiliates): array
    {
        $planRows = self::planRowsFor($owner);
        $frequency = (string) $owner->payment_frequency;

        $updated = 0;
        $unchanged = 0;
        $skipped = [];

        $before = self::totalsSnapshot($owner);

        DB::transaction(function () use ($owner, $affiliates, $planRows, $frequency, &$updated, &$unchanged, &$skipped): void {
            foreach ($affiliates as $affiliate) {
                $resolution = self::resolvePlanRowForAffiliate($owner, $affiliate, $planRows);
                $planRow = $resolution['row'];

                if ($planRow === null) {
                    $skipped[] = [
                        'name' => self::labelFor($affiliate),
                        'reason' => (string) $resolution['reason'],
                    ];

                    continue;
                }

                $attributes = self::attributesFor($owner, $planRow, $frequency);
                $changes = array_filter(
                    $attributes,
                    fn (mixed $value, string $key): bool => ! self::valuesMatch($affiliate->{$key}, $value),
                    ARRAY_FILTER_USE_BOTH,
                );

                if ($changes === []) {
                    $unchanged++;

                    continue;
                }

                $affiliate->update($attributes);
                $updated++;
            }

            if ($updated > 0) {
                CorporateAffiliatePlanSyncService::syncPlanRowTotalsFromAffiliates($owner, self::COUNTABLE_STATUSES);
                CorporateAffiliatePlanSyncService::syncOwnerTotalsFromAffiliates($owner, self::COUNTABLE_STATUSES);

                self::assertTotalsAreNotWipedOut($owner);
            }
        });

        $after = self::totalsSnapshot($owner);

        SecurityAudit::log('AUDIT_BUSINESS_CORPORATE_AFFILIATE_PLAN_SYNCED', 'business.affiliation-corporates.sync-affiliates', [
            'panel' => 'business',
            'module' => 'affiliation_corporates',
            'affiliation_corporate_id' => $owner->getKey(),
            'affiliation_code' => $owner->code,
            'updated' => $updated,
            'unchanged' => $unchanged,
            'skipped' => $skipped,
            'totals_before' => $before,
            'totals_after' => $after,
        ]);

        return ['updated' => $updated, 'unchanged' => $unchanged, 'skipped' => $skipped];
    }

    /**
     * @return array<string, mixed>
     */
    private static function attributesFor(
        AffiliationCorporate $owner,
        AfilliationCorporatePlan $planRow,
        string $frequency,
    ): array {
        $fee = (float) $planRow->fee;

        return [
            'plan_id' => $planRow->plan_id,
            'coverage_id' => $planRow->coverage_id,
            'fee' => $fee,
            'payment_frequency' => $frequency,
            'subtotal_anual' => $fee,
            'subtotal_payment_frequency' => CorporateAffiliateRemovalService::annualFeeToPerPeriodAmount($fee, $frequency),
            'subtotal_daily' => $fee / 30,
            'business_unit_id' => $owner->business_unit_id,
            'specific_business_unit' => AffiliationAffiliateBusinessContextSynchronizer::normalizeSpecificBusinessUnit(
                $owner->specific_business_unit,
            ),
            'business_line_id' => $owner->business_line_id,
        ];
    }

    private static function valuesMatch(mixed $current, mixed $target): bool
    {
        if ($current === null || $target === null) {
            return $current === null && $target === null;
        }

        if (is_numeric($current) && is_numeric($target)) {
            return abs((float) $current - (float) $target) < 0.01;
        }

        return (string) $current === (string) $target;
    }

    public static function labelFor(AffiliateCorporate $affiliate): string
    {
        $label = trim((string) $affiliate->first_name.' '.(string) $affiliate->last_name);

        return $label !== '' ? $label : 'Afiliado #'.$affiliate->getKey();
    }

    /**
     * Red de seguridad: recalcular nunca debe dejar en cero a una afiliación que
     * sí tiene población con tarifa. Si ocurre, se aborta y la transacción
     * revierte los cambios en lugar de dejar la afiliación vacía.
     */
    private static function assertTotalsAreNotWipedOut(AffiliationCorporate $owner): void
    {
        $owner->refresh();

        $expectedFee = (float) AffiliateCorporate::query()
            ->where('affiliation_corporate_id', $owner->getKey())
            ->whereIn('status', self::COUNTABLE_STATUSES)
            ->sum('fee');

        if ($expectedFee <= 0.0) {
            return;
        }

        if ((float) $owner->fee_anual > 0.0 && (int) $owner->poblation > 0) {
            return;
        }

        throw new RuntimeException(
            'La sincronización dejaría la afiliación en cero pese a tener población con tarifa. '
            .'No se aplicó ningún cambio.'
        );
    }

    /**
     * @return array{poblation: int, fee_anual: float, total_amount: float}
     */
    public static function totalsSnapshot(AffiliationCorporate $owner): array
    {
        $fresh = $owner->fresh() ?? $owner;

        return [
            'poblation' => (int) $fresh->poblation,
            'fee_anual' => (float) $fresh->fee_anual,
            'total_amount' => (float) $fresh->total_amount,
        ];
    }

    public static function reasonLabel(string $reason): string
    {
        return match ($reason) {
            self::REASON_NO_AGE => 'sin edad registrada',
            self::REASON_NO_PLAN_ROW => 'la afiliación no tiene un plan contratado para esa edad',
            self::REASON_AMBIGUOUS => 'varios planes de la afiliación aplican a esa edad',
            default => 'no se pudo determinar el plan',
        };
    }
}
