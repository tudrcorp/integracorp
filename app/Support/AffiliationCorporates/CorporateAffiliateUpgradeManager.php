<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates;

use App\Models\AffiliateCorporate;
use App\Models\AffiliateCorporateUpgrade;
use App\Models\AffiliationCorporate;
use App\Models\Collection as BillingCollection;
use App\Services\CorporateAffiliateRemovalService;
use App\Support\SecurityAudit;
use App\Support\Upgrades\UpgradeAssignmentPreview;
use App\Support\Upgrades\UpgradeIlsVoucher;
use App\Support\Upgrades\UpgradePricing;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Upgrades de afiliados corporativos: montos anuales en US$ que se suman a la
 * tarifa del afiliado. El precio sale del catálogo (`upgrades`) según la
 * cobertura elegida y la edad de cada afiliado, y queda congelado en la
 * asignación hasta la próxima renovación.
 *
 * Cada alta o baja se aplica como un delta, en una sola transacción, sobre:
 * la tarifa del afiliado, los totales de la afiliación (solo si el afiliado
 * cuenta como población) y los avisos de cobro pendientes. Así el monto que se
 * agrega es exactamente el que se refleja, sin recalcular desde cero lo que ya
 * estaba cuadrado.
 */
final class CorporateAffiliateUpgradeManager
{
    public const MAX_ITEMS = UpgradePricing::MAX_COVERAGES_PER_ASSIGNMENT;

    /**
     * Tope de `affiliate_corporates.fee` y `affiliation_corporates.total_amount` (decimal 8,2).
     */
    public const MAX_STORED_AMOUNT = 999999.99;

    public const PENDING_COLLECTION_STATUS = 'POR PAGAR';

    /**
     * @var list<string>
     */
    public const LOCKED_AFFILIATE_STATUSES = ['INACTIVO', 'EXCLUIDO'];

    public const REMOVAL_REASON = 'QUITADO POR ANALISTA';

    public const SKIP_NOT_IN_AFFILIATION = UpgradePricing::SKIP_NOT_IN_AFFILIATION;

    public const SKIP_AFFILIATE_INACTIVE = UpgradePricing::SKIP_AFFILIATE_INACTIVE;

    public const SKIP_ALREADY_ACTIVE = UpgradePricing::SKIP_ALREADY_ACTIVE;

    public const SKIP_NO_AGE = UpgradePricing::SKIP_NO_AGE;

    public const SKIP_NO_RANGE = UpgradePricing::SKIP_NO_RANGE;

    public static function normalizeName(?string $name): string
    {
        return UpgradePricing::normalizeName($name);
    }

    /**
     * Suma de upgrades activos del afiliado. Usa el agregado precargado
     * (`withActiveUpgradesTotal`) o la relación cargada antes de consultar.
     */
    public static function activeTotalFor(AffiliateCorporate $affiliate): float
    {
        if (array_key_exists('active_upgrades_total', $affiliate->getAttributes())) {
            return round((float) $affiliate->getAttribute('active_upgrades_total'), 2);
        }

        if ($affiliate->relationLoaded('activeUpgrades')) {
            return round((float) $affiliate->activeUpgrades->sum('amount'), 2);
        }

        if (! $affiliate->exists) {
            return 0.0;
        }

        return round((float) $affiliate->upgrades()
            ->where('status', AffiliateCorporateUpgrade::STATUS_ACTIVE)
            ->sum('amount'), 2);
    }

