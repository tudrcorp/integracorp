<?php

declare(strict_types=1);

namespace App\Support\Affiliations\Movements;

use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\AffiliateMovement;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\AfilliationCorporatePlan;
use App\Models\Collection;
use App\Models\CollectionAdjustment;
use App\Models\CollectionMovementLine;
use App\Models\PaidMembership;
use App\Models\PaidMembershipCorporate;
use App\Models\TelemedicinePatient;
use App\Models\User;
use App\Services\CorporateAffiliateRemovalService;
use App\Support\AffiliationCorporates\ActivePopulationTotals;
use App\Support\AffiliationCorporates\BillingAdjustments\PopulationBillingCalculator as Base;
use App\Support\AffiliationCorporates\CorporateAffiliatePlanSynchronizer;
use App\Support\AffiliationCorporates\CorporatePaymentFrequency;
use App\Support\AffiliationCorporates\Population\PopulationActivityInspector;
use App\Support\Affiliations\DateCorrection\AffiliationDateCorrector;
use App\Support\Affiliations\DateCorrection\DateCorrectionSubject;
use App\Support\Collections\CollectionDueDate;
use App\Support\SecurityAudit;
use App\Support\Telemedicine\TelemedicinePatientPlanBridge;
use App\Support\Upgrades\UpgradeFirstPaymentBilling;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Altas y bajas de afiliados (individuales y corporativos) con TODOS sus efectos, todo o nada:
 *
 *  1. Afiliación: estado del afiliado, fila de plan (personas y subtotal) y totales recalculados
 *     desde los afiliados activos (no sumando y restando: así no se desfasan).
 *  2. Cobranza: prorrateo por días del período en curso y cuota completa en los avisos
 *     pendientes siguientes (ver {@see MovementBillingCalculator}), con desglose por aviso en
 *     `collection_movement_lines` y traza en `collection_adjustments`.
 *  3. Operaciones: el paciente de telemedicina ligado cambia de estado; una baja con caso,
 *     coordinación u orden abierta se bloquea.
 *  4. Historial: un `AffiliateMovement` con la foto previa y cada efecto, para auditar y revertir.
 */
final class AffiliateMovements
{
    public const REASON_MIN_LENGTH = 15;

    public const EFFECTIVE_WINDOW_DAYS = 30;

    private const COUNTABLE = ['ACTIVO', 'PRE-APROBADA'];

    private const PENDING = 'POR PAGAR';

    private const PAID = 'PAGADO';

    private const CANCELLED = 'ANULADO';

    private const PROOF_PENDING = 'PENDIENTE';

    private const MAX_COLLECTION_CENTS = 99_999_999;

    /** Avisos que no son cuotas del calendario: ni se ajustan ni reciben créditos. */
    private const NON_CALENDAR_SERVICES = ['PRORRATEO POR INCLUSION', 'REVERSO DE MOVIMIENTO', 'AJUSTE POR POBLACION'];

    /**
     * Qué pasaría con una baja o un alta, sin escribir nada. Para la ventana del analista.
     *
     * @return array{blockers: list<string>, warnings: list<string>, billing: array<string, mixed>|null, summary: list<string>, fingerprint: string}
     */
    public static function preview(Model $owner, Model $affiliate, string $kind, mixed $effectiveDate): array
    {
        $blockers = [];
        $warnings = [];
        $effective = self::date($effectiveDate);

        if ($effective === null) {
            $blockers[] = 'Indique la fecha efectiva del movimiento.';
        } else {
            $blockers = [...$blockers, ...self::windowBlockers($effective)];
        }

        if ($kind === AffiliateMovement::KIND_REMOVE) {
            if (! in_array(strtoupper(trim((string) $affiliate->status)), self::COUNTABLE, true)) {
                $blockers[] = 'El afiliado ya no está activo (está «'.$affiliate->status.'»).';
            }

            $open = self::openActivity($owner, $affiliate);
            if ($open !== []) {
                $blockers[] = 'No se puede dar de baja: tiene actividad abierta en Operaciones/Telemedicina ('.implode(' · ', $open).'). El equipo de Operaciones debe cerrarla o reasignarla primero.';
            }
        }

        $billing = $effective !== null ? self::billingPlan($owner, $affiliate, $kind, $effective) : null;
        if ($billing !== null) {
            $blockers = [...$blockers, ...$billing['blockers']];
            $warnings = [...$warnings, ...$billing['warnings']];
        }

        return [
            'blockers' => array_values(array_unique($blockers)),
            'warnings' => array_values(array_unique($warnings)),
            'billing' => $billing,
            'summary' => self::summary($kind, $affiliate, $billing),
            'fingerprint' => self::fingerprint($billing),
        ];
    }

    /**
     * Baja de un afiliado activo.
     */
    public static function exclude(Model $owner, Model $affiliate, mixed $effectiveDate, string $reason, User $actor, string $source = '', ?string $expectedFingerprint = null): AffiliateMovement
    {
        return self::run(AffiliateMovement::KIND_REMOVE, $owner, $affiliate, $effectiveDate, $reason, $actor, $source, $expectedFingerprint);
    }

    /**
     * Alta de un afiliado que ACABA de guardarse como ACTIVO. Debe llamarse dentro de la
     * misma transacción que lo creó: si algo falla aquí, el alta tampoco queda.
     */
    public static function include(Model $owner, Model $affiliate, mixed $effectiveDate, string $reason, User $actor, string $source = '', ?string $expectedFingerprint = null): AffiliateMovement
    {
        return self::run(AffiliateMovement::KIND_ADD, $owner, $affiliate, $effectiveDate, $reason, $actor, $source, $expectedFingerprint);
    }

