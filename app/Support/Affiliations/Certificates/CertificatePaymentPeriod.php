<?php

declare(strict_types=1);

namespace App\Support\Affiliations\Certificates;

use App\Models\Collection;
use App\Services\AffiliationRenewalCollectionGenerator;
use App\Support\Collections\CollectionDueDate;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

/**
 * Qué período de la afiliación está pagado, para el sello «PAGADO» y el «Período
 * facturado» del certificado.
 *
 * No hay una columna que lo diga: se reconstruye con el calendario de cuotas del
 * año contractual (desde `effective_date` según la frecuencia) y los pagos reales.
 *
 * - Una cuota está pagada si hay una cuota `PAGADO` de la afiliación con esa fecha
 *   (±{@see self::TOLERANCE_DAYS} días: hay fechas corridas en los datos).
 * - La primera cuota del año de alta no existe en `collections`: la cubre el pago
 *   inicial aprobado (`paid_memberships`), si cae cerca del inicio.
 * - «Pagado hasta» avanza solo con cuotas contiguas desde la primera; un hueco lo
 *   detiene aunque haya pagos posteriores.
 * - El período vigente está pagado si la cuota que contiene hoy está pagada.
 */
final class CertificatePaymentPeriod
{
    public const TOLERANCE_DAYS = 5;

    /** Días antes del inicio en que todavía cuenta el pago inicial. */
    public const INITIAL_PAYMENT_WINDOW_DAYS = 30;

    /**
     * @param  list<CarbonImmutable>  $installments
     */
    private function __construct(
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
        public readonly array $installments,
        public readonly ?CarbonImmutable $currentFrom,
        public readonly ?CarbonImmutable $currentUntil,
        public readonly CarbonImmutable $paidUntil,
        public readonly bool $currentIsPaid,
    ) {}

    /**
     * @param  list<CarbonImmutable|string>  $paidCollectionDates  Fechas de cuotas PAGADO de la afiliación.
     * @param  list<CarbonImmutable|string>  $initialPaymentDates  Fechas de pagos iniciales aprobados.
     */
    public static function resolve(
        CarbonImmutable $effectiveDate,
        ?string $paymentFrequency,
        array $paidCollectionDates,
        array $initialPaymentDates,
        ?CarbonImmutable $today = null,
    ): self {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $start = $effectiveDate->startOfDay();

        // Renovación aceptada antes de tiempo: el año en curso es el anterior.
        while ($start->greaterThan($today)) {
            $start = $start->subYearNoOverflow();
        }

        $installments = array_map(
            static fn (Carbon $date): CarbonImmutable => CarbonImmutable::instance($date)->startOfDay(),
            AffiliationRenewalCollectionGenerator::upcomingPaymentDates(Carbon::instance($start), (string) ($paymentFrequency ?: 'ANUAL')),
        );

        $end = $start->addYearNoOverflow();
        $paid = self::normalizeDates($paidCollectionDates);
        $initial = self::normalizeDates($initialPaymentDates);

        $isPaid = function (int $index) use ($installments, $paid, $initial, $start): bool {
            $due = $installments[$index];

            foreach ($paid as $date) {
                if (abs($date->diffInDays($due, false)) <= self::TOLERANCE_DAYS) {
                    return true;
                }
            }

            if ($index !== 0) {
                return false;
            }

            $nextDue = $installments[1] ?? $start->addYearNoOverflow();

            foreach ($initial as $date) {
                if ($date->greaterThanOrEqualTo($start->subDays(self::INITIAL_PAYMENT_WINDOW_DAYS)) && $date->lessThan($nextDue)) {
                    return true;
                }
            }

            return false;
        };

        $paidUntil = $start;

        foreach ($installments as $index => $due) {
            if (! $isPaid($index)) {
                break;
            }

            $paidUntil = $installments[$index + 1] ?? $end;
        }

        $currentIndex = null;

        foreach ($installments as $index => $due) {
            $next = $installments[$index + 1] ?? $end;

            if ($today->greaterThanOrEqualTo($due) && $today->lessThan($next)) {
                $currentIndex = $index;
            }
        }

        $currentFrom = $currentIndex !== null ? $installments[$currentIndex] : null;
        $currentUntil = $currentIndex !== null ? ($installments[$currentIndex + 1] ?? $end) : null;

        return new self(
            start: $start,
            end: $end,
            installments: $installments,
            currentFrom: $currentFrom,
            currentUntil: $currentUntil,
            paidUntil: $paidUntil,
            currentIsPaid: $currentIndex !== null && $today->lessThan($paidUntil),
        );
    }

    /**
     * Fechas de las cuotas pagadas de una afiliación (individual o corporativa).
     *
     * @return list<string>
     */
    public static function paidCollectionDatesFor(string $affiliationCode): array
    {
        return Collection::query()
            ->where('affiliation_code', $affiliationCode)
            ->where('status', 'PAGADO')
            ->get(['next_payment_date', 'filter_next_payment_date'])
            ->map(static fn (Collection $collection): ?string => CollectionDueDate::of($collection)?->toDateString())
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Período facturado: la cuota vigente.
     */
    public function currentPeriodLabel(): string
    {
        if ($this->currentFrom === null || $this->currentUntil === null) {
            return $this->start->format('d/m/Y').' – '.$this->end->format('d/m/Y');
        }

        return $this->currentFrom->format('d/m/Y').' – '.$this->currentUntil->format('d/m/Y');
    }

    /**
     * Lo que cubren los pagos, desde el inicio del año contractual.
     */
    public function paidPeriodLabel(): ?string
    {
        if (! $this->paidUntil->greaterThan($this->start)) {
            return null;
        }

        return $this->start->format('d/m/Y').' – '.$this->paidUntil->format('d/m/Y');
    }

    /**
     * @param  list<CarbonImmutable|string>  $dates
     * @return list<CarbonImmutable>
     */
    private static function normalizeDates(array $dates): array
    {
        $normalized = [];

        foreach ($dates as $date) {
            $parsed = $date instanceof CarbonImmutable ? $date->startOfDay() : CollectionDueDate::parse($date);

            if ($parsed !== null) {
                $normalized[] = $parsed;
            }
        }

        return $normalized;
    }
}
