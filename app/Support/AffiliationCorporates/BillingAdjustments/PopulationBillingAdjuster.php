<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates\BillingAdjustments;

use App\Http\Controllers\UtilsController;
use App\Jobs\GeneratePopulationBillingAdjustmentPdf;
use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\Collection;
use App\Models\CollectionAdjustment;
use App\Models\CorporateBillingAdjustment;
use App\Models\CorporateBillingAdjustmentLine;
use App\Models\CorporatePopulationUpdate;
use App\Models\CorporatePopulationUpdateItem;
use App\Models\PaidMembershipCorporate;
use App\Models\User;
use App\Support\AffiliationCorporates\CorporatePaymentFrequency;
use App\Support\Affiliations\DateCorrection\AffiliationDateCorrector;
use App\Support\Affiliations\DateCorrection\DateCorrectionSubject;
use App\Support\Collections\CollectionDueDate;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\SecurityAudit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Ajuste de facturación por una actualización de población corporativa ya aplicada.
 *
 * - {@see self::plan()} arma el cálculo completo y explicado, con sus bloqueos, sin escribir nada.
 * - {@see self::apply()} lo escribe en una transacción, con la afiliación bloqueada y solo si el
 *   cálculo es idéntico al de la vista previa (huella). Ajusta el monto de las cuotas POR PAGAR
 *   (conservan su número; rastro en `collection_adjustments`, modo «poblacion») y, si hay altas
 *   en un período ya facturado, crea el aviso de cobro por ajuste marcado con
 *   `collections.billing_adjustment_id`.
 * - {@see self::revert()} lo deshace mientras nada se haya pagado ni cambiado después.
 *
 * Los mensajes de las excepciones están pensados para mostrarse tal cual al analista.
 */
final class PopulationBillingAdjuster
{
    public const REASON_MIN_LENGTH = 15;

    /** Hasta cuántos días antes o después de aplicar la población puede caer la fecha efectiva. */
    public const EFFECTIVE_DATE_WINDOW_DAYS = 30;

    public const STATUS_PENDING = 'POR PAGAR';

    public const STATUS_PAID = 'PAGADO';

    public const STATUS_CANCELLED = 'ANULADO';

    public const PENDING_PROOF_STATUS = 'PENDIENTE';

    /** Tope de `collections.total_amount` (decimal 8,2). */
    public const MAX_COLLECTION_CENTS = 99_999_999;

    public const ACTIVE_AFFILIATION_STATUS = 'ACTIVA';

    /**
     * Etiqueta clara de cada movimiento de la carga de población.
     *
     * @var array<string, string>
     */
    private const MOVEMENT_LABELS = [
        CorporatePopulationUpdateItem::ACTION_ADD => 'Alta nueva',
        CorporatePopulationUpdateItem::ACTION_REACTIVATE => 'Reactivación',
        CorporatePopulationUpdateItem::ACTION_DEACTIVATE => 'Baja',
        CorporatePopulationUpdateItem::ACTION_DUPLICATE_BASE => 'Duplicado depurado',
    ];

    /**
     * @return array<string, mixed> ver las claves al final del método
     */
    public static function plan(CorporatePopulationUpdate $update, mixed $effectiveDate = null, ?CarbonImmutable $today = null): array
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $owner = AffiliationCorporate::query()->find($update->affiliation_corporate_id);
        $blockers = [];
        $warnings = [];
        $appliedOn = $update->applied_at !== null ? CarbonImmutable::instance($update->applied_at)->startOfDay() : null;
        $effective = self::parseDate($effectiveDate) ?? $appliedOn;

        $base = [
            'update_id' => (int) $update->getKey(),
            'affiliation_id' => $owner?->getKey(),
            'affiliation_code' => $owner?->code,
            'affiliation_name' => $owner?->name_corporate,
            'applied_on' => $appliedOn,
            'effective_date' => $effective,
            'min_date' => $appliedOn?->subDays(self::EFFECTIVE_DATE_WINDOW_DAYS),
            'max_date' => $appliedOn?->addDays(self::EFFECTIVE_DATE_WINDOW_DAYS),
        ];

        if ($owner === null) {
            return $base + self::emptyResult(['La afiliación corporativa de esta carga ya no existe.']);
        }

        if ($update->status !== CorporatePopulationUpdate::STATUS_APPLIED) {
            $blockers[] = 'Solo se ajusta una carga de población aplicada (esta está «'.$update->statusLabel().'»).';
        }

        if (self::activeAdjustmentFor($update) instanceof CorporateBillingAdjustment) {
            $blockers[] = 'Esta carga ya tiene un ajuste aplicado. Para recalcularlo, revierta primero el ajuste actual.';
        }