    /**
     * Qué impide revertir un movimiento, o null si se puede.
     */
    public static function revertBlocker(AffiliateMovement $movement): ?string
    {
        if (! $movement->isApplied()) {
            return 'Este movimiento ya fue revertido.';
        }

        $later = AffiliateMovement::query()
            ->where('affiliation_type', $movement->affiliation_type)
            ->where('affiliate_id', $movement->affiliate_id)
            ->where('status', AffiliateMovement::STATUS_APPLIED)
            ->where('id', '>', $movement->getKey())
            ->exists();

        if ($later) {
            return 'Hay un movimiento posterior de esta persona. Revierta primero el más reciente.';
        }

        $affiliate = self::affiliateModel($movement)::query()->withTrashed()->find($movement->affiliate_id);
        if (! $affiliate instanceof Model) {
            return 'El afiliado ya no existe.';
        }

        $active = in_array(strtoupper(trim((string) $affiliate->status)), self::COUNTABLE, true) && $affiliate->deleted_at === null;
        if ($movement->isAddition() && ! $active) {
            return 'El afiliado ya no está activo: la inclusión no se puede revertir.';
        }

        if (! $movement->isAddition() && $active) {
            return 'El afiliado ya está activo otra vez: la exclusión no se puede revertir.';
        }

        if ($movement->isAddition()) {
            $owner = self::ownerModel($movement)::query()->find($movement->affiliation_id);
            $open = $owner instanceof Model ? self::openActivity($owner, $affiliate) : [];
            if ($open !== []) {
                return 'No se puede revertir la inclusión: la persona ya tiene actividad abierta ('.implode(' · ', $open).').';
            }
        }

        return null;
    }

    /**
     * Revierte un movimiento: la persona vuelve a su estado y la cobranza recibe lo inverso.
     * Lo que ya está pagado no se toca: su inverso va al próximo aviso pendiente.
     */
    public static function revert(AffiliateMovement $movement, string $reason, User $actor): AffiliateMovement
    {
        $reason = self::assertReason($reason);

        $movement = DB::transaction(static function () use ($movement, $reason, $actor): AffiliateMovement {
            $movement = AffiliateMovement::query()->lockForUpdate()->findOrFail($movement->getKey());
            $blocker = self::revertBlocker($movement);
            if ($blocker !== null) {
                throw new RuntimeException($blocker);
            }

            $owner = self::ownerModel($movement)::query()->lockForUpdate()->findOrFail($movement->affiliation_id);
            $affiliate = self::affiliateModel($movement)::query()->withTrashed()->lockForUpdate()->findOrFail($movement->affiliate_id);
            $batch = (string) Str::uuid();
            $effects = [];
            $label = ($movement->isAddition() ? 'Reverso de inclusión' : 'Reverso de exclusión').' de '.$movement->affiliate_name;

            $linked = self::linkedPatients($owner, $affiliate);

            if ($movement->isAddition()) {
                $affiliate->delete();
                $effects['affiliate'] = 'eliminado (reversible)';
            } else {
                $affiliate->forceFill(['status' => (string) ($movement->snapshot['affiliate']['status'] ?? 'ACTIVO')])->save();
                $effects['affiliate'] = 'reactivado';
            }

            $effects['plan_row'] = self::movePlanRow($owner, $affiliate, $movement->isAddition() ? -1 : 1);
            $effects['totals'] = self::recalculateTotals($owner);
            $effects['patients'] = self::syncPatients($linked, $movement->isAddition() ? 'INACTIVO' : 'ACTIVO');

            // Cobranza: cada aviso que el movimiento tocó.
            $loose = 0;
            $restored = [];
            foreach ((array) ($movement->effects['collections'] ?? []) as $change) {
                $collection = Collection::query()->lockForUpdate()->find((int) $change['id']);
                $deltaCents = (int) $change['delta_cents'];

                if ($collection instanceof Collection && strtoupper(trim((string) $collection->status)) === self::PENDING && self::cents($collection->total_amount) === (int) $change['after_cents']) {
                    $before = (string) $collection->total_amount;
                    $collection->total_amount = self::decimal((int) $change['before_cents']);
                    if (array_key_exists('persons_before', $change) && $change['persons_before'] !== null) {
                        $collection->persons = (string) $change['persons_before'];
                    }
                    $collection->save();
                    self::trace($collection, $batch, ['total_amount' => ['before' => $before, 'after' => (string) $collection->total_amount]], $label.': '.$reason, $actor);
                    $restored[] = $collection->collection_invoice_number;

                    continue;
                }

                // Ya pagado o modificado después: su inverso va a los avisos pendientes.
                $loose -= $deltaCents;
            }

            CollectionMovementLine::query()->where('affiliate_movement_id', $movement->getKey())->update(['reverted' => true, 'updated_at' => now()]);

            if ($movement->charge_collection_id !== null) {
                $charge = Collection::query()->lockForUpdate()->find($movement->charge_collection_id);
                if ($charge instanceof Collection && strtoupper(trim((string) $charge->status)) === self::PENDING) {
                    $charge->status = self::CANCELLED;
                    $charge->save();
                    self::trace($charge, $batch, ['status' => ['before' => self::PENDING, 'after' => self::CANCELLED]], $label.': '.$reason, $actor);
                    $effects['charge'] = 'aviso '.$charge->collection_invoice_number.' anulado';
                } elseif ($charge instanceof Collection) {
                    $loose -= self::cents($charge->total_amount);
                    $effects['charge'] = 'aviso '.$charge->collection_invoice_number.' ya pagado: se acredita';
                }
            }

            // Crédito pendiente: lo no usado se cancela; lo ya descontado se cobra de vuelta.
            $creditApplied = (int) array_sum(array_column((array) ($movement->effects['credit_applications'] ?? []), 'cents'));
            $loose += $creditApplied;
            $movement->pending_credit = 0;

            $effects['restored'] = $restored;
            if ($loose !== 0) {
                $effects['loose'] = self::placeLoose($owner, $movement, $loose, $label, $reason, $actor, $batch);
            }

            $movement->forceFill([
                'status' => AffiliateMovement::STATUS_REVERTED,
                'reverted_at' => now(),
                'reverted_by_id' => $actor->getKey(),
                'reverted_by_name' => (string) $actor->name,
                'revert_reason' => $reason,
                'revert_effects' => $effects,
            ])->save();

            AffiliationDateCorrector::log($owner, DateCorrectionSubject::for($owner), 'REVERSO DE MOVIMIENTO', mb_substr($label.' (movimiento #'.$movement->getKey().'). Motivo: '.$reason, 0, 250), $actor);
            SecurityAudit::log('AUDIT_AFFILIATE_MOVEMENT_REVERTED', 'affiliations.movements.revert', [
                'movement_id' => $movement->getKey(),
                'affiliation_code' => $movement->affiliation_code,
                'kind' => $movement->kind,
                'effects' => $effects,
                'reason' => $reason,
            ], $actor);

            return $movement;
        });

        self::flushPatientCaches((array) ($movement->revert_effects['patients'] ?? []));

        return $movement;
    }

