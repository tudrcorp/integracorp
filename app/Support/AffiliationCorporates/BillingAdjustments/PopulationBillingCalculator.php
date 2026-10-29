<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates\BillingAdjustments;

use Carbon\CarbonImmutable;

/**
 * Cálculo puro del ajuste de facturación por cambio de población: no lee ni escribe la base.
 *
 * Reglas (decididas por Negocios/Administración, 29/10/2026):
 * - Prorrateo por DÍAS: una persona que entra paga, del período ya facturado, la tarifa del
 *   período × días que le quedan ÷ días del período.
 * - SIN CRÉDITO: quien sale no genera devolución del período ya facturado; deja de cobrarse en
 *   las cuotas que todavía están por pagar.
 * - Cuotas futuras (POR PAGAR, que empiezan en la fecha efectiva o después): suman la tarifa del
 *   período de cada alta y restan la de cada baja. El aviso conserva su número.
 *
 * Todo el dinero va en CENTAVOS ENTEROS. Se redondea una sola vez por persona (mitad hacia
 * arriba) y los totales son la suma de las líneas: el documento siempre cuadra.
 *
 * @phpstan-type Movement array{item_id: ?int, affiliate_id: ?int, kind: string, movement: string, name: string, document: string, annual_cents: int}
 * @phpstan-type Installment array{period: int, id: ?int, invoice: ?string, due: CarbonImmutable, amount_cents: int, persons: ?int, state: string}
 * @phpstan-type Period array{index: int, start: CarbonImmutable, end: CarbonImmutable, days: int}
 */
final class PopulationBillingCalculator
{
    public const KIND_ADD = 'ALTA';

    public const KIND_REMOVE = 'BAJA';

    /** La cuota está POR PAGAR: se ajusta su monto. */
    public const STATE_PENDING = 'PENDING';

    /** La cuota ya se pagó: las altas se cobran aparte, las bajas no generan crédito. */
    public const STATE_PAID = 'PAID';

    /** Primer período del ciclo, cubierto por el pago inicial (no tiene aviso propio). */
    public const STATE_INITIAL = 'INITIAL';

    /**
     * Tarifa de un período en centavos: anual ÷ cuotas del año, redondeada mitad hacia arriba.
     */
    public static function periodCents(int $annualCents, int $perYear): int
    {
        $perYear = max(1, $perYear);

        return intdiv($annualCents * 2 + $perYear, $perYear * 2);
    }

    /**
     * Prorrata por días en centavos: tarifa del período × días ÷ días del período.
     */
    public static function prorateCents(int $periodCents, int $days, int $periodDays): int
    {
        if ($days <= 0 || $periodDays <= 0 || $periodCents <= 0) {
            return 0;
        }

        $days = min($days, $periodDays);

        return intdiv($periodCents * $days * 2 + $periodDays, $periodDays * 2);
    }

    /**
     * Los períodos del ciclo: el índice 0 empieza en el inicio del ciclo. El fin es exclusivo
     * (el período del 26/08 al 26/11 cubre 92 días: del 26/08 al 25/11).
     *
     * @return list<Period>
     */
    public static function periods(CarbonImmutable $cycleStart, int $months, int $perYear): array
    {
        $periods = [];

        for ($index = 0; $index < max(1, $perYear); $index++) {
            $start = $cycleStart->addMonthsNoOverflow($index * $months)->startOfDay();
            $end = $cycleStart->addMonthsNoOverflow(($index + 1) * $months)->startOfDay();
            $periods[] = ['index' => $index, 'start' => $start, 'end' => $end, 'days' => (int) $start->diffInDays($end)];
        }

        return $periods;
    }

