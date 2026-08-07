<?php

declare(strict_types=1);

namespace App\Services\IntegracorpApi;

use Illuminate\Support\Facades\Cache;

class AfiliacionesMetricsClient
{
    private const CACHE_TTL_SECONDS = 60;

    public function __construct(private IntegracorpApiClient $api) {}

    /**
     * @return array{
     *     current_month: array{year: int, month: int, start: string, end_exclusive: string},
     *     previous_month: array{year: int, month: int, start: string, end_exclusive: string},
     *     individual: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool},
     *     corporate: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool},
     *     year_series: array{year: int, labels: list<string>, individual: list<int>, corporate: list<int>}
     * }
     */
    public function statusComparison(): array
    {
        /** @var array{current_month: array{year: int, month: int, start: string, end_exclusive: string}, previous_month: array{year: int, month: int, start: string, end_exclusive: string}, individual: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}, corporate: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}, year_series: array{year: int, labels: list<string>, individual: list<int>, corporate: list<int>}} */
        return Cache::remember(
            $this->cacheKey('status-comparison-v1'),
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            function (): array {
                $payload = $this->api->getJson('/api/metrics/afiliaciones/status-comparison');
                $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

                return [
                    'current_month' => $this->normalizeMonthWindow($data['current_month'] ?? []),
                    'previous_month' => $this->normalizeMonthWindow($data['previous_month'] ?? []),
                    'individual' => $this->normalizeComparison($data['individual'] ?? []),
                    'corporate' => $this->normalizeComparison($data['corporate'] ?? []),
                    'year_series' => $this->normalizeYearSeries($data['year_series'] ?? []),
                ];
            },
        );
    }

    /**
     * @return array{
     *     kind: string,
     *     year: int,
     *     through_month: int,
     *     labels: list<string>,
     *     values: list<int>,
     *     total: int,
     *     peak_month: int|null,
     *     peak_label: string|null,
     *     peak_total: int
     * }
     */
    public function byMonth(string $kind): array
    {
        $kind = $this->normalizeKind($kind);

        /** @var array{kind: string, year: int, through_month: int, labels: list<string>, values: list<int>, total: int, peak_month: int|null, peak_label: string|null, peak_total: int} */
        return Cache::remember(
            $this->cacheKey('by-month-v1-'.$kind),
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            function () use ($kind): array {
                $payload = $this->api->getJson('/api/metrics/afiliaciones/by-month', [
                    'kind' => $kind,
                ]);
                $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
                $labels = $this->normalizeSeriesLabels($data['labels'] ?? []);
                $values = $this->normalizeSeriesValues($data['values'] ?? [], count($labels));
                $peakMonth = isset($data['peak_month']) ? (int) $data['peak_month'] : null;
                $peakLabel = $data['peak_label'] ?? null;

                return [
                    'kind' => $kind,
                    'year' => (int) ($data['year'] ?? now()->year),
                    'through_month' => (int) ($data['through_month'] ?? count($labels)),
                    'labels' => $labels,
                    'values' => $values,
                    'total' => (int) ($data['total'] ?? array_sum($values)),
                    'peak_month' => $peakMonth !== null && $peakMonth > 0 ? $peakMonth : null,
                    'peak_label' => is_string($peakLabel) && trim($peakLabel) !== '' ? trim($peakLabel) : null,
                    'peak_total' => (int) ($data['peak_total'] ?? 0),
                ];
            },
        );
    }

