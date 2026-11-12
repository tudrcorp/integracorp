<?php

declare(strict_types=1);

namespace App\Support\Affiliations;

use App\Models\Affiliate;
use App\Models\Affiliation;
use App\Models\User;
use App\Support\Affiliations\ActiveBilling\ActivePopulationBilling;
use App\Support\SecurityAudit;
use App\Support\Upgrades\UpgradeFirstPaymentBilling;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Población, tarifa anual y cuota de afiliación individual calculadas con sus
 * afiliados vigentes (ACTIVO y PRE-APROBADA). Alinea cobranza en Administración
 * vía {@see ActivePopulationBilling::sync()}.
 */
final class ActivePopulationIndividualTotals
{
    public const REASON_MIN_LENGTH = 15;

    private const TOLERANCE = 0.01;

    private const LOCK_PREFIX = 'affiliation-individual-active-population-recalc:';

    /**
     * @param  Builder<Affiliation>  $query
     * @return Builder<Affiliation>
     */
    public static function applyToQuery(Builder $query): Builder
    {
        $active = static fn ($sub) => $sub
            ->whereColumn('affiliates.affiliation_id', 'affiliations.id')
            ->whereIn('affiliates.status', ActivePopulationBilling::AFFILIATE_STATUSES)
            ->whereNull('affiliates.deleted_at');

        if ($query->getQuery()->columns === null) {
            $query->select('affiliations.*');
        }

        $query
            ->selectSub($active(DB::table('affiliates')->selectRaw('count(*)')), 'active_population')
            ->selectSub($active(DB::table('affiliates')->selectRaw('coalesce(sum(affiliates.fee), 0)')), 'active_fee_anual')
            ->selectSub($active(DB::table('affiliates')->selectRaw('count(*)'))->where(static fn ($q) => $q->whereNull('affiliates.fee')->orWhere('affiliates.fee', '<=', 0)), 'active_without_fee');

        if (self::upgradesHaveBillingMode()) {
            $query->selectSub(
                DB::table('affiliate_upgrades')
                    ->join('affiliates as upgrade_affiliates', 'upgrade_affiliates.id', '=', 'affiliate_upgrades.affiliate_id')
                    ->whereColumn('upgrade_affiliates.affiliation_id', 'affiliations.id')
                    ->where('affiliate_upgrades.status', \App\Models\AffiliateUpgrade::STATUS_ACTIVE)
                    ->where('affiliate_upgrades.billing_mode', UpgradeFirstPaymentBilling::MODE)
                    ->whereIn('upgrade_affiliates.status', ActivePopulationBilling::AFFILIATE_STATUSES)
                    ->whereNull('upgrade_affiliates.deleted_at')
                    ->selectRaw('coalesce(sum(affiliate_upgrades.amount), 0)'),
                'active_one_time',
            );
        }

        return $query;
    }

    /**
     * Quita las columnas de población activa para poder hacer COUNT/SUM sobre la
     * misma query del listado (widgets) sin chocar con ONLY_FULL_GROUP_BY.
     *
     * @param  Builder<Affiliation>  $query
     * @return Builder<Affiliation>
     */
    public static function withoutLiveSelects(Builder $query): Builder
    {
        $base = $query->getQuery();
        $base->columns = null;
        $base->bindings['select'] = [];

        return $query;
    }

    /**
     * @return array{persons: int, fee_anual: float, total_amount: float, without_fee: int, stored: array{persons: int, fee_anual: float, total_amount: float}, differs: bool}
     */
    public static function forRecord(Affiliation $record): array
    {
        if (! array_key_exists('active_population', $record->getAttributes())) {
            return self::compute($record);
        }

        $annual = round((float) $record->getAttribute('active_fee_anual'), 2);

        return self::result(
            $record,
            (int) $record->getAttribute('active_population'),
            $annual,
            UpgradeFirstPaymentBilling::individualRegular($record, $annual),
            (int) $record->getAttribute('active_without_fee'),
        );
    }

    /**
     * @return array{persons: int, fee_anual: float, total_amount: float, without_fee: int, stored: array{persons: int, fee_anual: float, total_amount: float}, differs: bool}
     */
    public static function compute(Affiliation $owner): array
    {
        $live = ActivePopulationBilling::live($owner);
        $active = Affiliate::query()
            ->where('affiliation_id', $owner->getKey())
            ->whereIn('status', ActivePopulationBilling::AFFILIATE_STATUSES);

        return self::result(
            $owner,
            $live['persons'],
            $live['fee_anual'],
            $live['total_amount'],
            (clone $active)->where(static fn ($q) => $q->whereNull('fee')->orWhere('fee', '<=', 0))->count(),
        );
    }

