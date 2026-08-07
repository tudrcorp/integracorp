<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Concerns;

use Carbon\Carbon;

trait FormatsCotizacionesMomChip
{
    /**
     * @param  array{current?: int, previous?: int, delta?: int, percent_change?: float, trend?: string, previous_was_zero?: bool}  $comparison
     */
    protected function formatCotizacionesMomPercentLabel(array $comparison): string
    {
        $previousWasZero = (bool) ($comparison['previous_was_zero'] ?? false);
        $current = (int) ($comparison['current'] ?? 0);
        $trend = (string) ($comparison['trend'] ?? 'flat');
        $percent = (float) ($comparison['percent_change'] ?? 0);
        $formattedPercent = number_format(abs($percent), 1, ',', '.').'%';

        if ($previousWasZero && $current === 0) {
            return 'Sin cambios';
        }

        if ($previousWasZero && $current > 0) {
            return 'Empezó este mes';
        }

        if ($trend === 'up') {
            return 'Subió '.$formattedPercent;
        }

        if ($trend === 'down') {
            return 'Bajó '.$formattedPercent;
        }

        return 'Igual que el mes pasado';
    }

    /**
     * @param  array{current?: int, previous?: int, delta?: int, percent_change?: float, trend?: string, previous_was_zero?: bool}  $comparison
     */
    protected function formatCotizacionesMomTrend(array $comparison): string
    {
        $trend = (string) ($comparison['trend'] ?? 'flat');

        return in_array($trend, ['up', 'down', 'flat'], true) ? $trend : 'flat';
    }

    /**
     * @param  array{current?: int, previous?: int, delta?: int, percent_change?: float, trend?: string, previous_was_zero?: bool}  $comparison
     */
    protected function formatCotizacionesMomDeltaLabel(array $comparison): string
    {
        $delta = (int) ($comparison['delta'] ?? 0);

        return ($delta > 0 ? '+' : '').number_format($delta, 0, ',', '.');
    }

    /**
     * @param  array{current?: int, previous?: int, delta?: int, percent_change?: float, trend?: string, previous_was_zero?: bool}  $comparison
     */
    protected function formatCotizacionesMomDeltaSentence(array $comparison, string $unit = 'cotizaciones'): string
    {
        $delta = (int) ($comparison['delta'] ?? 0);
        $previousWasZero = (bool) ($comparison['previous_was_zero'] ?? false);
        $current = (int) ($comparison['current'] ?? 0);
        $absolute = number_format(abs($delta), 0, ',', '.');

        if ($previousWasZero && $current === 0) {
            return 'No hubo '.$unit.' este mes ni el mes pasado.';
        }

        if ($previousWasZero && $current > 0) {
            return 'El mes pasado no hubo '.$unit.'; este mes empezaron a registrarse.';
        }

        if ($delta > 0) {
            return 'Son '.$absolute.' '.$unit.' más que el mes pasado.';
        }

        if ($delta < 0) {
            return 'Son '.$absolute.' '.$unit.' menos que el mes pasado.';
        }

        return 'Es la misma cantidad que el mes pasado.';
    }

    protected function formatCotizacionesMonthLabel(int $year, int $month): string
    {
        if ($year < 1 || $month < 1 || $month > 12) {
            return '—';
        }

        $label = Carbon::create($year, $month, 1)
            ->locale('es')
            ->translatedFormat('F Y');

        return mb_strtoupper(mb_substr($label, 0, 1)).mb_substr($label, 1);
    }
}
