<?php

declare(strict_types=1);

namespace App\Support\Affiliations\ActiveBilling;

use App\Models\Affiliate;
use App\Models\AffiliationCorporate;
use App\Models\Collection;
use App\Models\CollectionAdjustment;
use App\Models\CollectionMovementLine;
use App\Models\PaidMembership;
use App\Models\PaidMembershipCorporate;
use App\Models\User;
use App\Support\AffiliationCorporates\ActivePopulationTotals;
use App\Support\AffiliationCorporates\CorporatePaymentFrequency;
use App\Support\Affiliations\DateCorrection\AffiliationDateCorrector;
use App\Support\Collections\CollectionDueDate;
use App\Support\Upgrades\UpgradeFirstPaymentBilling;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * La afiliación y su cobranza del año se calculan contra su POBLACIÓN ACTIVA real (Gustavo, 11/11/2026):
 * afiliados ACTIVO y PRE-APROBADA, sin eliminados, con la tarifa que tiene cada uno.
 *
 *  - Totales guardados: `poblation`/`family_members`, `fee_anual` y `total_amount` = lo vivo.
 *  - Cada aviso POR PAGAR del año de afiliación en curso = cuota regular viva + lo que ese aviso
 *    lleva aparte (upgrades cobrados en él y prorrateos/créditos de altas y bajas). Personas = activos.
 *  - Lo pagado no se toca; un aviso con abono parcial tampoco (se informa).
 *
 * {@see self::plan()} no escribe; {@see self::sync()} aplica (dentro de la transacción de quien llama).
 */
final class ActivePopulationBilling
{
    public const AFFILIATE_STATUSES = ['ACTIVO', 'PRE-APROBADA'];

    private const PENDING = 'POR PAGAR';

    private const PROOF_PENDING = 'PENDIENTE';

    private const NON_CALENDAR_SERVICES = ['PRORRATEO POR INCLUSION', 'REVERSO DE MOVIMIENTO', 'AJUSTE POR POBLACION'];

    /** Líneas de altas/bajas que son aparte de la cuota de la población (la cuota ya la da lo vivo). */
    private const EXTRA_LINE_KINDS = [CollectionMovementLine::KIND_PRORATION, CollectionMovementLine::KIND_CREDIT, CollectionMovementLine::KIND_REVERSAL];

    /**
     * @return array{persons: int, fee_anual: float, total_amount: float}
     */
    public static function live(Model $owner): array
    {
        if ($owner instanceof AffiliationCorporate) {
            $live = ActivePopulationTotals::compute($owner);

            return ['persons' => $live['persons'], 'fee_anual' => $live['fee_anual'], 'total_amount' => $live['total_amount']];
        }

        $active = Affiliate::query()->where('affiliation_id', $owner->getKey())->whereIn('status', self::AFFILIATE_STATUSES);
        $annual = round((float) (clone $active)->sum('fee'), 2);

        return [
            'persons' => (clone $active)->count(),
            'fee_anual' => $annual,
            'total_amount' => round(UpgradeFirstPaymentBilling::individualRegular($owner, $annual), 2),
        ];
    }

    /**
     * Datos de los afiliados que impiden calcular con confianza contra la población activa.
     *
     * @return list<string>
     */
    public static function dataIssues(Model $owner): array
    {
        $issues = [];
        $isCorporate = $owner instanceof AffiliationCorporate;
        $query = $isCorporate
            ? \App\Models\AffiliateCorporate::query()->where('affiliation_corporate_id', $owner->getKey())
            : Affiliate::query()->where('affiliation_id', $owner->getKey());
        $active = $query->whereIn('status', self::AFFILIATE_STATUSES);

        $withoutFee = (clone $active)->where(static fn ($q) => $q->whereNull('fee')->orWhere('fee', '<=', 0))->count();
        if ($withoutFee > 0) {
            $issues[] = $withoutFee.' afiliado(s) activo(s) sin tarifa (vacía, cero o negativa).';
        }

        if (! $isCorporate) {
            $fees = (clone $active)->pluck('fee')->map(static fn ($fee): int => self::cents($fee));
            $stored = self::cents($owner->fee_anual);
            if ($fees->count() > 1 && $stored > 0 && $fees->every(static fn (int $fee): bool => $fee === $stored)) {
                $issues[] = 'Cada afiliado tiene la tarifa de todo el grupo (US$ '.self::decimal($stored).' cada uno): sumarlas multiplicaría el cobro.';
            }
        }

        return $issues;
    }