    /**
     * Upgrades activos entre los afiliados indicados, con cuántos lo tienen.
     *
     * @param  list<int>  $affiliateIds
     * @return array<int, array{name: string, total: int}> upgrade_id => datos
     */
    public static function activeUpgradesAmong(AffiliationCorporate $owner, array $affiliateIds): array
    {
        if ($affiliateIds === []) {
            return [];
        }

        return AffiliateCorporateUpgrade::query()
            ->where('affiliation_corporate_id', $owner->getKey())
            ->whereIn('affiliate_corporate_id', $affiliateIds)
            ->where('status', AffiliateCorporateUpgrade::STATUS_ACTIVE)
            ->whereNotNull('upgrade_id')
            ->selectRaw('upgrade_id, MAX(name) as name, COUNT(*) as total')
            ->groupBy('upgrade_id')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (AffiliateCorporateUpgrade $row): array => [
                (int) $row->upgrade_id => ['name' => (string) $row->getAttribute('name'), 'total' => (int) $row->getAttribute('total')],
            ])
            ->all();
    }

    /**
     * @param  list<int>  $affiliateIds
     * @param  array<int, mixed>  $catalogUpgradeIds  ids de `upgrades`
     * @return list<int> ids de `affiliate_corporate_upgrades`
     */
    public static function activeAssignmentIdsForUpgrades(AffiliationCorporate $owner, array $affiliateIds, array $catalogUpgradeIds): array
    {
        $catalogUpgradeIds = array_values(array_filter(array_map('intval', $catalogUpgradeIds), fn (int $id): bool => $id > 0));

        if ($affiliateIds === [] || $catalogUpgradeIds === []) {
            return [];
        }

        return AffiliateCorporateUpgrade::query()
            ->where('affiliation_corporate_id', $owner->getKey())
            ->whereIn('affiliate_corporate_id', $affiliateIds)
            ->where('status', AffiliateCorporateUpgrade::STATUS_ACTIVE)
            ->whereIn('upgrade_id', $catalogUpgradeIds)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * Agrega uno o varios upgrades del catálogo a uno o varios afiliados. Cada
     * afiliado paga el precio del rango de edad en el que cae.
     *
     * Cada afiliado que recibe el upgrade necesita su Voucher ILS (`$vouchers`,
     * afiliado_id => número, desde, hasta y comprobante). Si alguno falta o no es
     * válido, no se escribe nada.
     *
     * @param  iterable<int, AffiliateCorporate|int>  $affiliates
     * @param  array<int, mixed>  $coverageIds  ids de `upgrade_coverages`
     * @param  array<int|string, mixed>  $vouchers
     * @return array{affiliates_updated: int, upgrades_created: int, annual_delta: float, collections_adjusted: int, skipped: list<array{name: string, reason: string, upgrade?: string}>}
     */
    public static function add(AffiliationCorporate $owner, iterable $affiliates, array $coverageIds, ?string $actor = null, array $vouchers = []): array
    {
        $affiliateIds = self::affiliateIds($affiliates);

        if ($affiliateIds === []) {
            throw new InvalidArgumentException('Seleccione al menos un afiliado.');
        }

        $coverages = UpgradePricing::resolveCoverages($coverageIds);
        $actor = self::actorName($actor);
        $labels = AffiliateCorporate::query()->whereIn('id', $affiliateIds)->get()
            ->mapWithKeys(fn (AffiliateCorporate $affiliate): array => [(int) $affiliate->getKey() => CorporateAffiliatePlanSynchronizer::labelFor($affiliate)])
            ->all();
        $vouchers = UpgradeIlsVoucher::validateMany($vouchers, $labels);

        $result = DB::transaction(function () use ($owner, $affiliateIds, $coverages, $actor, $vouchers): array {
            $ownerLocked = self::lockOwner($owner);
            $locked = self::lockAffiliates($ownerLocked, $affiliateIds);
            $skipped = self::skippedForeignAffiliates($affiliateIds, $locked);

            $activeUpgradeIds = AffiliateCorporateUpgrade::query()
                ->whereIn('affiliate_corporate_id', $locked->modelKeys())
                ->where('status', AffiliateCorporateUpgrade::STATUS_ACTIVE)
                ->get(['affiliate_corporate_id', 'upgrade_id'])
                ->groupBy('affiliate_corporate_id')
                ->map(fn ($rows): array => $rows->pluck('upgrade_id')->filter()->map(fn (mixed $id): int => (int) $id)->all());

            $affiliatesUpdated = 0;
            $upgradesCreated = 0;
            $ownerDelta = 0.0;
            $created = [];

            foreach ($locked as $affiliate) {
                $name = CorporateAffiliatePlanSynchronizer::labelFor($affiliate);

                if (in_array((string) $affiliate->status, self::LOCKED_AFFILIATE_STATUSES, true)) {
                    $skipped[] = ['name' => $name, 'reason' => self::SKIP_AFFILIATE_INACTIVE];

                    continue;
                }

                $age = UpgradePricing::ageOf($affiliate);

                if ($age === null) {
                    $skipped[] = ['name' => $name, 'reason' => self::SKIP_NO_AGE];

                    continue;
                }

                $delta = 0.0;
                $already = $activeUpgradeIds->get($affiliate->getKey(), []);

                foreach ($coverages as $coverage) {
                    $upgradeName = (string) $coverage->upgrade->name;

                    if (in_array((int) $coverage->upgrade_id, $already, true)) {
                        $skipped[] = ['name' => $name, 'reason' => self::SKIP_ALREADY_ACTIVE, 'upgrade' => $upgradeName];

                        continue;
                    }

                    $range = UpgradePricing::rangeFor($coverage, $age);

                    if ($range === null) {
                        $skipped[] = ['name' => $name.' ('.$age.' años)', 'reason' => self::SKIP_NO_RANGE, 'upgrade' => $upgradeName];

                        continue;
                    }

                    $voucher = $vouchers[(int) $affiliate->getKey()] ?? null;

                    if ($voucher === null) {
                        throw new InvalidArgumentException('Falta el Voucher ILS de '.$name.': cada afiliado que recibe el upgrade necesita el suyo.');
                    }

                    $upgrade = AffiliateCorporateUpgrade::query()->create([
                        'affiliate_corporate_id' => $affiliate->getKey(),
                        'affiliation_corporate_id' => $ownerLocked->getKey(),
                        ...UpgradePricing::assignmentAttributes($coverage, $range, $age, $actor),
                        ...UpgradeIlsVoucher::columns($voucher),
                    ]);

                    $created[] = $upgrade->getKey();
                    $delta += (float) $upgrade->amount;
                    $upgradesCreated++;
                }

                if ($delta <= 0.0) {
                    continue;
                }

                self::applyAffiliateDelta($ownerLocked, $affiliate, $delta);
                $affiliatesUpdated++;

                if (self::countsAsPopulation($affiliate)) {
                    $ownerDelta += $delta;
                }
            }

            $collectionIds = self::applyOwnerDelta($ownerLocked, $ownerDelta);

            SecurityAudit::log('AUDIT_BUSINESS_CORPORATE_AFFILIATE_UPGRADES_ADDED', 'business.affiliation-corporates.upgrades.add', [
                'panel' => 'business',
                'module' => 'affiliation_corporates',
                'affiliation_corporate_id' => $ownerLocked->getKey(),
                'affiliation_code' => $ownerLocked->code,
                'upgrade_coverage_ids' => $coverages->modelKeys(),
                'affiliate_ids' => $locked->modelKeys(),
                'upgrade_ids' => $created,
                'annual_delta' => round($ownerDelta, 2),
                'collections_adjusted' => $collectionIds,
                'skipped' => $skipped,
            ]);

            return [
                'affiliates_updated' => $affiliatesUpdated,
                'upgrades_created' => $upgradesCreated,
                'annual_delta' => round($ownerDelta, 2),
                'collection_ids' => $collectionIds,
                'skipped' => $skipped,
            ];
        });

        CorporateCollectionNoticeDispatcher::dispatch($owner, $result['collection_ids']);

        return self::publicResult($result, 'upgrades_created');
    }

    /**
     * Da de baja upgrades activos: pasan a INACTIVO y su monto se descuenta.
     *
     * @param  list<int>  $upgradeIds
     * @return array{affiliates_updated: int, upgrades_removed: int, annual_delta: float, collections_adjusted: int, skipped: list<array{name: string, reason: string, upgrade?: string}>}
     */
    public static function deactivate(AffiliationCorporate $owner, array $upgradeIds, ?string $actor = null): array
    {
        $upgradeIds = array_values(array_unique(array_filter(array_map('intval', $upgradeIds), fn (int $id): bool => $id > 0)));

        if ($upgradeIds === []) {
            throw new InvalidArgumentException('Seleccione al menos un upgrade para dar de baja.');
        }

        $actor = self::actorName($actor);

        $result = DB::transaction(function () use ($owner, $upgradeIds, $actor): array {
            $ownerLocked = self::lockOwner($owner);

            $upgrades = AffiliateCorporateUpgrade::query()
                ->whereIn('id', $upgradeIds)
                ->where('affiliation_corporate_id', $ownerLocked->getKey())
                ->where('status', AffiliateCorporateUpgrade::STATUS_ACTIVE)
                ->lockForUpdate()
                ->get();

            if ($upgrades->isEmpty()) {
                throw new RuntimeException('Los upgrades seleccionados ya no están activos. Actualice la tabla e intente de nuevo.');
            }

            $locked = self::lockAffiliates($ownerLocked, $upgrades->pluck('affiliate_corporate_id')->unique()->values()->all());
            $byAffiliate = $upgrades->groupBy('affiliate_corporate_id');

            $affiliatesUpdated = 0;
            $ownerDelta = 0.0;

            foreach ($locked as $affiliate) {
                $rows = $byAffiliate->get($affiliate->getKey());

                if ($rows === null) {
                    continue;
                }

                $delta = -round((float) $rows->sum('amount'), 2);

                if ((float) $affiliate->fee + $delta < -0.004) {
                    throw new RuntimeException(
                        'La tarifa de '.CorporateAffiliatePlanSynchronizer::labelFor($affiliate)
                        .' quedaría negativa al quitar el upgrade. Revise su tarifa anual antes de continuar.'
                    );
                }

                AffiliateCorporateUpgrade::query()
                    ->whereIn('id', $rows->modelKeys())
                    ->update([
                        'status' => AffiliateCorporateUpgrade::STATUS_INACTIVE,
                        'deactivated_at' => now(),
                        'deactivated_by' => $actor,
                        'deactivation_reason' => self::REMOVAL_REASON,
                        'updated_by' => $actor,
                        'updated_at' => now(),
                    ]);

                self::applyAffiliateDelta($ownerLocked, $affiliate, $delta);
                $affiliatesUpdated++;

                if (self::countsAsPopulation($affiliate)) {
                    $ownerDelta += $delta;
                }
            }

            $collectionIds = self::applyOwnerDelta($ownerLocked, $ownerDelta);

            SecurityAudit::log('AUDIT_BUSINESS_CORPORATE_AFFILIATE_UPGRADES_REMOVED', 'business.affiliation-corporates.upgrades.remove', [
                'panel' => 'business',
                'module' => 'affiliation_corporates',
                'affiliation_corporate_id' => $ownerLocked->getKey(),
                'affiliation_code' => $ownerLocked->code,
                'upgrades' => $upgrades->map(fn (AffiliateCorporateUpgrade $upgrade): array => [
                    'id' => $upgrade->getKey(),
                    'affiliate_corporate_id' => $upgrade->affiliate_corporate_id,
                    'name' => $upgrade->name,
                    'amount' => (float) $upgrade->amount,
                ])->values()->all(),
                'annual_delta' => round($ownerDelta, 2),
                'collections_adjusted' => $collectionIds,
            ]);

            return [
                'affiliates_updated' => $affiliatesUpdated,
                'upgrades_removed' => $upgrades->count(),
                'annual_delta' => round($ownerDelta, 2),
                'collection_ids' => $collectionIds,
                'skipped' => [],
            ];
        });

        CorporateCollectionNoticeDispatcher::dispatch($owner, $result['collection_ids']);

        return self::publicResult($result, 'upgrades_removed');
    }

    /**
     * Qué pasaría al agregar el upgrade, sin escribir nada: precio por la edad de
     * cada afiliado (o por qué no lo recibe), tarifa antes y después, totales de
     * la afiliación y avisos de cobro pendientes que se ajustarían.
     *
     * @param  list<int>  $affiliateIds
     * @return array<string, mixed> ver `UpgradeAssignmentPreview`
     */
    public static function preview(AffiliationCorporate $owner, array $affiliateIds, mixed $coverageId): array
    {
        $owner = AffiliationCorporate::query()->findOrFail($owner->getKey());
        $frequency = (string) ($owner->payment_frequency ?: 'ANUAL');

        try {
            $coverage = UpgradePricing::resolveCoverages([$coverageId])->first();
        } catch (InvalidArgumentException $exception) {
            return UpgradeAssignmentPreview::empty($exception->getMessage());
        }

        $affiliates = AffiliateCorporate::query()
            ->whereIn('id', array_values(array_map('intval', $affiliateIds)))
            ->where('affiliation_corporate_id', $owner->getKey())
            ->orderBy('id')
            ->get();
        $alreadyActive = AffiliateCorporateUpgrade::query()
            ->whereIn('affiliate_corporate_id', $affiliates->modelKeys())
            ->where('status', AffiliateCorporateUpgrade::STATUS_ACTIVE)
            ->where('upgrade_id', $coverage->upgrade_id)
            ->pluck('affiliate_corporate_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
        $rows = [];
        $ownerDelta = 0.0;
        $outsidePopulation = 0;

        foreach ($affiliates as $affiliate) {
            $age = UpgradePricing::ageOf($affiliate);
            $range = $age !== null ? UpgradePricing::rangeFor($coverage, $age) : null;
            $affiliateFrequency = filled($affiliate->payment_frequency) ? (string) $affiliate->payment_frequency : $frequency;
            $reason = match (true) {
                in_array((string) $affiliate->status, self::LOCKED_AFFILIATE_STATUSES, true) => 'está dado de baja',
                in_array((int) $affiliate->getKey(), $alreadyActive, true) => 'ya tiene este upgrade activo',
                $age === null => 'no tiene fecha de nacimiento ni edad registrada',
                $range === null => 'tiene '.$age.' años y el upgrade cubre '.UpgradePricing::coversLabel($coverage),
                default => null,
            };
            $price = $reason === null ? round((float) $range->price, 2) : 0.0;
            $feeBefore = round((float) $affiliate->fee, 2);
            $feeAfter = round($feeBefore + $price, 2);

            if ($price > 0) {
                if (self::countsAsPopulation($affiliate)) {
                    $ownerDelta += $price;
                } else {
                    $outsidePopulation++;
                }
            }

            $rows[] = [
                'id' => (int) $affiliate->getKey(),
                'name' => CorporateAffiliatePlanSynchronizer::labelFor($affiliate),
                'age' => $age,
                'receives' => $reason === null,
                'reason' => $reason,
                'price' => $price,
                'range' => $range?->label(),
                'frequency' => $affiliateFrequency,
                'fee_before' => $feeBefore,
                'fee_after' => $feeAfter,
                'period_before' => round(CorporateAffiliateRemovalService::annualFeeToPerPeriodAmount($feeBefore, $affiliateFrequency), 2),
                'period_after' => round(CorporateAffiliateRemovalService::annualFeeToPerPeriodAmount($feeAfter, $affiliateFrequency), 2),
            ];
        }

        $annualBefore = round((float) $owner->fee_anual, 2);
        $annualAfter = round($annualBefore + $ownerDelta, 2);
        $collections = [];

        if (filled($owner->code) && $ownerDelta > 0) {
            foreach (BillingCollection::query()->where('affiliation_code', $owner->code)->where('status', self::PENDING_COLLECTION_STATUS)->orderBy('id')->get() as $collection) {
                $collectionFrequency = filled($collection->payment_frequency) ? (string) $collection->payment_frequency : $frequency;
                $before = round((float) $collection->total_amount, 2);
                $collections[] = [
                    'invoice' => (string) ($collection->collection_invoice_number ?: '#'.$collection->getKey()),
                    'date' => (string) ($collection->next_payment_date ?? ''),
                    'before' => $before,
                    'after' => round($before + CorporateAffiliateRemovalService::annualFeeToPerPeriodAmount($ownerDelta, $collectionFrequency), 2),
                ];
            }
        }

        return [
            'error' => null,
            'blockers' => [],
            'upgrade' => [
                'name' => (string) $coverage->upgrade->name,
                'coverage' => $coverage->label(),
                'covers' => UpgradePricing::coversLabel($coverage),
            ],
            'rows' => $rows,
            'owner' => [
                'label' => 'Afiliación '.$owner->code,
                'frequency' => $frequency,
                'annual_before' => $annualBefore,
                'annual_after' => $annualAfter,
                'period_before' => round(CorporateAffiliateRemovalService::annualFeeToPerPeriodAmount($annualBefore, $frequency), 2),
                'period_after' => round(CorporateAffiliateRemovalService::annualFeeToPerPeriodAmount($annualAfter, $frequency), 2),
                'note' => $outsidePopulation > 0
                    ? $outsidePopulation.' '.($outsidePopulation === 1 ? 'afiliado aún no cuenta' : 'afiliados aún no cuentan').' como población (pre-afiliado): su upgrade se suma a su tarifa, no al total de la afiliación.'
                    : null,
            ],
            'collections' => $collections,
            'annual_delta' => round($ownerDelta, 2),
        ];
    }

    public static function skipReasonLabel(string $reason): string
    {
        return UpgradePricing::skipReasonLabel($reason);
    }

    /**
     * Líneas de upgrades para el aviso de cobro, agrupadas por nombre.
     *
     * @return list<array{name: string, affiliates: int, annual_amount: float, amount: float}>
     */
    public static function noticeLinesFor(AffiliationCorporate $owner, ?string $paymentFrequency = null): array
    {
        $frequency = $paymentFrequency ?? (string) $owner->payment_frequency;

        return AffiliateCorporateUpgrade::query()
            ->where('affiliate_corporate_upgrades.affiliation_corporate_id', $owner->getKey())
            ->where('affiliate_corporate_upgrades.status', AffiliateCorporateUpgrade::STATUS_ACTIVE)
            ->whereHas('affiliateCorporate', fn ($query) => $query->whereIn('status', CorporateAffiliatePlanSynchronizer::COUNTABLE_STATUSES))
            ->selectRaw('name, COUNT(*) as affiliates, SUM(amount) as annual_amount')
            ->groupBy('name')
            ->orderBy('name')
            ->get()
            ->map(fn (AffiliateCorporateUpgrade $row): array => [
                'name' => (string) $row->name,
                'affiliates' => (int) $row->getAttribute('affiliates'),
                'annual_amount' => round((float) $row->getAttribute('annual_amount'), 2),
                'amount' => round(CorporateAffiliateRemovalService::annualFeeToPerPeriodAmount(
                    (float) $row->getAttribute('annual_amount'),
                    $frequency,
                ), 2),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  iterable<int, AffiliateCorporate|int>  $affiliates
     * @return list<int>
     */
    private static function affiliateIds(iterable $affiliates): array
    {
        $ids = [];

        foreach ($affiliates as $affiliate) {
            $id = $affiliate instanceof AffiliateCorporate ? (int) $affiliate->getKey() : (int) $affiliate;

            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    private static function lockOwner(AffiliationCorporate $owner): AffiliationCorporate
    {
        /** @var AffiliationCorporate */
        return AffiliationCorporate::query()
            ->whereKey($owner->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * @param  list<int>  $affiliateIds
     * @return \Illuminate\Database\Eloquent\Collection<int, AffiliateCorporate>
     */
    private static function lockAffiliates(AffiliationCorporate $owner, array $affiliateIds): \Illuminate\Database\Eloquent\Collection
    {
        return AffiliateCorporate::query()
            ->whereIn('id', $affiliateIds)
            ->where('affiliation_corporate_id', $owner->getKey())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * @param  list<int>  $requestedIds
     * @param  \Illuminate\Database\Eloquent\Collection<int, AffiliateCorporate>  $locked
     * @return list<array{name: string, reason: string}>
     */
    private static function skippedForeignAffiliates(array $requestedIds, \Illuminate\Database\Eloquent\Collection $locked): array
    {
        return collect(array_diff($requestedIds, $locked->modelKeys()))
            ->map(fn (int $id): array => ['name' => 'Afiliado #'.$id, 'reason' => self::SKIP_NOT_IN_AFFILIATION])
            ->values()
            ->all();
    }

    private static function applyAffiliateDelta(AffiliationCorporate $owner, AffiliateCorporate $affiliate, float $delta): void
    {
        $fee = round((float) $affiliate->fee + $delta, 2);

        if ($fee > self::MAX_STORED_AMOUNT) {
            throw new RuntimeException(
                'La tarifa anual de '.CorporateAffiliatePlanSynchronizer::labelFor($affiliate)
                .' superaría el máximo permitido ('.number_format(self::MAX_STORED_AMOUNT, 2, ',', '.').' US$).'
            );
        }

        $fee = max(0.0, $fee);
        $frequency = filled($affiliate->payment_frequency)
            ? (string) $affiliate->payment_frequency
            : (string) $owner->payment_frequency;

        $affiliate->update([
            'fee' => $fee,
            'subtotal_anual' => $fee,
            'subtotal_payment_frequency' => round(CorporateAffiliateRemovalService::annualFeeToPerPeriodAmount($fee, $frequency), 2),
            'subtotal_daily' => round($fee / 30, 2),
        ]);
    }

    private static function countsAsPopulation(AffiliateCorporate $affiliate): bool
    {
        return in_array((string) $affiliate->status, CorporateAffiliatePlanSynchronizer::COUNTABLE_STATUSES, true);
    }

    /**
     * Suma el delta anual a la afiliación y a sus avisos de cobro pendientes.
     *
     * @return list<int> IDs de cobros ajustados
     */
    private static function applyOwnerDelta(AffiliationCorporate $owner, float $annualDelta): array
    {
        $annualDelta = round($annualDelta, 2);

        if (abs($annualDelta) < 0.01) {
            return [];
        }

        $feeAnual = max(0.0, round((float) $owner->fee_anual + $annualDelta, 2));
        $totalAmount = round(CorporateAffiliateRemovalService::annualFeeToPerPeriodAmount($feeAnual, $owner->payment_frequency), 2);

        if ($totalAmount > self::MAX_STORED_AMOUNT) {
            throw new RuntimeException('El total de la afiliación superaría el máximo permitido. No se aplicó ningún cambio.');
        }

        $owner->fee_anual = $feeAnual;
        $owner->total_amount = $totalAmount;
        $owner->save();

        if (blank($owner->code)) {
            return [];
        }

        $collections = BillingCollection::query()
            ->where('affiliation_code', $owner->code)
            ->where('status', self::PENDING_COLLECTION_STATUS)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($collections as $collection) {
            $frequency = filled($collection->payment_frequency)
                ? (string) $collection->payment_frequency
                : (string) $owner->payment_frequency;

            $periodDelta = CorporateAffiliateRemovalService::annualFeeToPerPeriodAmount($annualDelta, $frequency);
            $collection->total_amount = max(0.0, round((float) $collection->total_amount + $periodDelta, 2));
            $collection->save();
        }

        return array_map('intval', $collections->modelKeys());
    }

    /**
     * @param  array{affiliates_updated: int, annual_delta: float, collection_ids: list<int>, skipped: list<array<string, string>>, upgrades_created?: int, upgrades_removed?: int}  $result
     * @return array<string, mixed>
     */
    private static function publicResult(array $result, string $countKey): array
    {
        return [
            'affiliates_updated' => $result['affiliates_updated'],
            $countKey => $result[$countKey],
            'annual_delta' => $result['annual_delta'],
            'collections_adjusted' => count($result['collection_ids']),
            'skipped' => $result['skipped'],
        ];
    }

    private static function actorName(?string $actor): string
    {
        $actor = trim((string) ($actor ?? Auth::user()?->name ?? ''));

        return $actor !== '' ? $actor : 'system';
    }
}
