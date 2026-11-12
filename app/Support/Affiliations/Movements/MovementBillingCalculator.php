<?php

declare(strict_types=1);

namespace App\Support\Affiliations\Movements;

use App\Support\AffiliationCorporates\BillingAdjustments\PopulationBillingCalculator as Base;
use Carbon\CarbonImmutable;

/**
 * Cálculo puro de cómo un alta o una baja afecta los avisos de cobro. No lee ni
 * escribe la base. Todo en centavos enteros.
 *
 * Reglas (Gustavo, 11/11/2026):
 *  - El período que contiene la fecha efectiva se PRORRATEA POR DÍAS: un alta paga los días
 *    que le quedan; una baja se acredita los días que no usará.
 *  - Ese prorrateo va al aviso del período en curso si todavía no está pagado; si ya se pagó,
 *    al PRÓXIMO aviso pendiente.
 *  - Los avisos pendientes de los períodos siguientes suman (alta) o restan (baja) la cuota
 *    completa de la persona.
 *  - Sin aviso pendiente donde ponerlo: un alta genera un aviso aparte por los días; una baja
 *    deja un crédito que se descuenta en el primer aviso que se emita (la renovación).
 *  - Ningún aviso queda negativo: el crédito que no cabe pasa al siguiente.
 *
 * @phpstan-type Period array{index: int, start: CarbonImmutable, end: CarbonImmutable, days: int}
 * @phpstan-type Installment array{period: int, id: ?int, invoice: ?string, due: CarbonImmutable, amount_cents: int, persons: ?int, state: string}
 */
final class MovementBillingCalculator
{
    public const ADD = 'ALTA';

    public const REMOVE = 'BAJA';

    /**
     * @param  list<Period>  $periods
     * @param  array<int, Installment>  $installments  por índice de período
     * @return array{
     *     blockers: list<string>,
     *     period: Period|null,
     *     period_cents: int,
     *     prorated_days: int,
     *     prorated_cents: int,
     *     changes: list<array{period: int, id: int, invoice: string, due: CarbonImmutable, before_cents: int, after_cents: int, delta_cents: int, persons_before: ?int, persons_after: ?int, lines: list<array{kind: string, cents: int}>}>,
     *     charge_cents: int,
     *     pending_credit_cents: int
     * }
     */
    public static function calculate(string $kind, CarbonImmutable $effectiveDate, int $annualCents, int $perYear, array $periods, array $installments): array
    {
        $effectiveDate = $effectiveDate->startOfDay();
        $sign = $kind === self::ADD ? 1 : -1;
        $periodCents = Base::periodCents(max(0, $annualCents), $perYear);

        $current = null;
        foreach ($periods as $period) {
            if ($period['start']->lessThanOrEqualTo($effectiveDate) && $period['end']->greaterThan($effectiveDate)) {
                $current = $period;
                break;
            }
        }

        if ($current === null) {
            return self::result(['La fecha efectiva cae fuera del ciclo de cobro actual de la afiliación.'], null, $periodCents, 0, 0, [], 0, 0);
        }

        $days = (int) $effectiveDate->diffInDays($current['end']);
        $prorated = Base::prorateCents($periodCents, $days, $current['days']);

        /** Lo que hay que cobrar (+) o acreditar (−) además de las cuotas futuras. */
        $owed = $sign * $prorated;
        /** @var array<int, array{installment: array<string, mixed>, delta: int, persons: int, lines: list<array{kind: string, cents: int}>}> $touched */
        $touched = [];

        foreach ($periods as $period) {
            if ($period['index'] <= $current['index']) {
                continue;
            }

            $installment = $installments[$period['index']] ?? null;
            if ($installment === null) {
                // Sin aviso todavía: se emitirá con la cuota ya recalculada de la afiliación.
                continue;
            }

            if ($installment['state'] === Base::STATE_PENDING) {
                $touched[$period['index']] = [
                    'installment' => $installment,
                    'delta' => $sign * $periodCents,
                    'persons' => $sign,
                    'lines' => [['kind' => 'CUOTA', 'cents' => $sign * $periodCents]],
                ];
            } else {
                // Período futuro ya pagado por adelantado: se cobra o acredita completo.
                $owed += $sign * $periodCents;
            }
        }

        $currentInstallment = $installments[$current['index']] ?? null;
        $targets = [];
        if ($currentInstallment !== null && $currentInstallment['state'] === Base::STATE_PENDING) {
            $touched[$current['index']] ??= ['installment' => $currentInstallment, 'delta' => 0, 'persons' => 0, 'lines' => []];
            $targets[] = $current['index'];
        }

        foreach (array_keys($touched) as $index) {
            if ($index !== $current['index']) {
                $targets[] = $index;
            }
        }

        sort($targets);

        foreach ($targets as $index) {
            if ($owed === 0) {
                break;
            }

            $base = $touched[$index]['installment']['amount_cents'] + $touched[$index]['delta'];
            $apply = $owed > 0 ? $owed : max($owed, -$base);
            if ($apply === 0) {
                continue;
            }

            $touched[$index]['delta'] += $apply;
            $touched[$index]['lines'][] = ['kind' => 'PRORRATEO', 'cents' => $apply];
            $owed -= $apply;
        }

        $changes = [];
        ksort($touched);
        foreach ($touched as $index => $row) {
            if ($row['delta'] === 0 && $row['persons'] === 0) {
                continue;
            }

            $installment = $row['installment'];
            $persons = $installment['persons'];
            $changes[] = [
                'period' => $index,
                'id' => (int) $installment['id'],
                'invoice' => (string) $installment['invoice'],
                'due' => $installment['due'],
                'before_cents' => $installment['amount_cents'],
                'after_cents' => $installment['amount_cents'] + $row['delta'],
                'delta_cents' => $row['delta'],
                'persons_before' => $persons,
                'persons_after' => $persons !== null ? max(0, $persons + $row['persons']) : null,
                'lines' => $row['lines'],
            ];
        }

        return self::result([], $current, $periodCents, $days, $prorated, $changes, $owed > 0 ? $owed : 0, $owed < 0 ? -$owed : 0);
    }