    /**
     * @param  list<Movement>  $movements
     * @param  list<Period>  $periods
     * @param  array<int, Installment>  $installments  por índice de período
     * @return array{
     *     lines: list<array{item_id: ?int, affiliate_id: ?int, kind: string, movement: string, name: string, document: string, annual_cents: int, period_cents: int, charge_cents: int, days: int, period_days: int, covered_from: ?CarbonImmutable, covered_until: ?CarbonImmutable, installment_delta_cents: int, segments: list<array{period: int, from: CarbonImmutable, until: CarbonImmutable, days: int, period_days: int, cents: int}>}>,
     *     charge_cents: int,
     *     installment_delta_cents: int,
     *     persons_added: int,
     *     persons_removed: int,
     *     adjusted: list<array{period: int, id: ?int, invoice: ?string, due: CarbonImmutable, before_cents: int, delta_cents: int, after_cents: int, persons_before: ?int, persons_after: ?int}>,
     *     charged_periods: list<array{period: int, start: CarbonImmutable, end: CarbonImmutable, days: int, state: string, from: CarbonImmutable, covered_days: int}>,
     *     untouched: list<array{period: int, invoice: ?string, due: CarbonImmutable, state: string, why: string}>
     * }
     */
    public static function calculate(CarbonImmutable $effectiveDate, array $movements, array $periods, array $installments, int $perYear): array
    {
        $effectiveDate = $effectiveDate->startOfDay();

        /** Períodos que se ajustan en su aviso (POR PAGAR y empiezan en la fecha efectiva o después). */
        $adjustable = [];
        /** Períodos ya facturados que la fecha efectiva alcanza: las altas se cobran aparte. */
        $charged = [];
        $untouched = [];

        foreach ($periods as $period) {
            $installment = $installments[$period['index']] ?? null;
            $state = $installment['state'] ?? self::STATE_INITIAL;

            if ($period['end']->lessThanOrEqualTo($effectiveDate)) {
                $untouched[] = [
                    'period' => $period['index'],
                    'invoice' => $installment['invoice'] ?? null,
                    'due' => $period['start'],
                    'state' => $state,
                    'why' => 'Terminó antes de la fecha efectiva.',
                ];

                continue;
            }

            if ($state === self::STATE_PENDING && $period['start']->greaterThanOrEqualTo($effectiveDate)) {
                $adjustable[] = $period['index'];

                continue;
            }

            $from = $period['start']->greaterThan($effectiveDate) ? $period['start'] : $effectiveDate;
            $charged[] = [
                'period' => $period['index'],
                'start' => $period['start'],
                'end' => $period['end'],
                'days' => $period['days'],
                'state' => $state,
                'from' => $from,
                'covered_days' => (int) $from->diffInDays($period['end']),
            ];
        }

        $lines = [];
        $chargeCents = 0;
        $deltaCents = 0;
        $added = 0;
        $removed = 0;

        foreach ($movements as $movement) {
            $isAdd = $movement['kind'] === self::KIND_ADD;
            $periodCents = self::periodCents(max(0, $movement['annual_cents']), $perYear);
            $segments = [];
            $lineCharge = 0;

            if ($isAdd) {
                foreach ($charged as $period) {
                    $cents = self::prorateCents($periodCents, $period['covered_days'], $period['days']);
                    $segments[] = [
                        'period' => $period['period'],
                        'from' => $period['from'],
                        'until' => $period['end']->subDay(),
                        'days' => $period['covered_days'],
                        'period_days' => $period['days'],
                        'cents' => $cents,
                    ];
                    $lineCharge += $cents;
                }
            }

            $lineDelta = $isAdd ? $periodCents : -$periodCents;
            $chargeCents += $lineCharge;
            $deltaCents += $lineDelta;
            $isAdd ? $added++ : $removed++;

            $lines[] = [
                'item_id' => $movement['item_id'],
                'affiliate_id' => $movement['affiliate_id'],
                'kind' => $movement['kind'],
                'movement' => $movement['movement'],
                'name' => $movement['name'],
                'document' => $movement['document'],
                'annual_cents' => max(0, $movement['annual_cents']),
                'period_cents' => $periodCents,
                'charge_cents' => $lineCharge,
                'days' => array_sum(array_column($segments, 'days')),
                'period_days' => $segments[0]['period_days'] ?? 0,
                'covered_from' => $segments[0]['from'] ?? null,
                'covered_until' => $segments === [] ? null : $segments[array_key_last($segments)]['until'],
                'installment_delta_cents' => $adjustable === [] ? 0 : $lineDelta,
                'segments' => $segments,
            ];
        }

        $netPersons = $added - $removed;
        $adjusted = [];

        foreach ($adjustable as $index) {
            $installment = $installments[$index];
            $adjusted[] = [
                'period' => $index,
                'id' => $installment['id'],
                'invoice' => $installment['invoice'],
                'due' => $installment['due'],
                'before_cents' => $installment['amount_cents'],
                'delta_cents' => $deltaCents,
                'after_cents' => $installment['amount_cents'] + $deltaCents,
                'persons_before' => $installment['persons'],
                'persons_after' => $installment['persons'] !== null ? max(0, $installment['persons'] + $netPersons) : null,
            ];
        }

        return [
            'lines' => $lines,
            'charge_cents' => $chargeCents,
            'installment_delta_cents' => $adjusted === [] ? 0 : $deltaCents,
            'persons_added' => $added,
            'persons_removed' => $removed,
            'adjusted' => $adjusted,
            'charged_periods' => $charged,
            'untouched' => $untouched,
        ];
    }

    public static function money(int $cents): string
    {
        $sign = $cents < 0 ? '−' : '';

        return $sign.'US$ '.number_format(abs($cents) / 100, 2, ',', '.');
    }
}
