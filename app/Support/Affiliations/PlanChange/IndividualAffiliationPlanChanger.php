<?php

declare(strict_types=1);

namespace App\Support\Affiliations\PlanChange;

use App\Exceptions\IndividualPlanChangeBlockedException;
use App\Exceptions\WhiteCompanyNegotiatedRateMissingException;
use App\Http\Controllers\UtilsController;
use App\Jobs\RegenerateMergedAffiliationDocumentsJob;
use App\Models\Affiliate;
use App\Models\Affiliation;
use App\Models\AffiliationPlanChange;
use App\Models\AgeRange;
use App\Models\AnnualCollection;
use App\Models\Collection as BillingCollection;
use App\Models\CollectionObservation;
use App\Models\Coverage;
use App\Models\Fee;
use App\Models\PaidMembership;
use App\Models\Plan;
use App\Models\Renovation;
use App\Models\User;
use App\Support\AffiliationAffiliateFeeCalculator;
use App\Support\AffiliationCorporates\CorporatePaymentFrequency;
use App\Support\AffiliationCorporates\CorporatePaymentFrequencyChanger;
use App\Support\ClinicalEntitlements\PlanClinicalCompleteness;
use App\Support\SecurityAudit;
use App\Support\Telemedicine\TelemedicinePatientPlanBridge;
use App\Support\WhiteCompanies\WhiteCompanyNegotiatedRateResolver;
use App\Support\WhiteCompanies\WhiteCompanyOwnership;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Cambia el plan de una afiliación individual: plan, cobertura, frecuencia de pago y rango de
 * edad (tarifa) de cada afiliado, con todo lo que depende de eso, en una sola transacción.
 *
 * - Afiliación y afiliados: plan, cobertura, rango, tarifa anual, monto por período y frecuencia.
 * - Cobranza: nunca toca lo pagado. Las cuotas POR PAGAR se recalculan; si cambia la frecuencia
 *   se anulan (CANCELADO, no se borran) y se reemplazan por cuotas nuevas que cubren los mismos
 *   meses con el monto del plan nuevo. Cada cuota afectada lleva su observación.
 * - Empresa aliada: se recalcula la tarifa negociada con la matriz del plan nuevo; si la aliada no
 *   tiene tarifa para ese plan, no se cambia nada.
 * - Telemedicina (plan del paciente), renovación preparada, bitácora, observación y auditoría.
 * - Bloquea si hay comprobantes por aprobar, si está en período de renovación, si un abono quedó
 *   a medias o si el Plan Ideal no admite la edad de algún afiliado.
 *
 * La vista previa ejecuta exactamente lo mismo y revierte la transacción: lo que el analista ve
 * es lo que pasará.
 */
final class IndividualAffiliationPlanChanger
{
    public const ACTION = 'CAMBIO DE PLAN';

    public const FREQUENCY_ACTION = 'CAMBIO DE FRECUENCIA';

    public const PENDING_COLLECTION_STATUS = 'POR PAGAR';

    public const CANCELLED_COLLECTION_STATUS = CorporatePaymentFrequencyChanger::CANCELLED_COLLECTION_STATUS;

    public const PENDING_PROOF_STATUS = 'PENDIENTE';

    public const RENEWAL_PERIOD_STATUS = 'PERIODO DE RENOVACION';

    /** @var list<string> */
    public const ALLOWED_AFFILIATION_STATUSES = ['ACTIVA', 'PRE-APROBADA'];

    public const MAX_BATCH = 25;

    /** La nota de una excepción debe explicar algo: no basta «ok» ni «autorizado». */
    public const EXCEPTION_NOTE_MIN_LENGTH = 15;

    public function __construct(
        private AffiliationAffiliateFeeCalculator $calculator = new AffiliationAffiliateFeeCalculator,
    ) {}

    /**
     * Qué pasaría, sin dejar nada escrito.
     *
     * @return array<string, mixed>
     */
    public function preview(Affiliation $affiliation, IndividualPlanChangeRequest $request): array
    {
        try {
            DB::transaction(function () use ($affiliation, $request): void {
                throw new IndividualPlanChangePreviewRollback($this->apply($affiliation, $request, null, (string) Str::uuid(), simulate: true));
            });
        } catch (IndividualPlanChangePreviewRollback $rollback) {
            return ['blocked' => null, 'blocked_rule' => null] + $rollback->result;
        } catch (IndividualPlanChangeBlockedException $exception) {
            return ['blocked' => $exception->getMessage(), 'blocked_rule' => $exception->rule] + self::identity($affiliation);
        }

        return ['blocked' => 'No se pudo calcular la vista previa.'] + self::identity($affiliation);
    }

    /**
     * Aplica el cambio a una afiliación.
     *
     * @return array<string, mixed>
     *
     * @throws IndividualPlanChangeBlockedException
     */
    public function change(Affiliation $affiliation, IndividualPlanChangeRequest $request, ?User $actor, ?string $batchUuid = null): array
    {
        return DB::transaction(
            fn (): array => $this->apply($affiliation, $request, $actor, $batchUuid ?? (string) Str::uuid(), simulate: false),
            3,
        );
    }

