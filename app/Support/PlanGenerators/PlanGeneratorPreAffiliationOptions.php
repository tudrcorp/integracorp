<?php

declare(strict_types=1);

namespace App\Support\PlanGenerators;

use App\Models\PlanGenerator;

/**
 * Traduce la matriz de un plan generado en las opciones que el analista escoge
 * al pre-afiliar, con los mismos números que ya muestra la cotización.
 *
 * Dos granularidades, porque los dos flujos no afilian lo mismo:
 *
 *   - **Individual**: una fila por (cobertura × rango etario). Un titular tiene
 *     una edad, así que necesita la tarifa exacta de su rango, no el promedio
 *     del grupo.
 *   - **Corporativo**: una fila por cobertura. Se afilia la población completa,
 *     que atraviesa todos los rangos etarios de esa columna.
 *
 * Los subtotales se calculan igual que en la tabla «Detalles de la cotización»
 * de Negocios: `subtotal_anual = tarifa individual × población`, semestral la
 * mitad, trimestral un cuarto, mensual un doceavo.
 */
final class PlanGeneratorPreAffiliationOptions
{
    /**
     * Separador de la clave de opción individual. `::` no aparece en un uuid de
     * columna, así que la clave se puede partir sin ambigüedad.
     */
    private const KEY_SEPARATOR = '::';

    /**
     * Filas seleccionables para afiliación individual.
     *
     * @return list<array{
     *     key: string,
     *     column_key: string,
     *     column_label: string,
     *     age_range_label: string,
     *     population: int,
     *     fee: float,
     *     subtotal_anual: float,
     *     subtotal_biannual: float,
     *     subtotal_quarterly: float,
     *     subtotal_monthly: float
     * }>
     */
    public static function individualRows(PlanGenerator $plan): array
    {
        $matrix = PlanGeneratorPersistence::formStateFromModel($plan);
        $columns = PlanGeneratorMatrixState::normalizeColumns($matrix['columns']);
        $rateRows = array_values($matrix['rate_rows']);

        $rows = [];

        foreach ($columns as $column) {
            $columnKey = $column['column_key'];

            foreach ($rateRows as $index => $rateRow) {
                if (! is_array($rateRow)) {
                    continue;
                }

                $fee = PlanGeneratorGroupTotalCalculator::parseAmount(
                    data_get($rateRow, "cells.{$columnKey}.rate_amount"),
                );

                // Un rango sin tarifa en esa columna no es una opción afiliable.
                if ($fee <= 0) {
                    continue;
                }

                $population = max(1, (int) ($rateRow['population'] ?? 0));
                $annual = $fee * $population;

                $rows[] = [
                    'key' => $columnKey.self::KEY_SEPARATOR.$index,
                    'column_key' => $columnKey,
                    'column_label' => $column['header_label'],
                    'age_range_label' => trim((string) ($rateRow['age_range_label'] ?? '')) !== ''
                        ? (string) $rateRow['age_range_label']
                        : 'Sin rango',
                    'population' => $population,
                    'fee' => $fee,
                    'subtotal_anual' => $annual,
                    'subtotal_biannual' => $annual / 2,
                    'subtotal_quarterly' => $annual / 4,
                    'subtotal_monthly' => $annual / 12,
                ];
            }
        }

        return $rows;
    }

