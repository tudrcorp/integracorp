<?php

declare(strict_types=1);

namespace App\Support\PlanGenerators;

use App\Models\AgeRange;
use App\Models\Benefit;
use App\Models\BenefitCoverage;
use App\Models\Coverage;
use App\Models\Fee;
use App\Models\Plan;
use App\Support\Plans\PlanStructureSummary;
use Illuminate\Support\Str;

/**
 * Convierte un plan del catálogo en el estado de formulario que ya consume el
 * generador de cotizaciones, para que el analista solo tenga que cargar las
 * imágenes de la cotización y la población del cliente.
 *
 * La correspondencia es casi 1:1:
 *
 *   coberturas del plan   -> columnas de la matriz
 *   beneficios del plan   -> filas de beneficios
 *   benefit_coverages     -> celdas (incluido + costo límite)
 *   rangos de edad        -> filas de tarifa
 *   fees                  -> celdas de tarifa
 *
 * Lo que **no** sale del plan es la población por rango de edad: es un dato del
 * cliente, no del producto, y `PlanGeneratorPopulationValidator` exige que
 * cuadre con el total declarado. Por eso las filas se importan con población
 * vacía.
 *
 * Un paquete de beneficios no tiene coberturas, así que genera una única
 * columna con el nombre del plan y la tarifa plana de cada rango de edad; de lo
 * contrario no se podría cotizar desde el generador.
 *
 * Devuelve arrays planos, sin tocar base más allá de leer el plan: quien decide
 * escribir es la página de Filament, con la persistencia que ya existe.
 */
final class PlanGeneratorStructureImporter
{
    /**
     * Coberturas ofrecibles del plan, para que el analista elija cuáles entran
     * en la cotización. Un mismo plan suele cotizarse con un subconjunto.
     *
     * @return array<string, string> id de cobertura => etiqueta
     */
    public static function coverageOptions(Plan $plan): array
    {
        if ($plan->isBenefitPackage()) {
            return [self::PACKAGE_COLUMN_ID => self::packageColumnLabel($plan)];
        }

        $options = [];

        foreach (PlanStructureSummary::coverageColumns($plan) as $column) {
            $options[(string) $column['id']] = self::columnLabel($plan, (float) self::coveragePrice($column['id']));
        }

        return $options;
    }

    /**
     * Identificador de la columna única de un paquete de beneficios, que no
     * corresponde a ninguna cobertura real.
     */
    public const PACKAGE_COLUMN_ID = 'paquete';

    /**
     * Estado de formulario listo para volcar en el generador.
     *
     * @param  list<string|int>  $selectedCoverageIds  Coberturas elegidas; vacío = todas.
     * @return array{columns: list<array{column_key: string, header_label: string}>, rows: array<string, mixed>, rate_rows: array<string, mixed>, summary: array{columns: int, benefits: int, age_ranges: int}}
     */
    public static function build(Plan $plan, array $selectedCoverageIds = []): array
    {
        $columns = self::buildColumns($plan, $selectedCoverageIds);

        return [
            'columns' => array_map(
                static fn (array $column): array => [
                    'column_key' => $column['column_key'],
                    'header_label' => $column['header_label'],
                ],
                $columns,
            ),
            'rows' => self::buildBenefitRows($plan, $columns),
            'rate_rows' => self::buildRateRows($plan, $columns),
            'summary' => [
                'columns' => count($columns),
                'benefits' => self::planBenefits($plan)->count(),
                'age_ranges' => count(self::planAgeRanges($plan)),
            ],
        ];
    }

    /**
     * Etiqueta comercial de la columna. Las cotizaciones reales no nombran las
     * columnas con el monto crudo sino con el plan y el monto abreviado
     * ("PLAN ESPECIAL 5K"), así que se propone eso y el analista lo edita.
     */
    public static function columnLabel(Plan $plan, float $coveragePrice): string
    {
        return trim(mb_strtoupper((string) $plan->description).' '.self::abbreviateAmount($coveragePrice));
    }

    /**
     * 5000 -> 5K, 10000 -> 10K, 1500 -> 1.5K, 500 -> 500.
     */
    public static function abbreviateAmount(float $amount): string
    {
        if ($amount < 1000.0) {
            return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
        }

        $thousands = $amount / 1000.0;

        $formatted = fmod($thousands, 1.0) === 0.0
            ? (string) (int) $thousands
            : rtrim(rtrim(number_format($thousands, 1, '.', ''), '0'), '.');

        return $formatted.'K';
    }