    /**
     * Cada afiliación en su propia transacción: una bloqueada no revierte a las demás.
     *
     * @param  iterable<int, Affiliation>  $affiliations
     * @param  callable(Affiliation): IndividualPlanChangeRequest  $requestFor
     * @return array{changed: list<array<string, mixed>>, blocked: list<array{label: string, reason: string}>, batch_uuid: string}
     */
    public function changeMany(iterable $affiliations, callable $requestFor, ?User $actor): array
    {
        $batchUuid = (string) Str::uuid();
        $summary = ['changed' => [], 'blocked' => [], 'batch_uuid' => $batchUuid];

        foreach ($affiliations as $affiliation) {
            try {
                $summary['changed'][] = $this->change($affiliation, $requestFor($affiliation), $actor, $batchUuid);
            } catch (IndividualPlanChangeBlockedException $exception) {
                $summary['blocked'][] = ['label' => self::label($affiliation), 'reason' => $exception->getMessage()];
            } catch (Throwable $exception) {
                Log::error('NEGOCIOS-AFILIACIONES: error al cambiar el plan.', [
                    'affiliation_id' => $affiliation->getKey(),
                    'error' => $exception->getMessage(),
                ]);
                report($exception);
                $summary['blocked'][] = ['label' => self::label($affiliation), 'reason' => 'Error inesperado; no se aplicó ningún cambio en esta afiliación.'];
            }
        }

        return $summary;
    }

    /**
     * Solo la frecuencia de pago: conserva plan, cobertura y la tarifa de cada afiliado, y reparte
     * las cuotas POR PAGAR en la frecuencia nueva. Lo usan las acciones «Editar frecuencia de pago».
     *
     * @param  iterable<int, Affiliation>  $affiliations
     * @return array{changed: list<array<string, mixed>>, blocked: list<array{label: string, reason: string}>, batch_uuid: string}
     */
    public function changeFrequencyMany(iterable $affiliations, string $frequency, ?User $actor, string $reason = 'Cambio de frecuencia de pago'): array
    {
        return $this->changeMany(
            $affiliations,
            fn (Affiliation $affiliation): IndividualPlanChangeRequest => IndividualPlanChangeRequest::frequencyOnly(
                (int) $affiliation->plan_id,
                (int) $affiliation->coverage_id > 0 ? (int) $affiliation->coverage_id : null,
                $frequency,
                $reason,
            ),
            $actor,
        );
    }

    /**
     * @return array<string, mixed>
     *
     * @throws IndividualPlanChangeBlockedException
     */
    private function apply(Affiliation $affiliation, IndividualPlanChangeRequest $request, ?User $actor, string $batchUuid, bool $simulate): array
    {
        /** @var Affiliation $locked */
        $locked = Affiliation::query()->whereKey($affiliation->getKey())->lockForUpdate()->firstOrFail();
        $actorName = trim((string) ($actor?->name ?? '')) ?: 'system';

        $this->assertAffiliationCanChange($locked);
        [$plan, $coverageId, $frequency] = $this->validatedTarget($request);

        $previous = [
            'plan_id' => $locked->plan_id !== null ? (int) $locked->plan_id : null,
            // Los paquetes guardan cobertura 0 en algunos registros: es «sin cobertura».
            'coverage_id' => (int) $locked->coverage_id > 0 ? (int) $locked->coverage_id : null,
            'frequency' => (string) $locked->payment_frequency,
            'fee_anual' => round((float) $locked->fee_anual, 2),
            'total_amount' => round((float) $locked->total_amount, 2),
        ];
        $samePlan = $previous['plan_id'] === (int) $plan->id && $previous['coverage_id'] === $coverageId;
        $frequencyChanged = CorporatePaymentFrequency::normalize($previous['frequency']) !== $frequency;

        if ($samePlan && ! $frequencyChanged && $request->ageRangeOverrides === []) {
            throw new IndividualPlanChangeBlockedException('Ya tiene ese plan, esa cobertura y esa frecuencia: no hay nada que cambiar.');
        }

        $affiliates = $this->billableAffiliates($locked);
        $snapshot = $this->snapshot($locked, $affiliates);
        $lines = $this->priceAffiliates($locked, $affiliates, $plan, $coverageId, $frequency, $request, $samePlan);

        $feeAnual = round(array_sum(array_column($lines, 'new_fee')), 2);
        $periodAmount = $this->calculator->totalAmountForPaymentFrequency($feeAnual, $frequency);

        foreach ($lines as $line) {
            /** @var Affiliate $affiliate */
            $affiliate = $line['model'];
            $affiliate->forceFill([
                'plan_id' => (int) $plan->id,
                'coverage_id' => $coverageId,
                'age_range_id' => $line['new_age_range_id'],
                'fee' => $line['new_fee'],
                'total_amount' => $line['new_period'],
                'payment_frequency' => $frequency,
            ])->save();

            TelemedicinePatientPlanBridge::syncFromAffiliate($affiliate);
        }

        $locked->forceFill([
            'plan_id' => (int) $plan->id,
            'coverage_id' => $coverageId,
            'payment_frequency' => $frequency,
            'fee_anual' => $feeAnual,
            'total_amount' => $periodAmount,
            'family_members' => count($lines),
        ])->save();

        $whiteCompany = $this->recalculateWhiteCompanyRate($locked);
        $billing = $this->rebuildPendingBilling($locked, $plan, $coverageId, $frequency, $previous['frequency'], $feeAnual, count($lines), $frequencyChanged, $actorName, $simulate);
        $renovations = $this->syncPreparedRenovations($locked, (int) $plan->id, $coverageId, $frequency);

        $result = self::identity($locked) + [
            'action' => $samePlan ? self::FREQUENCY_ACTION : self::ACTION,
            'previous' => $previous + ['plan' => self::planName($previous['plan_id']), 'coverage' => self::coverageLabel($previous['coverage_id'])],
            'new' => [
                'plan_id' => (int) $plan->id,
                'plan' => Str::upper((string) $plan->description),
                'coverage_id' => $coverageId,
                'coverage' => self::coverageLabel($coverageId),
                'frequency' => $frequency,
                'fee_anual' => $feeAnual,
                'total_amount' => $periodAmount,
            ],
            'affiliates' => array_map(fn (array $line): array => array_diff_key($line, ['model' => true]), $lines),
            'billing' => $billing,
            'white_company' => $whiteCompany,
            'renovations_updated' => $renovations,
            'warnings' => $this->warnings($plan, $locked),
            'exception' => self::exceptionSummary($lines, $request),
            'change_id' => null,
        ];

        if ($simulate) {
            return $result;
        }

        $result['change_id'] = $this->record($locked, $request, $result, $snapshot, $batchUuid, $actor, $actorName);

        $affiliationId = (int) $locked->getKey();
        $actorId = $actor?->getKey() !== null ? (int) $actor->getKey() : null;

        DB::afterCommit(function () use ($affiliationId, $actorId): void {
            RegenerateMergedAffiliationDocumentsJob::dispatch($affiliationId, $actorId);
        });

        return $result;
    }

