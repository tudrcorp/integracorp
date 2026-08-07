<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets;

use App\Filament\Metrics\Widgets\Concerns\BuildsRegistrationMomYearChart;
use App\Services\IntegracorpApi\AfiliacionesMetricsClient;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Log;
use Throwable;

class AfiliacionesStatusMomStats extends Widget
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
     *         current: int,
     *         previous: int,
     *         delta: int,
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
            'registered_period_label' => 'Afiliados registrados este mes',
            'wire_key_prefix' => 'afiliaciones-mom',
            'eyebrow' => 'Resumen fácil de entender',
            'title' => 'Cómo vamos este mes, comparado con el mes pasado',
            'subtitle_prefix' => 'Se cuentan afiliados individuales y corporativos nuevos',
            'chart_hint' => 'Afiliados nuevos por mes en el año',
            'grid_cols' => 2,
            'cards' => [
                $this->mapCard(
                    key: 'individual',
                    title: 'Afiliados individuales',
                    accent: 'sky',
                    noun: 'afiliados individuales',
                    comparison: $payload['individual'],
                    year: (int) $yearSeries['year'],
                    labels: $yearSeries['labels'],
                    values: $yearSeries['individual'],
                ),
                $this->mapCard(
                    key: 'corporate',
                    title: 'Afiliados corporativos',
                    accent: 'emerald',
                    noun: 'afiliados corporativos',
                    comparison: $payload['corporate'],
                    year: (int) $yearSeries['year'],
                    labels: $yearSeries['labels'],
                    values: $yearSeries['corporate'],
                ),
            ],
        ];
    }

    /**
     * @return array{
     *     current_month: array{year: int, month: int, start: string, end_exclusive: string},
     *     previous_month: array{year: int, month: int, start: string, end_exclusive: string},
     *     individual: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool},
     *     corporate: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool},
     *     year_series: array{year: int, labels: list<string>, individual: list<int>, corporate: list<int>}
     * }
     */
    private function resolveComparison(): array
    {
        try {
            return app(AfiliacionesMetricsClient::class)->statusComparison();
        } catch (Throwable $exception) {
            Log::warning('No se pudo cargar la comparación MoM de afiliaciones desde integracorp-api.', [
                'message' => $exception->getMessage(),
            ]);

            $now = Carbon::now();
            $previous = $now->copy()->subMonthNoOverflow()->startOfMonth();
            $emptySeries = $this->emptyRegistrationMomYearSeries((int) $now->year, (int) $now->month);

            $empty = [
                'current' => 0,
                'previous' => 0,
                'delta' => 0,
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
                'individual' => $empty,
                'corporate' => $empty,
                'year_series' => [
                    'year' => $emptySeries['year'],
                    'labels' => $emptySeries['labels'],
                    'individual' => $emptySeries['values'],
                    'corporate' => $emptySeries['values'],
                ],
            ];
        }
    }

    /**
     * @param  array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}  $comparison
     * @param  list<string>  $labels
     * @param  list<int>  $values
     * @return array{
     *     key: string,
     *     title: string,
     *     accent: string,
     *     current: int,
     *     previous: int,
     *     delta: int,
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
        string $noun,
        array $comparison,
        int $year,
        array $labels,
        array $values,
    ): array {
        $current = (int) $comparison['current'];
        $previous = (int) $comparison['previous'];
        $delta = (int) $comparison['delta'];
        $trend = (string) $comparison['trend'];
        $previousWasZero = (bool) $comparison['previous_was_zero'];
        $percent = (float) $comparison['percent_change'];

        $formattedPercent = number_format(abs($percent), 1, ',', '.').'%';

        if ($previousWasZero && $current === 0) {
            $percentLabel = 'Sin cambios';
            $verdict = 'Sin actividad';
            $verdictDetail = 'No hubo '.$noun.' este mes ni el mes pasado.';
            $trend = 'flat';
        } elseif ($previousWasZero && $current > 0) {
            $percentLabel = 'Empezó este mes';
            $verdict = 'Actividad nueva';
            $verdictDetail = 'El mes pasado no hubo '.$noun.'; este mes sí empezaron a registrarse.';
            $trend = 'up';
        } elseif ($trend === 'up') {
            $percentLabel = 'Subió '.$formattedPercent;
            $verdict = 'Vamos mejor que el mes pasado';
            $verdictDetail = 'Este mes hay más '.$noun.' que en todo el mes pasado.';
        } elseif ($trend === 'down') {
            $percentLabel = 'Bajó '.$formattedPercent;
            $verdict = 'Vamos por debajo del mes pasado';
            $verdictDetail = 'Este mes hay menos '.$noun.' que en todo el mes pasado. Recuerda que el mes actual todavía no termina.';
        } else {
            $percentLabel = 'Igual que el mes pasado';
            $verdict = 'Vamos igual que el mes pasado';
            $verdictDetail = 'La cantidad de este mes es la misma que la del mes pasado.';
        }

        return [
            'key' => $key,
            'title' => $title,
            'accent' => $accent,
            'current' => $current,
            'previous' => $previous,
            'delta' => $delta,
            'percent_label' => $percentLabel,
            'trend' => $trend,
            'verdict' => $verdict,
            'verdict_detail' => $verdictDetail,
            'chart' => $this->buildRegistrationMomYearChart(
                accent: $accent,
                year: $year,
                labels: $labels,
                values: $values,
                datasetLabel: 'Afiliados',
            ),
        ];
    }

    private function formatMonthLabel(int $year, int $month): string
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