    /**
     * @return array{
     *     kind: string,
     *     year: int,
     *     month: int,
     *     month_label: string,
     *     labels: list<string>,
     *     values: list<int>,
     *     total: int,
     *     peak_day: int|null,
     *     peak_label: string|null,
     *     peak_total: int
     * }
     */
    public function byDay(string $kind, int $year, int $month): array
    {
        $kind = $this->normalizeKind($kind);
        $year = max(2000, min(2100, $year));
        $month = max(1, min(12, $month));

        /** @var array{kind: string, year: int, month: int, month_label: string, labels: list<string>, values: list<int>, total: int, peak_day: int|null, peak_label: string|null, peak_total: int} */
        return Cache::remember(
            $this->cacheKey("by-day-v1-{$kind}-{$year}-{$month}"),
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            function () use ($kind, $year, $month): array {
                $payload = $this->api->getJson('/api/metrics/afiliaciones/by-day', [
                    'kind' => $kind,
                    'year' => $year,
                    'month' => $month,
                ]);
                $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
                $labels = $this->normalizeSeriesLabels($data['labels'] ?? []);
                $values = $this->normalizeSeriesValues($data['values'] ?? [], count($labels));
                $peakDay = isset($data['peak_day']) ? (int) $data['peak_day'] : null;
                $peakLabel = $data['peak_label'] ?? null;
                $monthLabel = trim((string) ($data['month_label'] ?? ''));

                return [
                    'kind' => $kind,
                    'year' => (int) ($data['year'] ?? $year),
                    'month' => (int) ($data['month'] ?? $month),
                    'month_label' => $monthLabel !== '' ? $monthLabel : '',
                    'labels' => $labels,
                    'values' => $values,
                    'total' => (int) ($data['total'] ?? array_sum($values)),
                    'peak_day' => $peakDay !== null && $peakDay > 0 ? $peakDay : null,
                    'peak_label' => is_string($peakLabel) && trim($peakLabel) !== '' ? trim($peakLabel) : null,
                    'peak_total' => (int) ($data['peak_total'] ?? 0),
                ];
            },
        );
    }

    /**
     * @return array{
     *     scope: string,
     *     year: int,
     *     through_month: int,
     *     labels: list<string>,
     *     plans: list<array{plan_id: int, code: string, label: string, values: list<int>, total: int}>,
     *     total: int,
     *     most_demanded: array{plan_id: int, label: string, total: int}|null,
     *     least_demanded: array{plan_id: int, label: string, total: int}|null
     * }
     */
    public function byPlanMonth(): array
    {
        /** @var array{scope: string, year: int, through_month: int, labels: list<string>, plans: list<array{plan_id: int, code: string, label: string, values: list<int>, total: int}>, total: int, most_demanded: array{plan_id: int, label: string, total: int}|null, least_demanded: array{plan_id: int, label: string, total: int}|null} */
        return Cache::remember(
            $this->cacheKey('by-plan-month-v1-combined'),
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            function (): array {
                $payload = $this->api->getJson('/api/metrics/afiliaciones/by-plan-month');
                $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
                $labels = $this->normalizeSeriesLabels($data['labels'] ?? []);
                $count = count($labels);
                $rawPlans = is_array($data['plans'] ?? null) ? $data['plans'] : [];

                $plans = [];
                foreach ($rawPlans as $plan) {
                    if (! is_array($plan)) {
                        continue;
                    }

                    $values = $this->normalizeSeriesValues($plan['values'] ?? [], $count);
                    $plans[] = [
                        'plan_id' => (int) ($plan['plan_id'] ?? 0),
                        'code' => trim((string) ($plan['code'] ?? '')),
                        'label' => trim((string) ($plan['label'] ?? 'Plan')) ?: 'Plan',
                        'values' => $values,
                        'total' => (int) ($plan['total'] ?? array_sum($values)),
                    ];
                }

                return [
                    'scope' => (string) ($data['scope'] ?? 'combined'),
                    'year' => (int) ($data['year'] ?? now()->year),
                    'through_month' => (int) ($data['through_month'] ?? $count),
                    'labels' => $labels,
                    'plans' => $plans,
                    'total' => (int) ($data['total'] ?? array_sum(array_column($plans, 'total'))),
                    'most_demanded' => $this->normalizePlanDemand($data['most_demanded'] ?? null),
                    'least_demanded' => $this->normalizePlanDemand($data['least_demanded'] ?? null),
                ];
            },
        );
    }