    private function assertAffiliationCanChange(Affiliation $affiliation): void
    {
        $status = mb_strtoupper(trim((string) $affiliation->status), 'UTF-8');

        if (! in_array($status, self::ALLOWED_AFFILIATION_STATUSES, true)) {
            throw new IndividualPlanChangeBlockedException('La afiliación está '.mb_strtolower($status !== '' ? $status : 'sin estado', 'UTF-8').': solo se cambia el plan de afiliaciones activas o pre-aprobadas.');
        }

        $pendingProofs = PaidMembership::query()
            ->where('affiliation_id', $affiliation->getKey())
            ->where('status', self::PENDING_PROOF_STATUS)
            ->count();

        if ($pendingProofs > 0) {
            throw new IndividualPlanChangeBlockedException(
                'Tiene '.($pendingProofs === 1 ? 'un comprobante de pago' : $pendingProofs.' comprobantes de pago').' por aprobar en Administración. Apruébelos o recházelos antes de cambiar el plan: al aprobarse generarían cuotas con el monto del plan actual.'
            );
        }

        $renewal = Renovation::query()
            ->where('affiliation_id', $affiliation->getKey())
            ->where('status', self::RENEWAL_PERIOD_STATUS)
            ->first(['id', 'date_renewal']);

        if ($renewal !== null) {
            throw new IndividualPlanChangeBlockedException(
                'Está en período de renovación'.(filled($renewal->date_renewal) ? ' (renueva el '.$renewal->date_renewal.')' : '').'. Cambie el plan al aceptar la renovación, en Renovaciones: allí ya se puede elegir el plan nuevo.'
            );
        }
    }

    /**
     * @return array{0: Plan, 1: int|null, 2: string}
     */
    private function validatedTarget(IndividualPlanChangeRequest $request): array
    {
        if (! array_key_exists($request->planId, IndividualPlanChangeCatalog::planOptions())) {
            throw new IndividualPlanChangeBlockedException('Ese plan no está disponible para afiliaciones individuales o no tiene tarifas.');
        }

        $plan = Plan::query()->findOrFail($request->planId);
        $coverageId = null;

        if (IndividualPlanChangeCatalog::usesCoverages((int) $plan->id)) {
            if ($request->coverageId === null || ! array_key_exists($request->coverageId, IndividualPlanChangeCatalog::coverageOptions((int) $plan->id))) {
                throw new IndividualPlanChangeBlockedException('Elija una cobertura del '.Str::title(mb_strtolower((string) $plan->description)).'.');
            }

            $coverageId = $request->coverageId;
        }

        $frequency = CorporatePaymentFrequency::normalize($request->frequency);

        if ($frequency === null) {
            throw new IndividualPlanChangeBlockedException('Elija una frecuencia de pago: Anual, Semestral, Trimestral o Mensual.');
        }

        return [$plan, $coverageId, $frequency];
    }

    /**
     * Afiliados que pagan: los ACTIVO y, si la afiliación aún está pre-aprobada, también los
     * PRE-APROBADA. Los inactivos no se tocan (son historia).
     *
     * @return Collection<int, Affiliate>
     */
    private function billableAffiliates(Affiliation $affiliation): Collection
    {
        $statuses = mb_strtoupper((string) $affiliation->status, 'UTF-8') === 'PRE-APROBADA' ? ['ACTIVO', 'PRE-APROBADA'] : ['ACTIVO'];
        $affiliates = $affiliation->affiliates()->whereIn('status', $statuses)->orderBy('id')->lockForUpdate()->get();

        if ($affiliates->isEmpty()) {
            throw new IndividualPlanChangeBlockedException('No tiene afiliados activos a los que asignar el plan.');
        }

        return $affiliates;
    }

