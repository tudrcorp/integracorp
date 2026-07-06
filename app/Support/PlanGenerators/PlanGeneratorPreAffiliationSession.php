<?php

declare(strict_types=1);

namespace App\Support\PlanGenerators;

use App\Models\PlanGenerator;

final class PlanGeneratorPreAffiliationSession
{
    public const SESSION_KEY = 'plan_generator_pre_affiliation';

    public const TYPE_INDIVIDUAL = 'individual';

    public const TYPE_CORPORATE = 'corporate';

    public const TYPE_NEW_BUSINESS = 'new_business';

    public static function store(PlanGenerator $plan, string $type): void
    {
        self::forget();

        $payload = self::buildPayload($plan, $type);

        session()->put(self::SESSION_KEY, $payload);
        session()->put('data_records', $payload['data_records']);
        session()->put('persons', $payload['total_persons']);
    }

    public static function isActive(): bool
    {
        return session()->has(self::SESSION_KEY);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(): ?array
    {
        $data = session()->get(self::SESSION_KEY);

        return is_array($data) ? $data : null;
    }

    public static function type(): ?string
    {
        return self::get()['type'] ?? null;
    }

    public static function forget(): void
    {
        session()->forget([
            self::SESSION_KEY,
            'data_records',
            'persons',
            'affiliates',
        ]);
    }

    public static function ratesSummary(): string
    {
        $payload = self::get();

        if ($payload === null) {
            return '—';
        }

        $lines = [];
        $columns = is_array($payload['columns'] ?? null) ? $payload['columns'] : [];
        $groupTotals = is_array($payload['group_totals'] ?? null) ? $payload['group_totals'] : [];

        foreach ($columns as $column) {
            if (! is_array($column)) {
                continue;
            }

            $columnKey = $column['column_key'] ?? null;
            $label = (string) ($column['header_label'] ?? 'Plan');

            if (! is_string($columnKey) || $columnKey === '') {
                continue;
            }

            $annual = (float) ($groupTotals['annual'][$columnKey] ?? 0);
            $lines[] = $label.': '.PlanGeneratorGroupTotalCalculator::formatGroupTotal($annual).' anual';
        }

        return $lines === [] ? '—' : implode(' · ', $lines);
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildPayloadForPlan(PlanGenerator $plan, string $type): array
    {
        return self::buildPayload($plan, $type);
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildPayload(PlanGenerator $plan, string $type): array
    {
        $plan->loadMissing(['columns', 'rows.cells', 'rateRows.cells']);

        $matrixState = PlanGeneratorPersistence::formStateFromModel($plan);
        $columns = $matrixState['columns'];
        $rateRows = $matrixState['rate_rows'];
        $groupTotals = PlanGeneratorGroupTotalCalculator::totalsByColumn($columns, $rateRows);
        $columnKeys = PlanGeneratorMatrixState::extractColumnKeys($columns);
        $primaryColumnKey = $columnKeys[0] ?? null;
        $totalPersons = self::totalPopulation($rateRows);

        $dataRecords = match ($type) {
            self::TYPE_CORPORATE, self::TYPE_NEW_BUSINESS => self::buildCorporateDataRecords($plan, $columns, $columnKeys, $groupTotals, $totalPersons),
            default => [self::buildIndividualDataRecord($plan, $primaryColumnKey, $groupTotals, $totalPersons)],
        };

        return [
            'type' => $type,
            'plan_generator_id' => $plan->getKey(),
            'plan' => [
                'name' => $plan->name,
                'control_number' => $plan->control_number,
                'client_data' => $plan->client_data,
                'agent_name' => $plan->agent_name,
                'population_summary' => $plan->population_summary,
                'population_unit' => $plan->population_unit,
                'include_monthly_total' => (bool) $plan->include_monthly_total,
            ],
            'columns' => $columns,
            'rate_rows' => array_values($rateRows),
            'group_totals' => $groupTotals,
            'total_persons' => $totalPersons,
            'data_records' => $dataRecords,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $rateRows
     */
    private static function totalPopulation(array $rateRows): int
    {
        $total = 0;

        foreach ($rateRows as $rateRow) {
            if (! is_array($rateRow)) {
                continue;
            }

            $total += max(0, (int) ($rateRow['population'] ?? 0));
        }

        return $total > 0 ? $total : 1;
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @param  array<int, string>  $columnKeys
     * @param  array{
     *     annual: array<string, float>,
     *     semestral: array<string, float>,
     *     trimestral: array<string, float>,
     *     mensual: array<string, float>
     * }  $groupTotals
     * @return array<string, mixed>
     */
    private static function buildIndividualDataRecord(
        PlanGenerator $plan,
        ?string $primaryColumnKey,
        array $groupTotals,
        int $totalPersons,
    ): array {
        $annual = $primaryColumnKey !== null
            ? (float) ($groupTotals['annual'][$primaryColumnKey] ?? 0)
            : 0.0;

        return [
            'source' => 'plan_generator',
            'plan_generator_id' => $plan->getKey(),
            'individual_quote_id' => null,
            'plan_id' => null,
            'coverage_id' => null,
            'total_persons' => $totalPersons,
            'fee' => $totalPersons > 0 ? $annual / $totalPersons : 0,
            'subtotal_anual' => $annual,
            'subtotal_biannual' => $primaryColumnKey !== null
                ? (float) ($groupTotals['semestral'][$primaryColumnKey] ?? 0)
                : 0.0,
            'subtotal_quarterly' => $primaryColumnKey !== null
                ? (float) ($groupTotals['trimestral'][$primaryColumnKey] ?? 0)
                : 0.0,
            'subtotal_monthly' => $primaryColumnKey !== null
                ? (float) ($groupTotals['mensual'][$primaryColumnKey] ?? 0)
                : 0.0,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @param  array<int, string>  $columnKeys
     * @param  array{
     *     annual: array<string, float>,
     *     semestral: array<string, float>,
     *     trimestral: array<string, float>,
     *     mensual: array<string, float>
     * }  $groupTotals
     * @return array<int, array<string, mixed>>
     */
    private static function buildCorporateDataRecords(
        PlanGenerator $plan,
        array $columns,
        array $columnKeys,
        array $groupTotals,
        int $totalPersons,
    ): array {
        $records = [];

        foreach ($columnKeys as $columnKey) {
            $headerLabel = collect($columns)
                ->firstWhere('column_key', $columnKey)['header_label'] ?? 'Plan';

            $annual = (float) ($groupTotals['annual'][$columnKey] ?? 0);

            $records[] = [
                'source' => 'plan_generator',
                'plan_generator_id' => $plan->getKey(),
                'header_label' => $headerLabel,
                'column_key' => $columnKey,
                'corporate_quote_id' => 0,
                'plan_id' => 0,
                'coverage_id' => null,
                'age_range_id' => 0,
                'total_persons' => $totalPersons,
                'fee' => $totalPersons > 0 ? $annual / $totalPersons : 0,
                'subtotal_anual' => $annual,
                'subtotal_biannual' => (float) ($groupTotals['semestral'][$columnKey] ?? 0),
                'subtotal_quarterly' => (float) ($groupTotals['trimestral'][$columnKey] ?? 0),
                'subtotal_monthly' => (float) ($groupTotals['mensual'][$columnKey] ?? 0),
            ];
        }

        return $records;
    }
}