    /**
     * Reparte un monto suelto (reverso de algo ya pagado, o un crédito pendiente) en los avisos
     * pendientes, sin dejar ninguno negativo.
     *
     * @param  array<int, Installment>  $installments
     * @return array{changes: list<array{id: int, invoice: string, before_cents: int, after_cents: int, delta_cents: int}>, rest_cents: int}
     */
    public static function spread(int $cents, array $installments): array
    {
        $changes = [];
        ksort($installments);

        foreach ($installments as $installment) {
            if ($cents === 0) {
                break;
            }

            if ($installment['state'] !== Base::STATE_PENDING) {
                continue;
            }

            $apply = $cents > 0 ? $cents : max($cents, -$installment['amount_cents']);
            if ($apply === 0) {
                continue;
            }

            $changes[] = [
                'id' => (int) $installment['id'],
                'invoice' => (string) $installment['invoice'],
                'before_cents' => $installment['amount_cents'],
                'after_cents' => $installment['amount_cents'] + $apply,
                'delta_cents' => $apply,
            ];
            $cents -= $apply;
        }

        return ['changes' => $changes, 'rest_cents' => $cents];
    }

    /**
     * @param  list<string>  $blockers
     * @param  list<array<string, mixed>>  $changes
     * @return array<string, mixed>
     */
    private static function result(array $blockers, ?array $period, int $periodCents, int $days, int $prorated, array $changes, int $charge, int $credit): array
    {
        return [
            'blockers' => $blockers,
            'period' => $period,
            'period_cents' => $periodCents,
            'prorated_days' => $days,
            'prorated_cents' => $prorated,
            'changes' => $changes,
            'charge_cents' => $charge,
            'pending_credit_cents' => $credit,
        ];
    }
}