    /**
     * Tarifa nueva de cada afiliado. Si solo cambia la frecuencia, cada uno conserva su tarifa:
     * cambiar la forma de pago no re-tarifica por edad.
     *
     * @param  Collection<int, Affiliate>  $affiliates
     * @return list<array<string, mixed>>
     */
    private function priceAffiliates(Affiliation $affiliation, Collection $affiliates, Plan $plan, ?int $coverageId, string $frequency, IndividualPlanChangeRequest $request, bool $samePlan): array
    {
        $planId = (int) $plan->id;
        $outOfIdeal = [];
        $lines = [];

        foreach ($affiliates as $affiliate) {
            $age = $this->calculator->resolveAffiliateAge($affiliate);
            $override = $request->ageRangeOverrides[(int) $affiliate->id] ?? null;
            $name = trim((string) $affiliate->full_name) ?: 'Afiliado #'.$affiliate->id;
            $keepCurrent = $samePlan && $override === null && (float) $affiliate->fee > 0;

            // Solo se re-tarifica lo que cambia: un cambio de frecuencia no vuelve a juzgar la edad.
            $outsideIdeal = ! $keepCurrent
                && $planId === AffiliationAffiliateFeeCalculator::IDEAL_PLAN_ID
                && $age !== null
                && ! $this->calculator->affiliateAgeFitsPlanAgeRanges($planId, $age, $coverageId);

            if ($outsideIdeal && ! $request->allowIdealAgeException) {
                $outOfIdeal[] = $name.' ('.$age.' años)';

                continue;
            }

            if ($keepCurrent) {
                $fee = null;
                $annual = round((float) $affiliate->fee, 2);
                $ageRangeId = $affiliate->age_range_id !== null ? (int) $affiliate->age_range_id : null;
            } else {
                $fee = match (true) {
                    $override !== null => IndividualPlanChangeCatalog::feeForAgeRange($planId, $coverageId, (int) $override),
                    $outsideIdeal => IndividualPlanChangeCatalog::nearestFeeForAge($planId, $coverageId, (int) $age),
                    $age !== null => $this->resolveActiveFee($planId, $coverageId, $age),
                    default => null,
                };

                if ($fee === null) {
                    throw new IndividualPlanChangeBlockedException(match (true) {
                        $override !== null => 'El rango de edad elegido para '.$name.' no tiene tarifa en ese plan y cobertura.',
                        $age === null => 'No se conoce la edad de '.$name.': elija su rango de edad a mano en la vista previa.',
                        default => 'No hay tarifa para '.$name.' ('.$age.' años) en ese plan y cobertura. Elija su rango de edad a mano o use otro plan.',
                    });
                }

                $annual = round((float) $fee->price, 2);
                $ageRangeId = $fee->age_range_id !== null ? (int) $fee->age_range_id : null;
            }

            $lines[] = [
                'model' => $affiliate,
                'id' => (int) $affiliate->id,
                'name' => $name,
                'relationship' => (string) ($affiliate->relationship ?? ''),
                'age' => $age,
                'status' => (string) $affiliate->status,
                'previous_age_range_id' => $affiliate->age_range_id !== null ? (int) $affiliate->age_range_id : null,
                'previous_fee' => round((float) $affiliate->fee, 2),
                'new_age_range_id' => $ageRangeId,
                'new_age_range' => $fee !== null ? IndividualPlanChangeCatalog::rangeLabel($fee->ageRange, $fee) : self::ageRangeLabel($ageRangeId),
                'new_fee' => $annual,
                'new_period' => $this->calculator->totalAmountForPaymentFrequency($annual, $frequency),
                'overridden' => $override !== null,
                'kept' => $keepCurrent,
                'exception' => $outsideIdeal,
            ];
        }

        if ($outOfIdeal !== []) {
            throw new IndividualPlanChangeBlockedException(
                AffiliationAffiliateFeeCalculator::NEGOTIATION_MESSAGE_IDEAL_OUT_OF_RANGE.' Fuera de rango: '.implode(', ', $outOfIdeal).'.'
                .' Si la excepción está autorizada, marque «Autorizar excepción» y explique el motivo.',
                IndividualPlanChangeBlockedException::RULE_IDEAL_AGE_RANGE,
            );
        }

        $exceptionLines = array_filter($lines, fn (array $line): bool => $line['exception']);

        if ($exceptionLines !== [] && mb_strlen(trim($request->exceptionNote)) < self::EXCEPTION_NOTE_MIN_LENGTH) {
            throw new IndividualPlanChangeBlockedException(
                'La excepción a la regla de edad del Plan Ideal necesita una nota de al menos '.self::EXCEPTION_NOTE_MIN_LENGTH.' caracteres que explique por qué se autoriza.',
                IndividualPlanChangeBlockedException::RULE_IDEAL_AGE_RANGE,
            );
        }

        return $lines;
    }

    private function resolveActiveFee(int $planId, ?int $coverageId, int $age): ?Fee
    {
        $fee = $this->calculator->resolveFeeForPlanCoverageAndAge($planId, $coverageId, $age);

        return $fee !== null && mb_strtoupper((string) ($fee->status ?? 'ACTIVO')) === 'ACTIVO' ? $fee : null;
    }

    /**
     * La tarifa negociada queda congelada en la afiliación; con otro plan se vuelve a tomar de la
     * matriz de la aliada. Si la aliada no tiene tarifa para el plan nuevo, se bloquea.
     *
     * @return array{company: string, sale_price: float, neta: float}|null
     */
    private function recalculateWhiteCompanyRate(Affiliation $affiliation): ?array
    {
        $company = WhiteCompanyOwnership::forAffiliation($affiliation);

        if ($company === null) {
            return null;
        }

        $affiliation->forceFill([
            'white_company_sale_price' => null,
            'white_company_neta' => null,
            'white_company_fee_id' => null,
        ])->save();
        $affiliation->unsetRelation('affiliates');

        try {
            $settlement = (new WhiteCompanyNegotiatedRateResolver($this->calculator))->settlementForAffiliation($affiliation->refresh());
        } catch (WhiteCompanyNegotiatedRateMissingException) {
            throw new IndividualPlanChangeBlockedException(
                'La empresa aliada '.$company->name.' no tiene tarifa negociada para ese plan y cobertura. Cárguela en su matriz de negociación antes de cambiar el plan.'
            );
        }

        return $settlement === null ? null : [
            'company' => (string) $company->name,
            'sale_price' => round((float) $settlement->annualSalePrice, 2),
            'neta' => round((float) $settlement->annualNeta, 2),
        ];
    }