    /**
     * @return array{
     *     live: array{persons: int, fee_anual: float, total_amount: float, without_fee: int, stored: array{persons: int, fee_anual: float, total_amount: float}, differs: bool},
     *     billing: array<string, mixed>,
     *     blocking: ?string,
     *     issues: list<string>
     * }
     */
    public static function preview(Affiliation $owner): array
    {
        $live = self::compute($owner);
        $billing = ActivePopulationBilling::plan($owner);

        return [
            'live' => $live,
            'billing' => $billing,
            'blocking' => self::blockingReason($owner, $live, $billing),
            'issues' => $billing['issues'],
        ];
    }

    /**
     * @return array{before: array<string, mixed>, after: array<string, mixed>, collections: list<array<string, mixed>>}
     */
    public static function recalculate(Affiliation $owner, User $user, string $reason): array
    {
        $reason = trim((string) preg_replace('/\s+/u', ' ', $reason));
        if (mb_strlen($reason) < self::REASON_MIN_LENGTH) {
            throw new RuntimeException('Explique por qué recalcula (mínimo '.self::REASON_MIN_LENGTH.' caracteres).');
        }

        $reason = mb_substr($reason, 0, 1000);
        $lock = Cache::lock(self::LOCK_PREFIX.(int) $owner->getKey(), 120);
        if (! $lock->get()) {
            throw new RuntimeException('Hay una operación de montos en curso en esta afiliación. Intente en unos minutos.');
        }

        try {
            $result = DB::transaction(static function () use ($owner, $user, $reason): array {
                $locked = Affiliation::query()->lockForUpdate()->findOrFail($owner->getKey());
                $preview = self::preview($locked);

                if ($preview['blocking'] !== null) {
                    throw new RuntimeException($preview['blocking']);
                }

                $live = $preview['live'];
                $billing = $preview['billing'];
                $before = [
                    'family_members' => (int) $locked->family_members,
                    'fee_anual' => round((float) $locked->fee_anual, 2),
                    'total_amount' => round((float) $locked->total_amount, 2),
                ];

                if (! $live['differs'] && $billing['collections'] === []) {
                    throw new RuntimeException('Los totales y los avisos ya cuadran con la población activa: no hay nada que recalcular.');
                }

                $applied = ActivePopulationBilling::sync($locked, $user, 'Recalcular totales: '.$reason);

                return [
                    'before' => $before,
                    'after' => [
                        'family_members' => $live['persons'],
                        'fee_anual' => $live['fee_anual'],
                        'total_amount' => $live['total_amount'],
                    ],
                    'collections' => $applied['collections'],
                ];
            });
        } finally {
            $lock->release();
        }

        SecurityAudit::log('AUDIT_INDIVIDUAL_TOTALS_RECALCULATED', 'business.affiliations.recalculate-totals', [
            'affiliation_id' => $owner->getKey(),
            'code' => $owner->code,
            'before' => $result['before'],
            'after' => $result['after'],
            'collections' => $result['collections'],
            'reason' => $reason,
        ], $user);

        return $result;
    }

    /**
     * @param  array{persons: int, fee_anual: float}  $live
     * @param  array<string, mixed>  $billing
     */
    private static function blockingReason(Affiliation $owner, array $live, array $billing): ?string
    {
        if ($billing['blocker'] !== null && ($live['differs'] || ($billing['differs'] ?? false))) {
            return $billing['blocker'];
        }

        if ($live['persons'] > 0 && $live['fee_anual'] <= 0) {
            return 'Los afiliados activos no tienen tarifa: el recálculo dejaría la afiliación en cero. Revise las tarifas antes de recalcular.';
        }

        if ($billing['issues'] !== []) {
            return implode(' ', $billing['issues']);
        }

        return null;
    }

    /**
     * @return array{persons: int, fee_anual: float, total_amount: float, without_fee: int, stored: array{persons: int, fee_anual: float, total_amount: float}, differs: bool}
     */
    private static function result(Affiliation $owner, int $persons, float $annual, float $total, int $withoutFee): array
    {
        $stored = [
            'persons' => (int) $owner->family_members,
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

        return $has ??= Schema::hasColumn('affiliate_upgrades', 'billing_mode');
    }
}