    /**
     * Filas seleccionables para afiliación corporativa: una por cobertura.
     *
     * @return list<array{
     *     column_key: string,
     *     column_label: string,
     *     population: int,
     *     age_ranges: int,
     *     fee: float,
     *     subtotal_anual: float,
     *     subtotal_biannual: float,
     *     subtotal_quarterly: float,
     *     subtotal_monthly: float
     * }>
     */
    public static function corporateRows(PlanGenerator $plan): array
    {
        $matrix = PlanGeneratorPersistence::formStateFromModel($plan);
        $columns = PlanGeneratorMatrixState::normalizeColumns($matrix['columns']);
        $rateRows = array_values($matrix['rate_rows']);
        $groupTotals = PlanGeneratorGroupTotalCalculator::totalsByColumn($columns, $rateRows);

        $rows = [];

        foreach ($columns as $column) {
            $columnKey = $column['column_key'];
            $annual = (float) ($groupTotals['annual'][$columnKey] ?? 0);

            if ($annual <= 0) {
                continue;
            }

            $population = 0;
            $ageRanges = 0;

            foreach ($rateRows as $rateRow) {
                if (! is_array($rateRow)) {
                    continue;
                }

                $fee = PlanGeneratorGroupTotalCalculator::parseAmount(
                    data_get($rateRow, "cells.{$columnKey}.rate_amount"),
                );

                if ($fee <= 0) {
                    continue;
                }

                $population += max(0, (int) ($rateRow['population'] ?? 0));
                $ageRanges++;
            }

            $population = max(1, $population);

            $rows[] = [
                'column_key' => $columnKey,
                'column_label' => $column['header_label'],
                'population' => $population,
                'age_ranges' => $ageRanges,
                // Tarifa promedio ponderada de la columna: la matriz tiene una
                // tarifa por rango etario y `afilliation_corporate_plans` guarda
                // una sola por plan.
                'fee' => $annual / $population,
                'subtotal_anual' => $annual,
                'subtotal_biannual' => (float) ($groupTotals['semestral'][$columnKey] ?? 0),
                'subtotal_quarterly' => (float) ($groupTotals['trimestral'][$columnKey] ?? 0),
                'subtotal_monthly' => (float) ($groupTotals['mensual'][$columnKey] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * Opciones del radio individual, agrupadas por cobertura en la etiqueta.
     *
     * @return array<string, string>
     */
    public static function individualOptions(PlanGenerator $plan): array
    {
        $options = [];

        foreach (self::individualRows($plan) as $row) {
            $options[$row['key']] = $row['column_label'].'  ·  Rango '.$row['age_range_label'];
        }

        return $options;
    }

    /**
     * Descripción de cada opción individual.
     *
     * Muestra la **tarifa unitaria** y, aparte, la población que ese rango tiene
     * cotizada. No muestra el total del rango como si fuera el de la afiliación:
     * una afiliación individual es un titular y sus beneficiarios, no las 230
     * personas que puede llevar cotizadas el rango.
     *
     * @return array<string, string>
     */
    public static function individualDescriptions(PlanGenerator $plan): array
    {
        $descriptions = [];

        foreach (self::individualRows($plan) as $row) {
            $descriptions[$row['key']] = 'Tarifa individual anual '.self::money($row['fee'])
                .' · el rango tiene '.$row['population'].' '
                .($row['population'] === 1 ? 'persona cotizada' : 'personas cotizadas');
        }

        return $descriptions;
    }

    /**
     * Subtotales de una afiliación individual para la cantidad de personas que
     * indique el analista, con la tarifa unitaria de la opción elegida.
     *
     * @param  array<string, mixed>  $row
     * @return array{
     *     people: int,
     *     fee: float,
     *     subtotal_anual: float,
     *     subtotal_biannual: float,
     *     subtotal_quarterly: float,
     *     subtotal_monthly: float
     * }
     */
    public static function amountsForPeople(array $row, int $people): array
    {
        $people = max(1, $people);
        $fee = (float) ($row['fee'] ?? 0);
        $annual = $fee * $people;

        return [
            'people' => $people,
            'fee' => $fee,
            'subtotal_anual' => $annual,
            'subtotal_biannual' => $annual / 2,
            'subtotal_quarterly' => $annual / 4,
            'subtotal_monthly' => $annual / 12,
        ];
    }

    /**
     * Línea de totales que la modal individual muestra en vivo al cambiar la
     * cantidad de personas.
     *
     * @param  array<string, mixed>|null  $row
     */
    public static function individualTotalsLine(?array $row, int $people): string
    {
        if ($row === null) {
            return 'Marque una cobertura para ver el total.';
        }

        $amounts = self::amountsForPeople($row, $people);

        return $amounts['people'].' '.($amounts['people'] === 1 ? 'persona' : 'personas')
            .' × '.self::money($amounts['fee'])
            .'  =  Anual '.self::money($amounts['subtotal_anual'])
            .' · Semestral '.self::money($amounts['subtotal_biannual'])
            .' · Trimestral '.self::money($amounts['subtotal_quarterly']);
    }

    /**
     * @return array<string, string>
     */
    public static function corporateOptions(PlanGenerator $plan): array
    {
        $options = [];

        foreach (self::corporateRows($plan) as $row) {
            $options[$row['column_key']] = $row['column_label'];
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function corporateDescriptions(PlanGenerator $plan): array
    {
        $descriptions = [];

        foreach (self::corporateRows($plan) as $row) {
            $descriptions[$row['column_key']] = self::amountsLine($row).' · '
                .$row['age_ranges'].' '.($row['age_ranges'] === 1 ? 'rango etario' : 'rangos etarios');
        }

        return $descriptions;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function amountsLine(array $row): string
    {
        $people = (int) ($row['population'] ?? 0);

        return 'Tarifa individual '.self::money($row['fee'] ?? 0)
            .' · '.$people.' '.($people === 1 ? 'persona' : 'personas')
            .' · Anual '.self::money($row['subtotal_anual'] ?? 0)
            .' · Semestral '.self::money($row['subtotal_biannual'] ?? 0)
            .' · Trimestral '.self::money($row['subtotal_quarterly'] ?? 0);
    }

    public static function money(mixed $amount): string
    {
        return 'US$ '.number_format((float) $amount, 0, ',', '.');
    }

    /**
     * Abre una cobertura elegida en una fila de `afilliation_corporate_plans`
     * por rango etario, con las referencias reales del catálogo.
     *
     * La modal corporativa se elige por cobertura, pero la tabla «Plan(es)
     * Afiliado(s)» muestra plan, cobertura **y rango de edad**: una sola fila
     * agregada por columna dejaba el rango vacío y la tarifa como promedio
     * ponderado. Acá cada rango lleva su tarifa real y su población.
     *
     * @param  array<string, mixed>  $dataRecord  registro de sesión de la cobertura elegida
     * @param  array{plan: \App\Models\Plan, coverage_ids: array<string, int>, age_range_ids: array<string, int>}  $catalog
     * @return list<array<string, mixed>>
     */
    public static function corporatePlanRowsForColumn(
        \App\Models\Plan $plan,
        array $dataRecord,
        array $catalog,
    ): array {
        $columnKey = (string) ($dataRecord['column_key'] ?? '');
        $coverageId = $catalog['coverage_ids'][$columnKey] ?? null;

        if ($coverageId === null) {
            return [$dataRecord];
        }

        $generator = PlanGenerator::query()->find($dataRecord['plan_generator_id'] ?? null);

        if ($generator === null) {
            return [$dataRecord];
        }

        $matrix = PlanGeneratorPersistence::formStateFromModel($generator);
        $rows = [];

        foreach (array_values((array) $matrix['rate_rows']) as $rateRow) {
            if (! is_array($rateRow)) {
                continue;
            }

            $label = trim((string) ($rateRow['age_range_label'] ?? ''));
            $fee = PlanGeneratorGroupTotalCalculator::parseAmount(
                data_get($rateRow, "cells.{$columnKey}.rate_amount"),
            );

            if ($fee <= 0 || ! isset($catalog['age_range_ids'][$label])) {
                continue;
            }

            $population = max(1, (int) ($rateRow['population'] ?? 0));
            $annual = $fee * $population;

            $rows[] = [
                'plan_id' => $plan->getKey(),
                'coverage_id' => $coverageId,
                'age_range_id' => $catalog['age_range_ids'][$label],
                'total_persons' => $population,
                'fee' => $fee,
                'subtotal_anual' => $annual,
                'subtotal_biannual' => $annual / 2,
                'subtotal_quarterly' => $annual / 4,
                'subtotal_monthly' => $annual / 12,
            ];
        }

        // Si la matriz no dejó ninguna fila usable, se conserva la agregada para
        // no perder el plan de la afiliación.
        return $rows === [] ? [$dataRecord] : $rows;
    }

    /**
     * Fila individual elegida, o null si la clave ya no existe en la matriz.
     *
     * @return array<string, mixed>|null
     */
    public static function findIndividualRow(PlanGenerator $plan, ?string $key): ?array
    {
        if (! is_string($key) || $key === '') {
            return null;
        }

        foreach (self::individualRows($plan) as $row) {
            if ($row['key'] === $key) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Filas corporativas elegidas, en el orden de las columnas de la matriz.
     *
     * @param  array<int, mixed>  $columnKeys
     * @return list<array<string, mixed>>
     */
    public static function findCorporateRows(PlanGenerator $plan, array $columnKeys): array
    {
        $wanted = array_values(array_filter(array_map(
            static fn (mixed $key): string => is_string($key) ? $key : '',
            $columnKeys,
        )));

        return array_values(array_filter(
            self::corporateRows($plan),
            static fn (array $row): bool => in_array($row['column_key'], $wanted, true),
        ));
    }
}