    /**
     * Cuotas POR PAGAR (nunca las pagadas). Misma frecuencia: se actualizan en el lugar, con su
     * mismo número de aviso. Otra frecuencia: se anulan y se crean las que cubren los mismos meses.
     *
     * @return array{mode: string, months: int, cancelled: list<array<string, mixed>>, created: list<array<string, mixed>>, updated: list<array<string, mixed>>}
     */
    private function rebuildPendingBilling(Affiliation $affiliation, Plan $plan, ?int $coverageId, string $frequency, string $previousFrequencyRaw, float $feeAnual, int $persons, bool $frequencyChanged, string $actorName, bool $simulate): array
    {
        $empty = ['mode' => 'none', 'months' => 0, 'cancelled' => [], 'created' => [], 'updated' => []];

        if (blank($affiliation->code)) {
            return $empty;
        }

        $pending = BillingCollection::query()
            ->where('affiliation_code', $affiliation->code)
            ->where('status', self::PENDING_COLLECTION_STATUS)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($pending->isEmpty()) {
            return $empty;
        }

        $installments = [];
        // Frecuencia que tenía la afiliación antes del cambio: una cuota sin frecuencia propia se mide con ella.
        $previousFrequency = CorporatePaymentFrequency::normalize($previousFrequencyRaw) ?? CorporatePaymentFrequency::ANUAL;

        foreach ($pending as $collection) {
            $invoice = (string) ($collection->collection_invoice_number ?: '#'.$collection->getKey());

            if ((float) ($collection->pay_amount_usd ?? 0) > 0 || (float) ($collection->pay_amount_ves ?? 0) > 0) {
                throw new IndividualPlanChangeBlockedException('El aviso de cobro '.$invoice.' tiene un abono registrado. Concilie ese pago en Administración antes de cambiar el plan.');
            }

            $date = CorporatePaymentFrequencyChanger::parseDueDate($collection->next_payment_date, $collection->filter_next_payment_date);

            if ($date === null) {
                throw new IndividualPlanChangeBlockedException('No se pudo leer la fecha de cobro del aviso '.$invoice.'. Corríjala en Gestión de Cobranza antes de cambiar el plan.');
            }

            $installments[] = [
                'collection' => $collection,
                'date' => $date,
                'months' => CorporatePaymentFrequency::monthsPerInstallment(CorporatePaymentFrequency::normalize((string) $collection->payment_frequency) ?? $previousFrequency),
                'amount' => round((float) $collection->total_amount, 2),
            ];
        }

        $totalMonths = array_sum(array_column($installments, 'months'));
        $note = 'Cambio de plan a '.Str::upper((string) $plan->description).' · '.$frequency.'.';

        if (! $frequencyChanged) {
            $updated = [];

            foreach ($installments as $installment) {
                /** @var BillingCollection $collection */
                $collection = $installment['collection'];
                $amount = round($feeAnual * $installment['months'] / 12, 2);
                $updated[] = [
                    'id' => (int) $collection->getKey(),
                    'invoice' => (string) $collection->collection_invoice_number,
                    'date' => $installment['date']->format('d/m/Y'),
                    'previous_amount' => $installment['amount'],
                    'amount' => $amount,
                ];
                $collection->forceFill([
                    'total_amount' => $amount,
                    'plan_id' => (int) $plan->id,
                    'coverage_id' => $coverageId,
                    'persons' => (string) $persons,
                    'payment_frequency' => $frequency,
                ])->save();

                $this->observe($collection, $installment['date'], $amount, $note.' Monto anterior: US$ '.number_format($installment['amount'], 2, ',', '.').'.', $simulate);
            }

            $this->syncAnnualCollections($affiliation, (int) $plan->id, $coverageId, $persons, null);

            return ['mode' => 'updated', 'months' => $totalMonths, 'cancelled' => [], 'created' => [], 'updated' => $updated];
        }

        $schedule = $this->reschedule($installments, $frequency, $feeAnual);
        $cancelled = [];

        foreach ($installments as $installment) {
            /** @var BillingCollection $collection */
            $collection = $installment['collection'];
            $collection->forceFill(['status' => self::CANCELLED_COLLECTION_STATUS])->save();
            $cancelled[] = [
                'id' => (int) $collection->getKey(),
                'invoice' => (string) $collection->collection_invoice_number,
                'date' => $installment['date']->format('d/m/Y'),
                'amount' => $installment['amount'],
                'frequency' => (string) $collection->payment_frequency,
            ];
        }

        $created = $this->createInstallments($affiliation, $installments[0]['collection'], $schedule, $plan, $coverageId, $frequency, $persons, $actorName, $simulate);
        $replacedBy = implode(', ', array_column($created, 'invoice'));

        foreach ($installments as $installment) {
            $this->observe($installment['collection'], $installment['date'], $installment['amount'], $note.' Aviso anulado; lo reemplazan: '.$replacedBy.'.', $simulate);
        }

        foreach ($created as $item) {
            $this->observe(BillingCollection::query()->findOrFail($item['id']), Carbon::createFromFormat('!d/m/Y', $item['date']), $item['amount'], $note.' Reemplaza los avisos anulados: '.implode(', ', array_column($cancelled, 'invoice')).'.', $simulate);
        }

        $this->syncAnnualCollections($affiliation, (int) $plan->id, $coverageId, $persons, array_map(fn (array $item): int => (int) Carbon::createFromFormat('!d/m/Y', $item['date'])->month, $created));

        return ['mode' => 'regenerated', 'months' => $totalMonths, 'cancelled' => $cancelled, 'created' => $created, 'updated' => []];
    }

