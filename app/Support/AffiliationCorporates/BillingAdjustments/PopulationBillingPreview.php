<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates\BillingAdjustments;

use Illuminate\Support\HtmlString;

/**
 * Explicación paso a paso del ajuste por población, con los números reales del caso. La usan la
 * vista previa (antes de aplicar) y la ficha del ajuste aplicado, a partir del mismo resumen
 * ({@see PopulationBillingAdjuster::summaryFromPlan()}): lo que el analista aprueba es
 * exactamente lo que queda guardado.
 */
final class PopulationBillingPreview
{
    /**
     * Agrupa a las personas que tienen el mismo cálculo (misma tarifa, mismos días), para
     * explicar «24 personas × US$ 15,95» en vez de 24 renglones iguales.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return list<array{kind: string, movements: array<string, int>, count: int, annual_cents: int, period_cents: int, days: int, period_days: int, unit_charge_cents: int, charge_cents: int, delta_cents: int}>
     */
    public static function groups(array $lines): array
    {
        $groups = [];

        foreach ($lines as $line) {
            $key = implode('|', [$line['kind'], $line['annual_cents'], $line['period_cents'], $line['days'], $line['period_days'], $line['charge_cents']]);
            $groups[$key] ??= [
                'kind' => $line['kind'],
                'movements' => [],
                'count' => 0,
                'annual_cents' => $line['annual_cents'],
                'period_cents' => $line['period_cents'],
                'days' => $line['days'],
                'period_days' => $line['period_days'],
                'unit_charge_cents' => $line['charge_cents'],
                'charge_cents' => 0,
                'delta_cents' => 0,
            ];
            $groups[$key]['movements'][$line['movement']] = ($groups[$key]['movements'][$line['movement']] ?? 0) + 1;
            $groups[$key]['count']++;
            $groups[$key]['charge_cents'] += $line['charge_cents'];
            $groups[$key]['delta_cents'] += $line['installment_delta_cents'];
        }

        usort($groups, static fn (array $a, array $b): int => [$a['kind'] === PopulationBillingCalculator::KIND_ADD ? 0 : 1, -$a['count']] <=> [$b['kind'] === PopulationBillingCalculator::KIND_ADD ? 0 : 1, -$b['count']]);

        return array_values($groups);
    }