    /**
     * @return array{persons: int, fee_anual: float, total_amount: float}
     */
    public static function stored(Model $owner): array
    {
        return [
            'persons' => (int) ($owner instanceof AffiliationCorporate ? $owner->poblation : $owner->family_members),
            'fee_anual' => round((float) $owner->fee_anual, 2),
            'total_amount' => round((float) $owner->total_amount, 2),
        ];
    }

    /**
     * Qué cambia para que la afiliación cuadre con su población activa. No escribe.
     *
     * @return array{
     *     blocker: ?string,
     *     issues: list<string>,
     *     stored: array{persons: int, fee_anual: float, total_amount: float},
     *     live: array{persons: int, fee_anual: float, total_amount: float},
     *     totals_differ: bool,
     *     collections: list<array{id: int, invoice: string, due: ?string, before_cents: int, after_cents: int, extras_cents: int, persons_before: ?int, persons_after: ?int}>,
     *     skipped: list<string>,
     *     cycle_end: ?CarbonImmutable,
     *     differs: bool
     * }
     */
    public static function plan(Model $owner, bool $lock = false): array
    {
        $stored = self::stored($owner);
        $live = self::live($owner);
        $context = self::pendingOfCurrentYear($owner, $lock);
        $totalsDiffer = $stored['persons'] !== $live['persons']
            || self::cents($stored['fee_anual']) !== self::cents($live['fee_anual'])
            || self::cents($stored['total_amount']) !== self::cents($live['total_amount']);

        $collections = [];
        $skipped = [];

        if ($context['blocker'] === null && $context['pending'] !== []) {
            $ids = array_map(static fn (Collection $c): int => (int) $c->getKey(), $context['pending']);
            $upgrades = UpgradeFirstPaymentBilling::chargedOnCollections($owner instanceof AffiliationCorporate ? 'affiliate_corporate_upgrades' : 'affiliate_upgrades', $ids);
            $lines = CollectionMovementLine::query()
                ->whereIn('collection_id', $ids)
                ->whereIn('kind', self::EXTRA_LINE_KINDS)
                ->where('reverted', false)
                ->selectRaw('collection_id, SUM(amount) as total')
                ->groupBy('collection_id')
                ->pluck('total', 'collection_id');

            foreach ($context['pending'] as $collection) {
                $invoice = (string) ($collection->collection_invoice_number ?: '#'.$collection->getKey());

                if ((float) ($collection->pay_amount_usd ?? 0) > 0 || (float) ($collection->pay_amount_ves ?? 0) > 0) {
                    $skipped[] = 'El aviso '.$invoice.' tiene un abono registrado: no se recalcula.';

                    continue;
                }

                $extras = self::cents($upgrades[(int) $collection->getKey()] ?? 0) + self::cents($lines[(int) $collection->getKey()] ?? 0);
                $before = self::cents($collection->total_amount);
                $after = max(0, self::cents($live['total_amount']) + $extras);
                $persons = trim((string) $collection->persons);
                $personsBefore = ctype_digit($persons) ? (int) $persons : null;

                if ($before === $after && $personsBefore === $live['persons']) {
                    continue;
                }

                $collections[] = [
                    'id' => (int) $collection->getKey(),
                    'invoice' => $invoice,
                    'due' => CollectionDueDate::of($collection)?->format('d/m/Y'),
                    'before_cents' => $before,
                    'after_cents' => $after,
                    'extras_cents' => $extras,
                    'persons_before' => $personsBefore,
                    'persons_after' => $live['persons'],
                ];
            }
        }

        return [
            'blocker' => $context['blocker'],
            'issues' => self::dataIssues($owner),
            'stored' => $stored,
            'live' => $live,
            'totals_differ' => $totalsDiffer,
            'collections' => $collections,
            'skipped' => $skipped,
            'cycle_end' => $context['cycle_end'],
            'differs' => $totalsDiffer || $collections !== [],
        ];
    }

