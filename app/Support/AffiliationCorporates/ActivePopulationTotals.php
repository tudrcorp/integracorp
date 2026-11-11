<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates;

use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\AfilliationCorporatePlan;
use App\Models\User;
use App\Services\CorporateAffiliateRemovalService;
use App\Support\AffiliationCorporates\Population\PopulationUpdateApplier;
use App\Support\SecurityAudit;
use App\Support\Upgrades\UpgradeFirstPaymentBilling;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Población, tarifa anual y cuota de una afiliación corporativa calculadas con
 * sus afiliados vigentes (ACTIVO y PRE-APROBADA, sin eliminados).
 *
 * Los totales guardados en la afiliación (`poblation`, `fee_anual`,
 * `total_amount`) se mueven por deltas y con el tiempo se desfasan; los usan la
 * cobranza, los avisos y las renovaciones. Por eso la tabla MUESTRA lo vivo y
 * solo «Recalcular totales» (una afiliación a la vez, con razón) los corrige.
 *
 * La tarifa del afiliado ya incluye sus upgrades; la cuota deja fuera los
 * upgrades de cobro único, igual que {@see UpgradeFirstPaymentBilling::corporateRegular()}.
 */
final class ActivePopulationTotals
{
    public const REASON_MIN_LENGTH = 15;

    /** Diferencia mínima para considerar que lo guardado no cuadra. */
    private const TOLERANCE = 0.01;

    /**
     * Agrega a la consulta de la tabla los totales vivos como subconsultas
     * correlacionadas: una sola consulta para toda la página, ordenable.
     *
     * @param  Builder<AffiliationCorporate>  $query
     * @return Builder<AffiliationCorporate>
     */
    public static function applyToQuery(Builder $query): Builder
    {
        $active = static fn ($sub) => $sub
            ->whereColumn('affiliate_corporates.affiliation_corporate_id', 'affiliation_corporates.id')
            ->whereIn('affiliate_corporates.status', CorporateAffiliatePlanSynchronizer::COUNTABLE_STATUSES)
            ->whereNull('affiliate_corporates.deleted_at');

        if ($query->getQuery()->columns === null) {
            $query->select('affiliation_corporates.*');
        }

        $query
            ->selectSub($active(DB::table('affiliate_corporates')->selectRaw('count(*)')), 'active_population')
            ->selectSub($active(DB::table('affiliate_corporates')->selectRaw('coalesce(sum(affiliate_corporates.fee), 0)')), 'active_fee_anual')
            ->selectSub($active(DB::table('affiliate_corporates')->selectRaw('count(*)'))->where(static fn ($q) => $q->whereNull('affiliate_corporates.fee')->orWhere('affiliate_corporates.fee', '<=', 0)), 'active_without_fee');

        if (self::upgradesHaveBillingMode()) {
            $query->selectSub(
                DB::table('affiliate_corporate_upgrades')
                    ->join('affiliate_corporates as upgrade_affiliates', 'upgrade_affiliates.id', '=', 'affiliate_corporate_upgrades.affiliate_corporate_id')
                    ->whereColumn('affiliate_corporate_upgrades.affiliation_corporate_id', 'affiliation_corporates.id')
                    ->where('affiliate_corporate_upgrades.status', \App\Models\AffiliateCorporateUpgrade::STATUS_ACTIVE)
                    ->where('affiliate_corporate_upgrades.billing_mode', UpgradeFirstPaymentBilling::MODE)
                    ->whereIn('upgrade_affiliates.status', CorporateAffiliatePlanSynchronizer::COUNTABLE_STATUSES)
                    ->selectRaw('coalesce(sum(affiliate_corporate_upgrades.amount), 0)'),
                'active_one_time',
            );
        }

        return $query;
    }

    /**
     * Totales vivos de una fila de la tabla (usa las subconsultas si están; si no, consulta).
     *
     * @return array{persons: int, fee_anual: float, total_amount: float, without_fee: int, stored: array{persons: int, fee_anual: float, total_amount: float}, differs: bool}
     */
    public static function forRecord(AffiliationCorporate $record): array
    {
        if (! array_key_exists('active_population', $record->getAttributes())) {
            return self::compute($record);
        }

        $annual = round((float) $record->getAttribute('active_fee_anual'), 2);

        return self::result(
            $record,
            (int) $record->getAttribute('active_population'),
            $annual,
            self::installment($annual, round((float) ($record->getAttribute('active_one_time') ?? 0), 2), (string) $record->payment_frequency),
            (int) $record->getAttribute('active_without_fee'),
        );
    }

