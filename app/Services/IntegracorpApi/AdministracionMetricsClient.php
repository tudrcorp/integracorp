<?php

declare(strict_types=1);

namespace App\Services\IntegracorpApi;

use Illuminate\Support\Facades\Cache;

class AdministracionMetricsClient
{
    private const CACHE_TTL_SECONDS = 60;

    public function __construct(private IntegracorpApiClient $api) {}

    /**
     * @return array{
     *     current_month: array{year: int, month: int, start: string, end_exclusive: string},
     *     previous_month: array{year: int, month: int, start: string, end_exclusive: string},
     *     usd: array{current: float, previous: float, delta: float, percent_change: float, trend: string, previous_was_zero: bool},
     *     ves: array{current: float, previous: float, delta: float, percent_change: float, trend: string, previous_was_zero: bool},
     *     year_series: array{year: int, labels: list<string>, usd: list<float>, ves: list<float>}
     * }
     */
    public function salesComparison(): array
    {
        /** @var array{current_month: array{year: int, month: int, start: string, end_exclusive: string}, previous_month: array{year: int, month: int, start: string, end_exclusive: string}, usd: array{current: float, previous: float, delta: float, percent_change: float, trend: string, previous_was_zero: bool}, ves: array{current: float, previous: float, delta: float, percent_change: float, trend: string, previous_was_zero: bool}, year_series: array{year: int, labels: list<string>, usd: list<float>, ves: list<float>}} */
        return Cache::remember(
            $this->cacheKey('sales-comparison-v1'),
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            function (): array {
                $payload = $this->api->getJson('/api/metrics/administracion/sales-comparison');
                $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

                return [
                    'current_month' => $this->normalizeMonthWindow($data['current_month'] ?? []),
                    'previous_month' => $this->normalizeMonthWindow($data['previous_month'] ?? []),
                    'usd' => $this->normalizeMoneyComparison($data['usd'] ?? []),
                    'ves' => $this->normalizeMoneyComparison($data['ves'] ?? []),
                    'year_series' => $this->normalizeYearSeries($data['year_series'] ?? []),
                ];
            },
        );
    }

    /**
     * @param  array<string, mixed>  $window
     * @return array{year: int, month: int, start: string, end_exclusive: string}
     */
    private function normalizeMonthWindow(mixed $window): array
    {
        $window = is_array($window) ? $window : [];

        return [
            'year' => (int) ($window['year'] ?? now()->year),
            'month' => (int) ($window['month'] ?? now()->month),
            'start' => (string) ($window['start'] ?? now()->startOfMonth()->toDateString()),
            'end_exclusive' => (string) ($window['end_exclusive'] ?? now()->startOfMonth()->addMonth()->toDateString()),
        ];
    }

    /**
     * @param  array<string, mixed>  $comparison
     * @return array{current: float, previous: float, delta: float, percent_change: float, trend: string, previous_was_zero: bool}
     */
    private function normalizeMoneyComparison(mixed $comparison): array
    {
        $comparison = is_array($comparison) ? $comparison : [];
        $trend = (string) ($comparison['trend'] ?? 'flat');

        if (! in_array($trend, ['up', 'down', 'flat'], true)) {
            $trend = 'flat';
        }

        return [
            'current' => round((float) ($comparison['current'] ?? 0), 2),
            'previous' => round((float) ($comparison['previous'] ?? 0), 2),
            'delta' => round((float) ($comparison['delta'] ?? 0), 2),
            'percent_change' => round((float) ($comparison['percent_change'] ?? 0), 1),
            'trend' => $trend,
            'previous_was_zero' => (bool) ($comparison['previous_was_zero'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $series
     * @return array{year: int, labels: list<string>, usd: list<float>, ves: list<float>}
     */
    private function normalizeYearSeries(mixed $series): array
    {
        $series = is_array($series) ? $series : [];
        $labels = is_array($series['labels'] ?? null) ? $series['labels'] : [];
        $usd = is_array($series['usd'] ?? null) ? $series['usd'] : [];
        $ves = is_array($series['ves'] ?? null) ? $series['ves'] : [];

        $normalizedLabels = array_map(
            static fn (mixed $label): string => (string) $label,
            array_values($labels),
        );
        $normalizedUsd = array_map(
            static fn (mixed $value): float => round((float) $value, 2),
            array_values($usd),
        );
        $normalizedVes = array_map(
            static fn (mixed $value): float => round((float) $value, 2),
            array_values($ves),
        );

        $count = min(count($normalizedLabels), count($normalizedUsd), count($normalizedVes));

        return [
            'year' => (int) ($series['year'] ?? now()->year),
            'labels' => array_slice($normalizedLabels, 0, $count),
            'usd' => array_slice($normalizedUsd, 0, $count),
            'ves' => array_slice($normalizedVes, 0, $count),
        ];
    }

    private function cacheKey(string $suffix): string
    {
        return 'integracorp-api.metrics.administracion.'.$suffix;
    }
}