        if (strtoupper(trim((string) $owner->status)) !== self::ACTIVE_AFFILIATION_STATUS) {
            $blockers[] = 'La afiliación no está ACTIVA (está «'.$owner->status.'»).';
        }

        if (PaidMembershipCorporate::query()->where('affiliation_corporate_id', $owner->getKey())->where('status', self::PENDING_PROOF_STATUS)->exists()) {
            $blockers[] = 'Hay un comprobante de pago pendiente de aprobación en esta afiliación. Apruébelo o recházelo antes de ajustar: cambia qué cuotas están pagadas.';
        }

        $frequency = CorporatePaymentFrequency::normalize((string) $owner->payment_frequency);

        if ($frequency === null) {
            return $base + self::emptyResult([...$blockers, 'La afiliación no tiene una frecuencia de pago válida («'.$owner->payment_frequency.'»).']);
        }

        $months = CorporatePaymentFrequency::monthsPerInstallment($frequency);
        $perYear = CorporatePaymentFrequency::installmentsPerYear($frequency);
        $calendar = AffiliationDateCorrector::calendar($owner);
        $cycleStart = $calendar['current_start'] instanceof CarbonImmutable ? $calendar['current_start']->startOfDay() : null;

        if ($cycleStart === null) {
            return $base + self::emptyResult([...$blockers, 'No se pudo determinar el inicio del ciclo de cobro de la afiliación.']);
        }

        $cycleEnd = $cycleStart->addMonthsNoOverflow(12);
        $periods = PopulationBillingCalculator::periods($cycleStart, $months, $perYear);
        [$installments, $installmentBlockers, $outsideCycle] = self::installments($owner, $cycleStart, $cycleEnd, $months);
        $blockers = [...$blockers, ...$installmentBlockers];

        if ($effective === null) {
            $blockers[] = 'Indique la fecha efectiva del cambio de población.';
        } else {
            if ($appliedOn !== null && ($effective->lessThan($base['min_date']) || $effective->greaterThan($base['max_date']))) {
                $blockers[] = 'La fecha efectiva debe estar entre el '.$base['min_date']->format('d/m/Y').' y el '.$base['max_date']->format('d/m/Y').' ('.self::EFFECTIVE_DATE_WINDOW_DAYS.' días alrededor del '.$appliedOn->format('d/m/Y').', cuando se aplicó la población).';
            }

            if ($effective->lessThan($cycleStart) || $effective->greaterThanOrEqualTo($cycleEnd)) {
                $blockers[] = 'La fecha efectiva cae fuera del ciclo de cobro actual ('.$cycleStart->format('d/m/Y').' al '.$cycleEnd->subDay()->format('d/m/Y').'). Si corresponde a la renovación, la renovación ya cobra la población vigente.';
            }
        }

        $movements = self::movements($update);

        if ($movements === []) {
            $blockers[] = 'Esta carga no tuvo altas ni bajas que cambien la facturación (solo coincidencias o correcciones de cédula).';
        }

        $calculation = $effective !== null && $movements !== []
            ? PopulationBillingCalculator::calculate($effective, $movements, $periods, $installments, $perYear)
            : null;

        if ($calculation !== null) {
            foreach ($calculation['adjusted'] as $row) {
                if ($row['after_cents'] < 0) {
                    $blockers[] = 'La cuota '.$row['invoice'].' quedaría en negativo ('.PopulationBillingCalculator::money($row['after_cents']).'). Revise la cobranza antes de ajustar.';
                }

                if ($row['after_cents'] > self::MAX_COLLECTION_CENTS) {
                    $blockers[] = 'La cuota '.$row['invoice'].' superaría el máximo que admite un aviso (US$ 999.999,99).';
                }
            }

            if ($calculation['charge_cents'] > self::MAX_COLLECTION_CENTS) {
                $blockers[] = 'El aviso de ajuste superaría el máximo que admite un aviso (US$ 999.999,99).';
            }

            if ($calculation['charge_cents'] === 0 && $calculation['adjusted'] === []) {
                $blockers[] = 'Con esta fecha efectiva no hay nada que cobrar ni cuotas que ajustar.';
            }

            $currentPeriodCents = self::cents($owner->total_amount);
            $firstAfter = $calculation['adjusted'][0]['after_cents'] ?? null;

            if ($firstAfter !== null && $firstAfter !== $currentPeriodCents) {
                $warnings[] = 'La afiliación calcula hoy '.PopulationBillingCalculator::money($currentPeriodCents).' por cuota y, tras el ajuste, las cuotas quedarán en '
                    .PopulationBillingCalculator::money($firstAfter).' ('.PopulationBillingCalculator::money(abs($firstAfter - $currentPeriodCents))
                    .($firstAfter > $currentPeriodCents ? ' por encima' : ' por debajo').'). Esa diferencia ya existía antes de esta carga y este ajuste no la toca: '
                    .'solo suma y resta las personas que cambiaron. Si hay que corregirla, se revisa aparte.';
            }

            $zeroFee = array_filter($calculation['lines'], static fn (array $line): bool => $line['annual_cents'] === 0);

            if ($zeroFee !== []) {
                $warnings[] = count($zeroFee).' '.(count($zeroFee) === 1 ? 'persona no tiene' : 'personas no tienen').' tarifa en la carga de población: no suman ni restan.';
            }
        }

