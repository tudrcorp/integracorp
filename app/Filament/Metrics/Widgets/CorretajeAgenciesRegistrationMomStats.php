<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets;

use App\Filament\Metrics\Widgets\Concerns\BuildsRegistrationMomYearChart;
use App\Services\IntegracorpApi\CorretajeAgenciesMetricsClient;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Log;
use Throwable;

class CorretajeAgenciesRegistrationMomStats extends Widget
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
            'registered_period_label' => 'Registradas este mes',
            'wire_key_prefix' => 'agencies-mom',
            'cards' => [
                $this->mapCard(
                    key: 'master',
                    title: 'Agencias MASTER',
                    accent: 'sky',
                    comparison: $payload['master'],
                    year: (int) $yearSeries['year'],
                    labels: $yearSeries['labels'],
                    values: $yearSeries['master'],
                ),
                $this->mapCard(
                    key: 'general',
                    title: 'Agencias GENERAL',
                    accent: 'violet',
                    comparison: $payload['general'],
                    year: (int) $yearSeries['year'],
                    labels: $yearSeries['labels'],
                    values: $yearSeries['general'],
                ),
            ],
        ];
    }

    /**
     * @return array{
     *     current_month: array{year: int, month: int, start: string, end_exclusive: string},
     *     previous_month: array{year: int, month: int, start: string, end_exclusive: string},
     *     master: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool},
     *     general: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool},
     *     year_series: array{year: int, labels: list<string>, master: list<int>, general: list<int>}
     * }
     */
    private function resolveComparison(): array
    {
        try {
            return app(CorretajeAgenciesMetricsClient::class)->registrationComparison();
        } catch (Throwable $exception) {
            Log::warning('No se pudo cargar la comparación MoM de agencias desde integracorp-api.', [
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
                'master' => $empty,
                'general' => $empty,
                'year_series' => [
                    'year' => $emptySeries['year'],
                    'labels' => $emptySeries['labels'],
                    'master' => $emptySeries['values'],
                    'general' => $emptySeries['values'],
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

        if ($previousWasZero && $current === 0) {
            $percentLabel = '0%';
            $verdict = 'Sin captación';
            $verdictDetail = 'No hubo registros este mes ni el anterior.';
            $trend = 'flat';
        } elseif ($previousWasZero && $current > 0) {
            $percentLabel = 'Nueva';
            $verdict = 'Captación nueva';
            $verdictDetail = 'Sin base el mes pasado: toda la captación es incremental.';
            $trend = 'up';
        } elseif ($trend === 'up') {
            $percentLabel = '+'.number_format(abs($percent), 1, ',', '.').'%';
            $verdict = 'Superó captación';
            $verdictDetail = 'La captación del mes en curso supera al mes pasado.';
        } elseif ($trend === 'down') {
            $percentLabel = '-'.number_format(abs($percent), 1, ',', '.').'%';
            $verdict = 'Bajó captación';
            $verdictDetail = 'La captación del mes en curso está por debajo del mes pasado.';
        } else {
            $percentLabel = '0%';
            $verdict = 'Sin variación';
            $verdictDetail = 'Misma captación que el mes pasado.';
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
            'chart' => $this->buildRegistrationMomYearChart($accent, $year, $labels, $values),
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
