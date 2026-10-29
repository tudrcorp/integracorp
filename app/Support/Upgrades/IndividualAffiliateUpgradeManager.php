<?php

declare(strict_types=1);

namespace App\Support\Upgrades;

use App\Models\Affiliate;
use App\Models\AffiliateUpgrade;
use App\Models\Affiliation;
use App\Models\Collection as BillingCollection;
use App\Models\CollectionObservation;
use App\Models\PaidMembership;
use App\Models\Renovation;
use App\Support\AffiliationAffiliateFeeCalculator;
use App\Support\AffiliationCorporates\CorporatePaymentFrequency;
use App\Support\AffiliationCorporates\CorporatePaymentFrequencyChanger;
use App\Support\SecurityAudit;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Upgrades de afiliados individuales: el precio sale del catálogo según la
 * cobertura elegida y la edad de cada afiliado, y queda congelado en la asignación.
 *
 * Cada alta o baja se aplica como un delta, en una sola transacción, sobre la
 * tarifa anual del afiliado y de la afiliación. Los upgrades se cobran una sola vez
 * (`UpgradeFirstPaymentBilling`): si la afiliación aún no hizo su primer pago van en
 * él; si ya está en curso, completos en el próximo aviso POR PAGAR (con su
 * observación en la cobranza). Las asignaciones de antes, repartidas en las cuotas,
 * se quitan como antes. Lo pagado no se toca.
 *
 * Se bloquea, como el cambio de plan, si hay comprobantes por aprobar (al
 * aprobarse generarían cuotas con el monto viejo), si está en período de
 * renovación (la renovación ya recotiza) o si una cuota tiene un abono a medias.
 */
final class IndividualAffiliateUpgradeManager
{
    public const PENDING_COLLECTION_STATUS = 'POR PAGAR';

    public const PENDING_PROOF_STATUS = 'PENDIENTE';

    public const RENEWAL_PERIOD_STATUS = 'PERIODO DE RENOVACION';

    public const REMOVAL_REASON = 'QUITADO POR ANALISTA';

    public const NO_PENDING_COLLECTION_MESSAGE = 'No hay un aviso de cobro por pagar donde sumar el upgrade: los upgrades se cobran completos en el próximo aviso. Genere el aviso pendiente en Administración (o espere la renovación) y vuelva a intentarlo.';

    /** @var list<string> */
    public const ALLOWED_AFFILIATION_STATUSES = ['ACTIVA', 'PRE-APROBADA'];

    /**
     * Tope de `affiliates.fee` y `affiliations.fee_anual` (decimal 8,2).
     */
    public const MAX_STORED_AMOUNT = 999999.99;

