<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates;

use App\Models\AffiliateCorporate;
use App\Models\AffiliateCorporateUpgrade;
use App\Models\AffiliationCorporate;
use App\Models\Collection as BillingCollection;
use App\Models\UpgradeBenefit;
use App\Services\CorporateAffiliateRemovalService;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Upgrades de afiliados corporativos: montos anuales en US$ que se suman a la
 * tarifa del afiliado.
 *
 * Cada alta o baja se aplica como un delta, en una sola transacción, sobre:
 * la tarifa del afiliado, los totales de la afiliación (solo si el afiliado
 * cuenta como población) y los avisos de cobro pendientes. Así el monto que se
 * agrega es exactamente el que se refleja, sin recalcular desde cero lo que ya
 * estaba cuadrado.
 */
final class CorporateAffiliateUpgradeManager
{
    public const MAX_NAME_LENGTH = 120;

    public const MAX_ITEMS = 10;

    public const MAX_AMOUNT = 99999.99;

    /**
     * Tope de `affiliate_corporates.fee` y `affiliation_corporates.total_amount` (decimal 8,2).
     */
    public const MAX_STORED_AMOUNT = 999999.99;

    public const PENDING_COLLECTION_STATUS = 'POR PAGAR';

    /**
     * @var list<string>
     */
    public const LOCKED_AFFILIATE_STATUSES = ['INACTIVO', 'EXCLUIDO'];

    public const SKIP_NOT_IN_AFFILIATION = 'not_in_affiliation';

    public const SKIP_AFFILIATE_INACTIVE = 'affiliate_inactive';

    public const SKIP_ALREADY_ACTIVE = 'already_active';

