<?php

declare(strict_types=1);

namespace App\Services\IntegracorpApi;

use Illuminate\Support\Facades\Cache;

class CorretajeAgenciesMetricsClient
{
    private const CACHE_TTL_SECONDS = 60;

    public function __construct(private IntegracorpApiClient $api) {}

    /**
     * @return array{
     *     total_registered: int,
     *     total_active: int,
     *     total_masters: int,
     *     total_generals: int
     * }
     */
    public function summary(): array
    {
        /** @var array{total_registered: int, total_active: int, total_masters: int, total_generals: int} */
        return Cache::remember(
            'integracorp_api.metrics.corretaje.agencies.summary',
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            function (): array {
                $payload = $this->api->getJson('/api/metrics/corretaje/agencies');
                $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

                return [
                    'total_registered' => (int) ($data['total_registered'] ?? 0),
                    'total_active' => (int) ($data['total_active'] ?? 0),
                    'total_masters' => (int) ($data['total_masters'] ?? 0),
                    'total_generals' => (int) ($data['total_generals'] ?? 0),
                ];
            },
        );
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
    public function registrationComparison(): array
    {
        /** @var array{current_month: array{year: int, month: int, start: string, end_exclusive: string}, previous_month: array{year: int, month: int, start: string, end_exclusive: string}, master: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}, general: array{current: int, previous: int, delta: int, percent_change: float, trend: string, previous_was_zero: bool}, year_series: array{year: int, labels: list<string>, master: list<int>, general: list<int>}} */
        return Cache::remember(
            'integracorp_api.metrics.corretaje.agencies.registration-comparison-v3-created-at',
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            function (): array {
                $payload = $this->api->getJson('/api/metrics/corretaje/agencies/registration-comparison');
                $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

                return [
                    'current_month' => $this->normalizeMonthWindow($data['current_month'] ?? []),
                    'previous_month' => $this->normalizeMonthWindow($data['previous_month'] ?? []),
                    'master' => $this->normalizeComparison($data['master'] ?? []),
                    'general' => $this->normalizeComparison($data['general'] ?? []),
                    'year_series' => $this->normalizeAgenciesYearSeries($data['year_series'] ?? []),
                ];
            },
        );
    }

    /**
     * @return array{
     *     items: list<array{state_id: int|null, state: string, total_masters: int, total_generals: int, total: int}>,
     *     total_active: int,
     *     total_masters: int,
     *     total_generals: int
     * }
     */
    public function byState(): array
    {
        /** @var array{items: list<array{state_id: int|null, state: string, total_masters: int, total_generals: int, total: int}>, total_active: int, total_masters: int, total_generals: int} */
        return Cache::remember(
            $this->cacheKey('by-state'),
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            function (): array {
                $payload = $this->api->getJson('/api/metrics/corretaje/agencies/by-state');
                $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
                $rawItems = is_array($data['items'] ?? null) ? $data['items'] : [];

                $items = [];
                foreach ($rawItems as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $stateId = $item['state_id'] ?? null;
                    $totalMasters = (int) ($item['total_masters'] ?? 0);
                    $totalGenerals = (int) ($item['total_generals'] ?? 0);

                    $items[] = [
                        'state_id' => $stateId === null ? null : (int) $stateId,
                        'state' => trim((string) ($item['state'] ?? 'Sin estado')) ?: 'Sin estado',
                        'total_masters' => $totalMasters,
                        'total_generals' => $totalGenerals,
                        'total' => (int) ($item['total'] ?? ($totalMasters + $totalGenerals)),
                    ];
                }

                return [
                    'items' => $items,
                    'total_active' => (int) ($data['total_active'] ?? array_sum(array_column($items, 'total'))),
                    'total_masters' => (int) ($data['total_masters'] ?? array_sum(array_column($items, 'total_masters'))),
                    'total_generals' => (int) ($data['total_generals'] ?? array_sum(array_column($items, 'total_generals'))),
                ];
            },
        );
    }

    /**
     * @return array{
     *     items: list<array{agency_type_id: int, agency_type: string, total: int}>,
     *     total_masters: int,
     *     total_generals: int,
     *     total_affiliations: int
     * }
     */
    public function byActiveAffiliations(): array
    {
        return $this->fetchAffiliationsByAgencyType(
            cacheSuffix: 'by-active-affiliations',
            path: '/api/metrics/corretaje/agencies/by-active-affiliations',
        );
    }

    /**
     * @return array{
     *     items: list<array{agency_type_id: int, agency_type: string, total: int}>,
     *     total_masters: int,
     *     total_generals: int,
     *     total_affiliations: int
     * }
     */
    public function byActiveCorporateAffiliations(): array
    {
        return $this->fetchAffiliationsByAgencyType(
            cacheSuffix: 'by-active-corporate-affiliations',
            path: '/api/metrics/corretaje/agencies/by-active-corporate-affiliations',
        );
    }

    /**
     * @return array{
     *     agency_type_id: int,
     *     agency_type: string,
     *     items: list<array{agency_code: string, agency_name: string, total: int}>,
     *     total_affiliations: int,
     *     agencies_count: int,
     *     limit: int
     * }
     */
    public function byActiveAffiliationsByAgency(int $agencyTypeId): array
    {
        return $this->fetchAffiliationsByAgencyDetail(
            cacheSuffix: 'by-active-affiliations.by-agency.'.$agencyTypeId,
            path: '/api/metrics/corretaje/agencies/by-active-affiliations/by-agency',
            agencyTypeId: $agencyTypeId,
        );
    }

    /**
     * @return array{
     *     agency_type_id: int,
     *     agency_type: string,
     *     items: list<array{agency_code: string, agency_name: string, total: int}>,
     *     total_affiliations: int,
     *     agencies_count: int,
     *     limit: int
     * }
     */
    public function byActiveCorporateAffiliationsByAgency(int $agencyTypeId): array
    {
        return $this->fetchAffiliationsByAgencyDetail(
            cacheSuffix: 'by-active-corporate-affiliations.by-agency.'.$agencyTypeId,
            path: '/api/metrics/corretaje/agencies/by-active-corporate-affiliations/by-agency',
            agencyTypeId: $agencyTypeId,
        );
    }

    /**
     * @return array{
     *     items: list<array{agency_code: string, agency_name: string, amount_individual: float, amount_corporate: float, total_amount: float, individual_count: int, corporate_count: int}>,
     *     total_amount: float,
     *     total_individual_amount: float,
     *     total_corporate_amount: float,
     *     total_agencies: int,
     *     limit: int
     * }
     */
    public function byActiveAffiliationAmount(int $limit = 20): array
    {
        $limit = max(5, min(50, $limit));

        /** @var array{items: list<array{agency_code: string, agency_name: string, amount_individual: float, amount_corporate: float, total_amount: float, individual_count: int, corporate_count: int}>, total_amount: float, total_individual_amount: float, total_corporate_amount: float, total_agencies: int, limit: int} */
        return Cache::remember(
            $this->cacheKey('by-active-affiliation-amount.'.$limit),
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            function () use ($limit): array {
                $payload = $this->api->getJson('/api/metrics/corretaje/agencies/by-active-affiliation-amount', [
                    'limit' => $limit,
                ]);
                $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
                $rawItems = is_array($data['items'] ?? null) ? $data['items'] : [];

                $items = [];
                foreach ($rawItems as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $amountIndividual = round((float) ($item['amount_individual'] ?? 0), 2);
                    $amountCorporate = round((float) ($item['amount_corporate'] ?? 0), 2);

                    $items[] = [
                        'agency_code' => trim((string) ($item['agency_code'] ?? '')),
                        'agency_name' => trim((string) ($item['agency_name'] ?? '')) ?: 'Sin nombre',
                        'amount_individual' => $amountIndividual,
                        'amount_corporate' => $amountCorporate,
                        'total_amount' => round((float) ($item['total_amount'] ?? ($amountIndividual + $amountCorporate)), 2),
                        'individual_count' => (int) ($item['individual_count'] ?? 0),
                        'corporate_count' => (int) ($item['corporate_count'] ?? 0),
                    ];
                }

                $totalIndividualAmount = round((float) ($data['total_individual_amount'] ?? array_sum(array_column($items, 'amount_individual'))), 2);
                $totalCorporateAmount = round((float) ($data['total_corporate_amount'] ?? array_sum(array_column($items, 'amount_corporate'))), 2);

                return [
                    'items' => $items,
                    'total_amount' => round((float) ($data['total_amount'] ?? ($totalIndividualAmount + $totalCorporateAmount)), 2),
                    'total_individual_amount' => $totalIndividualAmount,
                    'total_corporate_amount' => $totalCorporateAmount,
                    'total_agencies' => (int) ($data['total_agencies'] ?? count($items)),
                    'limit' => (int) ($data['limit'] ?? $limit),
                ];
            },
        );
    }

    /**
     * @return array{
     *     agency_type_id: int,
     *     agency_type: string,
     *     items: list<array{agency_code: string, agency_name: string, total: int}>,
     *     total_affiliations: int,
     *     agencies_count: int,
     *     limit: int
     * }
     */
    private function fetchAffiliationsByAgencyDetail(string $cacheSuffix, string $path, int $agencyTypeId): array
    {
        /** @var array{agency_type_id: int, agency_type: string, items: list<array{agency_code: string, agency_name: string, total: int}>, total_affiliations: int, agencies_count: int, limit: int} */
        return Cache::remember(
            $this->cacheKey($cacheSuffix),
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            function () use ($path, $agencyTypeId): array {
                $payload = $this->api->getJson($path, [
                    'agency_type_id' => $agencyTypeId,
                ]);
                $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
                $rawItems = is_array($data['items'] ?? null) ? $data['items'] : [];

                $items = [];
                foreach ($rawItems as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $items[] = [
                        'agency_code' => trim((string) ($item['agency_code'] ?? '')),
                        'agency_name' => trim((string) ($item['agency_name'] ?? '')) ?: 'Sin nombre',
                        'total' => (int) ($item['total'] ?? 0),
                    ];
                }

                return [
                    'agency_type_id' => (int) ($data['agency_type_id'] ?? $agencyTypeId),
                    'agency_type' => trim((string) ($data['agency_type'] ?? '')) ?: '—',
                    'items' => $items,
                    'total_affiliations' => (int) ($data['total_affiliations'] ?? array_sum(array_column($items, 'total'))),
                    'agencies_count' => (int) ($data['agencies_count'] ?? count($items)),
                    'limit' => (int) ($data['limit'] ?? count($items)),
                ];
            },
        );
    }

    /**
     * @return array{
     *     items: list<array{agency_type_id: int, agency_type: string, total: int}>,
     *     total_masters: int,
     *     total_generals: int,
     *     total_affiliations: int
     * }
     */
    private function fetchAffiliationsByAgencyType(string $cacheSuffix, string $path): array
    {
        /** @var array{items: list<array{agency_type_id: int, agency_type: string, total: int}>, total_masters: int, total_generals: int, total_affiliations: int} */
        return Cache::remember(
            $this->cacheKey($cacheSuffix),
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            function () use ($path): array {
                $payload = $this->api->getJson($path);
                $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
                $rawItems = is_array($data['items'] ?? null) ? $data['items'] : [];

                $items = [];
                foreach ($rawItems as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $items[] = [
                        'agency_type_id' => (int) ($item['agency_type_id'] ?? 0),
                        'agency_type' => trim((string) ($item['agency_type'] ?? '')) ?: '—',
                        'total' => (int) ($item['total'] ?? 0),
                    ];
                }

                return [
                    'items' => $items,
                    'total_masters' => (int) ($data['total_masters'] ?? 0),
                    'total_generals' => (int) ($data['total_generals'] ?? 0),
                    'total_affiliations' => (int) ($data['total_affiliations'] ?? array_sum(array_column($items, 'total'))),
                ];
            },
        );
    }

    private function cacheKey(string $suffix): string
    {
        return 'integracorp_api.metrics.corretaje.agencies.'.$suffix;
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
     * @return array{year: int, labels: list<string>, master: list<int>, general: list<int>}
     */
    private function normalizeAgenciesYearSeries(array $series): array
    {
        $labels = $this->normalizeSeriesLabels($series['labels'] ?? []);
        $count = count($labels);

        return [
            'year' => (int) ($series['year'] ?? 0),
            'labels' => $labels,
            'master' => $this->normalizeSeriesValues($series['master'] ?? [], $count),
            'general' => $this->normalizeSeriesValues($series['general'] ?? [], $count),
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
}