    /**
     * Descuenta créditos pendientes (bajas sin aviso donde descontarlas) del aviso recién creado.
     * Se llama al crear un aviso de cobro; nunca rompe la creación.
     */
    public static function applyPendingCredits(Collection $collection): void
    {
        try {
            if ($collection->billing_adjustment_id !== null
                || strtoupper(trim((string) $collection->status)) !== self::PENDING
                || blank($collection->affiliation_code)
                || in_array(strtoupper((string) $collection->service), self::NON_CALENDAR_SERVICES, true)
                || ! AffiliateMovement::query()->where('affiliation_code', $collection->affiliation_code)->where('pending_credit', '>', 0)->exists()) {
                return;
            }

            DB::transaction(static function () use ($collection): void {
                $movements = AffiliateMovement::query()
                    ->where('affiliation_code', $collection->affiliation_code)
                    ->where('status', AffiliateMovement::STATUS_APPLIED)
                    ->where('pending_credit', '>', 0)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($movements->isEmpty()) {
                    return;
                }

                $fresh = Collection::query()->lockForUpdate()->find($collection->getKey());
                if (! $fresh instanceof Collection) {
                    return;
                }

                foreach ($movements as $movement) {
                    $available = self::cents($fresh->total_amount);
                    $credit = self::cents($movement->pending_credit);
                    $apply = min($available, $credit);
                    if ($apply <= 0) {
                        break;
                    }

                    $before = (string) $fresh->total_amount;
                    $fresh->total_amount = self::decimal($available - $apply);
                    $fresh->save();

                    CollectionMovementLine::query()->create([
                        'collection_id' => $fresh->getKey(),
                        'affiliate_movement_id' => $movement->getKey(),
                        'kind' => CollectionMovementLine::KIND_CREDIT,
                        'description' => 'Crédito por exclusión de '.$movement->affiliate_name.' ('.$movement->effective_date?->format('d/m/Y').')',
                        'amount' => self::decimal(-$apply),
                    ]);

                    $effects = (array) $movement->effects;
                    $effects['credit_applications'][] = ['collection_id' => (int) $fresh->getKey(), 'invoice' => $fresh->collection_invoice_number, 'cents' => $apply, 'at' => now()->toIso8601String()];
                    $movement->forceFill(['pending_credit' => self::decimal($credit - $apply), 'effects' => $effects])->save();

                    CollectionAdjustment::query()->create([
                        'collection_id' => $fresh->getKey(),
                        'affiliation_code' => $fresh->affiliation_code,
                        'collection_invoice_number' => $fresh->collection_invoice_number,
                        'batch_uuid' => (string) $movement->public_id,
                        'mode' => CollectionAdjustment::MODE_POPULATION,
                        'changes' => ['total_amount' => ['before' => $before, 'after' => (string) $fresh->total_amount]],
                        'reason' => 'Crédito pendiente por exclusión de '.$movement->affiliate_name,
                        'performed_by_id' => null,
                        'performed_by_name' => 'Sistema',
                    ]);
                }
            });
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private static function run(string $kind, Model $owner, Model $affiliate, mixed $effectiveDate, string $reason, User $actor, string $source, ?string $expectedFingerprint): AffiliateMovement
    {
        $reason = self::assertReason($reason);

        $movement = DB::transaction(static function () use ($kind, $owner, $affiliate, $effectiveDate, $reason, $actor, $source, $expectedFingerprint): AffiliateMovement {
            $owner = $owner::query()->lockForUpdate()->findOrFail($owner->getKey());
            $affiliate = $affiliate::query()->lockForUpdate()->findOrFail($affiliate->getKey());

            if ((int) $affiliate->getAttribute(self::ownerKey($owner)) !== (int) $owner->getKey()) {
                throw new RuntimeException('El afiliado no pertenece a esta afiliación.');
            }

            if ($kind === AffiliateMovement::KIND_ADD && ! in_array(strtoupper(trim((string) $affiliate->status)), self::COUNTABLE, true)) {
                throw new RuntimeException('El afiliado incluido debe quedar activo.');
            }

            $preview = self::preview($owner, $affiliate, $kind, $effectiveDate);
            if ($preview['blockers'] !== []) {
                throw new RuntimeException($preview['blockers'][0]);
            }

            if ($expectedFingerprint !== null && ! hash_equals($expectedFingerprint, $preview['fingerprint'])) {
                throw new RuntimeException('La cobranza cambió desde la vista previa (otra persona registró un pago o un ajuste). Cierre la ventana y vuelva a intentarlo.');
            }

            $effective = self::date($effectiveDate);
            $billing = $preview['billing'];
            $linked = self::linkedPatients($owner, $affiliate);

            $snapshot = [
                'affiliate' => $affiliate->getAttributes(),
                'owner' => self::totalsOf($owner),
            ];

            $effects = [];

            if ($kind === AffiliateMovement::KIND_REMOVE) {
                $affiliate->forceFill(['status' => 'INACTIVO'])->save();
            }

            $effects['plan_row'] = self::movePlanRow($owner, $affiliate, $kind === AffiliateMovement::KIND_ADD ? 1 : -1);
            $effects['totals'] = self::recalculateTotals($owner);
            $effects['patients'] = self::syncPatients($linked, $kind === AffiliateMovement::KIND_ADD ? 'ACTIVO' : 'INACTIVO');

            $movement = AffiliateMovement::query()->create([
                'public_id' => (string) Str::uuid(),
                'affiliation_type' => $owner instanceof AffiliationCorporate ? AffiliateMovement::TYPE_CORPORATE : AffiliateMovement::TYPE_INDIVIDUAL,
                'affiliation_id' => $owner->getKey(),
                'affiliation_code' => (string) $owner->code,
                'affiliate_id' => $affiliate->getKey(),
                'affiliate_name' => self::nameOf($affiliate),
                'affiliate_document' => (string) $affiliate->nro_identificacion,
                'kind' => $kind,
                'status' => AffiliateMovement::STATUS_APPLIED,
                'effective_date' => $effective->toDateString(),
                'payment_frequency' => $billing['frequency'] ?? (string) $owner->payment_frequency,
                'annual_fee' => self::decimal((int) ($billing['annual_cents'] ?? self::cents($affiliate->fee))),
                'period_fee' => self::decimal((int) ($billing['calculation']['period_cents'] ?? 0)),
                'period_start' => ($billing['calculation']['period']['start'] ?? null)?->toDateString(),
                'period_end' => isset($billing['calculation']['period']['end']) ? $billing['calculation']['period']['end']->subDay()->toDateString() : null,
                'period_days' => (int) ($billing['calculation']['period']['days'] ?? 0),
                'prorated_days' => (int) ($billing['calculation']['prorated_days'] ?? 0),
                'prorated_amount' => self::decimal((int) ($billing['calculation']['prorated_cents'] ?? 0)),
                'pending_credit' => self::decimal((int) ($billing['calculation']['pending_credit_cents'] ?? 0)),
                'snapshot' => $snapshot,
                'effects' => $effects,
                'billing_note' => $billing['note'] ?? null,
                'reason' => $reason,
                'created_by_id' => $actor->getKey(),
                'created_by_name' => (string) $actor->name,
                'source' => mb_substr($source, 0, 80),
            ]);

            $effects['collections'] = self::applyBilling($owner, $movement, $billing, $reason, $actor);

            if (($billing['calculation']['charge_cents'] ?? 0) > 0) {
                $charge = self::createChargeCollection($owner, $movement, (int) $billing['calculation']['charge_cents'], $billing, $actor);
                $movement->charge_collection_id = $charge->getKey();
                $effects['charge'] = 'aviso '.$charge->collection_invoice_number;
            }

            $movement->effects = $effects;
            $movement->save();

            // La bitácora de la afiliación admite 255 caracteres: el detalle completo vive en el historial de movimientos.
            $text = ($kind === AffiliateMovement::KIND_ADD ? 'Inclusión' : 'Exclusión').' de '.$movement->affiliate_name.' desde el '.$effective->format('d/m/Y').' (movimiento #'.$movement->getKey().'). Motivo: '.$reason;
            AffiliationDateCorrector::log($owner, DateCorrectionSubject::for($owner), $kind === AffiliateMovement::KIND_ADD ? 'INCLUSION DE AFILIADO' : 'EXCLUSION DE AFILIADO', mb_substr($text, 0, 250), $actor);

            SecurityAudit::log('AUDIT_AFFILIATE_MOVEMENT_APPLIED', 'affiliations.movements.apply', [
                'movement_id' => $movement->getKey(),
                'affiliation_code' => $movement->affiliation_code,
                'kind' => $kind,
                'affiliate_id' => $movement->affiliate_id,
                'effective_date' => $movement->effective_date?->toDateString(),
                'effects' => $effects,
                'source' => $source,
            ], $actor);

            return $movement;
        });

        self::flushPatientCaches((array) ($movement->effects['patients'] ?? []));

        return $movement;
    }

    /**
     * Plan de cobranza del movimiento.
     *
     * @return array<string, mixed>
     */
    private static function billingPlan(Model $owner, Model $affiliate, string $kind, CarbonImmutable $effective): array
    {
        $plan = ['blockers' => [], 'warnings' => [], 'calculation' => null, 'note' => null, 'frequency' => null, 'annual_cents' => 0, 'installments' => []];
        $frequency = CorporatePaymentFrequency::normalize((string) $owner->payment_frequency);

        $annualCents = max(0, self::cents($affiliate->fee) - self::cents(UpgradeFirstPaymentBilling::affiliateOneTimeTotal($affiliate)));
        $plan['annual_cents'] = $annualCents;

        if ($annualCents === 0) {
            $plan['warnings'][] = 'El afiliado no tiene tarifa: el movimiento no cambia la cobranza.';
        }

        $hasInstallments = Collection::query()->calendarInstallments()->where('affiliation_code', $owner->code)->whereIn('status', [self::PENDING, self::PAID])->exists();
        if (! $hasInstallments) {
            $plan['note'] = 'La afiliación todavía no tiene avisos de cobro: se emitirán con los totales ya recalculados.';

            return $plan;
        }

        if ($frequency === null) {
            $plan['blockers'][] = 'La afiliación no tiene una frecuencia de pago válida («'.$owner->payment_frequency.'»). Corríjala antes de mover afiliados.';

            return $plan;
        }

        $proofModel = $owner instanceof AffiliationCorporate ? PaidMembershipCorporate::class : PaidMembership::class;
        $proofKey = $owner instanceof AffiliationCorporate ? 'affiliation_corporate_id' : 'affiliation_id';
        if ($proofModel::query()->where($proofKey, $owner->getKey())->where('status', self::PROOF_PENDING)->exists()) {
            $plan['blockers'][] = 'Hay un comprobante de pago pendiente de aprobación en esta afiliación. Apruébelo o recházelo antes: cambia qué avisos están pagados.';

            return $plan;
        }

        $months = CorporatePaymentFrequency::monthsPerInstallment($frequency);
        $perYear = CorporatePaymentFrequency::installmentsPerYear($frequency);
        $calendar = AffiliationDateCorrector::calendar($owner);
        $cycleStart = $calendar['current_start'] instanceof CarbonImmutable ? $calendar['current_start']->startOfDay() : null;

        if ($cycleStart === null) {
            $plan['blockers'][] = 'No se pudo determinar el ciclo de cobro de la afiliación. Revise sus fechas en «Inconsistencias de fechas».';

            return $plan;
        }

        $periods = Base::periods($cycleStart, $months, $perYear);
        [$installments, $installmentBlockers] = self::installments($owner, $cycleStart, $cycleStart->addMonthsNoOverflow(12), $months);
        $calculation = MovementBillingCalculator::calculate($kind === AffiliateMovement::KIND_ADD ? MovementBillingCalculator::ADD : MovementBillingCalculator::REMOVE, $effective, $annualCents, $perYear, $periods, $installments);

        foreach ($calculation['changes'] as $change) {
            if ($change['after_cents'] > self::MAX_COLLECTION_CENTS) {
                $plan['blockers'][] = 'El aviso '.$change['invoice'].' superaría el máximo que admite un aviso.';
            }
        }

        $plan['blockers'] = [...$plan['blockers'], ...$installmentBlockers, ...$calculation['blockers']];
        $plan['frequency'] = $frequency;
        $plan['per_year'] = $perYear;
        $plan['cycle_start'] = $cycleStart;
        $plan['installments'] = $installments;
        $plan['calculation'] = $calculation;

        return $plan;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function applyBilling(Model $owner, AffiliateMovement $movement, ?array $billing, string $reason, User $actor): array
    {
        $calculation = $billing['calculation'] ?? null;
        if ($calculation === null) {
            return [];
        }

        $batch = (string) $movement->public_id;
        $applied = [];
        $who = $movement->affiliate_name;
        $verb = $movement->isAddition() ? 'inclusión' : 'exclusión';

        foreach ($calculation['changes'] as $change) {
            $collection = Collection::query()->lockForUpdate()->find($change['id']);
            if (! $collection instanceof Collection || strtoupper(trim((string) $collection->status)) !== self::PENDING || self::cents($collection->total_amount) !== $change['before_cents']) {
                throw new RuntimeException('El aviso '.$change['invoice'].' cambió mientras se registraba el movimiento. No se guardó nada; vuelva a intentarlo.');
            }

            $before = ['total_amount' => (string) $collection->total_amount, 'persons' => (string) $collection->persons];
            $collection->total_amount = self::decimal($change['after_cents']);
            if ($change['persons_after'] !== null) {
                $collection->persons = (string) $change['persons_after'];
            }
            $collection->save();

            foreach ($change['lines'] as $line) {
                $isProration = $line['kind'] === 'PRORRATEO';
                CollectionMovementLine::query()->create([
                    'collection_id' => $collection->getKey(),
                    'affiliate_movement_id' => $movement->getKey(),
                    'kind' => $isProration ? CollectionMovementLine::KIND_PRORATION : CollectionMovementLine::KIND_PERIOD,
                    'description' => $isProration
                        ? 'Prorrateo por '.$verb.' de '.$who.' ('.$movement->effective_date?->format('d/m/Y').', '.$movement->prorated_days.' de '.$movement->period_days.' días)'
                        : ($movement->isAddition() ? 'Cuota de '.$who.' (incluido el '.$movement->effective_date?->format('d/m/Y').')' : 'Sin la cuota de '.$who.' (excluido el '.$movement->effective_date?->format('d/m/Y').')'),
                    'amount' => self::decimal($line['cents']),
                ]);
            }

            self::trace($collection, $batch, [
                'total_amount' => ['before' => $before['total_amount'], 'after' => (string) $collection->total_amount],
                'persons' => ['before' => $before['persons'], 'after' => (string) $collection->persons],
            ], ucfirst($verb).' de '.$who.': '.$reason, $actor);

            $applied[] = [
                'id' => (int) $collection->getKey(),
                'invoice' => (string) $collection->collection_invoice_number,
                'before_cents' => $change['before_cents'],
                'after_cents' => $change['after_cents'],
                'delta_cents' => $change['delta_cents'],
                'persons_before' => $change['persons_before'],
                'persons_after' => $change['persons_after'],
            ];
        }

        return $applied;
    }

    /**
     * Monto suelto del reverso (lo que no se pudo deshacer porque ya se pagó): a los avisos
     * pendientes; si sobra, un aviso aparte (cobro) o crédito pendiente.
     *
     * @return array<string, mixed>
     */
    private static function placeLoose(Model $owner, AffiliateMovement $movement, int $cents, string $label, string $reason, User $actor, string $batch): array
    {
        $frequency = CorporatePaymentFrequency::normalize((string) $owner->payment_frequency);
        $months = $frequency !== null ? CorporatePaymentFrequency::monthsPerInstallment($frequency) : 12;
        $calendar = AffiliationDateCorrector::calendar($owner);
        $cycleStart = $calendar['current_start'] instanceof CarbonImmutable ? $calendar['current_start'] : CarbonImmutable::today();
        [$installments] = self::installments($owner, $cycleStart, $cycleStart->addMonthsNoOverflow(12), $months);
        $today = CarbonImmutable::today();
        $installments = array_filter($installments, static fn (array $i): bool => $i['state'] === Base::STATE_PENDING && $i['due']->greaterThanOrEqualTo($today->subMonthsNoOverflow($months)));

        $spread = MovementBillingCalculator::spread($cents, $installments);
        foreach ($spread['changes'] as $change) {
            $collection = Collection::query()->lockForUpdate()->findOrFail($change['id']);
            $before = (string) $collection->total_amount;
            $collection->total_amount = self::decimal($change['after_cents']);
            $collection->save();
            CollectionMovementLine::query()->create([
                'collection_id' => $collection->getKey(),
                'affiliate_movement_id' => $movement->getKey(),
                'kind' => CollectionMovementLine::KIND_REVERSAL,
                'description' => $label.' (ya facturado)',
                'amount' => self::decimal($change['delta_cents']),
            ]);
            self::trace($collection, $batch, ['total_amount' => ['before' => $before, 'after' => (string) $collection->total_amount]], $label.': '.$reason, $actor);
        }

        $rest = $spread['rest_cents'];
        $out = ['spread' => $spread['changes'], 'rest_cents' => $rest];

        if ($rest > 0) {
            $charge = self::createChargeCollection($owner, $movement, $rest, ['frequency' => $frequency], $actor, 'REVERSO DE MOVIMIENTO');
            $out['charge'] = $charge->collection_invoice_number;
        } elseif ($rest < 0) {
            $movement->pending_credit = self::decimal(-$rest);
            $out['pending_credit_cents'] = -$rest;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $billing
     */
    private static function createChargeCollection(Model $owner, AffiliateMovement $movement, int $cents, array $billing, User $actor, string $service = 'PRORRATEO POR INCLUSION'): Collection
    {
        $template = Collection::query()->calendarInstallments()->where('affiliation_code', $owner->code)->orderByDesc('id')->first();
        if (! $template instanceof Collection) {
            throw new RuntimeException('La afiliación no tiene avisos de cobro de referencia para emitir el aviso del prorrateo.');
        }

        $lastNumber = (string) (Collection::query()->orderByDesc('id')->lockForUpdate()->value('collection_invoice_number') ?? '');

        $charge = $template->replicate(['sale_id', 'pay_amount_usd', 'pay_amount_ves', 'bank_usd', 'bank_ves', 'reference', 'payment_method', 'follow_up_status']);
        $charge->forceFill([
            'collection_invoice_number' => \App\Http\Controllers\UtilsController::generateCorrelativeCollection($lastNumber),
            'service' => $service,
            'persons' => '1',
            'next_payment_date' => CarbonImmutable::today()->format('d/m/Y'),
            'total_amount' => self::decimal($cents),
            'status' => self::PENDING,
            'days' => 0,
            'created_by' => (string) $actor->name,
            'affiliate_status' => (string) $owner->status,
        ]);
        $charge->save();

        CollectionMovementLine::query()->create([
            'collection_id' => $charge->getKey(),
            'affiliate_movement_id' => $movement->getKey(),
            'kind' => CollectionMovementLine::KIND_CHARGE,
            'description' => $service === 'PRORRATEO POR INCLUSION'
                ? 'Prorrateo por inclusión de '.$movement->affiliate_name.' ('.$movement->effective_date?->format('d/m/Y').', '.$movement->prorated_days.' de '.$movement->period_days.' días)'
                : 'Reverso de movimiento de '.$movement->affiliate_name,
            'amount' => self::decimal($cents),
        ]);

        self::trace($charge, (string) $movement->public_id, ['status' => ['before' => null, 'after' => self::PENDING], 'total_amount' => ['before' => null, 'after' => self::decimal($cents)]], $service.' de '.$movement->affiliate_name, $actor);

        return $charge;
    }

    /**
     * Avisos del ciclo por índice de período (mismo criterio que «Ajustes por población»).
     *
     * @return array{0: array<int, array<string, mixed>>, 1: list<string>}
     */
    private static function installments(Model $owner, CarbonImmutable $cycleStart, CarbonImmutable $cycleEnd, int $months): array
    {
        $collections = Collection::query()
            ->calendarInstallments()
            ->where('affiliation_code', $owner->code)
            ->whereIn('status', [self::PENDING, self::PAID])
            ->orderBy('id')
            ->get(['id', 'collection_invoice_number', 'next_payment_date', 'filter_next_payment_date', 'total_amount', 'persons', 'status', 'service']);

        $installments = [];
        $blockers = [];

        foreach ($collections as $collection) {
            if (in_array(strtoupper((string) $collection->service), self::NON_CALENDAR_SERVICES, true)) {
                continue;
            }

            $due = CollectionDueDate::of($collection);
            $invoice = (string) ($collection->collection_invoice_number ?: '#'.$collection->id);

            if ($due === null) {
                $blockers[] = 'El aviso '.$invoice.' no tiene una fecha de pago válida. Corríjalo con «Ajustar cuota» antes de mover afiliados.';

                continue;
            }

            if ($due->lessThan($cycleStart) || $due->greaterThanOrEqualTo($cycleEnd)) {
                continue;
            }

            $index = AffiliationDateCorrector::installmentIndex($cycleStart, $due, $months);
            if ($index === null) {
                $blockers[] = 'El aviso '.$invoice.' ('.$due->format('d/m/Y').') no cae en el calendario de cobro. Corríjalo en «Inconsistencias de fechas» antes de mover afiliados.';

                continue;
            }

            if (isset($installments[$index])) {
                $blockers[] = 'Hay dos avisos para el mismo período ('.$installments[$index]['invoice'].' y '.$invoice.'). Anule el duplicado antes de mover afiliados.';

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
                'state' => strtoupper(trim((string) $collection->status)) === self::PAID ? Base::STATE_PAID : Base::STATE_PENDING,
            ];
        }

        ksort($installments);

        return [$installments, $blockers];
    }

    /**
     * Fila de plan de la afiliación corporativa: personas ± 1 y su subtotal recalculado.
     *
     * @return array<string, mixed>|null
     */
    private static function movePlanRow(Model $owner, Model $affiliate, int $delta): ?array
    {
        if (! $owner instanceof AffiliationCorporate || ! $affiliate instanceof AffiliateCorporate) {
            return null;
        }

        $row = self::planRowFor($owner, $affiliate);
        if (! $row instanceof AfilliationCorporatePlan) {
            return ['row' => null, 'note' => 'No se encontró la fila de plan del afiliado (plan, cobertura y edad): revísela en la afiliación.'];
        }

        $before = (int) $row->total_persons;
        $row->total_persons = max(0, $before + $delta);
        CorporateAffiliateRemovalService::recalculateCorporatePlanRowTotals($row);
        $row->save();

        return ['row' => (int) $row->getKey(), 'before' => $before, 'after' => (int) $row->total_persons];
    }

    private static function planRowFor(AffiliationCorporate $owner, AffiliateCorporate $affiliate): ?AfilliationCorporatePlan
    {
        $rows = AfilliationCorporatePlan::query()->where('affiliation_corporate_id', $owner->getKey())->with('ageRange')->lockForUpdate()->get();
        $age = CorporateAffiliatePlanSynchronizer::ageOf($affiliate);

        $samePlan = $rows->filter(fn (AfilliationCorporatePlan $row): bool => (int) $row->plan_id === (int) $affiliate->plan_id
            && (int) ($row->coverage_id ?? 0) === (int) ($affiliate->coverage_id ?? 0));

        if ($age !== null) {
            $byAge = $samePlan->filter(fn (AfilliationCorporatePlan $row): bool => $row->ageRange !== null && $age >= (int) $row->ageRange->age_init && $age <= (int) $row->ageRange->age_end);
            if ($byAge->count() === 1) {
                return $byAge->first();
            }
        }

        if ($samePlan->count() === 1) {
            return $samePlan->first();
        }

        $resolved = CorporateAffiliatePlanSynchronizer::resolvePlanRowForAffiliate($owner, $affiliate, $rows->filter(fn ($row) => $row->ageRange !== null)->values())['row'];

        return $resolved instanceof AfilliationCorporatePlan ? $rows->firstWhere('id', $resolved->getKey()) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function recalculateTotals(Model $owner): array
    {
        $before = self::totalsOf($owner);

        if ($owner instanceof AffiliationCorporate) {
            $live = ActivePopulationTotals::compute($owner);
            $owner->forceFill(['poblation' => $live['persons'], 'fee_anual' => $live['fee_anual'], 'total_amount' => $live['total_amount']])->save();
        } else {
            $live = \App\Support\Affiliations\ActiveBilling\ActivePopulationBilling::live($owner);
            $owner->forceFill(['family_members' => $live['persons'], 'fee_anual' => $live['fee_anual'], 'total_amount' => $live['total_amount']])->save();
        }

        return ['before' => $before, 'after' => self::totalsOf($owner)];
    }

    /**
     * @return array{persons: int, fee_anual: float, total_amount: float}
     */
    private static function totalsOf(Model $owner): array
    {
        return [
            'persons' => (int) ($owner instanceof AffiliationCorporate ? $owner->poblation : $owner->family_members),
            'fee_anual' => round((float) $owner->fee_anual, 2),
            'total_amount' => round((float) $owner->total_amount, 2),
        ];
    }

    /**
     * @return list<TelemedicinePatient>
     */
    private static function linkedPatients(Model $owner, Model $affiliate): array
    {
        if ($owner instanceof AffiliationCorporate && $affiliate instanceof AffiliateCorporate) {
            return PopulationActivityInspector::linkedPatients($owner, [$affiliate])[(int) $affiliate->getKey()] ?? [];
        }

        $key = ltrim((string) preg_replace('/\D+/', '', (string) $affiliate->nro_identificacion), '0');
        if ($key === '') {
            return [];
        }

        return TelemedicinePatient::query()
            ->where('afilliation_id', $owner->getKey())
            ->where(fn ($q) => $q->where('type_affiliation', 'INDIVIDUAL')->orWhereNull('type_affiliation'))
            ->get(['id', 'nro_identificacion', 'status_affiliation'])
            ->filter(fn (TelemedicinePatient $patient): bool => ltrim((string) preg_replace('/\D+/', '', (string) $patient->nro_identificacion), '0') === $key)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private static function openActivity(Model $owner, Model $affiliate): array
    {
        $patients = self::linkedPatients($owner, $affiliate);
        if ($patients === []) {
            return [];
        }

        return PopulationActivityInspector::openActivity([(int) $affiliate->getKey() => $patients])[(int) $affiliate->getKey()] ?? [];
    }

    /**
     * @param  list<TelemedicinePatient>  $patients
     * @return list<array{id: int, before: ?string, after: string}>
     */
    private static function syncPatients(array $patients, string $status): array
    {
        $out = [];
        foreach ($patients as $patient) {
            $before = $patient->status_affiliation;
            if ($before === $status) {
                continue;
            }

            $patient->forceFill(['status_affiliation' => $status])->save();
            $out[] = ['id' => (int) $patient->getKey(), 'before' => $before, 'after' => $status];
        }

        return $out;
    }

    /**
     * @param  list<array{id: int}>  $patients
     */
    private static function flushPatientCaches(array $patients): void
    {
        foreach ($patients as $patient) {
            try {
                TelemedicinePatientPlanBridge::flushCachesFor((int) $patient['id']);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    /**
     * @param  array<string, mixed>|null  $billing
     * @return list<string>
     */
    private static function summary(string $kind, Model $affiliate, ?array $billing): array
    {
        $calculation = $billing['calculation'] ?? null;
        $lines = [];

        if ($billing !== null && $billing['note'] !== null) {
            $lines[] = $billing['note'];
        }

        if ($calculation === null || $calculation['period'] === null) {
            return $lines;
        }

        $money = static fn (int $cents): string => Base::money($cents);
        $add = $kind === AffiliateMovement::KIND_ADD;
        $lines[] = 'Cuota de la persona: '.$money($calculation['period_cents']).' por período. Prorrateo: '.$calculation['prorated_days'].' de '.$calculation['period']['days'].' días = '.$money($calculation['prorated_cents']).($add ? ' a cobrar.' : ' a acreditar.');

        foreach ($calculation['changes'] as $change) {
            $lines[] = 'Aviso '.$change['invoice'].' ('.$change['due']->format('d/m/Y').'): '.$money($change['before_cents']).' → '.$money($change['after_cents']).'.';
        }

        if ($calculation['charge_cents'] > 0) {
            $lines[] = 'No hay un aviso pendiente donde sumarlo: se emite un aviso aparte por '.$money($calculation['charge_cents']).'.';
        }

        if ($calculation['pending_credit_cents'] > 0) {
            $lines[] = 'No hay un aviso pendiente donde descontarlo: queda un crédito de '.$money($calculation['pending_credit_cents']).' que se descontará en el próximo aviso que se emita (renovación).';
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>|null  $billing
     */
    private static function fingerprint(?array $billing): string
    {
        $installments = array_map(static fn (array $i): string => $i['id'].':'.$i['amount_cents'].':'.$i['state'], array_values((array) ($billing['installments'] ?? [])));

        return sha1(json_encode([$installments, $billing['calculation']['prorated_cents'] ?? null, $billing['annual_cents'] ?? null]) ?: '');
    }

    /**
     * @return list<string>
     */
    private static function windowBlockers(CarbonImmutable $effective): array
    {
        $today = CarbonImmutable::today();
        if ($effective->lessThan($today->subDays(self::EFFECTIVE_WINDOW_DAYS)) || $effective->greaterThan($today->addDays(self::EFFECTIVE_WINDOW_DAYS))) {
            return ['La fecha efectiva debe estar entre el '.$today->subDays(self::EFFECTIVE_WINDOW_DAYS)->format('d/m/Y').' y el '.$today->addDays(self::EFFECTIVE_WINDOW_DAYS)->format('d/m/Y').'.'];
        }

        return [];
    }

    /**
     * @param  array<string, array{before: ?string, after: ?string}>  $changes
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
            'reason' => mb_substr($reason, 0, 1000),
            'performed_by_id' => $actor->getKey(),
            'performed_by_name' => (string) $actor->name,
            'ip' => request()?->ip(),
            'user_agent' => Str::limit((string) request()?->userAgent(), 1000, ''),
        ]);
    }

    private static function assertReason(string $reason): string
    {
        $reason = trim((string) preg_replace('/\s+/u', ' ', $reason));
        if (mb_strlen($reason) < self::REASON_MIN_LENGTH || preg_match_all('/\p{L}{3,}/u', $reason) < 3) {
            throw new RuntimeException('Explique el motivo con al menos tres palabras ('.self::REASON_MIN_LENGTH.' caracteres o más).');
        }

        return mb_substr($reason, 0, 1000);
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value)->startOfDay();
        }

        if (blank($value)) {
            return null;
        }

        $value = trim((string) $value);
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $match) === 1) {
            return CarbonImmutable::createFromFormat('!Y-m-d', $match[1]) ?: null;
        }

        return CollectionDueDate::parse($value)?->startOfDay();
    }

    private static function nameOf(Model $affiliate): string
    {
        return $affiliate instanceof AffiliateCorporate
            ? trim($affiliate->first_name.' '.$affiliate->last_name)
            : (string) $affiliate->full_name;
    }

    private static function ownerKey(Model $owner): string
    {
        return $owner instanceof AffiliationCorporate ? 'affiliation_corporate_id' : 'affiliation_id';
    }

    /**
     * @return class-string<Model>
     */
    private static function ownerModel(AffiliateMovement $movement): string
    {
        return $movement->isCorporate() ? AffiliationCorporate::class : Affiliation::class;
    }

    /**
     * @return class-string<Model>
     */
    private static function affiliateModel(AffiliateMovement $movement): string
    {
        return $movement->isCorporate() ? AffiliateCorporate::class : Affiliate::class;
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