    /**
     * Totales vivos consultando la base (para la vista previa y el recálculo).
     *
     * @return array{persons: int, fee_anual: float, total_amount: float, without_fee: int, stored: array{persons: int, fee_anual: float, total_amount: float}, differs: bool}
     */
    public static function compute(AffiliationCorporate $owner): array
    {
        $active = AffiliateCorporate::query()
            ->where('affiliation_corporate_id', $owner->getKey())
            ->whereIn('status', CorporateAffiliatePlanSynchronizer::COUNTABLE_STATUSES);

        $annual = round((float) (clone $active)->sum('fee'), 2);

        return self::result(
            $owner,
            (clone $active)->count(),
            $annual,
            UpgradeFirstPaymentBilling::corporateRegular($owner, $annual),
            (clone $active)->where(static fn ($q) => $q->whereNull('fee')->orWhere('fee', '<=', 0))->count(),
        );
    }

    /**
     * Qué cambiaría «Recalcular totales», sin escribir nada.
     *
     * @return array{
     *     live: array{persons: int, fee_anual: float, total_amount: float, without_fee: int, stored: array{persons: int, fee_anual: float, total_amount: float}, differs: bool},
     *     rows: list<array{id: int, label: string, fee: float, before: int, after: int}>,
     *     rows_updatable: bool,
     *     unresolved: list<string>,
     *     unresolved_total: int,
     *     blocking: string|null
     * }
     */
    public static function preview(AffiliationCorporate $owner): array
    {
        $live = self::compute($owner);
        $planRows = AfilliationCorporatePlan::query()->where('affiliation_corporate_id', $owner->getKey())->with('ageRange')->get()->keyBy('id');
        $resolution = CorporateAffiliatePlanSynchronizer::planRowsFor($owner);

        $counts = $planRows->map(static fn (): int => 0)->all();
        $unresolved = [];
        $unresolvedTotal = 0;

        AffiliateCorporate::query()
            ->where('affiliation_corporate_id', $owner->getKey())
            ->whereIn('status', CorporateAffiliatePlanSynchronizer::COUNTABLE_STATUSES)
            ->get(['id', 'first_name', 'last_name', 'nro_identificacion', 'age', 'plan_id', 'coverage_id', 'status'])
            ->each(static function (AffiliateCorporate $affiliate) use ($owner, $resolution, &$counts, &$unresolved, &$unresolvedTotal): void {
                $rowId = PopulationUpdateApplier::planRowIdFor($owner, $affiliate, $resolution);
                if ($rowId !== null && array_key_exists($rowId, $counts)) {
                    $counts[$rowId]++;

                    return;
                }

                $unresolvedTotal++;
                if (count($unresolved) < 15) {
                    $unresolved[] = trim($affiliate->first_name.' '.$affiliate->last_name).' (C.I. '.$affiliate->nro_identificacion.', '.($affiliate->age ?? 'sin edad').')';
                }
            });

        $rows = $planRows->map(static fn (AfilliationCorporatePlan $row): array => [
            'id' => (int) $row->getKey(),
            'label' => $row->ageRange !== null ? $row->ageRange->age_init.' a '.$row->ageRange->age_end.' años' : 'Fila #'.$row->getKey(),
            'fee' => round((float) $row->fee, 2),
            'before' => (int) $row->total_persons,
            'after' => $counts[(int) $row->getKey()] ?? 0,
        ])->values()->all();

        return [
            'live' => $live,
            'rows' => $rows,
            'rows_updatable' => $unresolvedTotal === 0 && $rows !== [],
            'unresolved' => $unresolved,
            'unresolved_total' => $unresolvedTotal,
            'blocking' => self::blockingReason($owner, $live),
        ];
    }

