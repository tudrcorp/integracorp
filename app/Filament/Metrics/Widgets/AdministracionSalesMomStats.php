<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets;

use App\Filament\Metrics\Widgets\Concerns\BuildsRegistrationMomYearChart;
use App\Services\IntegracorpApi\AdministracionMetricsClient;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Log;
use Throwable;

class AdministracionSalesMomStats extends Widget
{
    use BuildsRegistrationMomYearChart;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected string $view = 'filament.metrics.widgets.corretaje-registration-mom';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array{
     *     current_label: string,
     *     previous_label: string,
     *     registered_period_label: string,
     *     wire_key_prefix: string,
     *     eyebrow: string,
     *     title: string,
     *     subtitle_prefix: string,
     *     chart_hint: string,
     *     grid_cols: int,
     *     cards: list<array{
     *         key: string,
     *         title: string,
     *         accent: string,
     *         current: float,
     *         previous: float,
     *         delta: float,
     *         decimals: int,
     *         value_prefix: string,
     *         percent_label: string,
     *         trend: string,
     *         verdict: string,
     *         verdict_detail: string,
     *         chart: array{year: int, data: array{datasets: list<array<string, mixed>>, labels: list<string>}, options: array<string, mixed>}
     *     }>
     * }
     */
    public function getComparisonViewData(): array
    {
        $payload = $this->resolveComparison();
        $yearSeries = $payload['year_series'];

        $currentLabel = $this->formatMonthLabel(
            (int) $payload['current_month']['year'],
            (int) $payload['current_month']['month'],
        );
        $previousLabel = $this->formatMonthLabel(
            (int) $payload['previous_month']['year'],
            (int) $payload['previous_month']['month'],
        );

        return [
            'current_label' => $currentLabel,
            'previous_label' => $previousLabel,
            'registered_period_label' => 'Total del mes en curso',
            'wire_key_prefix' => 'administracion-sales-mom',
            'eyebrow' => 'Ventas del mes',
            'title' => 'Total de ventas: mes actual vs mes pasado',
            'subtitle_prefix' => 'Suma de ventas registradas (sin payment link)',
            'chart_hint' => 'Ventas por mes en el año',
            'grid_cols' => 2,
            'cards' => [
                $this->mapCard(
                    key: 'usd',
                    title: 'Total de Ventas General',
                    accent: 'emerald',
                    valuePrefix: 'US$ ',
                    comparison: $payload['usd'],
                    year: (int) $yearSeries['year'],
                    labels: $yearSeries['labels'],
                    values: $yearSeries['usd'],
                    datasetLabel: 'Ventas USD',
                    currencyName: 'dólares',
                ),
                $this->mapCard(
                    key: 'ves',
                    title: 'Total de ventas en VES',
                    accent: 'violet',
                    valuePrefix: 'Bs. ',
                    comparison: $payload['ves'],
                    year: (int) $yearSeries['year'],
                    labels: $yearSeries['labels'],
                    values: $yearSeries['ves'],
                    datasetLabel: 'Ventas VES',
                    currencyName: 'bolívares',
                ),
            ],
        ];
    }

    /**
     * @return array{
     *     current_month: array{year: int, month: int, start: string, end_exclusive: string},
     *     previous_month: array{year: int, month: int, start: string, end_exclusive: string},
     *     usd: array{current: float, previous: float, delta: float, percent_change: float, trend: string, previous_was_zero: bool},
     *     ves: array{current: float, previous: float, delta: float, percent_change: float, trend: string, previous_was_zero: bool},
     *     year_series: array{year: int, labels: list<string>, usd: list<float>, ves: list<float>}
     * }
     */
    private function resolveComparison(): array
    {
        try {
            return app(AdministracionMetricsClient::class)->salesComparison();
        } catch (Throwable $exception) {
            Log::warning('No se pudo cargar la comparación MoM de ventas desde integracorp-api.', [
                'message' => $exception->getMessage(),
            ]);

            $now = Carbon::now();
            $previous = $now->copy()->subMonthNoOverflow()->startOfMonth();
            $emptySeries = $this->emptyRegistrationMomYearSeries((int) $now->year, (int) $now->month);

            $empty = [
                'current' => 0.0,
                'previous' => 0.0,
                'delta' => 0.0,
                'percent_change' => 0.0,
                'trend' => 'flat',
                'previous_was_zero' => true,
            ];

            return [
                'current_month' => [
                    'year' => (int) $now->year,
                    'month' => (int) $now->month,
                    'start' => $now->copy()->startOfMonth()->toDateString(),
                    'end_exclusive' => $now->copy()->startOfMonth()->addMonth()->toDateString(),
                ],
                'previous_month' => [
                    'year' => (int) $previous->year,
                    'month' => (int) $previous->month,
                    'start' => $previous->toDateString(),
                    'end_exclusive' => $now->copy()->startOfMonth()->toDateString(),
                ],
                'usd' => $empty,
                'ves' => $empty,
                'year_series' => [
                    'year' => $emptySeries['year'],
                    'labels' => $emptySeries['labels'],
                    'usd' => array_map(static fn (int $value): float => (float) $value, $emptySeries['values']),
                    'ves' => array_map(static fn (int $value): float => (float) $value, $emptySeries['values']),
                ],
            ];
        }
    }

