<?php

declare(strict_types=1);

namespace App\Services\IntegracorpApi;

use Illuminate\Support\Facades\Cache;

class CorretajeAgentsMetricsClient
{
    private const CACHE_TTL_SECONDS = 60;

    public function __construct(private IntegracorpApiClient $api) {}

    /**
     * @return array{
     *     total_registered: int,
     *     total_active: int,
     *     total_superiors: int,
     *     total_subagents: int
     * }
     */
    public function summary(): array
    {
        /** @var array{total_registered: int, total_active: int, total_superiors: int, total_subagents: int} */
        return Cache::remember(
            $this->cacheKey('summary'),
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            function (): array {
                $payload = $this->api->getJson('/api/metrics/corretaje/agents');
                $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

                return [
                    'total_registered' => (int) ($data['total_registered'] ?? 0),
                    'total_active' => (int) ($data['total_active'] ?? 0),
                    'total_superiors' => (int) ($data['total_superiors'] ?? 0),
                    'total_subagents' => (int) ($data['total_subagents'] ?? 0),
                ];
            },
        );
    }

    /**
     * @return array{
     *     items: list<array{state_id: int|null, state: string, total: int}>,
     *     total_active: int
     * }
     */
    public function byState(): array
    {
        /** @var array{items: list<array{state_id: int|null, state: string, total: int}>, total_active: int} */
        return Cache::remember(
            $this->cacheKey('by-state'),
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            function (): array {
                $payload = $this->api->getJson('/api/metrics/corretaje/agents/by-state');
                $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
                $rawItems = is_array($data['items'] ?? null) ? $data['items'] : [];

                $items = [];
                foreach ($rawItems as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $stateId = $item['state_id'] ?? null;

                    $items[] = [
                        'state_id' => $stateId === null ? null : (int) $stateId,
                        'state' => trim((string) ($item['state'] ?? 'Sin estado')) ?: 'Sin estado',
                        'total' => (int) ($item['total'] ?? 0),
                    ];
                }

                return [
                    'items' => $items,
                    'total_active' => (int) ($data['total_active'] ?? array_sum(array_column($items, 'total'))),
                ];
            },
        );
    }

    /**
     * @return array{
     *     items: list<array{agent_id: int, agent_name: string, code_agent: string|null, total_individual: int, total_corporate: int, total: int}>,
     *     total_affiliations: int,
     *     total_individual_affiliations: int,
     *     total_corporate_affiliations: int,
     *     total_agents: int,
     *     limit: int
     * }
     */
    public function byActiveAffiliations(int $limit = 20): array
    {
        $limit = max(5, min(50, $limit));

        /** @var array{items: list<array{agent_id: int, agent_name: string, code_agent: string|null, total_individual: int, total_corporate: int, total: int}>, total_affiliations: int, total_individual_affiliations: int, total_corporate_affiliations: int, total_agents: int, limit: int} */
        return Cache::remember(
            $this->cacheKey('by-active-affiliations-v2.'.$limit),
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            function () use ($limit): array {
                $payload = $this->api->getJson('/api/metrics/corretaje/agents/by-active-affiliations', [
                    'limit' => $limit,
                ]);
                $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
                $rawItems = is_array($data['items'] ?? null) ? $data['items'] : [];

                $items = [];
                foreach ($rawItems as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $codeAgent = isset($item['code_agent']) ? trim((string) $item['code_agent']) : '';
                    $totalIndividual = (int) ($item['total_individual'] ?? 0);
                    $totalCorporate = (int) ($item['total_corporate'] ?? 0);
                    $total = (int) ($item['total'] ?? ($totalIndividual + $totalCorporate));

                    $items[] = [
                        'agent_id' => (int) ($item['agent_id'] ?? 0),
                        'agent_name' => trim((string) ($item['agent_name'] ?? 'Sin nombre')) ?: 'Sin nombre',
                        'code_agent' => $codeAgent !== '' ? $codeAgent : null,
                        'total_individual' => $totalIndividual,
                        'total_corporate' => $totalCorporate,
                        'total' => $total,
                    ];
                }

                $totalIndividualAffiliations = (int) ($data['total_individual_affiliations'] ?? array_sum(array_column($items, 'total_individual')));
                $totalCorporateAffiliations = (int) ($data['total_corporate_affiliations'] ?? array_sum(array_column($items, 'total_corporate')));

                return [
                    'items' => $items,
                    'total_affiliations' => (int) ($data['total_affiliations'] ?? ($totalIndividualAffiliations + $totalCorporateAffiliations)),
                    'total_individual_affiliations' => $totalIndividualAffiliations,
                    'total_corporate_affiliations' => $totalCorporateAffiliations,
                    'total_agents' => (int) ($data['total_agents'] ?? count($items)),
                    'limit' => (int) ($data['limit'] ?? $limit),
                ];
            },
        );
    }

