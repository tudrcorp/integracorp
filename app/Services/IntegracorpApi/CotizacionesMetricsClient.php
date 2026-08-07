<?php

declare(strict_types=1);

namespace App\Services\IntegracorpApi;

use Illuminate\Support\Facades\Cache;

class CotizacionesMetricsClient
{
    private const CACHE_TTL_SECONDS = 60;

    public function __construct(private IntegracorpApiClient $api) {}

    /**
     * @return array{
     *     current_month: array{year: int, month: int, start: string, end_exclusive: string},
     *     previous_month: array{year: int, month: int, start: string, end_exclusive: string},
     *     created: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool},
     *     executed: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool},
     *     annulled: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool},
     *     year_series: array{year: int, labels: list<string>, created: list<int>, executed: list<int>, annulled: list<int>}
     * }
     */
    public function statusComparison(): array
    {
        /** @var array{current_month: array{year: int, month: int, start: string, end_exclusive: string}, previous_month: array{year: int, month: int, start: string, end_exclusive: string}, created: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}, executed: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}, annulled: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}, year_series: array{year: int, labels: list<string>, created: list<int>, executed: list<int>, annulled: list<int>}} */
        return Cache::remember(
            $this->cacheKey('status-comparison-v2-executed-with-affiliation'),
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            function (): array {
                $payload = $this->api->getJson('/api/metrics/cotizaciones/status-comparison');
                $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

                return [
                    'current_month' => $this->normalizeMonthWindow($data['current_month'] ?? []),
                    'previous_month' => $this->normalizeMonthWindow($data['previous_month'] ?? []),
                    'created' => $this->normalizeComparison($data['created'] ?? []),
                    'executed' => $this->normalizeComparison($data['executed'] ?? []),
                    'annulled' => $this->normalizeComparison($data['annulled'] ?? []),
                    'year_series' => $this->normalizeYearSeries($data['year_series'] ?? []),
                ];
            },
        );
    }

    /**
     * @return array{
     *     current_month: array{year: int, month: int, start: string, end_exclusive: string},
     *     previous_month: array{year: int, month: int, start: string, end_exclusive: string},
     *     items: list<array{agent_id: int, agent_name: string, code_agent: string|null, quotes_total: int, executed_with_affiliation: int, remaining: int, quotes_total_previous: int, executed_with_affiliation_previous: int, quotes_mom: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}, executed_mom: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}}>,
     *     total_quotes: int,
     *     total_executed_with_affiliation: int,
     *     total_agents: int,
     *     conversion_rate: float,
     *     mom: array{quotes: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}, executed: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}},
     *     limit: int
     * }
     */
    public function byAgent(int $limit = 25): array
    {
        $limit = max(1, min(50, $limit));

        /** @var array{current_month: array{year: int, month: int, start: string, end_exclusive: string}, previous_month: array{year: int, month: int, start: string, end_exclusive: string}, items: list<array{agent_id: int, agent_name: string, code_agent: string|null, quotes_total: int, executed_with_affiliation: int, remaining: int, quotes_total_previous: int, executed_with_affiliation_previous: int, quotes_mom: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}, executed_mom: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}}>, total_quotes: int, total_executed_with_affiliation: int, total_agents: int, conversion_rate: float, mom: array{quotes: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}, executed: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}}, limit: int} */
        return Cache::remember(
            $this->cacheKey('by-agent-v3-mom-'.$limit),
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            function () use ($limit): array {
                $payload = $this->api->getJson('/api/metrics/cotizaciones/by-agent', [
                    'limit' => $limit,
                ]);
                $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
                $rawItems = is_array($data['items'] ?? null) ? $data['items'] : [];
                $mom = is_array($data['mom'] ?? null) ? $data['mom'] : [];

                $items = [];
                foreach ($rawItems as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $quotesTotal = (int) ($item['quotes_total'] ?? 0);
                    $executed = (int) ($item['executed_with_affiliation'] ?? 0);
                    $codeAgent = $item['code_agent'] ?? null;

                    $items[] = [
                        'agent_id' => (int) ($item['agent_id'] ?? 0),
                        'agent_name' => trim((string) ($item['agent_name'] ?? 'Sin nombre')) ?: 'Sin nombre',
                        'code_agent' => is_string($codeAgent) && trim($codeAgent) !== '' ? trim($codeAgent) : null,
                        'quotes_total' => $quotesTotal,
                        'executed_with_affiliation' => $executed,
                        'remaining' => max($quotesTotal - $executed, (int) ($item['remaining'] ?? 0)),
                        'quotes_total_previous' => (int) ($item['quotes_total_previous'] ?? 0),
                        'executed_with_affiliation_previous' => (int) ($item['executed_with_affiliation_previous'] ?? 0),
                        'quotes_mom' => $this->normalizeComparison(is_array($item['quotes_mom'] ?? null) ? $item['quotes_mom'] : []),
                        'executed_mom' => $this->normalizeComparison(is_array($item['executed_mom'] ?? null) ? $item['executed_mom'] : []),
                    ];
                }

                return [
                    'current_month' => $this->normalizeMonthWindow($data['current_month'] ?? []),
                    'previous_month' => $this->normalizeMonthWindow($data['previous_month'] ?? []),
                    'items' => $items,
                    'total_quotes' => (int) ($data['total_quotes'] ?? array_sum(array_column($items, 'quotes_total'))),
                    'total_executed_with_affiliation' => (int) ($data['total_executed_with_affiliation'] ?? array_sum(array_column($items, 'executed_with_affiliation'))),
                    'total_agents' => (int) ($data['total_agents'] ?? count($items)),
                    'conversion_rate' => round((float) ($data['conversion_rate'] ?? 0), 1),
                    'mom' => [
                        'quotes' => $this->normalizeComparison(is_array($mom['quotes'] ?? null) ? $mom['quotes'] : []),
                        'executed' => $this->normalizeComparison(is_array($mom['executed'] ?? null) ? $mom['executed'] : []),
                    ],
                    'limit' => (int) ($data['limit'] ?? $limit),
                ];
            },
        );
    }