    private static function packageColumnLabel(Plan $plan): string
    {
        return mb_strtoupper((string) $plan->description);
    }

    /**
     * @param  list<string|int>  $selectedCoverageIds
     * @return list<array{column_key: string, header_label: string, coverage_id: int|null}>
     */
    private static function buildColumns(Plan $plan, array $selectedCoverageIds): array
    {
        $selected = array_map(static fn (mixed $id): string => (string) $id, $selectedCoverageIds);

        if ($plan->isBenefitPackage()) {
            if ($selected !== [] && ! in_array(self::PACKAGE_COLUMN_ID, $selected, true)) {
                return [];
            }

            return [[
                'column_key' => (string) Str::uuid(),
                'header_label' => self::packageColumnLabel($plan),
                'coverage_id' => null,
            ]];
        }

        $columns = [];

        foreach (self::planCoverages($plan) as $coverage) {
            if ($selected !== [] && ! in_array((string) $coverage->getKey(), $selected, true)) {
                continue;
            }

            $columns[] = [
                'column_key' => (string) Str::uuid(),
                'header_label' => self::columnLabel($plan, (float) $coverage->price),
                'coverage_id' => (int) $coverage->getKey(),
            ];
        }

        return $columns;
    }

    /**
     * Filas de beneficios. Un beneficio asignado al plan está incluido en todas
     * sus coberturas; el costo límite se muestra cuando existe, y un límite en
     * NULL se lee como "incluido sin tope", no como "no incluido".
     *
     * @param  list<array{column_key: string, header_label: string, coverage_id: int|null}>  $columns
     * @return array<string, mixed>
     */
    private static function buildBenefitRows(Plan $plan, array $columns): array
    {
        $limits = BenefitCoverage::query()
            ->where('plan_id', $plan->getKey())
            ->get()
            ->keyBy(static fn (BenefitCoverage $row): string => $row->benefit_id.'-'.$row->coverage_id);

        $rows = [];

        foreach (self::planBenefits($plan) as $benefit) {
            $cells = [];

            foreach ($columns as $column) {
                $limit = $column['coverage_id'] === null
                    ? null
                    : $limits->get($benefit->getKey().'-'.$column['coverage_id'])?->limit;

                $cells[$column['column_key']] = [
                    'is_selected' => true,
                    'coverage_amount' => $limit === null ? null : (float) $limit,
                ];
            }

            $rows[PlanGeneratorMatrixState::newRowKey()] = [
                'benefit_label' => mb_strtoupper((string) $benefit->description),
                'cells' => $cells,
            ];
        }

        return $rows;
    }

    /**
     * Filas de tarifa. La población queda vacía a propósito: es del cliente.
     *
     * @param  list<array{column_key: string, header_label: string, coverage_id: int|null}>  $columns
     * @return array<string, mixed>
     */
    private static function buildRateRows(Plan $plan, array $columns): array
    {
        $fees = Fee::query()
            ->where('plan_id', $plan->getKey())
            ->get();

        $byCoverage = $fees
            ->whereNotNull('coverage_id')
            ->keyBy(static fn (Fee $fee): string => $fee->age_range_id.'-'.$fee->coverage_id);

        $flat = $fees
            ->whereNull('coverage_id')
            ->keyBy(static fn (Fee $fee): int => (int) $fee->age_range_id);

        $rows = [];

        foreach (self::planAgeRanges($plan) as $ageRange) {
            $cells = [];

            foreach ($columns as $column) {
                $price = $column['coverage_id'] === null
                    ? $flat->get((int) $ageRange->getKey())?->price
                    : $byCoverage->get($ageRange->getKey().'-'.$column['coverage_id'])?->price;

                $cells[$column['column_key']] = [
                    'rate_amount' => $price === null ? null : (float) $price,
                ];
            }

            $rows[PlanGeneratorMatrixState::newRowKey()] = [
                'age_range_label' => PlanStructureSummary::ageRangeLabel($ageRange),
                'population' => null,
                'cells' => $cells,
            ];
        }

        return $rows;
    }

    private static function coveragePrice(int $coverageId): float
    {
        return (float) (Coverage::query()->whereKey($coverageId)->value('price') ?? 0);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Coverage>
     */
    private static function planCoverages(Plan $plan)
    {
        return Coverage::query()
            ->where('plan_id', $plan->getKey())
            ->orderBy('price')
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Benefit>
     */
    private static function planBenefits(Plan $plan)
    {
        return $plan->benefitPlans()->orderBy('description')->get();
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
}