    /**
     * Guarda los totales vivos en la afiliación (y en sus filas de plan si todos
     * los afiliados tienen fila). Todo junto o nada, con la afiliación bloqueada.
     *
     * @return array{before: array<string, mixed>, after: array<string, mixed>, rows_updated: bool}
     */
    public static function recalculate(AffiliationCorporate $owner, User $user, string $reason): array
    {
        $reason = trim((string) preg_replace('/\s+/u', ' ', $reason));
        if (mb_strlen($reason) < self::REASON_MIN_LENGTH) {
            throw new RuntimeException('Explique por qué recalcula (mínimo '.self::REASON_MIN_LENGTH.' caracteres).');
        }

        $reason = mb_substr($reason, 0, 1000);
        $lock = Cache::lock(PopulationUpdateApplier::lockKey((int) $owner->getKey()), 120);
        if (! $lock->get()) {
            throw new RuntimeException('Hay una actualización de población en curso en esta afiliación. Intente en unos minutos.');
        }

        try {
            $result = DB::transaction(static function () use ($owner): array {
                $locked = AffiliationCorporate::query()->lockForUpdate()->findOrFail($owner->getKey());
                AfilliationCorporatePlan::query()->where('affiliation_corporate_id', $locked->getKey())->lockForUpdate()->get();

                $preview = self::preview($locked);
                if ($preview['blocking'] !== null) {
                    throw new RuntimeException($preview['blocking']);
                }

                $live = $preview['live'];
                $before = [
                    'poblation' => (int) $locked->poblation,
                    'fee_anual' => round((float) $locked->fee_anual, 2),
                    'total_amount' => round((float) $locked->total_amount, 2),
                    'rows' => array_map(static fn (array $row): array => ['id' => $row['id'], 'total_persons' => $row['before']], $preview['rows']),
                ];

                if (! $live['differs'] && (! $preview['rows_updatable'] || collect($preview['rows'])->every(static fn (array $row): bool => $row['before'] === $row['after']))) {
                    throw new RuntimeException('Los totales ya cuadran con la población activa: no hay nada que recalcular.');
                }

                $locked->forceFill([
                    'poblation' => $live['persons'],
                    'fee_anual' => $live['fee_anual'],
                    'total_amount' => $live['total_amount'],
                ])->save();

                if ($preview['rows_updatable']) {
                    foreach ($preview['rows'] as $row) {
                        $planRow = AfilliationCorporatePlan::query()->find($row['id']);
                        if ($planRow instanceof AfilliationCorporatePlan && (int) $planRow->total_persons !== $row['after']) {
                            $planRow->total_persons = $row['after'];
                            CorporateAffiliateRemovalService::recalculateCorporatePlanRowTotals($planRow);
                            $planRow->save();
                        }
                    }
                }

                return [
                    'before' => $before,
                    'after' => [
                        'poblation' => $live['persons'],
                        'fee_anual' => $live['fee_anual'],
                        'total_amount' => $live['total_amount'],
                        'rows' => $preview['rows_updatable'] ? array_map(static fn (array $row): array => ['id' => $row['id'], 'total_persons' => $row['after']], $preview['rows']) : null,
                    ],
                    'rows_updated' => $preview['rows_updatable'],
                    'unresolved_total' => $preview['unresolved_total'],
                ];
            });
        } finally {
            $lock->release();
        }

        SecurityAudit::log('AUDIT_CORPORATE_TOTALS_RECALCULATED', 'business.affiliation-corporates.recalculate-totals', [
            'affiliation_corporate_id' => $owner->getKey(),
            'code' => $owner->code,
            'before' => $result['before'],
            'after' => $result['after'],
            'rows_updated' => $result['rows_updated'],
            'unresolved_affiliates' => $result['unresolved_total'],
            'reason' => $reason,
        ], $user);

        return ['before' => $result['before'], 'after' => $result['after'], 'rows_updated' => $result['rows_updated']];
    }

    /**
     * @param  array{persons: int, fee_anual: float}  $live
     */
    private static function blockingReason(AffiliationCorporate $owner, array $live): ?string
    {
        $busy = \App\Models\CorporatePopulationUpdate::query()
            ->where('affiliation_corporate_id', $owner->getKey())
            ->whereIn('status', [\App\Models\CorporatePopulationUpdate::STATUS_APPLYING, \App\Models\CorporatePopulationUpdate::STATUS_ROLLING_BACK])
            ->exists();

        if ($busy) {
            return 'Hay una carga de población aplicándose o revirtiéndose en esta afiliación. Espere a que termine.';
        }

        if ($live['persons'] > 0 && $live['fee_anual'] <= 0) {
            return 'Los afiliados activos no tienen tarifa: el recálculo dejaría la afiliación en cero. Revise las tarifas antes de recalcular.';
        }

        return null;
    }

    private static function installment(float $annual, float $oneTime, string $frequency): float
    {
        return $oneTime > 0
            ? UpgradeFirstPaymentBilling::regularInstallment($annual, $oneTime, $frequency)
            : round(CorporateAffiliateRemovalService::annualFeeToPerPeriodAmount($annual, $frequency), 2);
    }

    /**
     * @return array{persons: int, fee_anual: float, total_amount: float, without_fee: int, stored: array{persons: int, fee_anual: float, total_amount: float}, differs: bool}
     */
    private static function result(AffiliationCorporate $owner, int $persons, float $annual, float $total, int $withoutFee): array
    {
        $stored = [
            'persons' => (int) $owner->poblation,
            'fee_anual' => round((float) $owner->fee_anual, 2),
            'total_amount' => round((float) $owner->total_amount, 2),
        ];
        $total = round($total, 2);

        return [
            'persons' => $persons,
            'fee_anual' => $annual,
            'total_amount' => $total,
            'without_fee' => $withoutFee,
            'stored' => $stored,
            'differs' => $stored['persons'] !== $persons
                || abs($stored['fee_anual'] - $annual) >= self::TOLERANCE
                || abs($stored['total_amount'] - $total) >= self::TOLERANCE,
        ];
    }

    private static function upgradesHaveBillingMode(): bool
    {
        static $has = null;

        return $has ??= Schema::hasColumn('affiliate_corporate_upgrades', 'billing_mode');
    }
}