    /**
     * @return array{
     *     current_month: array{year: int, month: int, start: string, end_exclusive: string},
     *     previous_month: array{year: int, month: int, start: string, end_exclusive: string},
     *     items: list<array{agency_id: int, agency_code: string|null, agency_name: string, agency_type: string, agency_type_id: int, quotes_total: int, executed_with_affiliation: int, remaining: int, quotes_total_previous: int, executed_with_affiliation_previous: int, quotes_mom: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}, executed_mom: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}}>,
     *     total_quotes: int,
     *     total_executed_with_affiliation: int,
     *     total_agencies: int,
     *     conversion_rate: float,
     *     mom: array{quotes: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}, executed: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}},
     *     limit: int
     * }
     */
    public function byAgency(int $limit = 25): array
    {
        $limit = max(1, min(50, $limit));

        /** @var array{current_month: array{year: int, month: int, start: string, end_exclusive: string}, previous_month: array{year: int, month: int, start: string, end_exclusive: string}, items: list<array{agency_id: int, agency_code: string|null, agency_name: string, agency_type: string, agency_type_id: int, quotes_total: int, executed_with_affiliation: int, remaining: int, quotes_total_previous: int, executed_with_affiliation_previous: int, quotes_mom: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}, executed_mom: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}}>, total_quotes: int, total_executed_with_affiliation: int, total_agencies: int, conversion_rate: float, mom: array{quotes: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}, executed: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}}, limit: int} */
        return Cache::remember(
            $this->cacheKey('by-agency-v3-mom-'.$limit),
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            function () use ($limit): array {
                $payload = $this->api->getJson('/api/metrics/cotizaciones/by-agency', [
                    'limit' => $limit,
                ]);
                $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
                $rawItems = is_array($data['items'] ?? null) ? $data['items'] : [];
                $mom = is_array($data['mom'] ?? null) ? $data['mom'] : [];

                $items = [];
                foreach ($rawItems as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $quotesTotal = (int) ($item['quotes_total'] ?? 0);
                    $executed = (int) ($item['executed_with_affiliation'] ?? 0);
                    $agencyCode = $item['agency_code'] ?? null;
                    $agencyType = strtoupper(trim((string) ($item['agency_type'] ?? '')));
                    if (! in_array($agencyType, ['MASTER', 'GENERAL'], true)) {
                        $agencyType = 'OTRO';
                    }

                    $items[] = [
                        'agency_id' => (int) ($item['agency_id'] ?? 0),
                        'agency_code' => is_string($agencyCode) && trim($agencyCode) !== '' ? trim($agencyCode) : null,
                        'agency_name' => trim((string) ($item['agency_name'] ?? 'Sin nombre')) ?: 'Sin nombre',
                        'agency_type' => $agencyType,
                        'agency_type_id' => (int) ($item['agency_type_id'] ?? 0),
                        'quotes_total' => $quotesTotal,
                        'executed_with_affiliation' => $executed,
                        'remaining' => max($quotesTotal - $executed, (int) ($item['remaining'] ?? 0)),
                        'quotes_total_previous' => (int) ($item['quotes_total_previous'] ?? 0),
                        'executed_with_affiliation_previous' => (int) ($item['executed_with_affiliation_previous'] ?? 0),
                        'quotes_mom' => $this->normalizeComparison(is_array($item['quotes_mom'] ?? null) ? $item['quotes_mom'] : []),
                        'executed_mom' => $this->normalizeComparison(is_array($item['executed_mom'] ?? null) ? $item['executed_mom'] : []),
                    ];
                }

                return [
                    'current_month' => $this->normalizeMonthWindow($data['current_month'] ?? []),
                    'previous_month' => $this->normalizeMonthWindow($data['previous_month'] ?? []),
                    'items' => $items,
                    'total_quotes' => (int) ($data['total_quotes'] ?? array_sum(array_column($items, 'quotes_total'))),
                    'total_executed_with_affiliation' => (int) ($data['total_executed_with_affiliation'] ?? array_sum(array_column($items, 'executed_with_affiliation'))),
                    'total_agencies' => (int) ($data['total_agencies'] ?? count($items)),
                    'conversion_rate' => round((float) ($data['conversion_rate'] ?? 0), 1),
                    'mom' => [
                        'quotes' => $this->normalizeComparison(is_array($mom['quotes'] ?? null) ? $mom['quotes'] : []),
                        'executed' => $this->normalizeComparison(is_array($mom['executed'] ?? null) ? $mom['executed'] : []),
                    ],
                    'limit' => (int) ($data['limit'] ?? $limit),
                ];
            },
        );
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
     * @return array{year: int, labels: list<string>, created: list<int>, executed: list<int>, annulled: list<int>}
     */
    private function normalizeYearSeries(array $series): array
    {
        $labels = $this->normalizeSeriesLabels($series['labels'] ?? []);
        $count = count($labels);

        return [
            'year' => (int) ($series['year'] ?? 0),
            'labels' => $labels,
            'created' => $this->normalizeSeriesValues($series['created'] ?? [], $count),
            'executed' => $this->normalizeSeriesValues($series['executed'] ?? [], $count),
            'annulled' => $this->normalizeSeriesValues($series['annulled'] ?? [], $count),
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
        return 'integracorp_api.metrics.cotizaciones.'.$suffix;
    }
}
