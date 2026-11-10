<?php

declare(strict_types=1);

namespace App\Support\Operations;

use Illuminate\Support\HtmlString;

/**
 * «Resumen de cotización» a ancho completo: detalle por ítem (costo, % de utilidad, ganancia y
 * precio de venta) y los totales destacados (costo, ganancia total con su % efectivo y total
 * en US$ y Bs.). Lo usan la creación y la edición de la cotización.
 */
final class QuoteSummaryPanel
{
    /**
     * @param  array<int|string, mixed>  $lineItems  filas con key, label, unit_price_usd y profit_percentage
     */
    public static function render(array $lineItems, ?float $bcvRate): HtmlString
    {
        $totals = QuoteLinePricing::totals($lineItems);
        $usd = fn (?float $value): string => $value === null ? '—' : 'US$ '.number_format($value, 2, ',', '.');
        $ves = fn (?float $value): string => ($value === null || $bcvRate === null) ? '—' : 'Bs. '.number_format($value * $bcvRate, 2, ',', '.');
        $pct = fn (?float $value): string => $value === null ? 'Sin utilidad' : number_format($value, 2, ',', '.').' %';

        $rows = '';

        foreach ($totals['lines'] as $line) {
            $rows .= '<tr class="align-top">'
                .'<td class="px-3 py-2.5 font-medium text-gray-900 dark:text-white">'.e($line['label']).'</td>'
                .'<td class="px-3 py-2.5 text-right tabular-nums text-gray-700 dark:text-gray-200">'.e($usd($line['cost'])).'</td>'
                .'<td class="px-3 py-2.5 text-right tabular-nums '.($line['percentage'] === null ? 'text-gray-400 dark:text-gray-500' : 'text-amber-700 dark:text-amber-300').'">'.e($pct($line['percentage'])).'</td>'
                .'<td class="px-3 py-2.5 text-right tabular-nums text-amber-700 dark:text-amber-300">'.e($usd($line['profit'])).'</td>'
                .'<td class="px-3 py-2.5 text-right font-semibold tabular-nums text-emerald-700 dark:text-emerald-300">'.e($usd($line['price']))
                .'<div class="text-[11px] font-normal text-gray-500 dark:text-gray-400">'.e($ves($line['price'])).'</div></td>'
                .'</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="5" class="px-3 py-4 text-center text-sm text-gray-500 dark:text-gray-400">Seleccione ítems para cotizar.</td></tr>';
        }

        $pending = $totals['count'] - $totals['priced'];
        $effective = $totals['effective_percentage'];

        $card = fn (string $label, string $value, string $hint, string $tone): string => '<div class="rounded-xl border px-4 py-3 '.$tone.'">'
            .'<p class="text-xs font-medium opacity-80">'.e($label).'</p>'
            .'<p class="mt-1 text-xl font-bold tracking-tight tabular-nums">'.e($value).'</p>'
            .'<p class="mt-0.5 text-xs tabular-nums opacity-75">'.e($hint).'</p>'
            .'</div>';

        return new HtmlString(
            '<div class="fi-manage-quote-summary rounded-2xl border border-amber-200/80 bg-gradient-to-br from-amber-50/95 via-white to-white p-4 shadow-sm dark:border-amber-500/25 dark:from-amber-950/30 dark:via-zinc-900/95 dark:to-zinc-900/90">'
            .'<div class="flex flex-wrap items-baseline justify-between gap-2">'
            .'<p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-amber-800/80 dark:text-amber-200/70">Resumen de cotización</p>'
            .($pending > 0
                ? '<p class="text-xs font-medium text-amber-700 dark:text-amber-300">'.e($pending === 1 ? 'Falta el precio de 1 ítem' : 'Falta el precio de '.$pending.' ítems').'</p>'
                : '')
            .'</div>'
            .'<div class="mt-3 overflow-x-auto rounded-xl border border-black/[0.06] bg-white/70 dark:border-white/10 dark:bg-zinc-900/50">'
            .'<table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-white/10">'
            .'<thead class="bg-gray-50/90 text-xs text-gray-600 dark:bg-white/5 dark:text-gray-300"><tr>'
            .'<th class="px-3 py-2 text-left font-semibold">Ítem</th>'
            .'<th class="px-3 py-2 text-right font-semibold">Costo</th>'
            .'<th class="px-3 py-2 text-right font-semibold">% utilidad</th>'
            .'<th class="px-3 py-2 text-right font-semibold">Ganancia</th>'
            .'<th class="px-3 py-2 text-right font-semibold">Precio de venta</th>'
            .'</tr></thead>'
            .'<tbody class="divide-y divide-gray-100 dark:divide-white/10">'.$rows.'</tbody>'
            .'</table></div>'
            .'<div class="mt-4 grid gap-3 sm:grid-cols-3">'
            .$card('Costo total', $usd($totals['cost']), $ves($totals['cost']), 'border-black/[0.06] bg-white/80 text-zinc-900 dark:border-white/10 dark:bg-zinc-900/60 dark:text-zinc-50')
            .$card(
                'Ganancia total',
                $usd($totals['profit']),
                ($effective !== null ? number_format($effective, 2, ',', '.').' % sobre el costo · ' : '').$ves($totals['profit']),
                'border-amber-200/80 bg-amber-50/70 text-amber-950 dark:border-amber-500/25 dark:bg-amber-950/25 dark:text-amber-50',
            )
            .$card('Total cotización', $usd($totals['total']), $ves($totals['total']), 'border-emerald-200/90 bg-emerald-50/80 text-emerald-950 dark:border-emerald-500/30 dark:bg-emerald-950/30 dark:text-emerald-50')
            .'</div>'
            .'<p class="mt-2 text-[11px] leading-relaxed text-gray-500 dark:text-gray-400">Precio de venta = costo del proveedor + su % de utilidad. Bolívares a la tasa BCV '
            .e($bcvRate !== null ? number_format($bcvRate, 2, ',', '.') : '—').'.</p>'
            .'</div>'
        );
    }
}
