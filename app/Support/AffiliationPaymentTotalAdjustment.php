<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Affiliation;
use Illuminate\Support\HtmlString;

final class AffiliationPaymentTotalAdjustment
{
    public static function adjust(float $baseTotal, float $percentage): float
    {
        return round($baseTotal * (1 + ($percentage / 100)), 2);
    }

    /**
     * @return array{base: float, percentage: float, delta: float, total: float}
     */
    public static function breakdown(float $baseTotal, float $percentage): array
    {
        $total = self::adjust($baseTotal, $percentage);

        return [
            'base' => round($baseTotal, 2),
            'percentage' => $percentage,
            'delta' => round($total - $baseTotal, 2),
            'total' => $total,
        ];
    }

    public static function vesTotalFromUsd(float $usdTotal, float $bcvRate): float
    {
        return round($usdTotal * $bcvRate, 2);
    }

    public static function previewHtml(float $baseTotal, float $percentage, ?float $bcvRate = null): HtmlString
    {
        $breakdown = self::breakdown($baseTotal, $percentage);
        $base = number_format($breakdown['base'], 2);
        $total = number_format($breakdown['total'], 2);
        $delta = number_format(abs($breakdown['delta']), 2);
        $sign = $breakdown['delta'] >= 0 ? '+' : '−';
        $pctLabel = ($breakdown['percentage'] >= 0 ? '+' : '').number_format($breakdown['percentage'], 2).'%';

        $deltaText = $breakdown['delta'] === 0.0
            ? 'Sin ajuste aplicado'
            : "Variación: {$sign}US$ {$delta} ({$pctLabel})";

        $bcvRateLabel = $bcvRate !== null && $bcvRate > 0
            ? number_format($bcvRate, 2).' Bs/US$ (BCV oficial)'
            : '—';
        $vesTotalLabel = $bcvRate !== null && $bcvRate > 0
            ? 'Bs. '.number_format(self::vesTotalFromUsd($breakdown['total'], $bcvRate), 2)
            : '—';

        return new HtmlString(<<<HTML
            <div class="rounded-xl border border-slate-200/90 bg-slate-50/80 px-4 py-3 text-sm dark:border-white/10 dark:bg-white/5">
                <dl class="grid gap-2 sm:grid-cols-3">
                    <div><dt class="text-slate-500 dark:text-slate-400">Total base</dt><dd class="font-semibold text-slate-900 dark:text-slate-100">US$ {$base}</dd></div>
                    <div><dt class="text-slate-500 dark:text-slate-400">Ajuste</dt><dd class="font-semibold text-slate-900 dark:text-slate-100">{$pctLabel}</dd></div>
                    <div><dt class="text-slate-500 dark:text-slate-400">Total a pagar</dt><dd class="font-semibold text-emerald-700 dark:text-emerald-300">US$ {$total}</dd></div>
                </dl>
                <dl class="mt-3 grid gap-2 border-t border-slate-200/80 pt-3 dark:border-white/10 sm:grid-cols-2">
                    <div><dt class="text-slate-500 dark:text-slate-400">Tasa BCV</dt><dd class="font-semibold text-slate-900 dark:text-slate-100">{$bcvRateLabel}</dd></div>
                    <div><dt class="text-slate-500 dark:text-slate-400">Total a pagar (VES)</dt><dd class="font-semibold text-sky-700 dark:text-sky-300">{$vesTotalLabel}</dd></div>
                </dl>
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{$deltaText}</p>
            </div>
        HTML);
    }

    public static function parseAmount(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $amount = (float) $value;

        return $amount >= 0 ? $amount : null;
    }

    public static function totalAmountHelperText(Affiliation $record): string
    {
        $planDescription = $record->plan?->description ?? '—';
        $frequency = $record->payment_frequency ?? '—';

        if (filled($record->coverage_id) && $record->coverage !== null) {
            return 'Plan: '.$planDescription.' - Cobertura: '.$record->coverage->price.' - Frecuencia: '.$frequency;
        }

        return 'Plan: '.$planDescription.' - Frecuencia: '.$frequency;
    }
}