    /**
     * Suma de upgrades activos del afiliado. Usa el agregado precargado
     * (`withActiveUpgradesTotal`) o la relación cargada antes de consultar.
     */
    public static function activeTotalFor(Affiliate $affiliate): float
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
            ->where('status', AffiliateUpgrade::STATUS_ACTIVE)
            ->sum('amount'), 2);
    }

    /**
     * Cada afiliado que recibe el upgrade necesita su Voucher ILS (`$vouchers`,
     * afiliado_id => número, desde, hasta y comprobante). Si alguno falta o no es
     * válido, no se escribe nada.
     *
     * @param  iterable<int, Affiliate|int>  $affiliates
     * @param  array<int, mixed>  $coverageIds
     * @param  array<int|string, mixed>  $vouchers
     * @return array{affiliates_updated: int, upgrades_created: int, annual_delta: float, collections_adjusted: int, skipped: list<array{name: string, reason: string, upgrade?: string}>}
     */
    public static function add(Affiliation $owner, iterable $affiliates, array $coverageIds, ?string $actor = null, array $vouchers = []): array
    {
        $affiliateIds = self::affiliateIds($affiliates);

        if ($affiliateIds === []) {
            throw new InvalidArgumentException('Seleccione al menos un afiliado.');
        }

        $coverages = UpgradePricing::resolveCoverages($coverageIds);
        $actor = self::actorName($actor);
        $labels = Affiliate::query()->whereIn('id', $affiliateIds)->pluck('full_name', 'id')->map(fn (mixed $name): string => trim((string) $name))->all();
        $vouchers = UpgradeIlsVoucher::validateMany($vouchers, $labels);

        return DB::transaction(function () use ($owner, $affiliateIds, $coverages, $actor, $vouchers): array {
            $locked = self::lockOwner($owner);
            self::assertCanChange($locked);

            $affiliates = self::lockAffiliates($locked, $affiliateIds);
            $skipped = self::skippedForeignAffiliates($affiliateIds, $affiliates);
            $billable = self::billableStatuses($locked);

            $activeUpgradeIds = AffiliateUpgrade::query()
                ->whereIn('affiliate_id', $affiliates->modelKeys())
                ->where('status', AffiliateUpgrade::STATUS_ACTIVE)
                ->get(['affiliate_id', 'upgrade_id'])
                ->groupBy('affiliate_id')
                ->map(fn ($rows): array => $rows->pluck('upgrade_id')->map(fn (mixed $id): int => (int) $id)->all());

            $affiliatesUpdated = 0;
            $upgradesCreated = 0;
            $ownerDelta = 0.0;
            $created = [];

            foreach ($affiliates as $affiliate) {
                $name = self::labelFor($affiliate);

                if (! in_array(mb_strtoupper((string) $affiliate->status, 'UTF-8'), $billable, true)) {
                    $skipped[] = ['name' => $name, 'reason' => UpgradePricing::SKIP_AFFILIATE_INACTIVE];

                    continue;
                }

                $age = UpgradePricing::ageOf($affiliate);

                if ($age === null) {
                    $skipped[] = ['name' => $name, 'reason' => UpgradePricing::SKIP_NO_AGE];

                    continue;
                }

                $delta = 0.0;
                $already = $activeUpgradeIds->get($affiliate->getKey(), []);

                foreach ($coverages as $coverage) {
                    $upgradeName = (string) $coverage->upgrade->name;

                    if (in_array((int) $coverage->upgrade_id, $already, true)) {
                        $skipped[] = ['name' => $name, 'reason' => UpgradePricing::SKIP_ALREADY_ACTIVE, 'upgrade' => $upgradeName];

                        continue;
                    }

                    $range = UpgradePricing::rangeFor($coverage, $age);

                    if ($range === null) {
                        $skipped[] = ['name' => $name.' ('.$age.' años)', 'reason' => UpgradePricing::SKIP_NO_RANGE, 'upgrade' => $upgradeName];

                        continue;
                    }

                    $voucher = $vouchers[(int) $affiliate->getKey()] ?? null;

                    if ($voucher === null) {
                        throw new InvalidArgumentException('Falta el Voucher ILS de '.$name.': cada afiliado que recibe el upgrade necesita el suyo.');
                    }

                    $upgrade = AffiliateUpgrade::query()->create([
                        'affiliate_id' => $affiliate->getKey(),
                        ...UpgradePricing::assignmentAttributes($coverage, $range, $age, $actor),
                        ...UpgradeIlsVoucher::columns($voucher),
                        ...UpgradeFirstPaymentBilling::assignmentAttributes('affiliate_upgrades'),
                    ]);

                    $created[] = (int) $upgrade->getKey();
                    $delta += (float) $upgrade->amount;
                    $upgradesCreated++;
                }

                if ($delta <= 0.0) {
                    continue;
                }

                self::applyAffiliateDelta($locked, $affiliate, $delta);
                $affiliatesUpdated++;
                $ownerDelta += $delta;
            }

            $collectionIds = UpgradeFirstPaymentBilling::hasColumn('affiliate_upgrades', 'billing_mode')
                ? self::chargeOnNextCollection($locked, $ownerDelta, $created, $actor)
                : self::applyOwnerDelta($locked, $ownerDelta, 'Upgrade agregado', $actor);

            SecurityAudit::log('AUDIT_BUSINESS_INDIVIDUAL_AFFILIATE_UPGRADES_ADDED', 'business.affiliations.upgrades.add', [
                'panel' => 'business',
                'module' => 'affiliations',
                'affiliation_id' => $locked->getKey(),
                'affiliation_code' => $locked->code,
                'upgrade_coverage_ids' => $coverages->modelKeys(),
                'affiliate_ids' => $affiliates->modelKeys(),
                'upgrade_ids' => $created,
                'annual_delta' => round($ownerDelta, 2),
                'collections_adjusted' => $collectionIds,
                'skipped' => $skipped,
            ]);

            return [
                'affiliates_updated' => $affiliatesUpdated,
                'upgrades_created' => $upgradesCreated,
                'annual_delta' => round($ownerDelta, 2),
                'collections_adjusted' => count($collectionIds),
                'skipped' => $skipped,
            ];
        });
    }

    /**
     * Da de baja upgrades activos: pasan a INACTIVO y su monto se descuenta.
     *
     * @param  list<int>  $upgradeIds  ids de `affiliate_upgrades`
     * @return array{affiliates_updated: int, upgrades_removed: int, annual_delta: float, collections_adjusted: int, skipped: list<array{name: string, reason: string}>}
     */
    public static function deactivate(Affiliation $owner, array $upgradeIds, ?string $actor = null): array
    {
        $upgradeIds = array_values(array_unique(array_filter(array_map('intval', $upgradeIds), fn (int $id): bool => $id > 0)));

        if ($upgradeIds === []) {
            throw new InvalidArgumentException('Seleccione al menos un upgrade para quitar.');
        }

        $actor = self::actorName($actor);

        return DB::transaction(function () use ($owner, $upgradeIds, $actor): array {
            $locked = self::lockOwner($owner);
            self::assertCanChange($locked);

            $upgrades = AffiliateUpgrade::query()
                ->whereIn('id', $upgradeIds)
                ->where('status', AffiliateUpgrade::STATUS_ACTIVE)
                ->whereHas('affiliate', fn ($query) => $query->where('affiliation_id', $locked->getKey()))
                ->lockForUpdate()
                ->get();

            if ($upgrades->isEmpty()) {
                throw new RuntimeException('Los upgrades seleccionados ya no están activos. Actualice la tabla e intente de nuevo.');
            }

            $affiliates = self::lockAffiliates($locked, $upgrades->pluck('affiliate_id')->unique()->map(fn (mixed $id): int => (int) $id)->values()->all());
            $byAffiliate = $upgrades->groupBy('affiliate_id');
            $billable = self::billableStatuses($locked);
            $affiliatesUpdated = 0;
            $ownerDelta = 0.0;
            /** Repartidos en las cuotas (antes del cobro único): se descuentan de todas, como siempre. */
            $spreadDelta = 0.0;
            $billedOnce = [];

            foreach ($affiliates as $affiliate) {
                $rows = $byAffiliate->get($affiliate->getKey());

                if ($rows === null) {
                    continue;
                }

                $delta = -round((float) $rows->sum('amount'), 2);

                if ((float) $affiliate->fee + $delta < -0.004) {
                    throw new RuntimeException('La tarifa de '.self::labelFor($affiliate).' quedaría negativa al quitar el upgrade. Revise su tarifa anual antes de continuar.');
                }

                AffiliateUpgrade::query()
                    ->whereIn('id', $rows->modelKeys())
                    ->update([
                        'status' => AffiliateUpgrade::STATUS_INACTIVE,
                        'deactivated_at' => now(),
                        'deactivated_by' => $actor,
                        'deactivation_reason' => self::REMOVAL_REASON,
                        'updated_by' => $actor,
                        'updated_at' => now(),
                    ]);

                self::applyAffiliateDelta($locked, $affiliate, $delta);
                $affiliatesUpdated++;

                /** Un afiliado dado de baja ya no suma en la afiliación: su upgrade tampoco. */
                if (in_array(mb_strtoupper((string) $affiliate->status, 'UTF-8'), $billable, true)) {
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
                ...self::applyOwnerDelta($locked, $ownerDelta, 'Upgrade quitado', $actor, $spreadDelta),
                ...self::releaseChargedCollections($locked, $billedOnce, $actor),
            ]));

            SecurityAudit::log('AUDIT_BUSINESS_INDIVIDUAL_AFFILIATE_UPGRADES_REMOVED', 'business.affiliations.upgrades.remove', [
                'panel' => 'business',
                'module' => 'affiliations',
                'affiliation_id' => $locked->getKey(),
                'affiliation_code' => $locked->code,
                'upgrades' => $upgrades->map(fn (AffiliateUpgrade $upgrade): array => [
                    'id' => $upgrade->getKey(),
                    'affiliate_id' => $upgrade->affiliate_id,
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
                'collections_adjusted' => count($collectionIds),
                'skipped' => [],
            ];
        });
    }

    /**
     * Upgrades activos entre los afiliados indicados, con cuántos lo tienen.
     *
     * @param  list<int>  $affiliateIds
     * @return array<int, array{name: string, total: int}> upgrade_id => datos
     */
    public static function activeUpgradesAmong(Affiliation $owner, array $affiliateIds): array
    {
        if ($affiliateIds === []) {
            return [];
        }

        return AffiliateUpgrade::query()
            ->whereIn('affiliate_id', $affiliateIds)
            ->whereHas('affiliate', fn ($query) => $query->where('affiliation_id', $owner->getKey()))
            ->where('status', AffiliateUpgrade::STATUS_ACTIVE)
            ->selectRaw('upgrade_id, MAX(name) as name, COUNT(*) as total')
            ->groupBy('upgrade_id')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (AffiliateUpgrade $row): array => [
                (int) $row->upgrade_id => ['name' => (string) $row->getAttribute('name'), 'total' => (int) $row->getAttribute('total')],
            ])
            ->all();
    }

    /**
     * @param  list<int>  $affiliateIds
     * @param  array<int, mixed>  $catalogUpgradeIds  ids de `upgrades`
     * @return list<int> ids de `affiliate_upgrades`
     */
    public static function activeAssignmentIdsForUpgrades(Affiliation $owner, array $affiliateIds, array $catalogUpgradeIds): array
    {
        $catalogUpgradeIds = array_values(array_filter(array_map('intval', $catalogUpgradeIds), fn (int $id): bool => $id > 0));

        if ($affiliateIds === [] || $catalogUpgradeIds === []) {
            return [];
        }

        return AffiliateUpgrade::query()
            ->whereIn('affiliate_id', $affiliateIds)
            ->whereHas('affiliate', fn ($query) => $query->where('affiliation_id', $owner->getKey()))
            ->where('status', AffiliateUpgrade::STATUS_ACTIVE)
            ->whereIn('upgrade_id', $catalogUpgradeIds)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * Qué pasaría al agregar el upgrade, sin escribir nada: precio por la edad de
     * cada afiliado (o por qué no lo recibe), tarifa antes y después, totales de
     * la afiliación, cuotas por pagar que se ajustarían y los bloqueos.
     *
     * @param  list<int>  $affiliateIds
     * @return array<string, mixed> ver `UpgradeAssignmentPreview`
     */
    public static function preview(Affiliation $owner, array $affiliateIds, mixed $coverageId): array
    {
        $owner = Affiliation::query()->findOrFail($owner->getKey());
        $frequency = CorporatePaymentFrequency::normalize((string) $owner->payment_frequency) ?? CorporatePaymentFrequency::ANUAL;
        $billable = self::billableStatuses($owner);
        $blockers = self::blockers($owner);

        try {
            $coverage = UpgradePricing::resolveCoverages([$coverageId])->first();
        } catch (InvalidArgumentException $exception) {
            return UpgradeAssignmentPreview::empty($exception->getMessage(), $blockers);
        }

        $affiliates = Affiliate::query()
            ->whereIn('id', array_values(array_map('intval', $affiliateIds)))
            ->where('affiliation_id', $owner->getKey())
            ->orderBy('id')
            ->get();
        $alreadyActive = AffiliateUpgrade::query()
            ->whereIn('affiliate_id', $affiliates->modelKeys())
            ->where('status', AffiliateUpgrade::STATUS_ACTIVE)
            ->where('upgrade_id', $coverage->upgrade_id)
            ->pluck('affiliate_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
        $calculator = app(AffiliationAffiliateFeeCalculator::class);
        $rows = [];
        $ownerDelta = 0.0;

        foreach ($affiliates as $affiliate) {
            $age = UpgradePricing::ageOf($affiliate);
            $range = $age !== null ? UpgradePricing::rangeFor($coverage, $age) : null;
            $affiliateFrequency = filled($affiliate->payment_frequency) ? (string) $affiliate->payment_frequency : $frequency;
            $reason = match (true) {
                ! in_array(mb_strtoupper((string) $affiliate->status, 'UTF-8'), $billable, true) => 'está '.mb_strtolower((string) ($affiliate->status ?: 'sin estatus'), 'UTF-8'),
                in_array((int) $affiliate->getKey(), $alreadyActive, true) => 'ya tiene este upgrade activo',
                $age === null => 'no tiene fecha de nacimiento ni edad registrada',
                $range === null => 'tiene '.$age.' años y el upgrade cubre '.UpgradePricing::coversLabel($coverage),
                default => null,
            };
            $price = $reason === null ? round((float) $range->price, 2) : 0.0;
            $feeBefore = round((float) $affiliate->fee, 2);
            $feeAfter = round($feeBefore + $price, 2);
            $ownerDelta += $price;

            $rows[] = [
                'id' => (int) $affiliate->getKey(),
                'name' => self::labelFor($affiliate),
                'age' => $age,
                'receives' => $reason === null,
                'reason' => $reason,
                'price' => $price,
                'range' => $range?->label(),
                'frequency' => $affiliateFrequency,
                'fee_before' => $feeBefore,
                'fee_after' => $feeAfter,
                'period_before' => round((float) $affiliate->total_amount, 2),
                'period_after' => UpgradeFirstPaymentBilling::hasColumn('affiliate_upgrades', 'billing_mode')
                    ? round((float) $affiliate->total_amount, 2)
                    : $calculator->totalAmountForPaymentFrequency($feeAfter, $affiliateFrequency),
            ];
        }

        $feeAnualBefore = round((float) $owner->fee_anual, 2);
        $feeAnualAfter = round($feeAnualBefore + $ownerDelta, 2);
        $collections = [];
        $note = null;
        $billedOnce = UpgradeFirstPaymentBilling::hasColumn('affiliate_upgrades', 'billing_mode');

        if ($billedOnce && $ownerDelta > 0) {
            [$collections, $note, $blocker] = self::nextCollectionPreview($owner, $ownerDelta);

            if ($blocker !== null) {
                $blockers[] = $blocker;
            }
        }

        if (! $billedOnce && filled($owner->code) && $ownerDelta > 0) {
            foreach (BillingCollection::query()->where('affiliation_code', $owner->code)->where('status', self::PENDING_COLLECTION_STATUS)->orderBy('id')->get() as $collection) {
                $invoice = (string) ($collection->collection_invoice_number ?: '#'.$collection->getKey());

                if ((float) ($collection->pay_amount_usd ?? 0) > 0 || (float) ($collection->pay_amount_ves ?? 0) > 0) {
                    $blockers[] = 'El aviso de cobro '.$invoice.' tiene un abono registrado. Concilie ese pago en Administración antes de cambiar upgrades.';
                }

                $months = CorporatePaymentFrequency::monthsPerInstallment(CorporatePaymentFrequency::normalize((string) $collection->payment_frequency) ?? $frequency);
                $before = round((float) $collection->total_amount, 2);
                $collections[] = [
                    'invoice' => $invoice,
                    'date' => CorporatePaymentFrequencyChanger::parseDueDate($collection->next_payment_date, $collection->filter_next_payment_date)?->format('d/m/Y'),
                    'before' => $before,
                    'after' => round($before + $ownerDelta * $months / 12, 2),
                ];
            }
        }

        return [
            'error' => null,
            'blockers' => array_values(array_unique($blockers)),
            'upgrade' => [
                'name' => (string) $coverage->upgrade->name,
                'coverage' => $coverage->label(),
                'covers' => UpgradePricing::coversLabel($coverage),
            ],
            'rows' => $rows,
            'owner' => [
                'label' => 'Afiliación '.$owner->code,
                'frequency' => $frequency,
                'annual_before' => $feeAnualBefore,
                'annual_after' => $feeAnualAfter,
                'period_before' => round((float) $owner->total_amount, 2),
                'period_after' => $billedOnce ? round((float) $owner->total_amount, 2) : $calculator->totalAmountForPaymentFrequency($feeAnualAfter, $frequency),
                'note' => $note,
            ],
            'collections' => $collections,
            'annual_delta' => round($ownerDelta, 2),
        ];
    }

    /**
     * Motivos por los que hoy no se pueden cambiar upgrades en la afiliación.
     *
     * @return list<string>
     */
    public static function blockers(Affiliation $affiliation): array
    {
        try {
            self::assertCanChange($affiliation);
        } catch (RuntimeException $exception) {
            return [$exception->getMessage()];
        }

        return [];
    }

    public static function labelFor(Affiliate $affiliate): string
    {
        $name = trim((string) $affiliate->full_name);

        return $name !== '' ? $name : 'Afiliado #'.$affiliate->getKey();
    }

    private static function assertCanChange(Affiliation $affiliation): void
    {
        $status = mb_strtoupper(trim((string) $affiliation->status), 'UTF-8');

        if (! in_array($status, self::ALLOWED_AFFILIATION_STATUSES, true)) {
            throw new RuntimeException('La afiliación está '.mb_strtolower($status !== '' ? $status : 'sin estado', 'UTF-8').': los upgrades solo se cambian en afiliaciones activas o pre-aprobadas.');
        }

        $pendingProofs = PaidMembership::query()
            ->where('affiliation_id', $affiliation->getKey())
            ->where('status', self::PENDING_PROOF_STATUS)
            ->count();

        if ($pendingProofs > 0) {
            throw new RuntimeException(
                'Tiene '.($pendingProofs === 1 ? 'un comprobante de pago' : $pendingProofs.' comprobantes de pago').' por aprobar en Administración. Apruébelos o recházelos antes de cambiar upgrades: al aprobarse generarían cuotas con el monto anterior.'
            );
        }

        $renewal = Renovation::query()
            ->where('affiliation_id', $affiliation->getKey())
            ->where('status', self::RENEWAL_PERIOD_STATUS)
            ->first(['id', 'date_renewal']);

        if ($renewal !== null) {
            $renewalDate = $renewal->date_renewal instanceof \DateTimeInterface
                ? $renewal->date_renewal->format('d/m/Y')
                : trim((string) $renewal->date_renewal);

            throw new RuntimeException(
                'Está en período de renovación'.($renewalDate !== '' ? ' (renueva el '.$renewalDate.')' : '').'. Acepte la renovación primero y luego cambie los upgrades.'
            );
        }
    }

    /**
     * Mismos estados que pagan en el cambio de plan: ACTIVO y, si la afiliación sigue
     * pre-aprobada, también PRE-APROBADA.
     *
     * @return list<string>
     */
    private static function billableStatuses(Affiliation $affiliation): array
    {
        return mb_strtoupper((string) $affiliation->status, 'UTF-8') === 'PRE-APROBADA'
            ? ['ACTIVO', 'PRE-APROBADA']
            : ['ACTIVO'];
    }

    private static function applyAffiliateDelta(Affiliation $owner, Affiliate $affiliate, float $delta): void
    {
        $fee = round((float) $affiliate->fee + $delta, 2);

        if ($fee > self::MAX_STORED_AMOUNT) {
            throw new RuntimeException('La tarifa anual de '.self::labelFor($affiliate).' superaría el máximo permitido ('.number_format(self::MAX_STORED_AMOUNT, 2, ',', '.').' US$).');
        }

        $fee = max(0.0, $fee);
        $frequency = filled($affiliate->payment_frequency)
            ? (string) $affiliate->payment_frequency
            : (string) ($owner->payment_frequency ?? 'ANUAL');

        $affiliate->forceFill([
            'fee' => $fee,
            'total_amount' => UpgradeFirstPaymentBilling::regularInstallment($fee, UpgradeFirstPaymentBilling::affiliateOneTimeTotal($affiliate), $frequency),
        ])->save();
    }

    /**
     * Suma el delta anual a la afiliación. La cuota regular deja fuera los upgrades de
     * cobro único. `$spreadDelta` (por defecto, todo el delta) es la parte repartida en las
     * cuotas: se suma a cada cuota POR PAGAR en proporción a los meses que cubre.
     *
     * @return list<int> ids de cuotas ajustadas
     */
    private static function applyOwnerDelta(Affiliation $owner, float $annualDelta, string $reason, string $actor, ?float $spreadDelta = null): array
    {
        $annualDelta = round($annualDelta, 2);

        if (abs($annualDelta) < 0.01) {
            return [];
        }

        $frequency = CorporatePaymentFrequency::normalize((string) $owner->payment_frequency) ?? CorporatePaymentFrequency::ANUAL;
        $feeAnual = max(0.0, round((float) $owner->fee_anual + $annualDelta, 2));

        if ($feeAnual > self::MAX_STORED_AMOUNT) {
            throw new RuntimeException('El total de la afiliación superaría el máximo permitido. No se aplicó ningún cambio.');
        }

        $owner->forceFill([
            'fee_anual' => $feeAnual,
            'total_amount' => UpgradeFirstPaymentBilling::regularInstallment($feeAnual, UpgradeFirstPaymentBilling::individualOneTimeTotal($owner), $frequency),
        ])->save();

        $annualDelta = round($spreadDelta ?? $annualDelta, 2);

        if (blank($owner->code) || abs($annualDelta) < 0.01) {
            return [];
        }

        $collections = BillingCollection::query()
            ->where('affiliation_code', $owner->code)
            ->where('status', self::PENDING_COLLECTION_STATUS)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $adjusted = [];

        foreach ($collections as $collection) {
            $invoice = (string) ($collection->collection_invoice_number ?: '#'.$collection->getKey());

            if ((float) ($collection->pay_amount_usd ?? 0) > 0 || (float) ($collection->pay_amount_ves ?? 0) > 0) {
                throw new RuntimeException('El aviso de cobro '.$invoice.' tiene un abono registrado. Concilie ese pago en Administración antes de cambiar upgrades.');
            }

            $months = CorporatePaymentFrequency::monthsPerInstallment(
                CorporatePaymentFrequency::normalize((string) $collection->payment_frequency) ?? $frequency,
            );
            $previous = round((float) $collection->total_amount, 2);
            $amount = max(0.0, round($previous + $annualDelta * $months / 12, 2));

            $collection->forceFill(['total_amount' => $amount])->save();
            $adjusted[] = (int) $collection->getKey();

            $dueDate = CorporatePaymentFrequencyChanger::parseDueDate($collection->next_payment_date, $collection->filter_next_payment_date);

            CollectionObservation::query()->create([
                'affiliation_code' => $collection->affiliation_code,
                'affiliation_type' => CollectionObservation::TYPE_INDIVIDUAL,
                'collection_id' => $collection->getKey(),
                'due_date' => $dueDate?->toDateString(),
                'amount' => $amount,
                'observation' => Str::limit($reason.' ('.($annualDelta > 0 ? '+' : '−').UpgradePricing::money(abs($annualDelta)).' al año). Monto anterior: '.UpgradePricing::money($previous).'.', 1000, ''),
                'created_by' => Auth::id(),
                'created_by_name' => $actor,
            ]);
        }

        return $adjusted;
    }

    /**
     * Upgrades nuevos (cobro único): suben el anual de la afiliación sin tocar la cuota regular.
     * Si el primer pago aún no se hizo, van en él; si no, completos en el próximo aviso POR
     * PAGAR, que queda anotado en cada asignación para poder descontarlo si se quita.
     *
     * @param  list<int>  $upgradeIds  ids de `affiliate_upgrades` recién creados
     * @return list<int> ids de cuotas ajustadas
     */
    private static function chargeOnNextCollection(Affiliation $owner, float $annualDelta, array $upgradeIds, string $actor): array
    {
        self::applyOwnerDelta($owner, $annualDelta, 'Upgrade agregado', $actor, 0.0);
        $annualDelta = round($annualDelta, 2);

        if ($annualDelta < 0.01 || UpgradeFirstPaymentBilling::awaitsFirstPayment($owner)) {
            return [];
        }

        $collection = UpgradeFirstPaymentBilling::nextPendingCollection((string) $owner->code, self::PENDING_COLLECTION_STATUS);

        if ($collection === null) {
            throw new RuntimeException(self::NO_PENDING_COLLECTION_MESSAGE);
        }

        self::assertNoPartialPayment($collection);

        $previous = round((float) $collection->total_amount, 2);
        $amount = round($previous + $annualDelta, 2);
        $collection->forceFill(['total_amount' => $amount])->save();

        if ($upgradeIds !== [] && UpgradeFirstPaymentBilling::hasColumn('affiliate_upgrades', 'charged_collection_id')) {
            AffiliateUpgrade::query()->whereIn('id', $upgradeIds)->update(['charged_collection_id' => $collection->getKey()]);
        }

        self::observe($collection, $amount, 'Upgrade agregado: '.UpgradePricing::money($annualDelta).' en este aviso, una sola vez (los upgrades se pagan completos, no en cuotas). Monto anterior: '.UpgradePricing::money($previous).'.', $actor);

        return [(int) $collection->getKey()];
    }

    /**
     * Quita del aviso donde se cobraron los upgrades de cobro único que se dan de baja, si ese
     * aviso sigue por pagar. Si ya se pagó (o fueron en el primer pago) no se toca nada.
     *
     * @param  list<AffiliateUpgrade>  $upgrades
     * @return list<int> ids de cuotas ajustadas
     */
    private static function releaseChargedCollections(Affiliation $owner, array $upgrades, string $actor): array
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

            $previous = round((float) $collection->total_amount, 2);
            $after = max(0.0, round($previous - $amount, 2));
            $collection->forceFill(['total_amount' => $after])->save();
            $adjusted[] = (int) $collection->getKey();

            self::observe($collection, $after, 'Upgrade quitado: se descuentan '.UpgradePricing::money($amount).' que se habían sumado a este aviso. Monto anterior: '.UpgradePricing::money($previous).'.', $actor);
        }

        return $adjusted;
    }

    /**
     * Aviso que recibiría el upgrade (vista previa), nota para el analista y bloqueo si no hay
     * dónde cobrarlo.
     *
     * @return array{0: list<array{invoice: string, date: ?string, before: float, after: float}>, 1: ?string, 2: ?string}
     */
    private static function nextCollectionPreview(Affiliation $owner, float $annualDelta): array
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
            'date' => CorporatePaymentFrequencyChanger::parseDueDate($collection->next_payment_date, $collection->filter_next_payment_date)?->format('d/m/Y'),
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

    private static function observe(BillingCollection $collection, float $amount, string $text, string $actor): void
    {
        $dueDate = CorporatePaymentFrequencyChanger::parseDueDate($collection->next_payment_date, $collection->filter_next_payment_date);

        CollectionObservation::query()->create([
            'affiliation_code' => $collection->affiliation_code,
            'affiliation_type' => CollectionObservation::TYPE_INDIVIDUAL,
            'collection_id' => $collection->getKey(),
            'due_date' => $dueDate?->toDateString(),
            'amount' => $amount,
            'observation' => Str::limit($text, 1000, ''),
            'created_by' => Auth::id(),
            'created_by_name' => $actor,
        ]);
    }

    /**
     * @param  iterable<int, Affiliate|int>  $affiliates
     * @return list<int>
     */
    private static function affiliateIds(iterable $affiliates): array
    {
        $ids = [];

        foreach ($affiliates as $affiliate) {
            $id = $affiliate instanceof Affiliate ? (int) $affiliate->getKey() : (int) $affiliate;

            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    private static function lockOwner(Affiliation $owner): Affiliation
    {
        /** @var Affiliation */
        return Affiliation::query()->whereKey($owner->getKey())->lockForUpdate()->firstOrFail();
    }

    /**
     * @param  list<int>  $affiliateIds
     * @return EloquentCollection<int, Affiliate>
     */
    private static function lockAffiliates(Affiliation $owner, array $affiliateIds): EloquentCollection
    {
        return Affiliate::query()
            ->whereIn('id', $affiliateIds)
            ->where('affiliation_id', $owner->getKey())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * @param  list<int>  $requestedIds
     * @param  EloquentCollection<int, Affiliate>  $locked
     * @return list<array{name: string, reason: string}>
     */
    private static function skippedForeignAffiliates(array $requestedIds, EloquentCollection $locked): array
    {
        return collect(array_diff($requestedIds, $locked->modelKeys()))
            ->map(fn (int $id): array => ['name' => 'Afiliado #'.$id, 'reason' => UpgradePricing::SKIP_NOT_IN_AFFILIATION])
            ->values()
            ->all();
    }

    private static function actorName(?string $actor): string
    {
        $actor = trim((string) ($actor ?? Auth::user()?->name ?? ''));

        return $actor !== '' ? $actor : 'system';
    }
}
