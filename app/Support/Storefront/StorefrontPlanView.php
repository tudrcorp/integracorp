<?php

declare(strict_types=1);

namespace App\Support\Storefront;

use App\Models\AgeRange;
use App\Models\Fee;
use App\Models\Plan;
use App\Support\PlanGenerators\PlanGeneratorStructureImporter;
use App\Support\Plans\PlanStructureSummary;
use Illuminate\Support\Collection;

/**
 * Ficha de producto de un plan básico: promesa, beneficios, rangos y tarifas.
 *
 * @phpstan-type PlanProduct array{
 *     plan: Plan,
 *     narrative: array<string, mixed>,
 *     is_package: bool,
 *     desde: float|null,
 *     benefits: array{columns: list<array{column_key: string, header_label: string}>, rows: list<array<string, mixed>>},
 *     rates: list<array<string, mixed>>,
 *     age_ranges: list<array{id: int, label: string, age_init: int|null, age_end: int|null}>
 * }
 */
final class StorefrontPlanView
{
    /**
     * Primera pintura de la ficha: narrativa y precio, sin matriz de beneficios.
     *
     * @return PlanProduct
     */
    public static function shell(Plan $plan): array
    {
        return [
            'plan' => $plan,
            'narrative' => StorefrontPlanNarrative::for($plan),
            'is_package' => $plan->isBenefitPackage(),
            'desde' => self::startingPrice($plan),
            'benefits' => [
                'columns' => [],
                'rows' => [],
            ],
            'rates' => [],
            'age_ranges' => [],
        ];
    }

    /**
     * @return PlanProduct
     */
    public static function make(Plan $plan): array
    {
        $matrix = PlanGeneratorStructureImporter::build($plan);
        $desde = self::startingPrice($plan);

        return [
            'plan' => $plan,
            'narrative' => StorefrontPlanNarrative::for($plan),
            'is_package' => $plan->isBenefitPackage(),
            'desde' => $desde,
            'benefits' => [
                'columns' => $matrix['columns'],
                'rows' => self::orderedBenefitRows($plan, $matrix['rows']),
            ],
            'rates' => self::rates($plan),
            'age_ranges' => self::ageRanges($plan),
        ];
    }

    public static function startingPrice(Plan $plan): ?float
    {
        $min = Fee::query()
            ->where('plan_id', $plan->getKey())
            ->whereNotNull('price')
            ->min('price');

        return is_numeric($min) ? (float) $min : null;
    }

    /**
     * Mismos beneficios, mismo orden: el del catálogo (id), que es el del Plan
     * Inicial. Ideal y Especial añaden los suyos al final, sin reordenar.
     *
     * @param  array<string, mixed>  $rows
     * @return list<array<string, mixed>>
     */
    public static function orderedBenefitRows(Plan $plan, array $rows): array
    {
        $order = $plan->benefitPlans()
            ->orderBy('benefits.id')
            ->pluck('benefits.description')
            ->map(static fn (mixed $description): string => self::benefitSortKey((string) $description))
            ->values()
            ->all();

        return self::sortBenefitRows($rows, $order);
    }

    /**
     * @param  array<string, mixed>  $rows
     * @param  list<string>  $orderedLabels
     * @return list<array<string, mixed>>
     */
    public static function sortBenefitRows(array $rows, array $orderedLabels): array
    {
        $ranked = [];

        foreach (array_values($rows) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = self::benefitSortKey((string) ($row['benefit_label'] ?? ''));
            $position = array_search($label, $orderedLabels, true);

            $ranked[] = [
                'position' => $position === false ? count($orderedLabels) + $index : $position,
                'row' => $row,
            ];
        }

        usort($ranked, static fn (array $left, array $right): int => $left['position'] <=> $right['position']);

        return array_column($ranked, 'row');
    }

    private static function benefitSortKey(string $label): string
    {
        return mb_strtoupper(trim($label));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rates(Plan $plan): array
    {
        if ($plan->isBenefitPackage()) {
            return array_map(
                static function (array $row): array {
                    return [
                        'label' => (string) ($row['range'] ?? 'Rango'),
                        'cells' => [
                            [
                                'label' => 'Tarifa anual',
                                'value' => $row['rate'] ?? null,
                            ],
                        ],
                    ];
                },
                PlanStructureSummary::flatRates($plan),
            );
        }

        $columns = PlanStructureSummary::coverageColumns($plan);
        $matrix = PlanStructureSummary::ratesMatrix($plan);
        $rows = [];

        foreach ($matrix['rows'] as $row) {
            $cells = [];

            foreach ($columns as $column) {
                $cells[] = [
                    'label' => $column['label'],
                    'value' => $row['cells'][$column['key']] ?? null,
                ];
            }

            $rows[] = [
                'label' => (string) ($row['label'] ?? 'Rango'),
                'cells' => $cells,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{id: int, label: string, age_init: int|null, age_end: int|null}>
     */
    private static function ageRanges(Plan $plan): array
    {
        return AgeRange::query()
            ->where('plan_id', $plan->getKey())
            ->where(static fn ($query) => $query->where('status', 'ACTIVO')->orWhereNull('status'))
            ->orderBy('age_init')
            ->orderBy('id')
            ->get()
            ->unique(static fn (AgeRange $range): string => sprintf(
                '%d-%d',
                (int) $range->age_init,
                (int) $range->age_end,
            ))
            ->map(static fn (AgeRange $range): array => [
                'id' => (int) $range->getKey(),
                'label' => PlanStructureSummary::ageRangeLabel($range),
                'age_init' => $range->age_init !== null ? (int) $range->age_init : null,
                'age_end' => $range->age_end !== null ? (int) $range->age_end : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, AgeRange>
     */
    public static function ageRangeModels(Plan $plan): Collection
    {
        return AgeRange::query()
            ->where('plan_id', $plan->getKey())
            ->where(static fn ($query) => $query->where('status', 'ACTIVO')->orWhereNull('status'))
            ->orderBy('age_init')
            ->orderBy('id')
            ->get();
    }
}