    /**
     * Los mismos meses pendientes repartidos en cuotas de la frecuencia nueva, con el monto del
     * plan nuevo (la última se prorratea si los meses no calzan).
     *
     * @param  list<array{collection: BillingCollection, date: Carbon, months: int, amount: float}>  $installments
     * @return list<array{date: Carbon, months: int, amount: float}>
     */
    private function reschedule(array $installments, string $frequency, float $feeAnual): array
    {
        $totalMonths = array_sum(array_column($installments, 'months'));

        if ($totalMonths <= 0) {
            return [];
        }

        $start = collect($installments)->min(fn (array $installment): Carbon => $installment['date'])->copy()->startOfDay();
        $interval = CorporatePaymentFrequency::monthsPerInstallment($frequency);
        $count = intdiv($totalMonths + $interval - 1, $interval);
        $schedule = [];

        for ($index = 0; $index < $count; $index++) {
            $months = min($interval, $totalMonths - ($index * $interval));
            $schedule[] = [
                'date' => $start->copy()->addMonthsNoOverflow($index * $interval),
                'months' => $months,
                'amount' => round($feeAnual * $months / 12, 2),
            ];
        }

        return $schedule;
    }

    /**
     * @param  list<array{date: Carbon, months: int, amount: float}>  $schedule
     * @return list<array{id: int, invoice: string, date: string, amount: float, months: int}>
     */
    private function createInstallments(Affiliation $affiliation, BillingCollection $template, array $schedule, Plan $plan, ?int $coverageId, string $frequency, int $persons, string $actorName, bool $simulate): array
    {
        if ($schedule === []) {
            return [];
        }

        // El número de aviso se calcula leyendo el último: sin este candado dos procesos repetirían número.
        $lock = $simulate ? null : Cache::lock('collections:invoice-sequence', 15);

        if ($lock !== null && ! $lock->block(10)) {
            throw new IndividualPlanChangeBlockedException('Otro proceso está generando avisos de cobro. Intente de nuevo en unos segundos.');
        }

        try {
            $lastInvoice = (string) (BillingCollection::query()->latest('id')->value('collection_invoice_number') ?? '');
            $expirationDays = $frequency === CorporatePaymentFrequency::MENSUAL ? 30 : 5;
            $created = [];

            foreach ($schedule as $installment) {
                $collection = $template->replicate();
                $collection->collection_invoice_number = UtilsController::generateCorrelativeCollection($lastInvoice);
                $collection->affiliate_status = $affiliation->status;
                $collection->plan_id = (int) $plan->id;
                $collection->coverage_id = $coverageId;
                $collection->persons = (string) $persons;
                $collection->payment_frequency = $frequency;
                $collection->total_amount = $installment['amount'];
                $collection->next_payment_date = $installment['date']->format('d/m/Y');
                $collection->filter_next_payment_date = $installment['date']->format('Y-m-d');
                $collection->expiration_date = $installment['date']->copy()->addDays($expirationDays)->format('d/m/Y');
                $collection->status = self::PENDING_COLLECTION_STATUS;
                $collection->days = 0;
                $collection->pay_amount_usd = null;
                $collection->pay_amount_ves = null;
                $collection->created_by = $actorName;
                $collection->save();

                $lastInvoice = (string) $collection->collection_invoice_number;
                $created[] = [
                    'id' => (int) $collection->getKey(),
                    'invoice' => $simulate ? 'nuevo' : (string) $collection->collection_invoice_number,
                    'date' => $installment['date']->format('d/m/Y'),
                    'amount' => $installment['amount'],
                    'months' => $installment['months'],
                ];
            }

            return $created;
        } finally {
            $lock?->release();
        }
    }

    /**
     * La planificación anual (meses con cuota) sigue a la cobranza: plan, cobertura, personas y,
     * si cambió la frecuencia, los meses en que cae cada cuota nueva.
     *
     * @param  list<int>|null  $months
     */
    private function syncAnnualCollections(Affiliation $affiliation, int $planId, ?int $coverageId, int $persons, ?array $months): void
    {
        $payload = ['plan_id' => $planId, 'coverage_id' => $coverageId, 'persons' => (string) $persons];

        if ($months !== null) {
            for ($month = 1; $month <= 12; $month++) {
                $payload['month_'.$month] = in_array($month, $months, true);
            }
        }

        AnnualCollection::query()
            ->where('affiliation_code', $affiliation->code)
            ->where('status', self::PENDING_COLLECTION_STATUS)
            ->update($payload);
    }

    /**
     * La renovación preparada (aún no en período) aplicaría el plan viejo hasta la próxima corrida.
     */
    private function syncPreparedRenovations(Affiliation $affiliation, int $planId, ?int $coverageId, string $frequency): int
    {
        return Renovation::query()
            ->where('affiliation_id', $affiliation->getKey())
            ->where('status', '!=', self::RENEWAL_PERIOD_STATUS)
            ->update(['plan_id' => $planId, 'coverage_id' => $coverageId, 'payment_frequency' => $frequency]);
    }