    /**
     * @return array{
     *     kind: string,
     *     currency: string,
     *     scope: string,
     *     plans: list<array{plan_id: int, code: string, label: string, amount: float, count: int}>,
     *     total_amount: float,
     *     total_count: int,
     *     top_plan: array{plan_id: int, label: string, amount: float}|null
     * }
     */
    public function byPlanAmount(string $kind): array
    {
        $kind = $this->normalizeKind($kind);

        /** @var array{kind: string, currency: string, scope: string, plans: list<array{plan_id: int, code: string, label: string, amount: float, count: int}>, total_amount: float, total_count: int, top_plan: array{plan_id: int, label: string, amount: float}|null} */
        return Cache::remember(
            $this->cacheKey('by-plan-amount-v1-'.$kind),
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            function () use ($kind): array {
                $payload = $this->api->getJson('/api/metrics/afiliaciones/by-plan-amount', [
                    'kind' => $kind,
                ]);
                $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
                $rawPlans = is_array($data['plans'] ?? null) ? $data['plans'] : [];

                $plans = [];
                foreach ($rawPlans as $plan) {
                    if (! is_array($plan)) {
                        continue;
                    }

                    $plans[] = [
                        'plan_id' => (int) ($plan['plan_id'] ?? 0),
                        'code' => trim((string) ($plan['code'] ?? '')),
                        'label' => trim((string) ($plan['label'] ?? 'Plan')) ?: 'Plan',
                        'amount' => round((float) ($plan['amount'] ?? 0), 2),
                        'count' => (int) ($plan['count'] ?? 0),
                    ];
                }

                return [
                    'kind' => $kind,
                    'currency' => (string) ($data['currency'] ?? 'USD'),
                    'scope' => (string) ($data['scope'] ?? 'active_stock'),
                    'plans' => $plans,
                    'total_amount' => round((float) ($data['total_amount'] ?? array_sum(array_column($plans, 'amount'))), 2),
                    'total_count' => (int) ($data['total_count'] ?? array_sum(array_column($plans, 'count'))),
                    'top_plan' => $this->normalizePlanAmountTop($data['top_plan'] ?? null),
                ];
            },
        );
    }

    /**
     * @return array{
     *     currency: string,
     *     scope: string,
     *     segments: list<array{kind: string, kind_label: string, plan_id: int, code: string, plan_label: string, label: string, amount: float, count: int}>,
     *     by_kind: array{individual: array{total_amount: float, total_count: int}, corporate: array{total_amount: float, total_count: int}},
     *     total_amount: float,
     *     total_count: int,
     *     top_segment: array{kind: string, kind_label: string, plan_id: int, plan_label: string, label: string, amount: float}|null
     * }
     */
    public function byPlanAmountCombined(): array
    {
        /** @var array{currency: string, scope: string, segments: list<array{kind: string, kind_label: string, plan_id: int, code: string, plan_label: string, label: string, amount: float, count: int}>, by_kind: array{individual: array{total_amount: float, total_count: int}, corporate: array{total_amount: float, total_count: int}}, total_amount: float, total_count: int, top_segment: array{kind: string, kind_label: string, plan_id: int, plan_label: string, label: string, amount: float}|null} */
        return Cache::remember(
            $this->cacheKey('by-plan-amount-combined-v1'),
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            function (): array {
                $payload = $this->api->getJson('/api/metrics/afiliaciones/by-plan-amount-combined');
                $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
                $rawSegments = is_array($data['segments'] ?? null) ? $data['segments'] : [];

                $segments = [];
                foreach ($rawSegments as $segment) {
                    if (! is_array($segment)) {
                        continue;
                    }

                    $kind = $this->normalizeKind((string) ($segment['kind'] ?? 'individual'));
                    $label = trim((string) ($segment['label'] ?? ''));
                    $planLabel = trim((string) ($segment['plan_label'] ?? ''));
                    $kindLabel = trim((string) ($segment['kind_label'] ?? ''));

                    $segments[] = [
                        'kind' => $kind,
                        'kind_label' => $kindLabel !== '' ? $kindLabel : ($kind === 'corporate' ? 'Corporativa' : 'Individual'),
                        'plan_id' => (int) ($segment['plan_id'] ?? 0),
                        'code' => trim((string) ($segment['code'] ?? '')),
                        'plan_label' => $planLabel !== '' ? $planLabel : 'Plan',
                        'label' => $label !== '' ? $label : 'Segmento',
                        'amount' => round((float) ($segment['amount'] ?? 0), 2),
                        'count' => (int) ($segment['count'] ?? 0),
                    ];
                }

                $byKindRaw = is_array($data['by_kind'] ?? null) ? $data['by_kind'] : [];

                return [
                    'currency' => (string) ($data['currency'] ?? 'USD'),
                    'scope' => (string) ($data['scope'] ?? 'active_stock'),
                    'segments' => $segments,
                    'by_kind' => [
                        'individual' => $this->normalizeKindTotals($byKindRaw['individual'] ?? null),
                        'corporate' => $this->normalizeKindTotals($byKindRaw['corporate'] ?? null),
                    ],
                    'total_amount' => round((float) ($data['total_amount'] ?? array_sum(array_column($segments, 'amount'))), 2),
                    'total_count' => (int) ($data['total_count'] ?? array_sum(array_column($segments, 'count'))),
                    'top_segment' => $this->normalizeTopSegment($data['top_segment'] ?? null),
                ];
            },
        );
    }