    /**
     * @param  array<string, mixed>  $summary  {@see PopulationBillingAdjuster::summaryFromPlan()}
     * @param  list<string>  $blockers
     */
    public static function html(array $summary, array $blockers = []): HtmlString
    {
        $money = static fn (int $cents): string => e(PopulationBillingCalculator::money($cents));
        $html = '<div class="space-y-5 text-sm">';
        $html .= self::list($blockers, 'No se puede aplicar hasta resolver esto:', 'text-red-700 dark:text-red-300');

        if (($summary['groups'] ?? []) === []) {
            return new HtmlString($html.'</div>');
        }

        $perYear = (int) ($summary['per_year'] ?? 1);
        $html .= '<div class="rounded-lg bg-gray-50 p-3 dark:bg-white/5">'
            .'<p class="font-semibold">Datos de partida</p>'
            .'<ul class="mt-1 list-disc space-y-0.5 pl-5">'
            .'<li>Fecha efectiva del cambio: <strong>'.e((string) $summary['effective_date']).'</strong> (la población se aplicó el '.e((string) ($summary['applied_on'] ?? '—')).').</li>'
            .'<li>Frecuencia de pago: <strong>'.e((string) ($summary['frequency_label'] ?? $summary['frequency'])).'</strong>: '.$perYear.' '.($perYear === 1 ? 'cuota' : 'cuotas').' al año.</li>'
            .'<li>Ciclo de cobro actual: del '.e((string) $summary['cycle_start']).' al '.e((string) $summary['cycle_end']).'.</li>'
            .'<li>Tarifa por cuota de cada persona = tarifa anual ÷ '.$perYear.'.</li>'
            .'<li>Movimientos: <strong>'.(int) $summary['persons_added'].' '.((int) $summary['persons_added'] === 1 ? 'alta' : 'altas').'</strong> y <strong>'.(int) $summary['persons_removed'].' '.((int) $summary['persons_removed'] === 1 ? 'baja' : 'bajas').'</strong>'.self::movementBreakdown($summary['groups']).'.</li>'
            .'</ul></div>';

        $html .= self::chargeSection($summary, $money);
        $html .= self::installmentsSection($summary, $money);

        $totalOne = (int) ($summary['charge_cents'] ?? 0);
        $html .= '<div class="rounded-lg border border-primary-200 p-3 dark:border-primary-500/30">'
            .'<p class="font-semibold">Resultado</p><ul class="mt-1 list-disc space-y-0.5 pl-5">'
            .'<li>Aviso de cobro por ajuste: <strong>'.$money($totalOne).'</strong>'.($totalOne > 0 ? ', vence el '.e((string) ($summary['charge_due'] ?? '—')).'.' : ' (no se emite).').'</li>'
            .'<li>Cada cuota por pagar del ciclo cambia en <strong>'.$money((int) ($summary['installment_delta_cents'] ?? 0)).'</strong> ('.count($summary['adjusted'] ?? []).' '.(count($summary['adjusted'] ?? []) === 1 ? 'cuota' : 'cuotas').').</li>'
            .'<li>Las bajas no generan crédito del período ya facturado: dejan de cobrarse en las cuotas por pagar.</li>'
            .'<li>Las cuotas PAGADAS, las ventas emitidas y los montos de la renovación no se tocan.</li>'
            .'</ul></div>';

        $html .= self::list($summary['warnings'] ?? [], 'Para tener en cuenta (no bloquea):', 'text-amber-700 dark:text-amber-300');

        return new HtmlString($html.'</div>');
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private static function chargeSection(array $summary, \Closure $money): string
    {
        $periods = $summary['charged_periods'] ?? [];
        $adds = array_values(array_filter($summary['groups'], static fn (array $group): bool => $group['kind'] === PopulationBillingCalculator::KIND_ADD));
        $html = '<div><p class="font-semibold">1. Cobro aparte por las altas en el período ya facturado</p>';

        if ($periods === []) {
            return $html.'<p class="mt-1 text-gray-600 dark:text-gray-400">La fecha efectiva coincide con el inicio de una cuota por pagar: no hay período ya facturado que cobrar aparte. Todo se refleja en las cuotas.</p></div>';
        }

        foreach ($periods as $period) {
            $state = match ($period['state']) {
                PopulationBillingCalculator::STATE_INITIAL => 'cubierto por el pago inicial',
                PopulationBillingCalculator::STATE_PAID => 'cuota ya pagada',
                default => 'cuota ya en curso (su aviso conserva el monto anterior)',
            };
            $html .= '<p class="mt-1">Cuota '.(int) $period['period'].': del '.e((string) $period['start']).' al '.e((string) $period['end']).' ('.(int) $period['days'].' días, '.$state.'). '
                .'Desde el '.e((string) $period['from']).' quedan <strong>'.(int) $period['covered_days'].' de '.(int) $period['days'].' días</strong>.</p>';
        }

        if ($adds === []) {
            return $html.'<p class="mt-1 text-gray-600 dark:text-gray-400">No hay altas: no se cobra nada aparte. Las bajas no generan crédito.</p></div>';
        }

        $html .= '<table class="mt-2 w-full border-collapse text-left"><thead><tr class="border-b border-gray-200 dark:border-white/10">'
            .'<th class="px-2 py-1">Personas</th><th class="px-2 py-1 text-right">Tarifa anual</th><th class="px-2 py-1 text-right">Por cuota</th>'
            .'<th class="px-2 py-1">Cálculo por persona</th><th class="px-2 py-1 text-right">Total</th></tr></thead><tbody>';

        foreach ($adds as $group) {
            $formula = $money($group['period_cents']).' × '.(int) $group['days'].' ÷ '.(int) $group['period_days'].' = <strong>'.$money($group['unit_charge_cents']).'</strong>';
            $html .= '<tr class="border-b border-gray-100 dark:border-white/5"><td class="px-2 py-1">'.(int) $group['count'].' '.self::movementText($group['movements']).'</td>'
                .'<td class="px-2 py-1 text-right">'.$money($group['annual_cents']).'</td>'
                .'<td class="px-2 py-1 text-right">'.$money($group['period_cents']).'</td>'
                .'<td class="px-2 py-1">'.$formula.'</td>'
                .'<td class="px-2 py-1 text-right"><strong>'.$money($group['charge_cents']).'</strong></td></tr>';
        }

        return $html.'<tr><td class="px-2 py-1 font-semibold" colspan="4">Aviso de cobro por ajuste</td><td class="px-2 py-1 text-right font-semibold">'.$money((int) $summary['charge_cents']).'</td></tr>'
            .'</tbody></table><p class="mt-1 text-gray-600 dark:text-gray-400">Cada persona se redondea a centavos una sola vez; el total es la suma de las personas.</p></div>';
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private static function installmentsSection(array $summary, \Closure $money): string
    {
        $adjusted = $summary['adjusted'] ?? [];
        $html = '<div><p class="font-semibold">2. Cuotas por pagar que cambian de monto</p>';

        if ($adjusted === []) {
            return $html.'<p class="mt-1 text-gray-600 dark:text-gray-400">No quedan cuotas por pagar en el ciclo después de la fecha efectiva.</p></div>';
        }

        $parts = [];

        foreach ($summary['groups'] as $group) {
            $sign = $group['kind'] === PopulationBillingCalculator::KIND_ADD ? '+' : '−';
            $parts[] = $sign.' '.(int) $group['count'].' × '.$money($group['period_cents']);
        }

        $html .= '<p class="mt-1">Por cuota: '.implode(' ', $parts).' = <strong>'.$money((int) $summary['installment_delta_cents']).'</strong>. El aviso conserva su número.</p>'
            .'<table class="mt-2 w-full border-collapse text-left"><thead><tr class="border-b border-gray-200 dark:border-white/10">'
            .'<th class="px-2 py-1">Aviso</th><th class="px-2 py-1">Vence</th><th class="px-2 py-1 text-right">Hoy</th><th class="px-2 py-1 text-right">Ajuste</th>'
            .'<th class="px-2 py-1 text-right">Queda</th><th class="px-2 py-1 text-right">Personas</th></tr></thead><tbody>';

        foreach ($adjusted as $row) {
            $persons = $row['persons_before'] !== null ? (int) $row['persons_before'].' → '.(int) $row['persons_after'] : '—';
            $html .= '<tr class="border-b border-gray-100 dark:border-white/5"><td class="px-2 py-1">'.e((string) $row['invoice']).'</td><td class="px-2 py-1">'.e((string) $row['due']).'</td>'
                .'<td class="px-2 py-1 text-right">'.$money((int) $row['before_cents']).'</td><td class="px-2 py-1 text-right">'.$money((int) $row['delta_cents']).'</td>'
                .'<td class="px-2 py-1 text-right"><strong>'.$money((int) $row['after_cents']).'</strong></td><td class="px-2 py-1 text-right">'.e($persons).'</td></tr>';
        }

        return $html.'</tbody></table></div>';
    }

    /**
     * @param  list<array{movements: array<string, int>}>  $groups
     */
    private static function movementBreakdown(array $groups): string
    {
        $counts = [];

        foreach ($groups as $group) {
            foreach ($group['movements'] as $movement => $count) {
                $counts[$movement] = ($counts[$movement] ?? 0) + $count;
            }
        }

        if ($counts === []) {
            return '';
        }

        return ' ('.implode(', ', array_map(static fn (string $movement, int $count): string => e(mb_strtolower($movement, 'UTF-8')).': '.$count, array_keys($counts), $counts)).')';
    }

    /**
     * @param  array<string, int>  $movements
     */
    private static function movementText(array $movements): string
    {
        return '<span class="text-gray-600 dark:text-gray-400">('.implode(', ', array_map(static fn (string $movement, int $count): string => e(mb_strtolower($movement, 'UTF-8')).' '.$count, array_keys($movements), $movements)).')</span>';
    }

    /**
     * @param  list<string>  $items
     */
    private static function list(array $items, string $title, string $class): string
    {
        if ($items === []) {
            return '';
        }

        return '<div class="'.$class.'"><p class="font-semibold">'.e($title).'</p><ul class="mt-1 list-disc space-y-0.5 pl-5">'
            .implode('', array_map(static fn (string $item): string => '<li>'.e($item).'</li>', $items))
            .'</ul></div>';
    }
}
