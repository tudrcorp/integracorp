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
use App\Support\Upgrades\UpgradeFirstPaymentBilling;
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
 * la tarifa del afiliado y los totales de la afiliación (solo si el afiliado
 * cuenta como población). Así el monto que se agrega es exactamente el que se
 * refleja, sin recalcular desde cero lo que ya estaba cuadrado.
 *
 * Los upgrades se cobran una sola vez (`UpgradeFirstPaymentBilling`): si la empresa
 * aún no hizo su primer pago van en él; si ya está en curso, completos en el próximo
 * aviso por pagar. Las asignaciones de antes, repartidas en las cuotas, se quitan
 * como antes.
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

    public const NO_PENDING_COLLECTION_MESSAGE = 'No hay un aviso de cobro por pagar donde sumar el upgrade: los upgrades se cobran completos en el próximo aviso. Genere el aviso pendiente en Administración (o espere la renovación) y vuelva a intentarlo.';

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
            $chargedIds = [];

            foreach ($locked as $affiliate) {
                $name = CorporateAffiliatePlanSynchronizer::labelFor($affiliate);
                $affiliateCreated = [];

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
                        ...UpgradeFirstPaymentBilling::assignmentAttributes('affiliate_corporate_upgrades'),
                    ]);

                    $created[] = $upgrade->getKey();
                    $affiliateCreated[] = (int) $upgrade->getKey();
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
                    $chargedIds = [...$chargedIds, ...$affiliateCreated];
                }
            }

            $collectionIds = UpgradeFirstPaymentBilling::hasColumn('affiliate_corporate_upgrades', 'billing_mode')
                ? self::chargeOnNextCollection($ownerLocked, $ownerDelta, $chargedIds)
                : self::applyOwnerDelta($ownerLocked, $ownerDelta);

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
            /** Repartidos en las cuotas (antes del cobro único): se descuentan de todas, como siempre. */
            $spreadDelta = 0.0;
            $billedOnce = [];

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

                    foreach ($rows as $row) {
                        if ($row->billing_mode === UpgradeFirstPaymentBilling::MODE) {
                            $billedOnce[] = $row;
                        } else {
                            $spreadDelta -= round((float) $row->amount, 2);
                        }
                    }
                }
            }

            $collectionIds = array_values(array_unique([
                ...self::applyOwnerDelta($ownerLocked, $ownerDelta, $spreadDelta),
                ...self::releaseChargedCollections($ownerLocked, $billedOnce),
            ]));

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
                'period_after' => UpgradeFirstPaymentBilling::hasColumn('affiliate_corporate_upgrades', 'billing_mode')
                    ? round(CorporateAffiliateRemovalService::annualFeeToPerPeriodAmount($feeBefore, $affiliateFrequency), 2)
                    : round(CorporateAffiliateRemovalService::annualFeeToPerPeriodAmount($feeAfter, $affiliateFrequency), 2),
            ];
        }

        $annualBefore = round((float) $owner->fee_anual, 2);
        $annualAfter = round($annualBefore + $ownerDelta, 2);
        $collections = [];
        $blockers = [];
        $billingNote = null;
        $billedOnce = UpgradeFirstPaymentBilling::hasColumn('affiliate_corporate_upgrades', 'billing_mode');

        if ($billedOnce && $ownerDelta > 0) {
            [$collections, $billingNote, $blocker] = self::nextCollectionPreview($owner, $ownerDelta);

            if ($blocker !== null) {
                $blockers[] = $blocker;
            }
        }

        if (! $billedOnce && filled($owner->code) && $ownerDelta > 0) {
            foreach (BillingCollection::query()->calendarInstallments()->where('affiliation_code', $owner->code)->where('status', self::PENDING_COLLECTION_STATUS)->orderBy('id')->get() as $collection) {
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
            'blockers' => $blockers,
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
                'period_before' => round((float) $owner->total_amount, 2),
                'period_after' => $billedOnce
                    ? round((float) $owner->total_amount, 2)
                    : round(CorporateAffiliateRemovalService::annualFeeToPerPeriodAmount($annualAfter, $frequency), 2),
                'note' => trim(implode(' ', array_filter([
                    $billingNote,
                    $outsidePopulation > 0
                        ? $outsidePopulation.' '.($outsidePopulation === 1 ? 'afiliado aún no cuenta' : 'afiliados aún no cuentan').' como población (pre-afiliado): su upgrade se suma a su tarifa, no al total de la afiliación.'
                        : null,
                ]))) ?: null,
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
     * Líneas de upgrades para el aviso de cobro, agrupadas por nombre. Los repartidos en
     * cuotas (de antes) llevan su parte del período; los de cobro único solo salen, completos,
     * en el aviso donde se cobraron (`$collectionId`).
     *
     * @return list<array{name: string, affiliates: int, annual_amount: float, amount: float}>
     */
    public static function noticeLinesFor(AffiliationCorporate $owner, ?string $paymentFrequency = null, ?int $collectionId = null): array
    {
        $frequency = $paymentFrequency ?? (string) $owner->payment_frequency;
        $billingColumns = UpgradeFirstPaymentBilling::hasColumn('affiliate_corporate_upgrades', 'charged_collection_id')
            ? ['billing_mode', 'charged_collection_id']
            : [];
        $lines = [];

        $rows = AffiliateCorporateUpgrade::query()
            ->where('affiliate_corporate_upgrades.affiliation_corporate_id', $owner->getKey())
            ->where('affiliate_corporate_upgrades.status', AffiliateCorporateUpgrade::STATUS_ACTIVE)
            ->whereHas('affiliateCorporate', fn ($query) => $query->whereIn('status', CorporateAffiliatePlanSynchronizer::COUNTABLE_STATUSES))
            ->orderBy('name')
            ->get(['name', 'amount', ...$billingColumns]);

        foreach ($rows as $row) {
            $annual = round((float) $row->amount, 2);
            $billedOnce = $billingColumns !== [] && $row->billing_mode === UpgradeFirstPaymentBilling::MODE;

            if ($billedOnce && ($collectionId === null || (int) $row->charged_collection_id !== $collectionId)) {
                continue;
            }

            $name = (string) $row->name;
            $lines[$name] ??= ['name' => $name, 'affiliates' => 0, 'annual_amount' => 0.0, 'amount' => 0.0];
            $lines[$name]['affiliates']++;
            $lines[$name]['annual_amount'] = round($lines[$name]['annual_amount'] + $annual, 2);
            $lines[$name]['amount'] = round($lines[$name]['amount'] + ($billedOnce
                ? $annual
                : CorporateAffiliateRemovalService::annualFeeToPerPeriodAmount($annual, $frequency)), 2);
        }

        return array_values($lines);
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
            'subtotal_payment_frequency' => UpgradeFirstPaymentBilling::regularInstallment($fee, UpgradeFirstPaymentBilling::affiliateOneTimeTotal($affiliate), $frequency),
            'subtotal_daily' => round($fee / 30, 2),
        ]);
    }

    private static function countsAsPopulation(AffiliateCorporate $affiliate): bool
    {
        return in_array((string) $affiliate->status, CorporateAffiliatePlanSynchronizer::COUNTABLE_STATUSES, true);
    }

    /**
     * Suma el delta anual a la afiliación. La cuota regular deja fuera los upgrades de cobro
     * único. `$spreadDelta` (por defecto, todo el delta) es la parte repartida en las cuotas:
     * se suma a cada aviso pendiente según su frecuencia.
     *
     * @return list<int> IDs de cobros ajustados
     */
    private static function applyOwnerDelta(AffiliationCorporate $owner, float $annualDelta, ?float $spreadDelta = null): array
    {
        $annualDelta = round($annualDelta, 2);

        if (abs($annualDelta) < 0.01) {
            return [];
        }

        $feeAnual = max(0.0, round((float) $owner->fee_anual + $annualDelta, 2));
        $totalAmount = UpgradeFirstPaymentBilling::regularInstallment($feeAnual, UpgradeFirstPaymentBilling::corporateOneTimeTotal($owner), (string) $owner->payment_frequency);

        if ($totalAmount > self::MAX_STORED_AMOUNT) {
            throw new RuntimeException('El total de la afiliación superaría el máximo permitido. No se aplicó ningún cambio.');
        }

        $owner->fee_anual = $feeAnual;
        $owner->total_amount = $totalAmount;
        $owner->save();

        $annualDelta = round($spreadDelta ?? $annualDelta, 2);

        if (blank($owner->code) || abs($annualDelta) < 0.01) {
            return [];
        }

        $collections = BillingCollection::query()
            ->calendarInstallments()
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
     * Upgrades nuevos (cobro único): suben el anual sin tocar la cuota regular. Si el primer
     * pago aún no se hizo, van en él; si no, completos en el próximo aviso por pagar, que queda
     * anotado en cada asignación para poder descontarlo si se quita.
     *
     * @param  list<int>  $upgradeIds  asignaciones recién creadas de afiliados que cuentan como población
     * @return list<int> IDs de cobros ajustados
     */
    private static function chargeOnNextCollection(AffiliationCorporate $owner, float $annualDelta, array $upgradeIds): array
    {
        self::applyOwnerDelta($owner, $annualDelta, 0.0);
        $annualDelta = round($annualDelta, 2);

        if ($annualDelta < 0.01 || UpgradeFirstPaymentBilling::awaitsFirstPayment($owner)) {
            return [];
        }

        $collection = UpgradeFirstPaymentBilling::nextPendingCollection((string) $owner->code, self::PENDING_COLLECTION_STATUS);

        if ($collection === null) {
            throw new RuntimeException(self::NO_PENDING_COLLECTION_MESSAGE);
        }

        self::assertNoPartialPayment($collection);

        $collection->total_amount = round((float) $collection->total_amount + $annualDelta, 2);
        $collection->save();

        if ($upgradeIds !== [] && UpgradeFirstPaymentBilling::hasColumn('affiliate_corporate_upgrades', 'charged_collection_id')) {
            AffiliateCorporateUpgrade::query()->whereIn('id', $upgradeIds)->update(['charged_collection_id' => $collection->getKey()]);
        }

        return [(int) $collection->getKey()];
    }

    /**
     * Quita del aviso donde se cobraron los upgrades de cobro único que se dan de baja, si ese
     * aviso sigue por pagar. Si ya se pagó (o fueron en el primer pago) no se toca nada.
     *
     * @param  list<AffiliateCorporateUpgrade>  $upgrades
     * @return list<int> IDs de cobros ajustados
     */
    private static function releaseChargedCollections(AffiliationCorporate $owner, array $upgrades): array
    {
        $byCollection = [];

        foreach ($upgrades as $upgrade) {
            $collectionId = (int) ($upgrade->charged_collection_id ?? 0);

            if ($collectionId > 0) {
                $byCollection[$collectionId] = ($byCollection[$collectionId] ?? 0.0) + round((float) $upgrade->amount, 2);
            }
        }

        $adjusted = [];

        foreach ($byCollection as $collectionId => $amount) {
            $collection = BillingCollection::query()
                ->whereKey($collectionId)
                ->where('affiliation_code', $owner->code)
                ->where('status', self::PENDING_COLLECTION_STATUS)
                ->lockForUpdate()
                ->first();

            if ($collection === null) {
                continue;
            }

            self::assertNoPartialPayment($collection);

            $collection->total_amount = max(0.0, round((float) $collection->total_amount - $amount, 2));
            $collection->save();
            $adjusted[] = (int) $collection->getKey();
        }

        return $adjusted;
    }

    /**
     * Aviso que recibiría el upgrade (vista previa), nota para el analista y bloqueo si no hay
     * dónde cobrarlo.
     *
     * @return array{0: list<array{invoice: string, date: string, before: float, after: float}>, 1: string, 2: ?string}
     */
    private static function nextCollectionPreview(AffiliationCorporate $owner, float $annualDelta): array
    {
        $note = 'Los upgrades se pagan una sola vez al año, completos: no cambian la cuota regular.';

        if (UpgradeFirstPaymentBilling::awaitsFirstPayment($owner)) {
            return [[], $note.' Este se cobra en el primer pago ('.UpgradePricing::money($annualDelta).' más).', null];
        }

        $collection = BillingCollection::query()
            ->calendarInstallments()
            ->where('affiliation_code', (string) $owner->code)
            ->where('status', self::PENDING_COLLECTION_STATUS)
            ->orderByRaw('filter_next_payment_date IS NULL')
            ->orderBy('filter_next_payment_date')
            ->orderBy('id')
            ->first();

        if ($collection === null) {
            return [[], $note, self::NO_PENDING_COLLECTION_MESSAGE];
        }

        $invoice = (string) ($collection->collection_invoice_number ?: '#'.$collection->getKey());
        $blocker = (float) ($collection->pay_amount_usd ?? 0) > 0 || (float) ($collection->pay_amount_ves ?? 0) > 0
            ? 'El aviso de cobro '.$invoice.' tiene un abono registrado. Concilie ese pago en Administración antes de cambiar upgrades.'
            : null;
        $before = round((float) $collection->total_amount, 2);

        return [[[
            'invoice' => $invoice,
            'date' => (string) ($collection->next_payment_date ?? ''),
            'before' => $before,
            'after' => round($before + $annualDelta, 2),
        ]], $note.' Se suma completo al próximo aviso por pagar.', $blocker];
    }

    private static function assertNoPartialPayment(BillingCollection $collection): void
    {
        if ((float) ($collection->pay_amount_usd ?? 0) > 0 || (float) ($collection->pay_amount_ves ?? 0) > 0) {
            $invoice = (string) ($collection->collection_invoice_number ?: '#'.$collection->getKey());

            throw new RuntimeException('El aviso de cobro '.$invoice.' tiene un abono registrado. Concilie ese pago en Administración antes de cambiar upgrades.');
        }
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
