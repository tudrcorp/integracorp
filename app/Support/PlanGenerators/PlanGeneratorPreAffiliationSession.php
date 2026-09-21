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
        self::put(self::buildPayload($plan, $type));
    }

    /**
     * Guarda la sesión con la cobertura y el rango etario que eligió el
     * analista, en vez de adivinar la primera columna de la matriz.
     *
     * `$people` es la cantidad de personas de **esta** afiliación (titular más
     * beneficiarios), no la población que el rango lleva cotizada: el
     * formulario individual abre un bloque de afiliado por persona y con la
     * población del rango —que puede ser de cientos— la pantalla se vuelve
     * inusable.
     *
     * @param  array<string, mixed>  $individualRow  fila de PlanGeneratorPreAffiliationOptions::individualRows()
     */
    public static function storeIndividual(PlanGenerator $plan, array $individualRow, int $people = 1): void
    {
        $payload = self::buildPayload($plan, self::TYPE_INDIVIDUAL);
        $amounts = PlanGeneratorPreAffiliationOptions::amountsForPeople($individualRow, $people);

        $payload['selection'] = [
            'column_key' => $individualRow['column_key'] ?? null,
            'column_label' => $individualRow['column_label'] ?? null,
            'age_range_label' => $individualRow['age_range_label'] ?? null,
            'people' => $amounts['people'],
        ];
        $payload['total_persons'] = $amounts['people'];
        $payload['data_records'] = [self::dataRecordFromRow(
            $plan,
            [...$individualRow, ...$amounts, 'population' => $amounts['people']],
            self::TYPE_INDIVIDUAL,
        )];

        self::put($payload);
    }

    /**
     * @param  list<array<string, mixed>>  $corporateRows  filas de PlanGeneratorPreAffiliationOptions::corporateRows()
     */
    public static function storeCorporate(PlanGenerator $plan, array $corporateRows): void
    {
        $payload = self::buildPayload($plan, self::TYPE_CORPORATE);

        $records = [];
        $population = 0;

        foreach ($corporateRows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $records[] = self::dataRecordFromRow($plan, $row, self::TYPE_CORPORATE);
            // Cada columna cubre a la misma población; se toma el mayor por si
            // alguna cobertura no tarifa todos los rangos etarios.
            $population = max($population, (int) ($row['population'] ?? 0));
        }

        $payload['selection'] = [
            'column_keys' => array_values(array_map(
                static fn (array $row): mixed => $row['column_key'] ?? null,
                array_filter($corporateRows, 'is_array'),
            )),
        ];
        $payload['total_persons'] = max(1, $population);
        $payload['data_records'] = $records;

        self::put($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function put(array $payload): void
    {
        self::forget();

        session()->put(self::SESSION_KEY, $payload);
        session()->put('data_records', $payload['data_records']);
        session()->put('persons', $payload['total_persons']);
    }

    /**
     * Registro que consumen los formularios de afiliación.
     *
     * `plan_id`, `coverage_id` y `age_range_id` van en cero o nulos a propósito:
     * la matriz de un plan generado no referencia el catálogo de planes ni de
     * coberturas, es su propia copia congelada. Lo que importa es la tarifa y
     * los subtotales.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function dataRecordFromRow(PlanGenerator $plan, array $row, string $type): array
    {
        $isCorporate = $type !== self::TYPE_INDIVIDUAL;

        return [
            'source' => 'plan_generator',
            'plan_generator_id' => $plan->getKey(),
            'column_key' => $row['column_key'] ?? null,
            'header_label' => $row['column_label'] ?? null,
            'age_range_label' => $row['age_range_label'] ?? null,
            'individual_quote_id' => null,
            'corporate_quote_id' => $isCorporate ? 0 : null,
            'plan_id' => $isCorporate ? 0 : null,
            'coverage_id' => null,
            'age_range_id' => $isCorporate ? 0 : null,
            'total_persons' => max(1, (int) ($row['population'] ?? 1)),
            'fee' => (float) ($row['fee'] ?? 0),
            'subtotal_anual' => (float) ($row['subtotal_anual'] ?? 0),
            'subtotal_biannual' => (float) ($row['subtotal_biannual'] ?? 0),
            'subtotal_quarterly' => (float) ($row['subtotal_quarterly'] ?? 0),
            'subtotal_monthly' => (float) ($row['subtotal_monthly'] ?? 0),
        ];
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

    /**
     * Resumen de lo que el analista eligió pre-afiliar, para mostrarlo en el
     * formulario de afiliación. Se arma desde `data_records` —lo elegido— y no
     * desde todas las columnas de la matriz.
     */
    public static function ratesSummary(): string
    {
        $payload = self::get();

        if ($payload === null) {
            return '—';
        }

        $lines = [];

        foreach (is_array($payload['data_records'] ?? null) ? $payload['data_records'] : [] as $record) {
            if (! is_array($record)) {
                continue;
            }

            $label = (string) ($record['header_label'] ?? 'Plan');
            $ageRange = $record['age_range_label'] ?? null;

            if (is_string($ageRange) && $ageRange !== '') {
                $label .= ' ('.$ageRange.')';
            }

            $lines[] = $label.': '
                .PlanGeneratorGroupTotalCalculator::formatGroupTotal((float) ($record['subtotal_anual'] ?? 0))
                .' anual';
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
