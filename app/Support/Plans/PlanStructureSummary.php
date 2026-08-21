<?php

declare(strict_types=1);

namespace App\Support\Plans;

use App\Models\AgeRange;
use App\Models\Benefit;
use App\Models\BenefitCoverage;
use App\Models\Coverage;
use App\Models\Fee;
use App\Models\Plan;

/**
 * Arma para la ficha del plan las mismas dos matrices que el analista llenó en
 * el asistente: costos límite por beneficio y tarifas por rango de edad, ambas
 * con las coberturas del plan como columnas.
 *
 * Que la ficha se lea igual que el formulario es el punto: es donde el analista
 * verifica lo que acaba de cargar.
 *
 * Cada método hace un puñado de consultas y arma el resto en memoria, para no
 * disparar una consulta por celda.
 *
 * @phpstan-type MatrixColumn array{key: string, label: string}
 * @phpstan-type MatrixRow array{label: string, cells: array<string, string|null>}
 * @phpstan-type Matrix array{columns: list<MatrixColumn>, rows: list<MatrixRow>}
 */
final class PlanStructureSummary
{
    /**
     * Columnas del plan: sus coberturas, de menor a mayor monto.
     *
     * @return list<array{key: string, label: string, id: int}>
     */
    public static function coverageColumns(Plan $plan): array
    {
        return Coverage::query()
            ->where('plan_id', $plan->getKey())
            ->orderBy('price')
            ->get()
            ->map(static fn (Coverage $coverage): array => [
                'key' => PlanStructureMatrix::keyForPersistedCoverage((int) $coverage->getKey()),
                'label' => PlanStructureMatrix::columnLabel((float) $coverage->price),
                'id' => (int) $coverage->getKey(),
            ])
            ->values()
            ->all();
    }

    /**
     * Matriz de costos límite: una fila por beneficio, una columna por
     * cobertura. Una celda vacía significa que ese beneficio no tiene límite en
     * esa cobertura, y se muestra distinto de un límite en cero.
     *
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array{label: string, cells: array<string, string|null>}>}
     */
    public static function limitsMatrix(Plan $plan): array
    {
        $columns = self::coverageColumns($plan);

        $limits = BenefitCoverage::query()
            ->where('plan_id', $plan->getKey())
            ->get()
            ->keyBy(static fn (BenefitCoverage $row): string => $row->benefit_id.'-'.$row->coverage_id);

        $rows = [];

        foreach (self::planBenefits($plan) as $benefit) {
            $cells = [];

            foreach ($columns as $column) {
                $limit = $limits->get($benefit->getKey().'-'.$column['id'])?->limit;

                $cells[$column['key']] = $limit === null
                    ? null
                    : PlanStructureMatrix::columnLabel((float) $limit);
            }

            $rows[] = [
                'label' => (string) $benefit->description,
                'cells' => $cells,
            ];
        }

        return self::matrix($columns, $rows);
    }

    /**
     * Matriz de tarifas: una fila por rango de edad, una columna por cobertura.
     *
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array{label: string, cells: array<string, string|null>}>}
     */
    public static function ratesMatrix(Plan $plan): array
    {
        $columns = self::coverageColumns($plan);

        $fees = Fee::query()
            ->where('plan_id', $plan->getKey())
            ->whereNotNull('coverage_id')
            ->get()
            ->keyBy(static fn (Fee $fee): string => $fee->age_range_id.'-'.$fee->coverage_id);

        $rows = [];

        foreach (self::planAgeRanges($plan) as $ageRange) {
            $cells = [];

            foreach ($columns as $column) {
                $price = $fees->get($ageRange->getKey().'-'.$column['id'])?->price;

                $cells[$column['key']] = $price === null
                    ? null
                    : PlanStructureMatrix::columnLabel((float) $price);
            }

            $rows[] = [
                'label' => self::ageRangeLabel($ageRange),
                'cells' => $cells,
            ];
        }

        return self::matrix($columns, $rows);
    }

    /**
     * Tarifas de un paquete de beneficios: una sola por rango de edad, sin
     * cobertura. Sin esto, un paquete se vería vacío en la ficha.
     *
     * @return list<array{range: string, rate: string|null}>
     */
    public static function flatRates(Plan $plan): array
    {
        $fees = Fee::query()
            ->where('plan_id', $plan->getKey())
            ->whereNull('coverage_id')
            ->get()
            ->keyBy(static fn (Fee $fee): int => (int) $fee->age_range_id);

        $rows = [];

        foreach (self::planAgeRanges($plan) as $ageRange) {
            $price = $fees->get((int) $ageRange->getKey())?->price;

            $rows[] = [
                'range' => self::ageRangeLabel($ageRange),
                'rate' => $price === null ? null : PlanStructureMatrix::columnLabel((float) $price),
            ];
        }

        return $rows;
    }

    public static function ageRangeLabel(AgeRange $ageRange): string
    {
        if (filled($ageRange->age_init) && filled($ageRange->age_end)) {
            return $ageRange->age_init.' a '.$ageRange->age_end.' años';
        }

        return filled($ageRange->range) ? (string) $ageRange->range : '—';
    }

    /**
     * @return \Illuminate\Support\Collection<int, Benefit>
     */
    private static function planBenefits(Plan $plan)
    {
        return $plan->benefitPlans()
            ->orderBy('description')
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, AgeRange>
     */
    private static function planAgeRanges(Plan $plan)
    {
        return AgeRange::query()
            ->where('plan_id', $plan->getKey())
            ->where(static fn ($query) => $query->where('status', 'ACTIVO')->orWhereNull('status'))
            ->orderBy('age_init')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  list<array{key: string, label: string, id: int}>  $columns
     * @param  list<array{label: string, cells: array<string, string|null>}>  $rows
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array{label: string, cells: array<string, string|null>}>}
     */
    private static function matrix(array $columns, array $rows): array
    {
        return [
            'columns' => array_map(
                static fn (array $column): array => ['key' => $column['key'], 'label' => $column['label']],
                $columns,
            ),
            'rows' => $rows,
        ];
    }
}
