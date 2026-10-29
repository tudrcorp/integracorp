<?php

declare(strict_types=1);

namespace App\Support\Affiliations;

use App\Http\Controllers\UtilsController;
use App\Models\Affiliation;
use App\Models\AnnualCollection;
use App\Models\Collection;
use App\Models\PaidMembership;
use App\Services\AffiliationRenewalCollectionGenerator;
use App\Support\AffiliationCorporates\CorporatePaymentFrequency;
use App\Support\AffiliationCorporates\CorporatePaymentFrequencyChanger;
use App\Support\PaidMemberships\AffiliationQuoteNumber;
use App\Support\Upgrades\UpgradeFirstPaymentBilling;
use Carbon\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Cache;

/**
 * Vigencia y cobro del grupo unificado.
 *
 * El analista fija la fecha de vigencia y la frecuencia; de ahí sale el calendario
 * de un año (igual que una renovación: la primera cuota cae en la fecha de vigencia,
 * aunque ya haya pasado, para no regalar un período). Reglas que protegen la cobranza:
 *
 * - Bloquea si la vigencia es anterior a lo ya pagado por alguna póliza (cobro doble).
 * - Bloquea si hay avisos POR PAGAR anteriores a la vigencia: anularlos perdonaría deuda.
 * - Bloquea si hay comprobantes de pago por aprobar: al aprobarse crearían avisos viejos.
 * - Advierte si queda un hueco sin cobrar entre lo pagado y la vigencia.
 *
 * Los avisos POR PAGAR desde la vigencia en adelante se anulan (CANCELADO, no se borran)
 * y se reemplazan por el calendario nuevo con el grupo completo.
 */
final class FamilyMergeBilling
{
    public const PENDING_PROOF_STATUS = 'PENDIENTE';

    public const PAID_COLLECTION_STATUS = 'PAGADO';

    /** Margen permitido para la vigencia respecto de hoy. */
    public const MAX_MONTHS_FROM_TODAY = 12;