    /**
     * @return array{
     *     scope: string,
     *     metric: string,
     *     states: list<array{state_id: int|null, label: string, count: int, is_other: bool}>,
     *     total_count: int,
     *     states_count: int,
     *     states_shown: int,
     *     others_count: int,
     *     top_state: array{state_id: int|null, label: string, count: int}|null
     * }
     */
    public function byState(?int $limit = null): array
    {
        $limit = $limit !== null ? max(3, min(20, $limit)) : 8;

        /** @var array{scope: string, metric: string, states: list<array{state_id: int|null, label: string, count: int, is_other: bool}>, total_count: int, states_count: int, states_shown: int, others_count: int, top_state: array{state_id: int|null, label: string, count: int}|null} */
        return Cache::remember(
            $this->cacheKey('by-state-v1-'.$limit),
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            function () use ($limit): array {
                $payload = $this->api->getJson('/api/metrics/afiliaciones/by-state', [
                    'limit' => $limit,
                ]);
                $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
                $rawStates = is_array($data['states'] ?? null) ? $data['states'] : [];

                $states = [];
                foreach ($rawStates as $state) {
                    if (! is_array($state)) {
                        continue;
                    }

                    $label = trim((string) ($state['label'] ?? ''));
                    if ($label === '') {
                        $label = 'Sin estado';
                    }

                    $stateId = $state['state_id'] ?? null;

                    $states[] = [
                        'state_id' => $stateId === null || $stateId === '' ? null : (int) $stateId,
                        'label' => $label,
                        'count' => (int) ($state['count'] ?? 0),
                        'is_other' => (bool) ($state['is_other'] ?? false),
                    ];
                }

                return [
                    'scope' => (string) ($data['scope'] ?? 'active_stock'),
                    'metric' => (string) ($data['metric'] ?? 'count'),
                    'states' => $states,
                    'total_count' => (int) ($data['total_count'] ?? array_sum(array_column($states, 'count'))),
                    'states_count' => (int) ($data['states_count'] ?? count($states)),
                    'states_shown' => (int) ($data['states_shown'] ?? count(array_filter(
                        $states,
                        static fn (array $item): bool => ! $item['is_other'],
                    ))),
                    'others_count' => (int) ($data['others_count'] ?? 0),
                    'top_state' => $this->normalizeStateTop($data['top_state'] ?? null),
                ];
            },
        );
    }

    /**
     * @return array{plan_id: int, label: string, total: int}|null
     */
    private function normalizePlanDemand(mixed $demand): ?array
    {
        if (! is_array($demand)) {
            return null;
        }

        $label = trim((string) ($demand['label'] ?? ''));
        if ($label === '') {
            return null;
        }

        return [
            'plan_id' => (int) ($demand['plan_id'] ?? 0),
            'label' => $label,
            'total' => (int) ($demand['total'] ?? 0),
        ];
    }

    /**
     * @return array{plan_id: int, label: string, amount: float}|null
     */
    private function normalizePlanAmountTop(mixed $top): ?array
    {
        if (! is_array($top)) {
            return null;
        }

        $label = trim((string) ($top['label'] ?? ''));
        if ($label === '') {
            return null;
        }

        return [
            'plan_id' => (int) ($top['plan_id'] ?? 0),
            'label' => $label,
            'amount' => round((float) ($top['amount'] ?? 0), 2),
        ];
    }

    /**
     * @return array{total_amount: float, total_count: int}
     */
    private function normalizeKindTotals(mixed $totals): array
    {
        if (! is_array($totals)) {
            return [
                'total_amount' => 0.0,
                'total_count' => 0,
            ];
        }

        return [
            'total_amount' => round((float) ($totals['total_amount'] ?? 0), 2),
            'total_count' => (int) ($totals['total_count'] ?? 0),
        ];
    }

