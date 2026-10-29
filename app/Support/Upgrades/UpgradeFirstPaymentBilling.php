<?php

declare(strict_types=1);

namespace App\Support\Upgrades;

use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\AffiliateCorporateUpgrade;
use App\Models\AffiliateUpgrade;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\Collection as BillingCollection;
use App\Services\CorporateAffiliateRemovalService;
use App\Support\AffiliationAffiliateFeeCalculator;
use App\Support\AffiliationCorporates\CorporateAffiliatePlanSynchronizer;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Upgrades de cobro único (`billing_mode = PRIMER_PAGO`): van completos en el primer pago del
 * año y la cuota regular de la afiliación lleva solo el plan (`UpgradeInstallments`).
 *
 * Las asignaciones con `billing_mode` NULL son de antes del 30/10/2026 y siguen repartidas en las
 * cuotas: no se tocan. La renovación las pasa a cobro único (el año nuevo arranca con la regla).
 *
 * - `fee_anual` / `fee` siguen siendo el total del año (plan + todos los upgrades).
 * - `total_amount` (cuota regular) = (anual − upgrades de cobro único) ÷ cuotas.
 * - El primer comprobante lleva cuota + upgrades y guarda esa parte en `upgrades_amount`, para
 *   que al aprobarlo las cuotas siguientes no la repitan.
 */
final class UpgradeFirstPaymentBilling
{
    public const MODE = 'PRIMER_PAGO';

    /** Mismos estados que pagan en una afiliación individual (activa o aún pre-aprobada). */
    public const INDIVIDUAL_BILLABLE_STATUSES = ['ACTIVO', 'PRE-APROBADA'];