    /**
     * @param  array{current: float, previous: float, delta: float, percent_change: float, trend: string, previous_was_zero: bool}  $comparison
     * @param  list<string>  $labels
     * @param  list<float>  $values
     * @return array{
     *     key: string,
     *     title: string,
     *     accent: string,
     *     current: float,
     *     previous: float,
     *     delta: float,
     *     decimals: int,
     *     value_prefix: string,
     *     percent_label: string,
     *     trend: string,
     *     verdict: string,
     *     verdict_detail: string,
     *     chart: array{year: int, data: array{datasets: list<array<string, mixed>>, labels: list<string>}, options: array<string, mixed>}
     * }
     */
    private function mapCard(
        string $key,
        string $title,
        string $accent,
        string $valuePrefix,
        array $comparison,
        int $year,
        array $labels,
        array $values,
        string $datasetLabel,
        string $currencyName,
    ): array {
        $current = round((float) $comparison['current'], 2);
        $previous = round((float) $comparison['previous'], 2);
        $delta = round((float) $comparison['delta'], 2);
        $trend = (string) $comparison['trend'];
        $previousWasZero = (bool) $comparison['previous_was_zero'];
        $percent = (float) $comparison['percent_change'];

        $formattedPercent = number_format(abs($percent), 1, ',', '.').'%';

        if ($previousWasZero && $current === 0.0) {
            $percentLabel = 'Sin cambios';
            $verdict = 'Sin actividad';
            $verdictDetail = "No hubo ventas en {$currencyName} este mes ni el mes pasado.";
            $trend = 'flat';
        } elseif ($previousWasZero && $current > 0.0) {
            $percentLabel = 'Empezó este mes';
            $verdict = 'Actividad nueva';
            $verdictDetail = "El mes pasado no hubo ventas en {$currencyName}; este mes sí se registraron.";
            $trend = 'up';
        } elseif ($trend === 'up') {
            $percentLabel = 'Subió '.$formattedPercent;
            $verdict = 'Vamos mejor que el mes pasado';
            $verdictDetail = "Este mes hay más ventas en {$currencyName} que en todo el mes pasado.";
        } elseif ($trend === 'down') {
            $percentLabel = 'Bajó '.$formattedPercent;
            $verdict = 'Vamos por debajo del mes pasado';
            $verdictDetail = "Este mes hay menos ventas en {$currencyName} que en todo el mes pasado. Recuerda que el mes actual todavía no termina.";
        } else {
            $percentLabel = 'Igual que el mes pasado';
            $verdict = 'Vamos igual que el mes pasado';
            $verdictDetail = "El total de este mes en {$currencyName} es el mismo que el del mes pasado.";
        }

        return [
            'key' => $key,
            'title' => $title,
            'accent' => $accent,
            'current' => $current,
            'previous' => $previous,
            'delta' => $delta,
            'decimals' => 2,
            'value_prefix' => $valuePrefix,
            'percent_label' => $percentLabel,
            'trend' => $trend,
            'verdict' => $verdict,
            'verdict_detail' => $verdictDetail,
            'chart' => $this->buildRegistrationMomYearChart(
                accent: $accent,
                year: $year,
                labels: $labels,
                values: $values,
                datasetLabel: $datasetLabel,
                asFloat: true,
            ),
        ];
    }

    private function formatMonthLabel(int $year, int $month): string
    {
        if ($year < 1 || $month < 1 || $month > 12) {
            return '—';
        }

        return Carbon::create($year, $month, 1)
            ->locale('es')
            ->translatedFormat('F Y');
    }
}
