<?php

declare(strict_types=1);

namespace App\Support\Quotes\Adjustments;

use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\AfilliationCorporatePlan;
use App\Models\CorporateQuote;
use App\Models\DetailCorporateQuote;
use App\Models\DetailIndividualQuote;
use App\Models\IndividualQuote;
use App\Models\QuoteValueAdjustment;
use App\Models\QuoteValueAdjustmentAffiliation;
use App\Models\StatusLogCorpQuote;
use App\Models\StatusLogInQuote;
use App\Models\User;
use App\Services\CorporateAffiliateRemovalService;
use App\Support\AffiliationCorporates\CorporateAffiliatePlanSynchronizer;
use App\Support\AffiliationCorporates\CorporatePlanRowCreator;
use App\Support\Affiliations\ActiveBilling\ActivePopulationBilling;
use App\Support\Affiliations\DateCorrection\AffiliationDateCorrector;
use App\Support\Affiliations\DateCorrection\DateCorrectionSubject;
use App\Support\Renovations\EarlyRenovationRecipients;
use App\Support\SecurityAudit;
use App\Support\Upgrades\UpgradeFirstPaymentBilling;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Ajuste de los valores de una cotización (tarifa por persona y cantidad de personas de cada
 * línea) y su efecto automático en la afiliación enlazada y su cobranza.
 *
 * Reglas (Gustavo, 11/11/2026):
 *  - La TARIFA de una línea pasa a la afiliación: en la corporativa a la fila del plan (plan,
 *    cobertura y rango de edad) y a sus afiliados vigentes; en la individual a los afiliados de
 *    esa línea.
 *  - La CANTIDAD de personas cambia solo la cotización: la afiliación se factura siempre con su
 *    población activa real ({@see ActivePopulationBilling}).
 *  - Cobranza: avisos POR PAGAR del año de afiliación en curso, recalculados contra la población
 *    activa. Lo pagado no se toca. La renovación vuelve a cotizar por catálogo.
 *
 * La vista previa es la ejecución real dentro de una transacción que se revierte: lo que se
 * ve es exactamente lo que se aplica.
 */
final class QuoteValueAdjustments
{
    public const REASON_MIN_LENGTH = 15;

    public const MAX_FEE = 1_000_000;

    public const MAX_PERSONS = 100_000;

    /** Afiliaciones a las que llega el ajuste. */
    public const CASCADE_STATUSES = ['ACTIVA', 'PRE-APROBADA'];

    private const AFFILIATE_STATUSES = ['ACTIVO', 'PRE-APROBADA'];

    /**
     * Líneas editables de la cotización, con la marca de si están contratadas en la afiliación.
     *
     * @return list<array{detail_id: int, label: string, plan_id: int, coverage_id: ?int, age_range_id: ?int, fee: string, persons: int, contracted: bool}>
     */
    public static function lines(Model $quote): array
    {
        $isCorporate = $quote instanceof CorporateQuote;
        $details = self::detailQuery($quote)->with(['plan:id,description', 'coverage:id,price', 'ageRange:id,range'])->orderBy('plan_id')->orderBy('coverage_id')->orderBy('age_range_id')->get();
        $owner = self::affiliationsOf($quote)->count() === 1 ? self::affiliationsOf($quote)->first() : null;
        $contracted = [];

        if ($owner instanceof AffiliationCorporate) {
            foreach (AfilliationCorporatePlan::query()->where('affiliation_corporate_id', $owner->getKey())->get(['plan_id', 'coverage_id', 'age_range_id']) as $row) {
                $contracted[self::lineKey($row->plan_id, $row->coverage_id, $row->age_range_id)] = true;
            }
        } elseif ($owner instanceof Affiliation) {
            foreach ($owner->affiliates()->whereIn('status', self::AFFILIATE_STATUSES)->get(['plan_id', 'coverage_id', 'age_range_id']) as $affiliate) {
                $contracted[self::lineKey($affiliate->plan_id, $affiliate->coverage_id, $affiliate->age_range_id)] = true;
            }
        }

        return $details->map(fn (Model $detail): array => [
            'detail_id' => (int) $detail->getKey(),
            'label' => self::labelOf($detail),
            'plan_id' => (int) $detail->plan_id,
            'coverage_id' => self::nullableId($detail->coverage_id),
            'age_range_id' => self::nullableId($detail->age_range_id),
            'fee' => self::decimal(self::cents($detail->fee)),
            'persons' => (int) $detail->total_persons,
            'contracted' => isset($contracted[self::lineKey($detail->plan_id, $detail->coverage_id, $detail->age_range_id)]),
        ])->values()->all();
    }