    private function observe(BillingCollection $collection, Carbon $dueDate, float $amount, string $text, bool $simulate): void
    {
        if ($simulate) {
            return;
        }

        CollectionObservation::query()->create([
            'affiliation_code' => $collection->affiliation_code,
            'affiliation_type' => CollectionObservation::TYPE_INDIVIDUAL,
            'collection_id' => $collection->getKey(),
            'due_date' => $dueDate->toDateString(),
            'amount' => $amount,
            'observation' => Str::limit($text, 1000, ''),
            'created_by' => auth()->id(),
            'created_by_name' => auth()->user()?->name ?? 'system',
        ]);
    }

    /**
     * @return list<string>
     */
    private function warnings(Plan $plan, Affiliation $affiliation): array
    {
        $warnings = [];

        if (! PlanClinicalCompleteness::isComplete($plan)) {
            $warnings[] = 'El plan nuevo no tiene configurados todos sus cupos clínicos: Telemedicina puede bloquear consultas hasta que se completen.';
        }

        if (! in_array((int) $plan->id, [1, 2, 3], true)) {
            $warnings[] = 'El plan nuevo no tiene condicionado publicado: la tarjeta saldrá sin el QR del condicionado.';
        }

        if (filled($affiliation->individual_quote_id)) {
            $warnings[] = 'La cotización original conserva el plan anterior (no se modifica).';
        }

        return $warnings;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $snapshot
     */
    private function record(Affiliation $affiliation, IndividualPlanChangeRequest $request, array $result, array $snapshot, string $batchUuid, ?User $actor, string $actorName): int
    {
        $previous = $result['previous'];
        $new = $result['new'];
        $billing = $result['billing'];
        $exception = $result['exception'];

        $change = AffiliationPlanChange::query()->create([
            'batch_uuid' => $batchUuid,
            'affiliation_id' => $affiliation->getKey(),
            'affiliation_code' => $affiliation->code,
            'previous_plan_id' => $previous['plan_id'],
            'new_plan_id' => $new['plan_id'],
            'previous_coverage_id' => $previous['coverage_id'],
            'new_coverage_id' => $new['coverage_id'],
            'previous_frequency' => $previous['frequency'],
            'new_frequency' => $new['frequency'],
            'previous_fee_anual' => $previous['fee_anual'],
            'new_fee_anual' => $new['fee_anual'],
            'previous_total_amount' => $previous['total_amount'],
            'new_total_amount' => $new['total_amount'],
            'affiliates' => $result['affiliates'],
            'cancelled_collections' => $billing['cancelled'] ?: null,
            'created_collections' => $billing['created'] ?: null,
            'updated_collections' => $billing['updated'] ?: null,
            'snapshot' => $snapshot,
            'reason' => Str::limit(trim($request->reason), 500, '') ?: null,
            'exception_rule' => $exception['rule'] ?? null,
            'exception_note' => $exception['note'] ?? null,
            'exception_affiliates' => $exception['affiliates'] ?? null,
            'performed_by_id' => $actor?->getKey(),
            'performed_by_name' => $actorName,
            'performed_from' => self::currentPanelId(),
            'ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);

        $summary = sprintf(
            '%s: %s%s · %s → %s%s · %s. Tarifa anual US$ %s → US$ %s; monto por período US$ %s → US$ %s.%s%s',
            $result['action'],
            $previous['plan'],
            $previous['coverage'] !== null ? ' ('.$previous['coverage'].')' : '',
            $previous['frequency'] ?: 'sin frecuencia',
            $new['plan'],
            $new['coverage'] !== null ? ' ('.$new['coverage'].')' : '',
            $new['frequency'],
            number_format((float) $previous['fee_anual'], 2, ',', '.'),
            number_format((float) $new['fee_anual'], 2, ',', '.'),
            number_format((float) $previous['total_amount'], 2, ',', '.'),
            number_format((float) $new['total_amount'], 2, ',', '.'),
            filled($request->reason) ? ' Motivo: '.trim($request->reason) : '',
            $exception !== null
                ? ' EXCEPCIÓN AUTORIZADA a la regla de edad del Plan Ideal para '
                    .implode(', ', array_map(fn (array $item): string => $item['name'].' ('.$item['age'].' años → '.$item['age_range'].')', $exception['affiliates']))
                    .'. Nota: '.$exception['note']
                : '',
        );

        $affiliation->status_log_affiliations()->create([
            'affiliation_id' => $affiliation->getKey(),
            'action' => $result['action'],
            'observation' => self::statusLogText($result, $exception !== null),
            'updated_by' => $actorName,
        ]);

        $affiliation->affiliationObservations()->create([
            'description' => $summary,
            'created_by' => $actor?->getKey() !== null ? (string) $actor->getKey() : $actorName,
        ]);

        SecurityAudit::log('AUDIT_BUSINESS_AFFILIATION_PLAN_CHANGED', 'business.affiliations.change-plan', [
            'panel' => self::currentPanelId(),
            'affiliation_id' => $affiliation->getKey(),
            'affiliation_code' => $affiliation->code,
            'change_id' => $change->getKey(),
            'previous' => $previous,
            'new' => $new,
            'cancelled_collection_ids' => array_column($billing['cancelled'], 'id'),
            'created_collection_ids' => array_column($billing['created'], 'id'),
            'updated_collection_ids' => array_column($billing['updated'], 'id'),
            'white_company' => $result['white_company'],
            'exception' => $exception,
        ], $actor);

        if ($exception !== null) {
            SecurityAudit::log('AUDIT_BUSINESS_AFFILIATION_PLAN_RULE_EXCEPTION', 'business.affiliations.change-plan.exception', [
                'panel' => self::currentPanelId(),
                'affiliation_id' => $affiliation->getKey(),
                'affiliation_code' => $affiliation->code,
                'change_id' => $change->getKey(),
                'plan_id' => $new['plan_id'],
                'rule' => $exception['rule'],
                'note' => $exception['note'],
                'affiliates' => $exception['affiliates'],
            ], $actor);
        }

        return (int) $change->getKey();
    }

    /**
     * Estado anterior de todo lo que el cambio toca.
     *
     * @param  Collection<int, Affiliate>  $affiliates
     * @return array<string, mixed>
     */
    private function snapshot(Affiliation $affiliation, Collection $affiliates): array
    {
        return [
            'affiliation' => $affiliation->only([
                'plan_id', 'coverage_id', 'payment_frequency', 'fee_anual', 'total_amount', 'family_members',
                'white_company_sale_price', 'white_company_neta', 'white_company_fee_id',
            ]),
            'affiliates' => $affiliates->map(fn (Affiliate $affiliate): array => $affiliate->only([
                'id', 'plan_id', 'coverage_id', 'age_range_id', 'fee', 'total_amount', 'payment_frequency', 'status',
            ]))->values()->all(),
            'pending_collections' => blank($affiliation->code) ? [] : BillingCollection::query()
                ->where('affiliation_code', $affiliation->code)
                ->where('status', self::PENDING_COLLECTION_STATUS)
                ->get(['id', 'collection_invoice_number', 'plan_id', 'coverage_id', 'payment_frequency', 'total_amount', 'persons', 'next_payment_date'])
                ->toArray(),
            'annual_collections' => blank($affiliation->code) ? [] : AnnualCollection::query()
                ->where('affiliation_code', $affiliation->code)
                ->where('status', self::PENDING_COLLECTION_STATUS)
                ->get()
                ->map(fn (AnnualCollection $row): array => $row->only(array_merge(['id', 'plan_id', 'coverage_id', 'persons'], array_map(fn (int $m): string => 'month_'.$m, range(1, 12)))))
                ->all(),
            'renovations' => Renovation::query()
                ->where('affiliation_id', $affiliation->getKey())
                ->get(['id', 'plan_id', 'coverage_id', 'payment_frequency', 'status'])
                ->toArray(),
        ];
    }

    /**
     * La bitácora de estados guarda hasta 255 caracteres: va lo esencial y el detalle completo
     * (montos, motivo y nota de la excepción) queda en la observación de la afiliación.
     *
     * @param  array<string, mixed>  $result
     */
    private static function statusLogText(array $result, bool $withException): string
    {
        $suffix = $withException ? ' · EXCEPCIÓN de edad del Plan Ideal autorizada (ver observaciones)' : '';
        $head = sprintf(
            '%s: %s → %s%s · %s → %s',
            $result['action'],
            $result['previous']['plan'],
            $result['new']['plan'],
            $result['new']['coverage'] !== null ? ' ('.$result['new']['coverage'].')' : '',
            $result['previous']['frequency'] ?: 'sin frecuencia',
            $result['new']['frequency'],
        );

        return Str::limit($head, 255 - mb_strlen($suffix), '…').$suffix;
    }

    /**
     * Afiliados tarifados por excepción, con la nota que la justifica. Null si no hubo excepción.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return array{rule: string, note: string, affiliates: list<array{id: int, name: string, age: int|null, age_range_id: int|null, age_range: string, fee: float}>}|null
     */
    private static function exceptionSummary(array $lines, IndividualPlanChangeRequest $request): ?array
    {
        $affiliates = array_values(array_map(fn (array $line): array => [
            'id' => $line['id'],
            'name' => $line['name'],
            'age' => $line['age'],
            'age_range_id' => $line['new_age_range_id'],
            'age_range' => $line['new_age_range'],
            'fee' => $line['new_fee'],
        ], array_filter($lines, fn (array $line): bool => $line['exception'])));

        if ($affiliates === []) {
            return null;
        }

        return [
            'rule' => IndividualPlanChangeBlockedException::RULE_IDEAL_AGE_RANGE,
            'note' => Str::limit(trim($request->exceptionNote), 1000, ''),
            'affiliates' => $affiliates,
        ];
    }

    /**
     * @return array{affiliation_id: int, code: string, label: string}
     */
    private static function identity(Affiliation $affiliation): array
    {
        return [
            'affiliation_id' => (int) $affiliation->getKey(),
            'code' => (string) $affiliation->code,
            'label' => self::label($affiliation),
        ];
    }

    public static function label(Affiliation $affiliation): string
    {
        $label = implode(' · ', array_filter([trim((string) $affiliation->code), trim((string) $affiliation->full_name_ti)]));

        return $label !== '' ? $label : 'Afiliación #'.$affiliation->getKey();
    }

    private static function planName(?int $planId): string
    {
        return $planId !== null ? Str::upper((string) (Plan::query()->whereKey($planId)->value('description') ?? 'Plan #'.$planId)) : 'SIN PLAN';
    }

    private static function coverageLabel(?int $coverageId): ?string
    {
        if ($coverageId === null || $coverageId <= 0) {
            return null;
        }

        $price = Coverage::query()->whereKey($coverageId)->value('price');

        return $price !== null ? 'cobertura US$ '.number_format((float) $price, 0, ',', '.') : 'cobertura #'.$coverageId;
    }

    private static function ageRangeLabel(?int $ageRangeId): string
    {
        if ($ageRangeId === null) {
            return 'Sin rango';
        }

        return IndividualPlanChangeCatalog::rangeLabel(AgeRange::query()->find($ageRangeId));
    }

    private static function currentPanelId(): ?string
    {
        try {
            return Filament::getCurrentPanel()?->getId();
        } catch (Throwable) {
            return null;
        }
    }
}