    /**
     * @return array{kind: string, kind_label: string, plan_id: int, plan_label: string, label: string, amount: float}|null
     */
    private function normalizeTopSegment(mixed $top): ?array
    {
        if (! is_array($top)) {
            return null;
        }

        $label = trim((string) ($top['label'] ?? ''));
        if ($label === '') {
            return null;
        }

        $kind = $this->normalizeKind((string) ($top['kind'] ?? 'individual'));
        $kindLabel = trim((string) ($top['kind_label'] ?? ''));
        $planLabel = trim((string) ($top['plan_label'] ?? ''));

        return [
            'kind' => $kind,
            'kind_label' => $kindLabel !== '' ? $kindLabel : ($kind === 'corporate' ? 'Corporativa' : 'Individual'),
            'plan_id' => (int) ($top['plan_id'] ?? 0),
            'plan_label' => $planLabel !== '' ? $planLabel : 'Plan',
            'label' => $label,
            'amount' => round((float) ($top['amount'] ?? 0), 2),
        ];
    }

    /**
     * @return array{state_id: int|null, label: string, count: int}|null
     */
    private function normalizeStateTop(mixed $top): ?array
    {
        if (! is_array($top)) {
            return null;
        }

        $label = trim((string) ($top['label'] ?? ''));
        if ($label === '') {
            return null;
        }

        $stateId = $top['state_id'] ?? null;

        return [
            'state_id' => $stateId === null || $stateId === '' ? null : (int) $stateId,
            'label' => $label,
            'count' => (int) ($top['count'] ?? 0),
        ];
    }

    private function normalizeKind(string $kind): string
    {
        $normalized = strtolower(trim($kind));

        return in_array($normalized, ['individual', 'corporate'], true)
            ? $normalized
            : 'individual';
    }

    /**
     * @param  array<string, mixed>  $month
     * @return array{year: int, month: int, start: string, end_exclusive: string}
     */
    private function normalizeMonthWindow(array $month): array
    {
        return [
            'year' => (int) ($month['year'] ?? 0),
            'month' => (int) ($month['month'] ?? 0),
            'start' => (string) ($month['start'] ?? ''),
            'end_exclusive' => (string) ($month['end_exclusive'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $comparison
     * @return array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}
     */
    private function normalizeComparison(array $comparison): array
    {
        $trend = (string) ($comparison['trend'] ?? 'flat');
        if (! in_array($trend, ['up', 'down', 'flat'], true)) {
            $trend = 'flat';
        }

        return [
            'current' => (int) ($comparison['current'] ?? 0),
            'previous' => (int) ($comparison['previous'] ?? 0),
            'delta' => (int) ($comparison['delta'] ?? 0),
            'percent_change' => round((float) ($comparison['percent_change'] ?? 0), 1),
            'trend' => $trend,
            'previous_was_zero' => (bool) ($comparison['previous_was_zero'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $series
     * @return array{year: int, labels: list<string>, individual: list<int>, corporate: list<int>}
     */
    private function normalizeYearSeries(array $series): array
    {
        $labels = $this->normalizeSeriesLabels($series['labels'] ?? []);
        $count = count($labels);

        return [
            'year' => (int) ($series['year'] ?? 0),
            'labels' => $labels,
            'individual' => $this->normalizeSeriesValues($series['individual'] ?? [], $count),
            'corporate' => $this->normalizeSeriesValues($series['corporate'] ?? [], $count),
        ];
    }

    /**
     * @return list<string>
     */
    private function normalizeSeriesLabels(mixed $labels): array
    {
        if (! is_array($labels)) {
            return [];
        }

        $normalized = [];
        foreach ($labels as $label) {
            $text = trim((string) $label);
            if ($text !== '') {
                $normalized[] = $text;
            }
        }

        return $normalized;
    }

    /**
     * @return list<int>
     */
    private function normalizeSeriesValues(mixed $values, int $count): array
    {
        if (! is_array($values) || $count < 1) {
            return $count > 0 ? array_fill(0, $count, 0) : [];
        }

        $normalized = [];
        foreach (array_slice(array_values($values), 0, $count) as $value) {
            $normalized[] = (int) $value;
        }

        while (count($normalized) < $count) {
            $normalized[] = 0;
        }

        return $normalized;
    }

    private function cacheKey(string $suffix): string
    {
        return 'integracorp_api.metrics.afiliaciones.'.$suffix;
    }
}