    /**
     * Qué pasaría con el ajuste, sin guardar nada.
     *
     * @param  list<array<string, mixed>>  $changes  [{detail_id, fee, persons, fee_before?, persons_before?}]
     * @return array{blockers: list<string>, warnings: list<string>, lines: list<array<string, mixed>>, affiliation: array<string, mixed>|null}
     */
    public static function preview(Model $quote, array $changes): array
    {
        DB::beginTransaction();

        try {
            $adjustment = self::execute($quote, $changes, 'Vista previa del ajuste de valores', null, true);

            return ['blockers' => [], 'warnings' => (array) ($adjustment->effects['warnings'] ?? []), 'lines' => (array) $adjustment->lines, 'affiliation' => $adjustment->effects['affiliation'] ?? null];
        } catch (RuntimeException $exception) {
            return ['blockers' => [$exception->getMessage()], 'warnings' => [], 'lines' => [], 'affiliation' => null];
        } finally {
            DB::rollBack();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $changes
     */
    public static function apply(Model $quote, array $changes, string $reason, User $actor): QuoteValueAdjustment
    {
        $reason = self::assertReason($reason);

        $adjustment = DB::transaction(static fn (): QuoteValueAdjustment => self::execute($quote, $changes, $reason, $actor, false));

        self::afterCommit($adjustment, $actor, false);

        return $adjustment;
    }

    /**
     * Qué pasaría al revertir (o por qué no se puede), sin guardar nada.
     *
     * @return array{blocker: ?string, effects: array<string, mixed>}
     */
    public static function revertPreview(QuoteValueAdjustment $adjustment): array
    {
        DB::beginTransaction();

        try {
            $reverted = self::executeRevert($adjustment, 'Vista previa del reverso del ajuste', null, true);

            return ['blocker' => null, 'effects' => (array) $reverted->revert_effects];
        } catch (RuntimeException $exception) {
            return ['blocker' => $exception->getMessage(), 'effects' => []];
        } finally {
            DB::rollBack();
        }
    }

    public static function revert(QuoteValueAdjustment $adjustment, string $reason, User $actor): QuoteValueAdjustment
    {
        $reason = self::assertReason($reason);

        $reverted = DB::transaction(static fn (): QuoteValueAdjustment => self::executeRevert($adjustment, $reason, $actor, false));

        self::afterCommit($reverted, $actor, true);

        return $reverted;
    }

    /**
     * @param  list<array<string, mixed>>  $changes
     */
    private static function execute(Model $quote, array $changes, string $reason, ?User $actor, bool $dryRun): QuoteValueAdjustment
    {
        $isCorporate = $quote instanceof CorporateQuote;
        if (! $isCorporate && ! $quote instanceof IndividualQuote) {
            throw new RuntimeException('Tipo de cotización no admitido.');
        }

        $quote = $quote::query()->lockForUpdate()->findOrFail($quote->getKey());
        $details = self::detailQuery($quote)->with(['plan:id,description', 'coverage:id,price', 'ageRange:id,range,age_init,age_end'])->lockForUpdate()->get()->keyBy('id');

        $lines = [];
        $seen = [];
        foreach ($changes as $change) {
            $detail = $details->get((int) ($change['detail_id'] ?? 0));
            if (! $detail instanceof Model || isset($seen[$detail->getKey()])) {
                throw new RuntimeException('Una de las líneas no pertenece a esta cotización. Cierre la ventana y vuelva a abrirla.');
            }
            $seen[$detail->getKey()] = true;

            if ((array_key_exists('fee_before', $change) && self::cents($change['fee_before']) !== self::cents($detail->fee))
                || (array_key_exists('persons_before', $change) && (int) $change['persons_before'] !== (int) $detail->total_persons)) {
                throw new RuntimeException('La cotización cambió mientras la editaba ('.self::labelOf($detail).'). Cierre la ventana y vuelva a abrirla.');
            }

            $feeCents = self::parseFee($change['fee'] ?? null, self::labelOf($detail));
            $persons = self::parsePersons($change['persons'] ?? null, self::labelOf($detail));

            if ($feeCents === self::cents($detail->fee) && $persons === (int) $detail->total_persons) {
                continue;
            }

            $lines[] = [
                'detail_id' => (int) $detail->getKey(),
                'label' => self::labelOf($detail),
                'plan_id' => (int) $detail->plan_id,
                'coverage_id' => self::nullableId($detail->coverage_id),
                'age_range_id' => self::nullableId($detail->age_range_id),
                'age_init' => $detail->ageRange?->age_init !== null ? (int) $detail->ageRange->age_init : null,
                'age_end' => $detail->ageRange?->age_end !== null ? (int) $detail->ageRange->age_end : null,
                'fee_before' => self::decimal(self::cents($detail->fee)),
                'fee_after' => self::decimal($feeCents),
                'persons_before' => (int) $detail->total_persons,
                'persons_after' => $persons,
            ];
        }

        if ($lines === []) {
            throw new RuntimeException('No cambió ningún valor: modifique al menos una tarifa o una cantidad de personas.');
        }

        $affiliations = self::affiliationsOf($quote)->lockForUpdate()->get();
        if ($affiliations->count() > 1) {
            throw new RuntimeException('Esta cotización está enlazada a '.$affiliations->count().' afiliaciones ('.$affiliations->pluck('code')->implode(', ').'), como las cotizaciones de migración: no se puede saber a cuál corresponde el ajuste. Ajuste cada afiliación desde su ficha.');
        }

        foreach ($lines as $line) {
            $detail = $details->get($line['detail_id']);
            $detail->fee = $line['fee_after'];
            $detail->total_persons = $line['persons_after'];
            self::fillSubtotals($detail);
            $detail->save();
        }

        $adjustment = QuoteValueAdjustment::query()->create([
            'public_id' => (string) Str::uuid(),
            'quote_type' => $isCorporate ? QuoteValueAdjustment::TYPE_CORPORATE : QuoteValueAdjustment::TYPE_INDIVIDUAL,
            'quote_id' => $quote->getKey(),
            'quote_code' => (string) $quote->code,
            'status' => QuoteValueAdjustment::STATUS_APPLIED,
            'reason' => $reason,
            'lines' => $lines,
            'effects' => [],
            'created_by_id' => $actor?->getKey(),
            'created_by_name' => $actor?->name,
        ]);

        $warnings = [];
        $affiliationEffects = null;
        $owner = $affiliations->first();

        if ($owner === null) {
            $warnings[] = 'La cotización no tiene afiliación enlazada: solo cambian los valores de la cotización.';
        } elseif (! in_array(strtoupper(trim((string) $owner->status)), self::CASCADE_STATUSES, true)) {
            $warnings[] = 'La afiliación '.$owner->code.' está '.$owner->status.': solo cambian los valores de la cotización.';
            $affiliationEffects = ['type' => $isCorporate ? QuoteValueAdjustment::TYPE_CORPORATE : QuoteValueAdjustment::TYPE_INDIVIDUAL, 'id' => (int) $owner->getKey(), 'code' => (string) $owner->code, 'status' => (string) $owner->status, 'cascade' => false];
        } else {
            $affiliationEffects = $isCorporate
                ? self::cascadeCorporate($owner, $lines, $adjustment, $reason, $actor, $dryRun)
                : self::cascadeIndividual($owner, $lines, $adjustment, $reason, $actor, $dryRun);
        }

        $adjustment->effects = ['warnings' => $warnings, 'affiliation' => $affiliationEffects];
        $adjustment->save();

        self::logQuote($quote, 'AJUSTE DE VALORES', self::linesText($lines).'. Motivo: '.$reason, $actor);

        if (! $dryRun && $actor instanceof User) {
            if ($owner !== null && ($affiliationEffects['cascade'] ?? false)) {
                AffiliationDateCorrector::log($owner, DateCorrectionSubject::for($owner), 'AJUSTE DE COTIZACION', mb_substr('Ajuste de valores de la cotización '.$quote->code.' (#'.$adjustment->getKey().'). Motivo: '.$reason, 0, 250), $actor);
            }

            SecurityAudit::log('AUDIT_QUOTE_VALUE_ADJUSTMENT_APPLIED', 'business.quotes.adjust-values', [
                'adjustment_id' => $adjustment->getKey(),
                'quote_type' => $adjustment->quote_type,
                'quote_code' => $adjustment->quote_code,
                'lines' => $lines,
                'affiliation' => self::auditSummary($affiliationEffects),
                'reason' => $reason,
            ], $actor);
        }

        return $adjustment;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    private static function cascadeCorporate(AffiliationCorporate $owner, array $lines, QuoteValueAdjustment $adjustment, string $reason, ?User $actor, bool $dryRun): array
    {
        $frequency = (string) $owner->payment_frequency;
        $rows = AfilliationCorporatePlan::query()->where('affiliation_corporate_id', $owner->getKey())->with('ageRange')->lockForUpdate()->get();
        $affiliatesByRow = null;
        $rowEffects = [];
        $affiliateGroups = [];
        $notes = [];

        foreach ($lines as $line) {
            if ((int) $line['persons_after'] !== (int) $line['persons_before']) {
                $notes[] = $line['label'].': la cantidad de personas cambia solo en la cotización; la afiliación factura su población activa.';
            }

            $rateChanged = self::cents($line['fee_after']) !== self::cents($line['fee_before']);
            if (! $rateChanged) {
                continue;
            }

            $row = $rows->first(fn (AfilliationCorporatePlan $row): bool => self::lineKey($row->plan_id, $row->coverage_id, $row->age_range_id) === self::lineKey($line['plan_id'], $line['coverage_id'], $line['age_range_id']));

            if (! $row instanceof AfilliationCorporatePlan) {
                $notes[] = $line['label'].': esta opción no está contratada en la afiliación; solo cambia la cotización.';

                continue;
            }

            $before = $row->only(['fee', 'total_persons', 'subtotal_anual', 'subtotal_quarterly', 'subtotal_biannual', 'subtotal_monthly', 'fee_source', 'fee_negotiation_reason', 'fee_negotiated_by']);
            $before['fee_negotiated_at'] = $row->fee_negotiated_at !== null ? (string) $row->getRawOriginal('fee_negotiated_at') : null;

            $rateDelta = self::cents($line['fee_after']) - self::cents($row->fee);
            $row->fee = $line['fee_after'];
            CorporateAffiliateRemovalService::recalculateCorporatePlanRowTotals($row);
            if ($rateDelta !== 0) {
                $row->fee_source = CorporatePlanRowCreator::SOURCE_NEGOTIATED;
                $row->fee_negotiation_reason = mb_substr('Ajuste de cotización: '.$reason, 0, 1000);
                $row->fee_negotiated_by = $actor?->getKey();
                $row->fee_negotiated_at = now();
            }
            $row->save();

            $affiliateIds = [];
            if ($rateDelta !== 0) {
                $affiliatesByRow ??= self::corporateAffiliatesByRow($owner, $rows);
                $matched = $affiliatesByRow[(int) $row->getKey()] ?? [];
                $affiliateIds = array_map(static fn (AffiliateCorporate $a): int => (int) $a->getKey(), $matched);
                $previous = self::applyRate(AffiliateCorporate::class, $matched, $rateDelta, self::cents($line['fee_after']), $frequency, $dryRun);
                $affiliateGroups[] = ['row_id' => (int) $row->getKey(), 'ids' => $affiliateIds, 'rate_delta_cents' => $rateDelta, 'before' => $previous];
            }

            $rowEffects[] = [
                'row_id' => (int) $row->getKey(),
                'label' => $line['label'],
                'before' => $before,
                'fee_after' => self::decimal(self::cents($row->fee)),
                'persons_after' => (int) $row->total_persons,
                'subtotal_after' => self::decimal(self::cents($row->subtotal_anual)),
                'rate_delta_cents' => $rateDelta,
                'persons_delta' => 0,
                'affiliates' => count($affiliateIds),
            ];
        }

        return self::finishCascade($owner, QuoteValueAdjustment::TYPE_CORPORATE, $rowEffects, $affiliateGroups, $notes, $reason, $actor, $adjustment);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    private static function cascadeIndividual(Affiliation $owner, array $lines, QuoteValueAdjustment $adjustment, string $reason, ?User $actor, bool $dryRun): array
    {
        $frequency = (string) ($owner->payment_frequency ?? 'ANUAL');
        foreach (ActivePopulationBilling::dataIssues($owner) as $issue) {
            if (str_starts_with($issue, 'Cada afiliado')) {
                throw new RuntimeException('Afiliación '.$owner->code.': '.$issue.' Corrija la tarifa de cada afiliado antes de ajustar la cotización.');
            }
        }

        $affiliates = Affiliate::query()->where('affiliation_id', $owner->getKey())->whereIn('status', self::AFFILIATE_STATUSES)->lockForUpdate()->get();
        $lineEffects = [];
        $affiliateGroups = [];
        $notes = [];

        foreach ($lines as $line) {
            $rateDelta = self::cents($line['fee_after']) - self::cents($line['fee_before']);

            if ((int) $line['persons_after'] !== (int) $line['persons_before']) {
                $notes[] = $line['label'].': en una afiliación individual las personas son sus afiliados; la cantidad cambia solo en la cotización.';
            }

            if ($rateDelta === 0) {
                continue;
            }

            $matched = $affiliates->filter(static function (Affiliate $affiliate) use ($line): bool {
                if ((int) $affiliate->plan_id !== $line['plan_id'] || self::nullableId($affiliate->coverage_id) !== $line['coverage_id']) {
                    return false;
                }

                if (self::nullableId($affiliate->age_range_id) !== null) {
                    return self::nullableId($affiliate->age_range_id) === $line['age_range_id'];
                }

                $age = is_numeric($affiliate->age) ? (int) $affiliate->age : null;

                return $age !== null && $line['age_init'] !== null && $age >= $line['age_init'] && $age <= (int) $line['age_end'];
            })->values();

            if ($matched->isEmpty()) {
                $notes[] = $line['label'].': ningún afiliado activo de la afiliación está en esta línea; solo cambia la cotización.';

                continue;
            }

            $previous = self::applyRate(Affiliate::class, $matched->all(), $rateDelta, self::cents($line['fee_after']), $frequency, $dryRun);

            $ids = $matched->map(static fn (Affiliate $a): int => (int) $a->getKey())->all();
            $affiliateGroups[] = ['line' => $line['detail_id'], 'ids' => $ids, 'rate_delta_cents' => $rateDelta, 'before' => $previous];
            $lineEffects[] = ['label' => $line['label'], 'rate_delta_cents' => $rateDelta, 'affiliates' => count($ids), 'names' => $matched->pluck('full_name')->take(10)->all()];
        }

        return self::finishCascade($owner, QuoteValueAdjustment::TYPE_INDIVIDUAL, $lineEffects, $affiliateGroups, $notes, $reason, $actor, $adjustment);
    }

    /**
     * Totales y avisos POR PAGAR del año contra la población activa ({@see ActivePopulationBilling}).
     *
     * @param  list<array<string, mixed>>  $rowEffects
     * @param  list<array<string, mixed>>  $affiliateGroups
     * @param  list<string>  $notes
     * @return array<string, mixed>
     */
    private static function finishCascade(Model $owner, string $type, array $rowEffects, array $affiliateGroups, array $notes, string $reason, ?User $actor, QuoteValueAdjustment $adjustment): array
    {
        $before = self::totalsOf($owner);
        $plan = ['collections' => [], 'skipped' => []];

        if ($affiliateGroups === []) {
            $notes[] = 'Ningún afiliado de la afiliación '.$owner->code.' cambia de tarifa: la afiliación y su cobranza quedan igual.';
        } else {
            $plan = ActivePopulationBilling::sync($owner, $actor, 'Ajuste de valores de cotización: '.$reason, (string) $adjustment->public_id);
            $owner->refresh();

            if ($plan['collections'] === [] && $plan['skipped'] === []) {
                $notes[] = 'Ningún aviso POR PAGAR del año en curso cambia.';
            }
        }

        return [
            'type' => $type,
            'id' => (int) $owner->getKey(),
            'code' => (string) $owner->code,
            'status' => (string) $owner->status,
            'cascade' => true,
            'frequency' => (string) $owner->payment_frequency,
            'rows' => $rowEffects,
            'affiliate_groups' => $affiliateGroups,
            'persons_delta' => self::totalsOf($owner)['persons'] - $before['persons'],
            'totals' => ['before' => $before, 'after' => self::totalsOf($owner)],
            'collections' => $plan['collections'],
            'notes' => [...$notes, ...$plan['skipped']],
        ];
    }

    private static function executeRevert(QuoteValueAdjustment $adjustment, string $reason, ?User $actor, bool $dryRun): QuoteValueAdjustment
    {
        $adjustment = QuoteValueAdjustment::query()->lockForUpdate()->findOrFail($adjustment->getKey());

        if (! $adjustment->isApplied()) {
            throw new RuntimeException('Este ajuste ya fue revertido.');
        }

        $later = QuoteValueAdjustment::query()
            ->where('quote_type', $adjustment->quote_type)
            ->where('quote_id', $adjustment->quote_id)
            ->where('status', QuoteValueAdjustment::STATUS_APPLIED)
            ->where('id', '>', $adjustment->getKey())
            ->orderByDesc('id')
            ->value('id');

        if ($later !== null) {
            throw new RuntimeException('Primero revierta el ajuste más reciente de esta cotización (#'.$later.').');
        }

        $detailModel = $adjustment->isCorporate() ? DetailCorporateQuote::class : DetailIndividualQuote::class;
        foreach ((array) $adjustment->lines as $line) {
            $detail = $detailModel::query()->lockForUpdate()->find((int) $line['detail_id']);
            if (! $detail instanceof Model) {
                throw new RuntimeException('La línea «'.$line['label'].'» ya no existe en la cotización. No se puede revertir automáticamente.');
            }

            if (self::cents($detail->fee) !== self::cents($line['fee_after']) || (int) $detail->total_persons !== (int) $line['persons_after']) {
                throw new RuntimeException('La línea «'.$line['label'].'» cambió después del ajuste (hoy: US$ '.self::decimal(self::cents($detail->fee)).' × '.(int) $detail->total_persons.'). No se puede revertir automáticamente.');
            }

            $detail->fee = $line['fee_before'];
            $detail->total_persons = (int) $line['persons_before'];
            self::fillSubtotals($detail);
            $detail->save();
        }

        $effects = ['lines' => count((array) $adjustment->lines)];
        $affiliation = (array) ($adjustment->effects['affiliation'] ?? []);
        $owner = null;

        if (($affiliation['cascade'] ?? false) === true) {
            $ownerModel = $adjustment->isCorporate() ? AffiliationCorporate::class : Affiliation::class;
            $owner = $ownerModel::query()->lockForUpdate()->find((int) $affiliation['id']);
            if (! $owner instanceof Model) {
                throw new RuntimeException('La afiliación '.$affiliation['code'].' ya no existe. No se puede revertir automáticamente.');
            }

            $effects['affiliation'] = self::revertCascade($owner, $adjustment, $affiliation, $reason, $actor, $dryRun);
        }

        $adjustment->forceFill([
            'status' => QuoteValueAdjustment::STATUS_REVERTED,
            'reverted_at' => now(),
            'reverted_by_id' => $actor?->getKey(),
            'reverted_by_name' => $actor?->name,
            'revert_reason' => $reason,
            'revert_effects' => $effects,
        ])->save();

        $quoteModel = $adjustment->isCorporate() ? CorporateQuote::class : IndividualQuote::class;
        $quote = $quoteModel::query()->find($adjustment->quote_id);
        if ($quote instanceof Model) {
            self::logQuote($quote, 'REVERSO AJUSTE', 'Reverso del ajuste #'.$adjustment->getKey().'. Motivo: '.$reason, $actor);
        }

        if (! $dryRun && $actor instanceof User) {
            if ($owner instanceof Model) {
                AffiliationDateCorrector::log($owner, DateCorrectionSubject::for($owner), 'REVERSO AJUSTE COTIZ', mb_substr('Reverso del ajuste de valores de la cotización '.$adjustment->quote_code.' (#'.$adjustment->getKey().'). Motivo: '.$reason, 0, 250), $actor);
            }

            SecurityAudit::log('AUDIT_QUOTE_VALUE_ADJUSTMENT_REVERTED', 'business.quotes.adjust-values.revert', [
                'adjustment_id' => $adjustment->getKey(),
                'quote_code' => $adjustment->quote_code,
                'effects' => $effects,
                'reason' => $reason,
            ], $actor);
        }

        return $adjustment;
    }

    /**
     * @param  array<string, mixed>  $affiliation
     * @return array<string, mixed>
     */
    private static function revertCascade(Model $owner, QuoteValueAdjustment $adjustment, array $affiliation, string $reason, ?User $actor, bool $dryRun): array
    {
        $isCorporate = $owner instanceof AffiliationCorporate;
        $frequency = (string) $owner->payment_frequency;

        foreach ((array) ($affiliation['rows'] ?? []) as $rowEffect) {
            if (! isset($rowEffect['row_id'])) {
                continue;
            }

            $row = AfilliationCorporatePlan::query()->lockForUpdate()->find((int) $rowEffect['row_id']);
            if (! $row instanceof AfilliationCorporatePlan) {
                throw new RuntimeException('La fila «'.$rowEffect['label'].'» de la afiliación ya no existe. No se puede revertir automáticamente.');
            }

            if (self::cents($row->fee) !== self::cents($rowEffect['fee_after'])) {
                throw new RuntimeException('La tarifa de la fila «'.$rowEffect['label'].'» de la afiliación cambió después del ajuste (hoy: US$ '.self::decimal(self::cents($row->fee)).'). No se puede revertir automáticamente.');
            }

            $before = (array) $rowEffect['before'];
            $row->forceFill([
                'fee' => $before['fee'],
                'total_persons' => (int) $before['total_persons'],
                'fee_source' => $before['fee_source'] ?? null,
                'fee_negotiation_reason' => $before['fee_negotiation_reason'] ?? null,
                'fee_negotiated_by' => $before['fee_negotiated_by'] ?? null,
                'fee_negotiated_at' => $before['fee_negotiated_at'] ?? null,
            ]);
            CorporateAffiliateRemovalService::recalculateCorporatePlanRowTotals($row);
            $row->save();
        }

        $restoredAffiliates = 0;
        foreach ((array) ($affiliation['affiliate_groups'] ?? []) as $group) {
            $ids = array_map('intval', (array) $group['ids']);
            $restoredAffiliates += count($ids);
            if ($ids === []) {
                continue;
            }

            $model = $isCorporate ? AffiliateCorporate::class : Affiliate::class;
            self::restoreRate($model, $ids, isset($group['before']) ? (array) $group['before'] : null, (int) $group['rate_delta_cents'], $frequency, $dryRun);
        }

        // Ajustes de la primera versión dejaban «población contratada»: al revertir deja de existir.
        QuoteValueAdjustmentAffiliation::query()->where('quote_value_adjustment_id', $adjustment->getKey())->update(['status' => QuoteValueAdjustment::STATUS_REVERTED, 'updated_at' => now()]);

        $before = self::totalsOf($owner);
        $plan = ['collections' => [], 'skipped' => []];
        if ((array) ($affiliation['affiliate_groups'] ?? []) !== [] || (int) ($affiliation['persons_delta'] ?? 0) !== 0) {
            $plan = ActivePopulationBilling::sync($owner, $actor, 'Reverso de ajuste de cotización: '.$reason);
            $owner->refresh();
        }

        return [
            'code' => (string) $owner->code,
            'affiliates' => $restoredAffiliates,
            'totals' => ['before' => $before, 'after' => self::totalsOf($owner)],
            'restored' => array_map(static fn (array $c): array => ['invoice' => $c['invoice'], 'before_cents' => $c['before_cents'], 'after_cents' => $c['after_cents']], $plan['collections']),
            'spread' => [],
            'notes' => $plan['skipped'],
        ];
    }

    /**
     * Tarifa nueva de los afiliados de la línea: quien tenía tarifa se mueve por la diferencia
     * (conserva sus upgrades); quien no tenía (vacía o en cero) recibe la tarifa nueva. Nunca
     * queda negativa. Devuelve la tarifa anterior de cada uno para el reverso exacto.
     *
     * Va por lotes (pocas sentencias aunque sean miles de afiliados: en producción, uno por uno
     * superaba los 30 s del request). La traza por afiliado es el mapa que devuelve, guardado en
     * el ajuste.
     *
     * @param  class-string<Model>  $model
     * @param  list<Model>  $affiliates
     * @return array<int, string|null>
     */
    private static function applyRate(string $model, array $affiliates, int $deltaCents, int $newRateCents, string $frequency, bool $dryRun): array
    {
        $previous = [];
        foreach ($affiliates as $affiliate) {
            $previous[(int) $affiliate->getKey()] = $affiliate->getRawOriginal('fee') !== null ? (string) $affiliate->getRawOriginal('fee') : null;
        }

        if ($previous === [] || $deltaCents === 0) {
            return $previous;
        }

        $delta = self::decimal($deltaCents);
        $rate = self::decimal($newRateCents);
        foreach (array_chunk(array_keys($previous), 1000) as $chunk) {
            $model::query()->withTrashed()->whereIn('id', $chunk)->toBase()->update([
                'fee' => DB::raw('CASE WHEN fee IS NULL OR fee <= 0 THEN '.$rate.' ELSE GREATEST(0, fee + '.$delta.') END'),
                'updated_at' => now(),
            ]);
        }

        if (! $dryRun) {
            self::refreshInstallments($model, array_keys($previous), $frequency);
        }

        return $previous;
    }

    /**
     * Deja a cada afiliado con la tarifa que tenía antes del ajuste (una sentencia por valor).
     *
     * @param  class-string<Model>  $model
     * @param  list<int>  $ids
     * @param  array<int|string, string|null>|null  $previous  null en ajustes antiguos: se resta la diferencia
     */
    private static function restoreRate(string $model, array $ids, ?array $previous, int $deltaCents, string $frequency, bool $dryRun): void
    {
        if ($ids === []) {
            return;
        }

        if ($previous === null) {
            foreach (array_chunk($ids, 1000) as $chunk) {
                $model::query()->withTrashed()->whereIn('id', $chunk)->toBase()->update(['fee' => DB::raw('fee - '.self::decimal($deltaCents)), 'updated_at' => now()]);
            }
        } else {
            $byValue = [];
            foreach ($ids as $id) {
                $value = array_key_exists($id, $previous) ? $previous[$id] : null;
                $byValue[$value === null ? 'null' : self::decimal(self::cents($value))][] = $id;
            }

            foreach ($byValue as $value => $group) {
                foreach (array_chunk($group, 1000) as $chunk) {
                    $model::query()->withTrashed()->whereIn('id', $chunk)->toBase()->update(['fee' => $value === 'null' ? null : $value, 'updated_at' => now()]);
                }
            }
        }

        if (! $dryRun) {
            self::refreshInstallments($model, $ids, $frequency);
        }
    }

    /**
     * Subtotales derivados de la tarifa: anual = tarifa y cuota = tarifa ÷ cuotas; quien tiene
     * upgrades de cobro único se recalcula aparte (su cuota los deja fuera).
     *
     * @param  class-string<Model>  $model
     * @param  list<int>  $ids
     */
    private static function refreshInstallments(string $model, array $ids, string $frequency): void
    {
        $divisor = match (mb_strtoupper(trim($frequency))) {
            'SEMESTRAL' => 2,
            'TRIMESTRAL' => 4,
            'MENSUAL' => 12,
            default => 1,
        };
        $isCorporate = $model === AffiliateCorporate::class;

        foreach (array_chunk($ids, 1000) as $chunk) {
            $values = $isCorporate
                ? ['subtotal_anual' => DB::raw('fee'), 'subtotal_payment_frequency' => DB::raw('CASE WHEN fee IS NULL THEN NULL ELSE ROUND(fee / '.$divisor.', 2) END')]
                : ['total_amount' => DB::raw('CASE WHEN fee IS NULL THEN NULL ELSE ROUND(fee / '.$divisor.', 2) END')];
            $model::query()->withTrashed()->whereIn('id', $chunk)->toBase()->update($values);

            $upgradeTable = $isCorporate ? 'affiliate_corporate_upgrades' : 'affiliate_upgrades';
            if (! UpgradeFirstPaymentBilling::hasColumn($upgradeTable, 'billing_mode')) {
                continue;
            }

            $withOneTime = DB::table($upgradeTable)
                ->whereIn($isCorporate ? 'affiliate_corporate_id' : 'affiliate_id', $chunk)
                ->where('status', \App\Models\AffiliateUpgrade::STATUS_ACTIVE)
                ->where('billing_mode', UpgradeFirstPaymentBilling::MODE)
                ->distinct()
                ->pluck($isCorporate ? 'affiliate_corporate_id' : 'affiliate_id')
                ->all();

            foreach ($model::query()->withTrashed()->whereIn('id', $withOneTime)->get() as $affiliate) {
                $regular = $affiliate->fee === null ? null : UpgradeFirstPaymentBilling::affiliateRegular($affiliate, (float) $affiliate->fee, $frequency);
                $affiliate->forceFill($isCorporate ? ['subtotal_payment_frequency' => $regular] : ['total_amount' => $regular])->saveQuietly();
            }
        }
    }

    /**
     * Afiliados vigentes agrupados por la fila del plan a la que pertenecen.
     *
     * @param  EloquentCollection<int, AfilliationCorporatePlan>  $rows
     * @return array<int, list<AffiliateCorporate>>
     */
    private static function corporateAffiliatesByRow(AffiliationCorporate $owner, EloquentCollection $rows): array
    {
        $byRow = [];
        $rowsWithRange = $rows->filter(static fn (AfilliationCorporatePlan $row): bool => $row->ageRange !== null)->values();

        AffiliateCorporate::query()
            ->where('affiliation_corporate_id', $owner->getKey())
            ->whereIn('status', CorporateAffiliatePlanSynchronizer::COUNTABLE_STATUSES)
            ->lockForUpdate()
            ->get()
            ->each(static function (AffiliateCorporate $affiliate) use ($owner, $rows, $rowsWithRange, &$byRow): void {
                $samePlan = $rows->filter(static fn (AfilliationCorporatePlan $row): bool => (int) $row->plan_id === (int) $affiliate->plan_id
                    && self::nullableId($row->coverage_id) === self::nullableId($affiliate->coverage_id));
                $age = CorporateAffiliatePlanSynchronizer::ageOf($affiliate);
                $row = null;

                if ($age !== null) {
                    $byAge = $samePlan->filter(static fn (AfilliationCorporatePlan $row): bool => $row->ageRange !== null && $age >= (int) $row->ageRange->age_init && $age <= (int) $row->ageRange->age_end);
                    $row = $byAge->count() === 1 ? $byAge->first() : null;
                }

                if ($row === null && $samePlan->count() === 1) {
                    $row = $samePlan->first();
                }

                if ($row === null) {
                    $resolved = CorporateAffiliatePlanSynchronizer::resolvePlanRowForAffiliate($owner, $affiliate, $rowsWithRange)['row'];
                    $row = $resolved instanceof AfilliationCorporatePlan ? $rows->firstWhere('id', $resolved->getKey()) : null;
                }

                if ($row instanceof AfilliationCorporatePlan) {
                    $byRow[(int) $row->getKey()][] = $affiliate;
                }
            });

        return $byRow;
    }

    private static function fillSubtotals(Model $detail): void
    {
        $annual = self::cents($detail->fee) * max(0, (int) $detail->total_persons);
        $detail->subtotal_anual = self::decimal($annual);
        $detail->subtotal_biannual = self::decimal((int) round($annual / 2));
        $detail->subtotal_quarterly = self::decimal((int) round($annual / 4));
        $detail->subtotal_monthly = self::decimal((int) round($annual / 12));
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Model>
     */
    private static function detailQuery(Model $quote): \Illuminate\Database\Eloquent\Builder
    {
        return $quote instanceof CorporateQuote
            ? DetailCorporateQuote::query()->where('corporate_quote_id', $quote->getKey())
            : DetailIndividualQuote::query()->where('individual_quote_id', $quote->getKey());
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Model>
     */
    private static function affiliationsOf(Model $quote): \Illuminate\Database\Eloquent\Builder
    {
        return $quote instanceof CorporateQuote
            ? AffiliationCorporate::query()->where('corporate_quote_id', $quote->getKey())->where('corporate_quote_id', '>', 0)
            : Affiliation::query()->where('individual_quote_id', $quote->getKey())->where('individual_quote_id', '>', 0);
    }

    private static function labelOf(Model $detail): string
    {
        $parts = [(string) ($detail->plan?->description ?? 'Plan '.$detail->plan_id)];
        if ($detail->coverage_id !== null && (int) $detail->coverage_id > 0) {
            $parts[] = 'cobertura US$ '.number_format((float) ($detail->coverage?->price ?? 0), 0, ',', '.');
        }
        $parts[] = 'edad '.($detail->ageRange?->range ?? '—');

        return implode(' · ', $parts);
    }

    private static function lineKey(mixed $planId, mixed $coverageId, mixed $ageRangeId): string
    {
        return (int) $planId.'|'.(int) ($coverageId ?? 0).'|'.(int) ($ageRangeId ?? 0);
    }

    private static function nullableId(mixed $value): ?int
    {
        return $value === null || $value === '' || (int) $value === 0 ? null : (int) $value;
    }

    private static function parseFee(mixed $value, string $label): int
    {
        $text = str_replace([' ', ','], ['', '.'], trim((string) $value));
        if ($text === '' || ! is_numeric($text) || (float) $text < 0 || (float) $text > self::MAX_FEE) {
            throw new RuntimeException($label.': la tarifa debe ser un monto entre 0 y '.number_format(self::MAX_FEE, 0, ',', '.').'.');
        }

        return self::cents($text);
    }

    private static function parsePersons(mixed $value, string $label): int
    {
        $text = trim((string) $value);
        if ($text === '' || ! ctype_digit($text) || (int) $text > self::MAX_PERSONS) {
            throw new RuntimeException($label.': la cantidad de personas debe ser un número entero entre 0 y '.number_format(self::MAX_PERSONS, 0, ',', '.').'.');
        }

        return (int) $text;
    }

    /**
     * @return array{persons: int, fee_anual: string, total_amount: string}
     */
    private static function totalsOf(Model $owner): array
    {
        return [
            'persons' => (int) ($owner instanceof AffiliationCorporate ? $owner->poblation : $owner->family_members),
            'fee_anual' => self::decimal(self::cents($owner->fee_anual)),
            'total_amount' => self::decimal(self::cents($owner->total_amount)),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private static function linesText(array $lines): string
    {
        return collect($lines)->map(static fn (array $line): string => $line['label'].': US$ '.$line['fee_before'].' × '.$line['persons_before'].' → US$ '.$line['fee_after'].' × '.$line['persons_after'])->implode('; ');
    }

    private static function logQuote(Model $quote, string $action, string $text, ?User $actor): void
    {
        $text = mb_substr($text, 0, 250);

        if ($quote instanceof CorporateQuote) {
            StatusLogCorpQuote::query()->create(['corporate_quote_id' => $quote->getKey(), 'action' => $action, 'observation' => $text, 'updated_by' => (string) ($actor?->name ?? 'Sistema')]);

            return;
        }

        StatusLogInQuote::query()->create(['individual_quote_id' => $quote->getKey(), 'action' => $action, 'observation' => $text, 'updated_by' => (string) ($actor?->name ?? 'Sistema')]);
    }

    /**
     * @param  array<string, mixed>|null  $affiliation
     * @return array<string, mixed>|null
     */
    private static function auditSummary(?array $affiliation): ?array
    {
        if ($affiliation === null) {
            return null;
        }

        unset($affiliation['affiliate_groups']);

        return $affiliation;
    }

    /**
     * Fuera de la transacción: el PDF guardado de la cotización se descarta (la próxima descarga
     * lo genera con los valores nuevos) y se avisa a los SUPERADMIN.
     */
    private static function afterCommit(QuoteValueAdjustment $adjustment, User $actor, bool $reverted): void
    {
        try {
            $path = public_path('storage/quotes/'.$adjustment->quote_code.'.pdf');
            if ($adjustment->quote_code !== null && $adjustment->quote_code !== '' && is_file($path)) {
                @unlink($path);
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        try {
            $recipients = EarlyRenovationRecipients::superAdmins()->reject(fn (User $user): bool => (int) $user->getKey() === (int) $actor->getKey());
            if ($recipients->isEmpty()) {
                return;
            }

            $affiliation = (array) ($adjustment->effects['affiliation'] ?? []);
            $body = $actor->name.($reverted ? ' revirtió' : ' ajustó').' los valores de la cotización '.$adjustment->quote_code
                .(($affiliation['cascade'] ?? false) ? ' (afiliación '.$affiliation['code'].')' : '')
                .'. Motivo: '.($reverted ? $adjustment->revert_reason : $adjustment->reason);

            Notification::make()
                ->title($reverted ? 'Reverso de ajuste de cotización' : 'Ajuste de valores de cotización')
                ->body(mb_substr($body, 0, 500))
                ->icon('heroicon-o-adjustments-horizontal')
                ->warning()
                ->sendToDatabase($recipients);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private static function assertReason(string $reason): string
    {
        $reason = trim((string) preg_replace('/\s+/u', ' ', $reason));
        if (mb_strlen($reason) < self::REASON_MIN_LENGTH || preg_match_all('/\p{L}{3,}/u', $reason) < 3) {
            throw new RuntimeException('Explique el motivo con al menos tres palabras ('.self::REASON_MIN_LENGTH.' caracteres o más).');
        }

        return mb_substr($reason, 0, 1000);
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