    /**
     * La tabla tiene la columna en la conexión actual (el código puede llegar antes que la
     * migración, y los tests usan conexiones sqlite propias).
     */
    public static function hasColumn(string $table, string $column): bool
    {
        static $exists = [];
        $connection = (string) config('database.default');
        $key = $connection.'.'.$table.'.'.$column;

        try {
            return $exists[$key] ??= Schema::connection($connection)->hasColumn($table, $column);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Atributos de una asignación nueva: cobro único si la tabla ya lo admite.
     *
     * @return array<string, string>
     */
    public static function assignmentAttributes(string $table): array
    {
        return self::hasColumn($table, 'billing_mode') ? ['billing_mode' => self::MODE] : [];
    }

    /**
     * Upgrades de cobro único activos de la afiliación, solo de familiares que pagan.
     */
    public static function individualOneTimeTotal(Affiliation $affiliation): float
    {
        if (! $affiliation->exists || ! self::hasColumn('affiliate_upgrades', 'billing_mode')) {
            return 0.0;
        }

        return round((float) AffiliateUpgrade::query()
            ->where('status', AffiliateUpgrade::STATUS_ACTIVE)
            ->where('billing_mode', self::MODE)
            ->whereHas('affiliate', fn ($query) => $query
                ->where('affiliation_id', $affiliation->getKey())
                ->whereIn('status', self::INDIVIDUAL_BILLABLE_STATUSES))
            ->sum('amount'), 2);
    }

    /**
     * Upgrades de cobro único activos de la empresa, solo de afiliados que cuentan como población.
     */
    public static function corporateOneTimeTotal(AffiliationCorporate $affiliation): float
    {
        if (! $affiliation->exists || ! self::hasColumn('affiliate_corporate_upgrades', 'billing_mode')) {
            return 0.0;
        }

        return round((float) AffiliateCorporateUpgrade::query()
            ->where('affiliation_corporate_id', $affiliation->getKey())
            ->where('status', AffiliateCorporateUpgrade::STATUS_ACTIVE)
            ->where('billing_mode', self::MODE)
            ->whereHas('affiliateCorporate', fn ($query) => $query->whereIn('status', CorporateAffiliatePlanSynchronizer::COUNTABLE_STATUSES))
            ->sum('amount'), 2);
    }

    /**
     * Upgrades de cobro único activos de un afiliado (individual o corporativo).
     */
    public static function affiliateOneTimeTotal(Affiliate|AffiliateCorporate $affiliate): float
    {
        $table = $affiliate instanceof Affiliate ? 'affiliate_upgrades' : 'affiliate_corporate_upgrades';

        if (! $affiliate->exists || ! self::hasColumn($table, 'billing_mode')) {
            return 0.0;
        }

        return round((float) $affiliate->upgrades()
            ->where('status', AffiliateUpgrade::STATUS_ACTIVE)
            ->where('billing_mode', self::MODE)
            ->sum('amount'), 2);
    }

    /**
     * Cuota regular: el anual sin los upgrades de cobro único, repartido según la frecuencia.
     */
    public static function regularInstallment(float $annual, float $oneTime, ?string $frequency): float
    {
        return UpgradeInstallments::regular(max(0.0, round($annual - $oneTime, 2)), $frequency);
    }

    /**
     * Cuota regular de una afiliación individual a partir de su anual. Sin upgrades de cobro único
     * da exactamente lo de siempre (anual ÷ cuotas).
     */
    public static function individualRegular(Affiliation $affiliation, float $annual, ?string $frequency = null): float
    {
        $frequency ??= (string) ($affiliation->payment_frequency ?? 'ANUAL');
        $oneTime = self::individualOneTimeTotal($affiliation);

        return $oneTime > 0
            ? self::regularInstallment($annual, $oneTime, $frequency)
            : app(AffiliationAffiliateFeeCalculator::class)->totalAmountForPaymentFrequency($annual, $frequency);
    }

    /**
     * Cuota regular de una afiliación corporativa a partir de su anual. Sin upgrades de cobro único
     * da exactamente lo de siempre (anual ÷ cuotas).
     */
    public static function corporateRegular(AffiliationCorporate $affiliation, float $annual, ?string $frequency = null): float
    {
        $frequency ??= (string) ($affiliation->payment_frequency ?? 'ANUAL');
        $oneTime = self::corporateOneTimeTotal($affiliation);

        return $oneTime > 0
            ? self::regularInstallment($annual, $oneTime, $frequency)
            : CorporateAffiliateRemovalService::annualFeeToPerPeriodAmount($annual, $frequency);
    }

    /**
     * Cuota regular de un afiliado a partir de su tarifa anual (sin sus upgrades de cobro único).
     */
    public static function affiliateRegular(Affiliate|AffiliateCorporate $affiliate, float $annual, string $frequency): float
    {
        $oneTime = self::affiliateOneTimeTotal($affiliate);

        if ($oneTime > 0) {
            return self::regularInstallment($annual, $oneTime, $frequency);
        }

        return $affiliate instanceof Affiliate
            ? app(AffiliationAffiliateFeeCalculator::class)->totalAmountForPaymentFrequency($annual, $frequency)
            : CorporateAffiliateRemovalService::annualFeeToPerPeriodAmount($annual, $frequency);
    }

    /**
     * El primer pago todavía no se hizo: la afiliación no se ha activado.
     */
    public static function awaitsFirstPayment(Affiliation|AffiliationCorporate $affiliation): bool
    {
        return blank($affiliation->activated_at);
    }

    /**
     * Upgrades que van en el comprobante: solo en el primer pago y solo si el monto los incluye.
     */
    public static function proofUpgradesAmount(Affiliation|AffiliationCorporate|null $affiliation, mixed $proofTotal): ?float
    {
        if ($affiliation === null) {
            return null;
        }

        $oneTime = self::pendingFirstPaymentUpgrades($affiliation);

        /**
         * Un comprobante por la cuota sola (una carga hecha a mano) no los lleva y no se descuentan.
         * Se toma el punto medio para tolerar un ajuste porcentual aplicado al total.
         */
        if ($oneTime <= 0 || round((float) $proofTotal, 2) <= round((float) ($affiliation->total_amount ?? 0) + $oneTime / 2, 2)) {
            return null;
        }

        return $oneTime;
    }

    /**
     * Monto sugerido al cargar el comprobante: cuota regular y, si es el primer pago, los upgrades.
     */
    public static function suggestedProofAmount(Affiliation|AffiliationCorporate $affiliation): float
    {
        return round((float) ($affiliation->total_amount ?? 0) + self::pendingFirstPaymentUpgrades($affiliation), 2);
    }

    /**
     * Upgrades que todavía faltan por cobrar en el primer pago (0 si ya se hizo).
     */
    public static function pendingFirstPaymentUpgrades(Affiliation|AffiliationCorporate $affiliation): float
    {
        if (! self::awaitsFirstPayment($affiliation)) {
            return 0.0;
        }

        return $affiliation instanceof Affiliation
            ? self::individualOneTimeTotal($affiliation)
            : self::corporateOneTimeTotal($affiliation);
    }

    /**
     * Datos de la carga múltiple con el monto de cada afiliación ya sugerido (cuota y, si es su
     * primer pago, los upgrades).
     *
     * @param  iterable<int, Affiliation|AffiliationCorporate>  $records
     * @return list<array<string, mixed>>
     */
    public static function withSuggestedProofAmounts(iterable $records): array
    {
        $rows = [];

        foreach ($records as $record) {
            $rows[] = [...$record->toArray(), 'total_amount' => self::suggestedProofAmount($record)];
        }

        return $rows;
    }

    /**
     * Texto de ayuda del monto del comprobante, o null si no hay upgrades que cobrar en él.
     */
    public static function proofAmountHint(Affiliation|AffiliationCorporate $affiliation): ?string
    {
        $oneTime = self::pendingFirstPaymentUpgrades($affiliation);

        if ($oneTime <= 0) {
            return null;
        }

        return 'Primer pago: cuota '.UpgradePricing::money((float) $affiliation->total_amount)
            .' + upgrades '.UpgradePricing::money($oneTime)
            .'. Los upgrades se pagan una sola vez al año; las cuotas siguientes llevan solo el plan.';
    }

    /**
     * Cuota de los avisos que se generan al aprobar el primer pago: el comprobante sin la parte
     * de upgrades. En ANUAL el aviso es el del año siguiente, que vuelve a llevar los upgrades.
     */
    public static function installmentAfterFirstPayment(float $proofTotal, mixed $upgradesAmount, ?string $frequency): float
    {
        if (UpgradeInstallments::installments($frequency) === 1) {
            return round($proofTotal, 2);
        }

        return max(0.0, round($proofTotal - (float) ($upgradesAmount ?? 0), 2));
    }

    /**
     * Quita de la cuota de cada afiliado corporativo sus upgrades de cobro único (después de un
     * recálculo masivo que la reparte desde `fee`).
     */
    public static function refreshCorporateAffiliateInstallments(AffiliationCorporate $affiliation): void
    {
        if (! self::hasColumn('affiliate_corporate_upgrades', 'billing_mode')) {
            return;
        }

        $oneTimeByAffiliate = AffiliateCorporateUpgrade::query()
            ->where('affiliation_corporate_id', $affiliation->getKey())
            ->where('status', AffiliateCorporateUpgrade::STATUS_ACTIVE)
            ->where('billing_mode', self::MODE)
            ->selectRaw('affiliate_corporate_id, SUM(amount) as one_time')
            ->groupBy('affiliate_corporate_id')
            ->pluck('one_time', 'affiliate_corporate_id');

        if ($oneTimeByAffiliate->isEmpty()) {
            return;
        }

        AffiliateCorporate::query()
            ->whereIn('id', $oneTimeByAffiliate->keys()->all())
            ->get(['id', 'fee', 'payment_frequency'])
            ->each(function (AffiliateCorporate $affiliate) use ($oneTimeByAffiliate, $affiliation): void {
                $affiliate->forceFill([
                    'subtotal_payment_frequency' => self::regularInstallment(
                        (float) $affiliate->fee,
                        (float) $oneTimeByAffiliate->get($affiliate->getKey()),
                        (string) ($affiliate->payment_frequency ?: $affiliation->payment_frequency),
                    ),
                ])->save();
            });
    }

    /**
     * Upgrades de cobro único activos que se sumaron a cada uno de esos avisos.
     *
     * @param  list<int>  $collectionIds
     * @return array<int, float> aviso => monto
     */
    public static function chargedOnCollections(string $table, array $collectionIds): array
    {
        if ($collectionIds === [] || ! self::hasColumn($table, 'charged_collection_id')) {
            return [];
        }

        $model = $table === 'affiliate_upgrades' ? AffiliateUpgrade::class : AffiliateCorporateUpgrade::class;

        return $model::query()
            ->whereIn('charged_collection_id', $collectionIds)
            ->where('status', AffiliateUpgrade::STATUS_ACTIVE)
            ->where('billing_mode', self::MODE)
            ->selectRaw('charged_collection_id, SUM(amount) as charged')
            ->groupBy('charged_collection_id')
            ->pluck('charged', 'charged_collection_id')
            ->mapWithKeys(fn (mixed $amount, mixed $id): array => [(int) $id => round((float) $amount, 2)])
            ->all();
    }

    /**
     * Pasa a otro aviso los upgrades cobrados en avisos que se anularon y reemplazaron.
     *
     * @param  list<int>  $fromCollectionIds
     */
    public static function moveCharges(string $table, array $fromCollectionIds, int $toCollectionId): void
    {
        if ($fromCollectionIds === [] || ! self::hasColumn($table, 'charged_collection_id')) {
            return;
        }

        $model = $table === 'affiliate_upgrades' ? AffiliateUpgrade::class : AffiliateCorporateUpgrade::class;

        $model::query()
            ->whereIn('charged_collection_id', $fromCollectionIds)
            ->where('status', AffiliateUpgrade::STATUS_ACTIVE)
            ->update(['charged_collection_id' => $toCollectionId]);
    }

    /**
     * Próximo aviso por pagar de la afiliación (por fecha de cobro), bloqueado para escribir.
     */
    public static function nextPendingCollection(string $affiliationCode, string $pendingStatus = 'POR PAGAR'): ?BillingCollection
    {
        if (trim($affiliationCode) === '') {
            return null;
        }

        return BillingCollection::query()
            ->calendarInstallments()
            ->where('affiliation_code', $affiliationCode)
            ->where('status', $pendingStatus)
            ->orderByRaw('filter_next_payment_date IS NULL')
            ->orderBy('filter_next_payment_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();
    }
}