        $chargeDue = self::chargeDueDate($installments, $today);

        $result = [
            'frequency' => $frequency,
            'frequency_label' => CorporatePaymentFrequency::label($frequency),
            'months' => $months,
            'per_year' => $perYear,
            'cycle_start' => $cycleStart,
            'cycle_end' => $cycleEnd,
            'periods' => $periods,
            'installments' => $installments,
            'outside_cycle' => $outsideCycle,
            'calculation' => $calculation,
            'charge_due' => $chargeDue,
            'current_period_cents' => self::cents($owner->total_amount),
            'blockers' => array_values(array_unique($blockers)),
            'warnings' => $warnings,
        ];

        $result['fingerprint'] = self::fingerprint($base + $result);

        return $base + $result;
    }

    /**
     * @throws RuntimeException con un mensaje listo para el analista
     */
    public static function apply(CorporatePopulationUpdate $update, mixed $effectiveDate, string $reason, User $actor, string $expectedFingerprint): CorporateBillingAdjustment
    {
        self::assertCan($actor);
        $reason = trim((string) preg_replace('/\s+/u', ' ', $reason));

        if (mb_strlen($reason) < self::REASON_MIN_LENGTH) {
            throw new RuntimeException('Explique el motivo del ajuste (mínimo '.self::REASON_MIN_LENGTH.' caracteres).');
        }

        $adjustment = DB::transaction(function () use ($update, $effectiveDate, $reason, $actor, $expectedFingerprint): CorporateBillingAdjustment {
            $owner = AffiliationCorporate::query()->whereKey($update->affiliation_corporate_id)->lockForUpdate()->first();

            if ($owner === null) {
                throw new RuntimeException('La afiliación corporativa de esta carga ya no existe.');
            }

            $update = CorporatePopulationUpdate::query()->whereKey($update->getKey())->lockForUpdate()->firstOrFail();
            $plan = self::plan($update, $effectiveDate);

            if ($plan['blockers'] !== []) {
                throw new RuntimeException($plan['blockers'][0]);
            }

            if (! hash_equals($expectedFingerprint, $plan['fingerprint'])) {
                throw new RuntimeException('Los datos cambiaron desde la vista previa (otra persona tocó la cobranza o la población). Cierre y vuelva a calcular el ajuste.');
            }

            $calculation = $plan['calculation'];
            $adjustment = CorporateBillingAdjustment::query()->create([
                'public_id' => (string) Str::uuid(),
                'affiliation_corporate_id' => $owner->getKey(),
                'affiliation_code' => (string) $owner->code,
                'corporate_population_update_id' => $update->getKey(),
                'status' => CorporateBillingAdjustment::STATUS_APPLIED,
                'effective_date' => $plan['effective_date']->toDateString(),
                'payment_frequency' => $plan['frequency'],
                'cycle_start' => $plan['cycle_start']->toDateString(),
                'cycle_end' => $plan['cycle_end']->toDateString(),
                'persons_added' => $calculation['persons_added'],
                'persons_removed' => $calculation['persons_removed'],
                'charge_amount' => self::decimal($calculation['charge_cents']),
                'installment_delta' => self::decimal($calculation['installment_delta_cents']),
                'installments_adjusted' => count($calculation['adjusted']),
                'fingerprint' => $plan['fingerprint'],
                'summary' => self::summaryFromPlan($plan),
                'reason' => $reason,
                'created_by_id' => $actor->getKey(),
                'created_by_name' => $actor->name,
            ]);

            self::storeLines($adjustment, $calculation['lines']);
            $batch = (string) $adjustment->public_id;

            foreach ($calculation['adjusted'] as $row) {
                $collection = Collection::query()->whereKey($row['id'])->lockForUpdate()->first();

                if ($collection === null || strtoupper(trim((string) $collection->status)) !== self::STATUS_PENDING || self::cents($collection->total_amount) !== $row['before_cents']) {
                    throw new RuntimeException('La cuota '.$row['invoice'].' cambió mientras se ajustaba. No se guardó nada; vuelva a calcular.');
                }

                $before = ['total_amount' => self::decimal($row['before_cents']), 'persons' => (string) $collection->persons];
                $collection->total_amount = self::decimal($row['after_cents']);

                if ($row['persons_after'] !== null) {
                    $collection->persons = (string) $row['persons_after'];
                }

                $collection->save();
                self::trace($collection, $batch, [
                    'total_amount' => ['before' => $before['total_amount'], 'after' => self::decimal($row['after_cents'])],
                    'persons' => ['before' => $before['persons'], 'after' => (string) $collection->persons],
                ], 'Ajuste por población (carga del '.$plan['applied_on']?->format('d/m/Y').'): '.$reason, $actor);
            }

            if ($calculation['charge_cents'] > 0) {
                $charge = self::createChargeCollection($owner, $adjustment, $plan, $actor);
                $adjustment->charge_collection_id = $charge->getKey();
                $adjustment->save();
                self::trace($charge, $batch, [
                    'status' => ['before' => null, 'after' => self::STATUS_PENDING],
                    'total_amount' => ['before' => null, 'after' => self::decimal($calculation['charge_cents'])],
                ], 'Aviso de cobro por ajuste de población: '.$reason, $actor);
            }

            $text = 'AJUSTE POR POBLACIÓN. Fecha efectiva '.$plan['effective_date']->format('d/m/Y')
                .'. Altas: '.$calculation['persons_added'].', bajas: '.$calculation['persons_removed']
                .'. Aviso por ajuste: '.PopulationBillingCalculator::money($calculation['charge_cents'])
                .'. Cuotas ajustadas: '.count($calculation['adjusted']).' ('.PopulationBillingCalculator::money($calculation['installment_delta_cents']).' cada una). Motivo: '.$reason;
            AffiliationDateCorrector::log($owner, DateCorrectionSubject::corporate(), 'AJUSTE POR POBLACION', $text, $actor);

            SecurityAudit::log('AUDIT_CORPORATE_POPULATION_BILLING_ADJUSTED', 'administration.population-billing-adjustments.apply', [
                'adjustment_id' => $adjustment->getKey(),
                'affiliation_code' => $owner->code,
                'population_update_id' => $update->getKey(),
                'effective_date' => $plan['effective_date']->toDateString(),
                'charge_amount' => self::decimal($calculation['charge_cents']),
                'installment_delta' => self::decimal($calculation['installment_delta_cents']),
                'installments_adjusted' => count($calculation['adjusted']),
            ], $actor);

            return $adjustment;
        });

        GeneratePopulationBillingAdjustmentPdf::dispatchFor($adjustment);

        return $adjustment;
    }

    /**
     * Qué impide revertir, o null si se puede.
     */
    public static function revertBlocker(CorporateBillingAdjustment $adjustment): ?string
    {
        if (! $adjustment->isApplied()) {
            return 'Este ajuste ya no está aplicado.';
        }

        $later = CorporateBillingAdjustment::query()
            ->where('affiliation_corporate_id', $adjustment->affiliation_corporate_id)
            ->where('status', CorporateBillingAdjustment::STATUS_APPLIED)
            ->where('id', '>', $adjustment->getKey())
            ->exists();

        if ($later) {
            return 'Hay un ajuste posterior aplicado en esta afiliación. Revierta primero el más reciente.';
        }

        if ($adjustment->charge_collection_id !== null) {
            $charge = Collection::query()->find($adjustment->charge_collection_id);

            if ($charge !== null && strtoupper(trim((string) $charge->status)) !== self::STATUS_PENDING) {
                return 'El aviso por ajuste '.$charge->collection_invoice_number.' ya está «'.$charge->status.'»: no se puede revertir un ajuste cobrado.';
            }
        }

        foreach ((array) ($adjustment->summary['adjusted'] ?? []) as $row) {
            $collection = Collection::query()->find($row['id'] ?? 0);

            if ($collection === null) {
                return 'La cuota '.($row['invoice'] ?? '').' ya no existe.';
            }

            if (strtoupper(trim((string) $collection->status)) !== self::STATUS_PENDING) {
                return 'La cuota '.$collection->collection_invoice_number.' ya está «'.$collection->status.'»: se cobró con el monto ajustado.';
            }

            if (self::cents($collection->total_amount) !== (int) ($row['after_cents'] ?? -1)) {
                return 'La cuota '.$collection->collection_invoice_number.' cambió de monto después del ajuste ('.PopulationBillingCalculator::money(self::cents($collection->total_amount)).'). Revise quién la tocó antes de revertir.';
            }
        }

        return null;
    }

    /**
     * @throws RuntimeException con un mensaje listo para el analista
     */
    public static function revert(CorporateBillingAdjustment $adjustment, string $reason, User $actor): CorporateBillingAdjustment
    {
        self::assertCan($actor);
        $reason = trim((string) preg_replace('/\s+/u', ' ', $reason));

        if (mb_strlen($reason) < self::REASON_MIN_LENGTH) {
            throw new RuntimeException('Explique por qué se revierte (mínimo '.self::REASON_MIN_LENGTH.' caracteres).');
        }

        return DB::transaction(function () use ($adjustment, $reason, $actor): CorporateBillingAdjustment {
            $owner = AffiliationCorporate::query()->whereKey($adjustment->affiliation_corporate_id)->lockForUpdate()->first();
            $adjustment = CorporateBillingAdjustment::query()->whereKey($adjustment->getKey())->lockForUpdate()->firstOrFail();

            if (($blocker = self::revertBlocker($adjustment)) !== null) {
                throw new RuntimeException($blocker);
            }

            $batch = (string) Str::uuid();

            foreach ((array) ($adjustment->summary['adjusted'] ?? []) as $row) {
                $collection = Collection::query()->whereKey($row['id'])->lockForUpdate()->firstOrFail();
                $before = ['total_amount' => (string) $collection->total_amount, 'persons' => (string) $collection->persons];
                $collection->total_amount = self::decimal((int) $row['before_cents']);

                if (($row['persons_before'] ?? null) !== null) {
                    $collection->persons = (string) $row['persons_before'];
                }

                $collection->save();
                self::trace($collection, $batch, [
                    'total_amount' => ['before' => $before['total_amount'], 'after' => (string) $collection->total_amount],
                    'persons' => ['before' => $before['persons'], 'after' => (string) $collection->persons],
                ], 'Reversión del ajuste por población: '.$reason, $actor);
            }

            if ($adjustment->charge_collection_id !== null) {
                $charge = Collection::query()->whereKey($adjustment->charge_collection_id)->lockForUpdate()->first();

                if ($charge !== null) {
                    $charge->status = self::STATUS_CANCELLED;
                    $charge->save();
                    self::trace($charge, $batch, ['status' => ['before' => self::STATUS_PENDING, 'after' => self::STATUS_CANCELLED]], 'Reversión del ajuste por población: '.$reason, $actor);
                }
            }

            $adjustment->status = CorporateBillingAdjustment::STATUS_REVERTED;
            $adjustment->reverted_at = now();
            $adjustment->reverted_by_id = $actor->getKey();
            $adjustment->reverted_by_name = $actor->name;
            $adjustment->revert_reason = $reason;
            $adjustment->save();

            if ($owner !== null) {
                AffiliationDateCorrector::log($owner, DateCorrectionSubject::corporate(), 'REVERSION AJUSTE POR POBLACION', 'Se revirtió el ajuste por población del '.$adjustment->created_at?->format('d/m/Y').'. Las cuotas volvieron a su monto anterior y el aviso por ajuste quedó ANULADO. Motivo: '.$reason, $actor);
            }

            SecurityAudit::log('AUDIT_CORPORATE_POPULATION_BILLING_REVERTED', 'administration.population-billing-adjustments.revert', [
                'adjustment_id' => $adjustment->getKey(),
                'affiliation_code' => $adjustment->affiliation_code,
            ], $actor);

            return $adjustment;
        });
    }

    public static function activeAdjustmentFor(CorporatePopulationUpdate $update): ?CorporateBillingAdjustment
    {
        if (! self::tablesReady()) {
            return null;
        }

        return CorporateBillingAdjustment::query()
            ->where('corporate_population_update_id', $update->getKey())
            ->where('status', CorporateBillingAdjustment::STATUS_APPLIED)
            ->latest('id')
            ->first();
    }

    /**
     * Si el código llega antes que la migración, nada de esto rompe otras pantallas.
     */
    public static function tablesReady(): bool
    {
        static $ready = null;

        try {
            return $ready ??= \Illuminate\Support\Facades\Schema::hasTable('corporate_billing_adjustments') && Collection::hasBillingAdjustmentColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    public static function userCan(): bool
    {
        return BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::ADJUST_POPULATION_BILLING);
    }

    /**
     * Las altas y bajas de la carga que cambian la facturación, con la tarifa anual de cada persona.
     *
     * @return list<array{item_id: ?int, affiliate_id: ?int, kind: string, movement: string, name: string, document: string, annual_cents: int}>
     */
    public static function movements(CorporatePopulationUpdate $update): array
    {
        $items = CorporatePopulationUpdateItem::query()
            ->where('corporate_population_update_id', $update->getKey())
            ->where('apply_status', CorporatePopulationUpdateItem::APPLY_APPLIED)
            ->orderBy('id')
            ->get(['id', 'action', 'affiliate_corporate_id', 'file_data', 'result']);

        $rows = [];

        foreach ($items as $item) {
            $result = (array) ($item->result ?? []);
            $persons = (int) ($result['persons_delta'] ?? 0);

            if ($persons === 0) {
                continue;
            }

            $rows[] = [$item, $persons, (int) ($result['affiliate_id'] ?? $item->affiliate_corporate_id ?? 0), $result];
        }

        $affiliates = AffiliateCorporate::query()
            ->whereIn('id', array_values(array_filter(array_column($rows, 2))))
            ->get(['id', 'first_name', 'last_name', 'nro_identificacion'])
            ->keyBy('id');

        $movements = [];

        foreach ($rows as [$item, $persons, $affiliateId, $result]) {
            $affiliate = $affiliates->get($affiliateId);
            $fileData = (array) ($item->file_data ?? []);
            $name = $affiliate !== null
                ? trim($affiliate->first_name.' '.$affiliate->last_name)
                : trim((string) ($fileData['full_name'] ?? $fileData['name'] ?? ''));

            $movements[] = [
                'item_id' => (int) $item->getKey(),
                'affiliate_id' => $affiliateId > 0 ? $affiliateId : null,
                'kind' => $persons > 0 ? PopulationBillingCalculator::KIND_ADD : PopulationBillingCalculator::KIND_REMOVE,
                'movement' => self::MOVEMENT_LABELS[$item->action] ?? Str::title(strtolower((string) $item->action)),
                'name' => $name !== '' ? Str::upper($name) : 'Sin nombre',
                'document' => (string) ($affiliate?->nro_identificacion ?? $fileData['document'] ?? ''),
                'annual_cents' => abs(self::cents($result['fee_delta'] ?? 0)),
            ];
        }

        return $movements;
    }

    /**
     * Cuotas del calendario del ciclo (sin avisos por ajuste), indexadas por período.
     *
     * @return array{0: array<int, array{period: int, id: ?int, invoice: ?string, due: CarbonImmutable, amount_cents: int, persons: ?int, state: string}>, 1: list<string>, 2: list<array{invoice: string, due: ?string, status: string}>}
     */
    private static function installments(AffiliationCorporate $owner, CarbonImmutable $cycleStart, CarbonImmutable $cycleEnd, int $months): array
    {
        $collections = Collection::query()
            ->calendarInstallments()
            ->where('affiliation_code', $owner->code)
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_PAID])
            ->orderBy('id')
            ->get(['id', 'collection_invoice_number', 'next_payment_date', 'filter_next_payment_date', 'total_amount', 'persons', 'status']);

        $installments = [];
        $blockers = [];
        $outside = [];

        foreach ($collections as $collection) {
            $due = CollectionDueDate::of($collection);
            $invoice = (string) ($collection->collection_invoice_number ?: '#'.$collection->id);

            if ($due === null) {
                $blockers[] = 'La cuota '.$invoice.' no tiene una fecha de pago válida. Corríjala con «Ajustar cuota» antes de ajustar la población.';

                continue;
            }

            if ($due->lessThan($cycleStart) || $due->greaterThanOrEqualTo($cycleEnd)) {
                $outside[] = ['invoice' => $invoice, 'due' => $due->format('d/m/Y'), 'status' => (string) $collection->status];

                continue;
            }

            $index = AffiliationDateCorrector::installmentIndex($cycleStart, $due, $months);

            if ($index === null) {
                $blockers[] = 'La cuota '.$invoice.' ('.$due->format('d/m/Y').') no cae en el calendario de cobro. Corríjala con «Ajustar cuota» o en «Inconsistencias de fechas» antes de ajustar la población.';

                continue;
            }

            if (isset($installments[$index])) {
                $blockers[] = 'Hay dos cuotas para el mismo período ('.$installments[$index]['invoice'].' y '.$invoice.'). Anule la duplicada antes de ajustar la población.';

                continue;
            }

            $persons = trim((string) $collection->persons);
            $installments[$index] = [
                'period' => $index,
                'id' => (int) $collection->id,
                'invoice' => $invoice,
                'due' => $due,
                'amount_cents' => self::cents($collection->total_amount),
                'persons' => ctype_digit($persons) ? (int) $persons : null,
                'state' => strtoupper(trim((string) $collection->status)) === self::STATUS_PAID ? PopulationBillingCalculator::STATE_PAID : PopulationBillingCalculator::STATE_PENDING,
            ];
        }

        ksort($installments);

        return [$installments, $blockers, $outside];
    }

    /**
     * El aviso por ajuste vence con la próxima cuota pendiente; si no queda ninguna, hoy.
     *
     * @param  array<int, array{due: CarbonImmutable, state: string}>  $installments
     */
    private static function chargeDueDate(array $installments, CarbonImmutable $today): CarbonImmutable
    {
        foreach ($installments as $installment) {
            if ($installment['state'] === PopulationBillingCalculator::STATE_PENDING && $installment['due']->greaterThanOrEqualTo($today)) {
                return $installment['due'];
            }
        }

        return $today;
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private static function createChargeCollection(AffiliationCorporate $owner, CorporateBillingAdjustment $adjustment, array $plan, User $actor): Collection
    {
        $template = Collection::query()
            ->calendarInstallments()
            ->where('affiliation_code', $owner->code)
            ->orderByDesc('id')
            ->first();

        if ($template === null) {
            throw new RuntimeException('La afiliación no tiene avisos de cobro de referencia para emitir el aviso por ajuste.');
        }

        $lastNumber = (string) (Collection::query()->orderByDesc('id')->lockForUpdate()->value('collection_invoice_number') ?? '');
        $calculation = $plan['calculation'];

        $charge = new Collection;
        $charge->sale_id = null;
        $charge->include_date = $plan['cycle_start']->format('d/m/Y');
        $charge->owner_code = (string) $template->owner_code;
        $charge->code_agency = (string) $template->code_agency;
        $charge->agent_id = $template->agent_id;
        $charge->coverage_id = $template->coverage_id;
        $charge->collection_invoice_number = UtilsController::generateCorrelativeCollection($lastNumber);
        $charge->quote_number = (string) ($template->quote_number ?? '');
        $charge->affiliation_code = (string) $owner->code;
        $charge->affiliate_full_name = $template->affiliate_full_name ?: $owner->name_corporate;
        $charge->affiliate_contact = $template->affiliate_contact;
        $charge->affiliate_ci_rif = $template->affiliate_ci_rif;
        $charge->affiliate_phone = $template->affiliate_phone;
        $charge->affiliate_email = $template->affiliate_email;
        $charge->affiliate_status = (string) $owner->status;
        $charge->plan_id = $template->plan_id;
        $charge->service = 'AJUSTE POR POBLACION';
        $charge->persons = (string) $calculation['persons_added'];
        $charge->type = (string) $template->type;
        $charge->payment_frequency = (string) $owner->payment_frequency;
        $charge->next_payment_date = $plan['charge_due']->format('d/m/Y');
        $charge->total_amount = self::decimal($calculation['charge_cents']);
        $charge->status = self::STATUS_PENDING;
        $charge->days = 0;
        $charge->created_by = $actor->name;
        $charge->billing_adjustment_id = $adjustment->getKey();
        $charge->save();

        return $charge;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private static function storeLines(CorporateBillingAdjustment $adjustment, array $lines): void
    {
        $now = now();
        $rows = array_map(static fn (array $line): array => [
            'corporate_billing_adjustment_id' => $adjustment->getKey(),
            'corporate_population_update_item_id' => $line['item_id'],
            'affiliate_corporate_id' => $line['affiliate_id'],
            'kind' => $line['kind'] === PopulationBillingCalculator::KIND_ADD ? CorporateBillingAdjustmentLine::KIND_ADD : CorporateBillingAdjustmentLine::KIND_REMOVE,
            'movement' => Str::limit($line['movement'], 40, ''),
            'full_name' => Str::limit($line['name'], 255, ''),
            'document' => Str::limit($line['document'], 60, ''),
            'annual_fee' => self::decimal($line['annual_cents']),
            'period_fee' => self::decimal($line['period_cents']),
            'covered_from' => $line['covered_from']?->toDateString(),
            'covered_until' => $line['covered_until']?->toDateString(),
            'days' => $line['days'],
            'period_days' => $line['period_days'],
            'prorated_amount' => self::decimal($line['charge_cents']),
            'installment_delta' => self::decimal($line['installment_delta_cents']),
            'created_at' => $now,
            'updated_at' => $now,
        ], $lines);

        foreach (array_chunk($rows, 500) as $chunk) {
            CorporateBillingAdjustmentLine::query()->insert($chunk);
        }
    }

    /**
     * Foto del cálculo guardada en el ajuste: con ella se explica y se revierte sin recalcular.
     *
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    public static function summaryFromPlan(array $plan): array
    {
        $calculation = $plan['calculation'];

        if ($calculation === null) {
            return ['groups' => [], 'warnings' => $plan['warnings'] ?? []];
        }

        $date = static fn (?CarbonImmutable $value): ?string => $value?->format('d/m/Y');

        return [
            'applied_on' => $date($plan['applied_on']),
            'effective_date' => $date($plan['effective_date']),
            'affiliation_code' => $plan['affiliation_code'],
            'affiliation_name' => $plan['affiliation_name'],
            'frequency' => $plan['frequency'],
            'frequency_label' => $plan['frequency_label'],
            'per_year' => $plan['per_year'],
            'cycle_start' => $date($plan['cycle_start']),
            'cycle_end' => $date($plan['cycle_end']?->subDay()),
            'charge_due' => $date($plan['charge_due']),
            'current_period_cents' => $plan['current_period_cents'],
            'charge_cents' => $calculation['charge_cents'],
            'installment_delta_cents' => $calculation['installment_delta_cents'],
            'persons_added' => $calculation['persons_added'],
            'persons_removed' => $calculation['persons_removed'],
            'charged_periods' => array_map(static fn (array $period): array => [
                'period' => $period['period'] + 1,
                'start' => $date($period['start']),
                'end' => $date($period['end']->subDay()),
                'days' => $period['days'],
                'from' => $date($period['from']),
                'covered_days' => $period['covered_days'],
                'state' => $period['state'],
            ], $calculation['charged_periods']),
            'adjusted' => array_map(static fn (array $row): array => [
                'period' => $row['period'] + 1,
                'id' => $row['id'],
                'invoice' => $row['invoice'],
                'due' => $date($row['due']),
                'before_cents' => $row['before_cents'],
                'delta_cents' => $row['delta_cents'],
                'after_cents' => $row['after_cents'],
                'persons_before' => $row['persons_before'],
                'persons_after' => $row['persons_after'],
            ], $calculation['adjusted']),
            'groups' => PopulationBillingPreview::groups($calculation['lines']),
            'warnings' => $plan['warnings'],
        ];
    }

    /**
     * Huella de todo lo que decide el resultado: si algo cambia entre la vista previa y el
     * «Aplicar», no se aplica.
     *
     * @param  array<string, mixed>  $plan
     */
    private static function fingerprint(array $plan): string
    {
        $calculation = $plan['calculation'];

        return sha1(json_encode([
            'update' => $plan['update_id'],
            'effective' => $plan['effective_date']?->toDateString(),
            'frequency' => $plan['frequency'],
            'cycle' => $plan['cycle_start']?->toDateString(),
            'installments' => array_map(static fn (array $row): array => [$row['id'], $row['amount_cents'], $row['state'], $row['due']->toDateString(), $row['persons']], array_values($plan['installments'])),
            'lines' => $calculation === null ? [] : array_map(static fn (array $line): array => [$line['item_id'], $line['kind'], $line['annual_cents'], $line['charge_cents']], $calculation['lines']),
            'charge' => $calculation['charge_cents'] ?? null,
            'delta' => $calculation['installment_delta_cents'] ?? null,
            'blockers' => $plan['blockers'],
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, array{before: mixed, after: mixed}>  $changes
     */
    private static function trace(Collection $collection, string $batch, array $changes, string $reason, User $actor): void
    {
        CollectionAdjustment::query()->create([
            'collection_id' => $collection->getKey(),
            'affiliation_code' => $collection->affiliation_code,
            'collection_invoice_number' => $collection->collection_invoice_number,
            'batch_uuid' => $batch,
            'mode' => CollectionAdjustment::MODE_POPULATION,
            'changes' => $changes,
            'reason' => Str::limit($reason, 2000, ''),
            'performed_by_id' => $actor->getKey(),
            'performed_by_name' => $actor->name,
            'ip' => request()?->ip(),
            'user_agent' => Str::limit((string) request()?->userAgent(), 1000, ''),
        ]);
    }

    private static function assertCan(User $actor): void
    {
        if (! self::userCan()) {
            throw new RuntimeException('No tiene permiso para ajustar la facturación por población.');
        }
    }

    /**
     * @param  list<string>  $blockers
     * @return array<string, mixed>
     */
    private static function emptyResult(array $blockers): array
    {
        return [
            'frequency' => null,
            'frequency_label' => null,
            'months' => null,
            'per_year' => null,
            'cycle_start' => null,
            'cycle_end' => null,
            'periods' => [],
            'installments' => [],
            'outside_cycle' => [],
            'calculation' => null,
            'charge_due' => null,
            'current_period_cents' => 0,
            'blockers' => array_values(array_unique($blockers)),
            'warnings' => [],
            'fingerprint' => sha1(json_encode($blockers)),
        ];
    }

    private static function parseDate(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value)->startOfDay();
        }

        return is_string($value) && trim($value) !== '' ? CollectionDueDate::parse(trim($value)) : null;
    }

    public static function cents(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function decimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