    /**
     * Guarda los totales vivos y deja los avisos POR PAGAR del año contra la población activa.
     * Debe llamarse dentro de una transacción.
     *
     * @return array<string, mixed> el plan aplicado
     *
     * @throws \RuntimeException si hay algo que impide tocar la cobranza
     */
    public static function sync(Model $owner, ?User $actor, string $reason, ?string $batch = null): array
    {
        $plan = self::plan($owner, true);

        if ($plan['blocker'] !== null && ($plan['totals_differ'] || self::hasPending($owner))) {
            throw new \RuntimeException($plan['blocker']);
        }

        if ($plan['totals_differ']) {
            $owner->forceFill([
                $owner instanceof AffiliationCorporate ? 'poblation' : 'family_members' => $plan['live']['persons'],
                'fee_anual' => self::decimal(self::cents($plan['live']['fee_anual'])),
                'total_amount' => self::decimal(self::cents($plan['live']['total_amount'])),
            ])->save();
        }

        $batch ??= (string) Str::uuid();
        foreach ($plan['collections'] as $change) {
            $collection = Collection::query()->lockForUpdate()->findOrFail($change['id']);
            $collection->total_amount = self::decimal($change['after_cents']);
            if ($change['persons_before'] !== null || trim((string) $collection->persons) === '') {
                $collection->persons = (string) $change['persons_after'];
            }
            $collection->save();

            if ($actor instanceof User) {
                CollectionAdjustment::query()->create([
                    'collection_id' => $collection->getKey(),
                    'affiliation_code' => $collection->affiliation_code,
                    'collection_invoice_number' => $collection->collection_invoice_number,
                    'batch_uuid' => $batch,
                    'mode' => CollectionAdjustment::MODE_ACTIVE_POPULATION,
                    'changes' => [
                        'total_amount' => ['before' => self::decimal($change['before_cents']), 'after' => self::decimal($change['after_cents'])],
                        'persons' => ['before' => $change['persons_before'] !== null ? (string) $change['persons_before'] : null, 'after' => (string) $change['persons_after']],
                    ],
                    'reason' => mb_substr($reason, 0, 1000),
                    'performed_by_id' => $actor->getKey(),
                    'performed_by_name' => (string) $actor->name,
                    'ip' => request()?->ip(),
                    'user_agent' => Str::limit((string) request()?->userAgent(), 1000, ''),
                ]);
            }
        }

        return $plan;
    }

    /**
     * Avisos POR PAGAR del año de afiliación en curso y lo que impide recalcularlos.
     *
     * @return array{blocker: ?string, pending: list<Collection>, cycle_end: ?CarbonImmutable}
     */
    public static function pendingOfCurrentYear(Model $owner, bool $lock = false): array
    {
        $calendar = AffiliationDateCorrector::calendar($owner);
        $start = $calendar['current_start'] instanceof CarbonImmutable ? $calendar['current_start']->startOfDay() : null;
        $end = $start?->addYearsNoOverflow(1);

        $query = self::pendingQuery($owner)->orderBy('id');
        $pending = ($lock ? $query->lockForUpdate() : $query)->get();

        if ($pending->isEmpty()) {
            return ['blocker' => null, 'pending' => [], 'cycle_end' => $end];
        }

        $proofModel = $owner instanceof AffiliationCorporate ? PaidMembershipCorporate::class : PaidMembership::class;
        $proofKey = $owner instanceof AffiliationCorporate ? 'affiliation_corporate_id' : 'affiliation_id';
        if ($proofModel::query()->where($proofKey, $owner->getKey())->where('status', self::PROOF_PENDING)->exists()) {
            return ['blocker' => 'Hay un comprobante de pago pendiente de aprobación en la afiliación '.$owner->code.'. Apruébelo o recházelo antes: cambia qué avisos están pagados.', 'pending' => [], 'cycle_end' => $end];
        }

        if (CorporatePaymentFrequency::normalize((string) $owner->payment_frequency) === null) {
            return ['blocker' => 'La afiliación '.$owner->code.' no tiene una frecuencia de pago válida («'.$owner->payment_frequency.'»).', 'pending' => [], 'cycle_end' => $end];
        }

        if ($start === null) {
            return ['blocker' => 'No se pudo determinar el año de cobro en curso de la afiliación '.$owner->code.'. Revise sus fechas en «Inconsistencias de fechas».', 'pending' => [], 'cycle_end' => null];
        }

        $inCycle = [];
        foreach ($pending as $collection) {
            $due = CollectionDueDate::of($collection);
            if ($due === null) {
                return ['blocker' => 'El aviso '.$collection->collection_invoice_number.' de '.$owner->code.' no tiene una fecha de pago válida. Corríjalo con «Ajustar cuota».', 'pending' => [], 'cycle_end' => $end];
            }

            if ($due->greaterThanOrEqualTo($start) && $due->lessThan($end)) {
                $inCycle[] = $collection;
            }
        }

        return ['blocker' => null, 'pending' => $inCycle, 'cycle_end' => $end];
    }

    private static function hasPending(Model $owner): bool
    {
        return self::pendingQuery($owner)->exists();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Collection>
     */
    private static function pendingQuery(Model $owner): \Illuminate\Database\Eloquent\Builder
    {
        return Collection::query()
            ->calendarInstallments()
            ->where('affiliation_code', $owner->code)
            ->where('status', self::PENDING)
            ->where(static fn ($q) => $q->whereNull('service')->orWhereNotIn('service', self::NON_CALENDAR_SERVICES));
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