    /**
     * @param  SupportCollection<int, Affiliation>  $sources
     * @param  SupportCollection<int, Collection>  $pendingCollections  avisos POR PAGAR de todas las pólizas
     * @return array{
     *     effective_date: Carbon|null,
     *     frequency: string,
     *     renewal_date: Carbon|null,
     *     period_amount: float,
     *     installments: list<array{date: Carbon, due: Carbon, amount: float}>,
     *     paid_through: list<array{code: string, date: Carbon|null}>,
     *     cancel_ids: list<int>,
     *     blockers: list<string>,
     *     warnings: list<string>
     * }
     */
    public static function plan(
        Affiliation $target,
        SupportCollection $sources,
        SupportCollection $pendingCollections,
        ?string $requestedEffectiveDate,
        ?string $requestedFrequency,
        float $feeAnual,
        ?Carbon $today = null,
    ): array {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();
        $blockers = [];
        $warnings = [];

        $frequency = CorporatePaymentFrequency::normalize($requestedFrequency ?: (string) $target->payment_frequency);

        if ($frequency === null) {
            $blockers[] = 'Elija una frecuencia de pago válida (anual, semestral, trimestral o mensual).';
            $frequency = CorporatePaymentFrequency::ANUAL;
        }

        $effective = filled($requestedEffectiveDate)
            ? CorporatePaymentFrequencyChanger::parseDueDate((string) $requestedEffectiveDate)
            : CorporatePaymentFrequencyChanger::parseDueDate((string) $target->effective_date);

        if ($effective === null) {
            $blockers[] = 'Indique la fecha de vigencia del grupo unificado: de ella salen las fechas de cobro y la renovación.';
        } elseif ($effective->lt($today->copy()->subMonths(self::MAX_MONTHS_FROM_TODAY)) || $effective->gt($today->copy()->addMonths(self::MAX_MONTHS_FROM_TODAY))) {
            $blockers[] = 'La fecha de vigencia debe estar dentro de los '.self::MAX_MONTHS_FROM_TODAY.' meses anteriores o siguientes a hoy.';
            $effective = null;
        }

        $affiliations = new SupportCollection([$target, ...$sources->all()]);
        $paidThrough = [];

        foreach ($affiliations as $affiliation) {
            $date = self::paidThrough($affiliation);
            $paidThrough[] = ['code' => (string) $affiliation->code, 'date' => $date];

            if ($effective === null) {
                continue;
            }

            if ($date === null) {
                $warnings[] = 'No se pudo determinar hasta cuándo tiene pagado '.$affiliation->code.' (no hay avisos pagados). Confírmelo con Administración.';
            } elseif ($effective->lt($date)) {
                $blockers[] = $affiliation->code.' tiene pagado hasta el '.$date->format('d/m/Y').'. Una vigencia anterior ('.$effective->format('d/m/Y').') le cobraría dos veces ese período: use el '.$date->format('d/m/Y').' o una fecha posterior.';
            } elseif ($effective->gt($date)) {
                $warnings[] = $affiliation->code.' tiene pagado hasta el '.$date->format('d/m/Y').': quedan '.$date->diffInDays($effective).' día(s) sin cobrar hasta la vigencia del '.$effective->format('d/m/Y').'.';
            }
        }

        $cancelIds = [];

        foreach ($pendingCollections as $collection) {
            $due = CorporatePaymentFrequencyChanger::parseDueDate($collection->next_payment_date, $collection->filter_next_payment_date);

            if ($effective !== null && $due !== null && $due->lt($effective)) {
                $blockers[] = $collection->affiliation_code.' tiene el aviso '.($collection->collection_invoice_number ?: '#'.$collection->id)
                    .' POR PAGAR del '.$due->format('d/m/Y').', anterior a la vigencia. Cóbrelo o anúlelo en Administración antes de unificar: anularlo aquí perdonaría esa deuda.';

                continue;
            }

            $cancelIds[] = (int) $collection->id;
        }

        $pendingProofs = PaidMembership::query()
            ->whereIn('affiliation_id', $affiliations->map(static fn (Affiliation $affiliation): int => (int) $affiliation->id)->all())
            ->where('status', self::PENDING_PROOF_STATUS)
            ->count();

        if ($pendingProofs > 0) {
            $blockers[] = 'Hay '.$pendingProofs.' comprobante(s) de pago por aprobar en estas pólizas. Apruébelos o recházelos en Administración antes de unificar: al aprobarse generarían avisos con el monto anterior.';
        }

        /**
         * El grupo unificado arranca un año nuevo: los upgrades de cobro único van completos en
         * la primera cuota y las demás llevan solo el plan.
         */
        $oneTimeUpgrades = round(array_sum($affiliations->map(
            static fn (Affiliation $affiliation): float => UpgradeFirstPaymentBilling::individualOneTimeTotal($affiliation),
        )->all()), 2);
        $periodAmount = CorporatePaymentFrequency::periodAmount(max(0.0, $feeAnual - $oneTimeUpgrades), $frequency);
        $installments = [];

        if ($effective !== null) {
            $expirationDays = $frequency === CorporatePaymentFrequency::MENSUAL ? 30 : 5;

            foreach (AffiliationRenewalCollectionGenerator::upcomingPaymentDates($effective, $frequency) as $date) {
                $installments[] = [
                    'date' => $date,
                    'due' => $date->copy()->addDays($expirationDays),
                    'amount' => $periodAmount,
                ];
            }

            if ($installments !== [] && $oneTimeUpgrades > 0) {
                $installments[0]['amount'] = round($installments[0]['amount'] + $oneTimeUpgrades, 2);
            }

            $overdue = count(array_filter($installments, static fn (array $row): bool => $row['date']->lt($today)));

            if ($overdue > 0) {
                $warnings[] = $overdue.' cuota(s) del calendario tienen fecha ya pasada: se crean igual, como avisos a cobrar.';
            }
        }

        return [
            'effective_date' => $effective,
            'frequency' => $frequency,
            'renewal_date' => $effective?->copy()->addYear(),
            'period_amount' => $periodAmount,
            'installments' => $installments,
            'paid_through' => $paidThrough,
            'cancel_ids' => $cancelIds,
            'blockers' => array_values(array_unique($blockers)),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * Hasta qué fecha está cubierta una póliza por lo pagado: el último aviso PAGADO más un
     * período de su frecuencia. Sin avisos pagados, el primer período desde la vigencia (o la
     * activación) si hay un pago aprobado. Null si no hay forma de saberlo.
     */
    public static function paidThrough(Affiliation $affiliation): ?Carbon
    {
        $latest = null;
        $latestFrequency = (string) $affiliation->payment_frequency;

        $paid = Collection::query()
            ->where('affiliation_code', $affiliation->code)
            ->where('status', self::PAID_COLLECTION_STATUS)
            ->get(['next_payment_date', 'filter_next_payment_date', 'payment_frequency']);

        foreach ($paid as $collection) {
            $date = CorporatePaymentFrequencyChanger::parseDueDate($collection->next_payment_date, $collection->filter_next_payment_date);

            if ($date !== null && ($latest === null || $date->gt($latest))) {
                $latest = $date;
                $latestFrequency = filled($collection->payment_frequency) ? (string) $collection->payment_frequency : $latestFrequency;
            }
        }

        if ($latest === null) {
            $hasApprovedPayment = PaidMembership::query()
                ->where('affiliation_id', $affiliation->id)
                ->whereIn('status', ['APROBADO', 'PAGADO'])
                ->exists();

            $start = CorporatePaymentFrequencyChanger::parseDueDate((string) $affiliation->effective_date)
                ?? CorporatePaymentFrequencyChanger::parseDueDate((string) $affiliation->activated_at);

            if (! $hasApprovedPayment || $start === null) {
                return null;
            }

            $latest = $start;
        }

        return $latest->copy()->addMonthsNoOverflow(CorporatePaymentFrequency::monthsPerInstallment($latestFrequency));
    }

    /**
     * Anula los avisos reemplazados y crea el calendario nuevo para la póliza destino.
     *
     * @param  array{frequency: string, effective_date: Carbon|null, installments: list<array{date: Carbon, due: Carbon, amount: float}>, cancel_ids: list<int>}  $plan
     * @param  list<string>  $sourceCodes
     * @return array{cancelled_ids: list<int>, created_ids: list<int>}
     */
    public static function apply(Affiliation $target, array $plan, array $sourceCodes, string $actorName, string $cancelledStatus): array
    {
        $cancelled = [];

        foreach (Collection::query()->whereIn('id', $plan['cancel_ids'] ?: [0])->get() as $collection) {
            $collection->forceFill([
                'status' => $cancelledStatus,
                'affiliate_status' => in_array((string) $collection->affiliation_code, $sourceCodes, true) ? 'EXCLUIDO' : $collection->affiliate_status,
            ])->save();
            $cancelled[] = (int) $collection->id;
        }

        if ($plan['effective_date'] === null || $plan['installments'] === []) {
            return ['cancelled_ids' => $cancelled, 'created_ids' => []];
        }

        $target->loadMissing('individual_quote');
        $saleId = Collection::query()
            ->whereIn('affiliation_code', [(string) $target->code, ...$sourceCodes])
            ->whereNotNull('sale_id')
            ->orderByRaw('affiliation_code = ? desc', [(string) $target->code])
            ->latest('id')
            ->value('sale_id');

        // El número de aviso se calcula leyendo el último: sin este candado dos procesos repetirían número.
        $lock = Cache::lock('collections:invoice-sequence', 15);

        if (! $lock->block(10)) {
            throw MergeIndividualAffiliationsException::fromBlockers([
                'Otro proceso está generando avisos de cobro. Intente de nuevo en unos segundos.',
            ]);
        }

        try {
            $lastInvoice = (string) (Collection::query()->latest('id')->value('collection_invoice_number') ?? '');
            $created = [];

            foreach ($plan['installments'] as $installment) {
                $collection = new Collection;
                $collection->sale_id = $saleId !== null ? (int) $saleId : null;
                $collection->include_date = $plan['effective_date']->format('d/m/Y');
                $collection->owner_code = $target->owner_code;
                $collection->code_agency = $target->code_agency;
                $collection->plan_id = $target->plan_id;
                $collection->coverage_id = $target->coverage_id;
                $collection->agent_id = $target->agent_id;
                $collection->collection_invoice_number = UtilsController::generateCorrelativeCollection($lastInvoice);
                $collection->quote_number = AffiliationQuoteNumber::forIndividual($target);
                $collection->affiliation_code = $target->code;
                $collection->affiliate_full_name = $target->full_name_ti;
                $collection->affiliate_contact = $target->full_name_ti;
                $collection->affiliate_ci_rif = $target->nro_identificacion_ti;
                $collection->affiliate_phone = $target->phone_ti;
                $collection->affiliate_email = $target->email_ti;
                $collection->affiliate_status = $target->status;
                $collection->type = 'AFILIACION INDIVIDUAL';
                $collection->service = 'servicio';
                $collection->persons = (string) ($target->family_members ?? 0);
                $collection->total_amount = $installment['amount'];
                $collection->payment_method = null;
                $collection->payment_frequency = $plan['frequency'];
                $collection->next_payment_date = $installment['date']->format('d/m/Y');
                $collection->filter_next_payment_date = $installment['date']->format('Y-m-d');
                $collection->expiration_date = $installment['due']->format('d/m/Y');
                $collection->status = MergeIndividualAffiliationsService::COLLECTION_PENDING_STATUS;
                $collection->days = 0;
                $collection->created_by = $actorName;
                $collection->save();

                $lastInvoice = (string) $collection->collection_invoice_number;
                $created[] = (int) $collection->id;
            }
        } finally {
            $lock->release();
        }

        $months = array_values(array_unique(array_map(
            static fn (array $installment): int => (int) $installment['date']->month,
            $plan['installments'],
        )));
        $annualPayload = ['persons' => (string) ($target->family_members ?? 0), 'plan_id' => $target->plan_id];

        for ($month = 1; $month <= 12; $month++) {
            $annualPayload['month_'.$month] = in_array($month, $months, true);
        }

        AnnualCollection::query()
            ->where('affiliation_code', $target->code)
            ->where('status', MergeIndividualAffiliationsService::COLLECTION_PENDING_STATUS)
            ->update($annualPayload);

        return ['cancelled_ids' => $cancelled, 'created_ids' => $created];
    }
}