    /**
     * @return array{
     *     items: list<array{agent_id: int, agent_name: string, code_agent: string|null, affiliations_count: int, total_amount: float}>,
     *     total_affiliations: int,
     *     total_agents: int,
     *     total_amount: float,
     *     limit: int
     * }
     */
    public function byActiveAffiliationAmount(int $limit = 20): array
    {
        $limit = max(5, min(50, $limit));

        /** @var array{items: list<array{agent_id: int, agent_name: string, code_agent: string|null, affiliations_count: int, total_amount: float}>, total_affiliations: int, total_agents: int, total_amount: float, limit: int} */
        return Cache::remember(
            $this->cacheKey('by-active-affiliation-amount.'.$limit),
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            function () use ($limit): array {
                $payload = $this->api->getJson('/api/metrics/corretaje/agents/by-active-affiliation-amount', [
                    'limit' => $limit,
                ]);
                $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
                $rawItems = is_array($data['items'] ?? null) ? $data['items'] : [];

                $items = [];
                foreach ($rawItems as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $codeAgent = isset($item['code_agent']) ? trim((string) $item['code_agent']) : '';

                    $items[] = [
                        'agent_id' => (int) ($item['agent_id'] ?? 0),
                        'agent_name' => trim((string) ($item['agent_name'] ?? 'Sin nombre')) ?: 'Sin nombre',
                        'code_agent' => $codeAgent !== '' ? $codeAgent : null,
                        'affiliations_count' => (int) ($item['affiliations_count'] ?? 0),
                        'total_amount' => round((float) ($item['total_amount'] ?? 0), 2),
                    ];
                }

                return [
                    'items' => $items,
                    'total_affiliations' => (int) ($data['total_affiliations'] ?? array_sum(array_column($items, 'affiliations_count'))),
                    'total_agents' => (int) ($data['total_agents'] ?? count($items)),
                    'total_amount' => round((float) ($data['total_amount'] ?? array_sum(array_column($items, 'total_amount'))), 2),
                    'limit' => (int) ($data['limit'] ?? $limit),
                ];
            },
        );
    }

    /**
     * @return array{
     *     items: list<array{state_id: int, state: string, affiliations_count: int, total_amount: float}>,
     *     total_affiliations: int,
     *     total_agents: int,
     *     total_amount: float,
     *     states_count: int,
     *     top_state: array{state_id: int, state: string, affiliations_count: int, total_amount: float}|null
     * }
     */
    public function salesByState(): array
    {
        /** @var array{items: list<array{state_id: int, state: string, affiliations_count: int, total_amount: float}>, total_affiliations: int, total_agents: int, total_amount: float, states_count: int, top_state: array{state_id: int, state: string, affiliations_count: int, total_amount: float}|null} */
        return Cache::remember(
            $this->cacheKey('sales-by-state'),
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            function (): array {
                $payload = $this->api->getJson('/api/metrics/corretaje/agents/sales-by-state');
                $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
                $rawItems = is_array($data['items'] ?? null) ? $data['items'] : [];

                $items = [];
                foreach ($rawItems as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $items[] = [
                        'state_id' => (int) ($item['state_id'] ?? 0),
                        'state' => trim((string) ($item['state'] ?? 'Sin estado')) ?: 'Sin estado',
                        'affiliations_count' => (int) ($item['affiliations_count'] ?? 0),
                        'total_amount' => round((float) ($item['total_amount'] ?? 0), 2),
                    ];
                }

                $topState = null;
                if (is_array($data['top_state'] ?? null)) {
                    $top = $data['top_state'];
                    $topState = [
                        'state_id' => (int) ($top['state_id'] ?? 0),
                        'state' => trim((string) ($top['state'] ?? 'Sin estado')) ?: 'Sin estado',
                        'affiliations_count' => (int) ($top['affiliations_count'] ?? 0),
                        'total_amount' => round((float) ($top['total_amount'] ?? 0), 2),
                    ];
                } elseif ($items !== []) {
                    $sorted = $items;
                    usort($sorted, static fn (array $a, array $b): int => $b['total_amount'] <=> $a['total_amount']);
                    $topState = $sorted[0];
                }

                return [
                    'items' => $items,
                    'total_affiliations' => (int) ($data['total_affiliations'] ?? array_sum(array_column($items, 'affiliations_count'))),
                    'total_agents' => (int) ($data['total_agents'] ?? 0),
                    'total_amount' => round((float) ($data['total_amount'] ?? array_sum(array_column($items, 'total_amount'))), 2),
                    'states_count' => (int) ($data['states_count'] ?? count($items)),
                    'top_state' => $topState,
                ];
            },
        );
    }

    /**
     * @return array{
     *     current_month: array{year: int, month: int, start: string, end_exclusive: string},
     *     previous_month: array{year: int, month: int, start: string, end_exclusive: string},
     *     superiors: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool},
     *     subagents: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool},
     *     year_series: array{year: int, labels: list<string>, superiors: list<int>, subagents: list<int>}
     * }
     */
    public function registrationComparison(): array
    {
        /** @var array{current_month: array{year: int, month: int, start: string, end_exclusive: string}, previous_month: array{year: int, month: int, start: string, end_exclusive: string}, superiors: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}, subagents: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}, year_series: array{year: int, labels: list<string>, superiors: list<int>, subagents: list<int>}} */
        return Cache::remember(
            $this->cacheKey('registration-comparison-v3-created-at'),
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            function (): array {
                $payload = $this->api->getJson('/api/metrics/corretaje/agents/registration-comparison');
                $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

                return [
                    'current_month' => $this->normalizeMonthWindow($data['current_month'] ?? []),
                    'previous_month' => $this->normalizeMonthWindow($data['previous_month'] ?? []),
                    'superiors' => $this->normalizeComparison($data['superiors'] ?? []),
                    'subagents' => $this->normalizeComparison($data['subagents'] ?? []),
                    'year_series' => $this->normalizeAgentsYearSeries($data['year_series'] ?? []),
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
     * @return array{year: int, labels: list<string>, superiors: list<int>, subagents: list<int>}
     */
    private function normalizeAgentsYearSeries(array $series): array
    {
        $labels = $this->normalizeSeriesLabels($series['labels'] ?? []);
        $count = count($labels);

        return [
            'year' => (int) ($series['year'] ?? 0),
            'labels' => $labels,
            'superiors' => $this->normalizeSeriesValues($series['superiors'] ?? [], $count),
            'subagents' => $this->normalizeSeriesValues($series['subagents'] ?? [], $count),
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
        return 'integracorp_api.metrics.corretaje.agents.'.$suffix;
    }
}