    public static function normalizeName(?string $name): string
    {
        $name = preg_replace('/\s+/u', ' ', trim((string) $name)) ?? '';

        return mb_strtoupper($name, 'UTF-8');
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
     * Catálogo activo para autocompletar el nombre del upgrade.
     *
     * @return array<string, float> nombre => precio de referencia
     */
    public static function catalogPrices(): array
    {
        return UpgradeBenefit::query()
            ->where('status', 'ACTIVO')
            ->orderBy('description')
            ->get(['description', 'price'])
            ->mapWithKeys(fn (UpgradeBenefit $benefit): array => [
                self::normalizeName($benefit->description) => round((float) $benefit->price, 2),
            ])
            ->filter(fn (float $price, string $name): bool => $name !== '')
            ->all();
    }

    public static function catalogPriceFor(?string $name): ?float
    {
        $name = self::normalizeName($name);

        if ($name === '') {
            return null;
        }

        return self::catalogPrices()[$name] ?? null;
    }

    /**
     * Nombres de upgrades activos entre los afiliados indicados, con cuántos lo tienen.
     *
     * @param  list<int>  $affiliateIds
     * @return array<string, int>
     */
    public static function activeNamesAmong(AffiliationCorporate $owner, array $affiliateIds): array
    {
        if ($affiliateIds === []) {
            return [];
        }

        return AffiliateCorporateUpgrade::query()
            ->where('affiliation_corporate_id', $owner->getKey())
            ->whereIn('affiliate_corporate_id', $affiliateIds)
            ->where('status', AffiliateCorporateUpgrade::STATUS_ACTIVE)
            ->selectRaw('name, COUNT(*) as total')
            ->groupBy('name')
            ->orderBy('name')
            ->pluck('total', 'name')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();
    }

    /**
     * @param  list<int>  $affiliateIds
     * @param  list<string>  $names
     * @return list<int>
     */
    public static function activeUpgradeIdsForNames(AffiliationCorporate $owner, array $affiliateIds, array $names): array
    {
        $names = array_values(array_unique(array_filter(array_map(self::normalizeName(...), $names))));

        if ($affiliateIds === [] || $names === []) {
            return [];
        }

        return AffiliateCorporateUpgrade::query()
            ->where('affiliation_corporate_id', $owner->getKey())
            ->whereIn('affiliate_corporate_id', $affiliateIds)
            ->where('status', AffiliateCorporateUpgrade::STATUS_ACTIVE)
            ->whereIn('name', $names)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * Agrega uno o varios upgrades a uno o varios afiliados.
     *
     * @param  iterable<int, AffiliateCorporate|int>  $affiliates
     * @param  array<int, array{name?: mixed, amount?: mixed}>  $items
     * @return array{affiliates_updated: int, upgrades_created: int, annual_delta: float, collections_adjusted: int, skipped: list<array{name: string, reason: string, upgrade?: string}>}
     */
    public static function add(AffiliationCorporate $owner, iterable $affiliates, array $items, ?string $actor = null): array
    {
        $items = self::normalizeItems($items);
        $affiliateIds = self::affiliateIds($affiliates);

        if ($affiliateIds === []) {
            throw new InvalidArgumentException('Seleccione al menos un afiliado.');
        }

        $actor = self::actorName($actor);

        $result = DB::transaction(function () use ($owner, $affiliateIds, $items, $actor): array {
            $ownerLocked = self::lockOwner($owner);
            $locked = self::lockAffiliates($ownerLocked, $affiliateIds);
            $skipped = self::skippedForeignAffiliates($affiliateIds, $locked);
            $catalog = self::resolveCatalog($items);

            $activeNames = AffiliateCorporateUpgrade::query()
                ->whereIn('affiliate_corporate_id', $locked->modelKeys())
                ->where('status', AffiliateCorporateUpgrade::STATUS_ACTIVE)
                ->get(['affiliate_corporate_id', 'name'])
                ->groupBy('affiliate_corporate_id')
                ->map(fn ($rows): array => $rows->pluck('name')->map(self::normalizeName(...))->all());

            $affiliatesUpdated = 0;
            $upgradesCreated = 0;
            $ownerDelta = 0.0;
            $created = [];

            foreach ($locked as $affiliate) {
                if (in_array((string) $affiliate->status, self::LOCKED_AFFILIATE_STATUSES, true)) {
                    $skipped[] = ['name' => CorporateAffiliatePlanSynchronizer::labelFor($affiliate), 'reason' => self::SKIP_AFFILIATE_INACTIVE];

                    continue;
                }

                $delta = 0.0;
                $already = $activeNames->get($affiliate->getKey(), []);

                foreach ($items as $item) {
                    if (in_array($item['name'], $already, true)) {
                        $skipped[] = [
                            'name' => CorporateAffiliatePlanSynchronizer::labelFor($affiliate),
                            'reason' => self::SKIP_ALREADY_ACTIVE,
                            'upgrade' => $item['name'],
                        ];

                        continue;
                    }

                    $upgrade = AffiliateCorporateUpgrade::query()->create([
                        'affiliate_corporate_id' => $affiliate->getKey(),
                        'affiliation_corporate_id' => $ownerLocked->getKey(),
                        'upgrade_benefit_id' => $catalog[$item['name']] ?? null,
                        'name' => $item['name'],
                        'amount' => $item['amount'],
                        'status' => AffiliateCorporateUpgrade::STATUS_ACTIVE,
                        'created_by' => $actor,
                        'updated_by' => $actor,
                    ]);

                    $created[] = $upgrade->getKey();
                    $delta += $item['amount'];
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
                'items' => $items,
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

    public static function skipReasonLabel(string $reason): string
    {
        return match ($reason) {
            self::SKIP_NOT_IN_AFFILIATION => 'no pertenece a esta afiliación',
            self::SKIP_AFFILIATE_INACTIVE => 'está dado de baja',
            self::SKIP_ALREADY_ACTIVE => 'ya tenía ese upgrade activo',
            default => 'no se pudo procesar',
        };
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
     * @param  array<int, array{name?: mixed, amount?: mixed}>  $items
     * @return list<array{name: string, amount: float}>
     */
    private static function normalizeItems(array $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            $name = self::normalizeName(is_scalar($item['name'] ?? null) ? (string) $item['name'] : '');
            $rawAmount = $item['amount'] ?? null;

            if ($name === '') {
                throw new InvalidArgumentException('Cada upgrade debe tener un nombre.');
            }

            if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
                throw new InvalidArgumentException('El nombre «'.$name.'» supera los '.self::MAX_NAME_LENGTH.' caracteres.');
            }

            if (! is_numeric($rawAmount)) {
                throw new InvalidArgumentException('El monto del upgrade «'.$name.'» debe ser un número.');
            }

            $amount = round((float) $rawAmount, 2);

            if ($amount <= 0.0 || $amount > self::MAX_AMOUNT) {
                throw new InvalidArgumentException('El monto del upgrade «'.$name.'» debe ser mayor a 0 y no superar '.number_format(self::MAX_AMOUNT, 2, ',', '.').' US$.');
            }

            if (isset($normalized[$name])) {
                throw new InvalidArgumentException('El upgrade «'.$name.'» está repetido. Cárguelo una sola vez.');
            }

            $normalized[$name] = ['name' => $name, 'amount' => $amount];
        }

        if ($normalized === []) {
            throw new InvalidArgumentException('Agregue al menos un upgrade.');
        }

        if (count($normalized) > self::MAX_ITEMS) {
            throw new InvalidArgumentException('Puede cargar hasta '.self::MAX_ITEMS.' upgrades a la vez.');
        }

        return array_values($normalized);
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

    /**
     * Busca cada nombre en el catálogo de upgrades y crea los que no existen.
     *
     * @param  list<array{name: string, amount: float}>  $items
     * @return array<string, int> nombre => upgrade_benefit_id
     */
    private static function resolveCatalog(array $items): array
    {
        $ids = [];

        foreach ($items as $item) {
            $existing = UpgradeBenefit::query()
                ->whereRaw('UPPER(TRIM(description)) = ?', [$item['name']])
                ->orderByRaw("CASE WHEN status = 'ACTIVO' THEN 0 ELSE 1 END")
                ->orderBy('id')
                ->first();

            if ($existing === null) {
                $existing = new UpgradeBenefit([
                    'code' => self::nextCatalogCode(),
                    'description' => $item['name'],
                    'price' => $item['amount'],
                    'status' => 'ACTIVO',
                ]);
                $existing->save();
            }

            $ids[$item['name']] = (int) $existing->getKey();
        }

        return $ids;
    }

    private static function nextCatalogCode(): string
    {
        $next = (int) UpgradeBenefit::query()->max('id') + 1;

        return 'UPG-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
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
