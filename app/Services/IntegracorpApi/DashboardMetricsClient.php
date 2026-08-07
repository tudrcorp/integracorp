<?php

declare(strict_types=1);

namespace App\Services\IntegracorpApi;

use Illuminate\Support\Facades\Cache;

class DashboardMetricsClient
{
    private const CACHE_TTL_SECONDS = 60;

    public function __construct(private IntegracorpApiClient $api) {}

    /**
     * @return array{
     *     years: array{current: int, previous: int, through_month: int},
     *     totals: array{
     *         current: array{agents: int, agencies: int, affiliations_count: int, affiliations_amount: float},
     *         previous: array{agents: int, agencies: int, affiliations_count: int, affiliations_amount: float},
     *         delta: array{agents_pct: float|null, agencies_pct: float|null, affiliations_count_pct: float|null, affiliations_amount_pct: float|null},
     *         providers: array{juridical: int, natural: int, total: int}
     *     },
     *     states: list<array{
     *         state_id: int,
     *         state: string,
     *         geo_key: string,
     *         current: array{agents: int, agencies: int, affiliations_count: int, affiliations_amount: float},
     *         previous: array{agents: int, agencies: int, affiliations_count: int, affiliations_amount: float},
     *         delta: array{agents_pct: float|null, agencies_pct: float|null, affiliations_count_pct: float|null, affiliations_amount_pct: float|null},
     *         providers: array{juridical: int, natural: int, total: int}
     *     }>
     * }
     */
    public function venezuelaByState(): array
    {
        /** @var array{years: array{current: int, previous: int, through_month: int}, totals: array{current: array{agents: int, agencies: int, affiliations_count: int, affiliations_amount: float}, previous: array{agents: int, agencies: int, affiliations_count: int, affiliations_amount: float}, delta: array{agents_pct: float|null, agencies_pct: float|null, affiliations_count_pct: float|null, affiliations_amount_pct: float|null}, providers: array{juridical: int, natural: int, total: int}}, states: list<array{state_id: int, state: string, geo_key: string, current: array{agents: int, agencies: int, affiliations_count: int, affiliations_amount: float}, previous: array{agents: int, agencies: int, affiliations_count: int, affiliations_amount: float}, delta: array{agents_pct: float|null, agencies_pct: float|null, affiliations_count_pct: float|null, affiliations_amount_pct: float|null}, providers: array{juridical: int, natural: int, total: int}}>} */
        return Cache::remember(
            $this->cacheKey('venezuela-by-state-v2'),
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            function (): array {
                $payload = $this->api->getJson('/api/metrics/dashboard/venezuela-by-state');
                $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

                $years = is_array($data['years'] ?? null) ? $data['years'] : [];
                $totals = is_array($data['totals'] ?? null) ? $data['totals'] : [];
                $rawStates = is_array($data['states'] ?? null) ? $data['states'] : [];

                $states = [];
                foreach ($rawStates as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $states[] = [
                        'state_id' => (int) ($item['state_id'] ?? 0),
                        'state' => trim((string) ($item['state'] ?? 'Sin estado')) ?: 'Sin estado',
                        'geo_key' => trim((string) ($item['geo_key'] ?? $item['state'] ?? 'Sin estado')) ?: 'Sin estado',
                        'current' => $this->normalizeMetrics($item['current'] ?? []),
                        'previous' => $this->normalizeMetrics($item['previous'] ?? []),
                        'delta' => $this->normalizeDelta($item['delta'] ?? []),
                        'providers' => $this->normalizeProviders($item['providers'] ?? []),
                    ];
                }

                return [
                    'years' => [
                        'current' => (int) ($years['current'] ?? now()->year),
                        'previous' => (int) ($years['previous'] ?? now()->year - 1),
                        'through_month' => (int) ($years['through_month'] ?? now()->month),
                    ],
                    'totals' => [
                        'current' => $this->normalizeMetrics($totals['current'] ?? []),
                        'previous' => $this->normalizeMetrics($totals['previous'] ?? []),
                        'delta' => $this->normalizeDelta($totals['delta'] ?? []),
                        'providers' => $this->normalizeProviders($totals['providers'] ?? []),
                    ],
                    'states' => $states,
                ];
            },
        );
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array{agents: int, agencies: int, affiliations_count: int, affiliations_amount: float}
     */
    private function normalizeMetrics(mixed $metrics): array
    {
        $metrics = is_array($metrics) ? $metrics : [];

        return [
            'agents' => (int) ($metrics['agents'] ?? 0),
            'agencies' => (int) ($metrics['agencies'] ?? 0),
            'affiliations_count' => (int) ($metrics['affiliations_count'] ?? 0),
            'affiliations_amount' => round((float) ($metrics['affiliations_amount'] ?? 0), 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $providers
     * @return array{juridical: int, natural: int, total: int}
     */
    private function normalizeProviders(mixed $providers): array
    {
        $providers = is_array($providers) ? $providers : [];
        $juridical = (int) ($providers['juridical'] ?? 0);
        $natural = (int) ($providers['natural'] ?? 0);

        return [
            'juridical' => $juridical,
            'natural' => $natural,
            'total' => (int) ($providers['total'] ?? ($juridical + $natural)),
        ];
    }

    /**
     * @param  array<string, mixed>  $delta
     * @return array{agents_pct: float|null, agencies_pct: float|null, affiliations_count_pct: float|null, affiliations_amount_pct: float|null}
     */
    private function normalizeDelta(mixed $delta): array
    {
        $delta = is_array($delta) ? $delta : [];

        return [
            'agents_pct' => $this->nullableFloat($delta['agents_pct'] ?? null),
            'agencies_pct' => $this->nullableFloat($delta['agencies_pct'] ?? null),
            'affiliations_count_pct' => $this->nullableFloat($delta['affiliations_count_pct'] ?? null),
            'affiliations_amount_pct' => $this->nullableFloat($delta['affiliations_amount_pct'] ?? null),
        ];
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 1);
    }

    private function cacheKey(string $suffix): string
    {
        return 'integracorp-api:metrics:dashboard:'.$suffix;
    }
}
