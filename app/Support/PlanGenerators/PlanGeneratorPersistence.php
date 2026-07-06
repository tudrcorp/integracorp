<?php

declare(strict_types=1);

namespace App\Support\PlanGenerators;

use App\Models\PlanGenerator;
use App\Models\PlanGeneratorColumn;
use App\Models\PlanGeneratorRateRow;
use App\Models\PlanGeneratorRow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PlanGeneratorPersistence
{
    /**
     * @param  array<string, mixed>  $formState
     */
    public static function syncFromFormState(PlanGenerator $planGenerator, array $formState): void
    {
        DB::transaction(function () use ($planGenerator, $formState): void {
            $planGenerator->rows()->each(function (PlanGeneratorRow $row): void {
                $row->cells()->delete();
            });
            $planGenerator->rows()->delete();

            $planGenerator->rateRows()->each(function (PlanGeneratorRateRow $rateRow): void {
                $rateRow->cells()->delete();
            });
            $planGenerator->rateRows()->delete();

            $planGenerator->columns()->delete();

            $columnIdByKey = [];
            $columnSortOrder = 0;

            foreach ((array) ($formState['columns'] ?? []) as $columnRow) {
                if (! is_array($columnRow) || ! filled($columnRow['header_label'] ?? null)) {
                    continue;
                }

                $columnKey = filled($columnRow['column_key'] ?? null)
                    ? (string) $columnRow['column_key']
                    : (string) Str::uuid();

                $column = $planGenerator->columns()->create([
                    'column_key' => $columnKey,
                    'header_label' => (string) $columnRow['header_label'],
                    'sort_order' => $columnSortOrder++,
                ]);

                $columnIdByKey[$columnKey] = (int) $column->getKey();
            }

            $rowSortOrder = 0;

            foreach ((array) ($formState['rows'] ?? []) as $benefitRow) {
                if (! is_array($benefitRow) || ! filled($benefitRow['benefit_label'] ?? null)) {
                    continue;
                }

                $row = $planGenerator->rows()->create([
                    'benefit_label' => (string) $benefitRow['benefit_label'],
                    'sort_order' => $rowSortOrder++,
                ]);

                self::syncBenefitCells($row, (array) ($benefitRow['cells'] ?? []), $columnIdByKey);
            }

            $rateRowSortOrder = 0;

            foreach ((array) ($formState['rate_rows'] ?? []) as $rateRow) {
                if (! is_array($rateRow) || ! filled($rateRow['age_range_label'] ?? null)) {
                    continue;
                }

                $rateRowModel = $planGenerator->rateRows()->create([
                    'age_range_label' => (string) $rateRow['age_range_label'],
                    'population' => filled($rateRow['population'] ?? null)
                        ? (int) $rateRow['population']
                        : null,
                    'sort_order' => $rateRowSortOrder++,
                ]);

                self::syncRateCells($rateRowModel, (array) ($rateRow['cells'] ?? []), $columnIdByKey);
            }

            self::syncQuotationPages($planGenerator, (array) ($formState['quotation_pages'] ?? []));
        });
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $quotationPages
     */
    private static function syncQuotationPages(PlanGenerator $planGenerator, array $quotationPages): void
    {
        $planGenerator->quotationPages()->delete();

        $sortOrder = 0;

        foreach (PlanGeneratorQuotationState::normalizePages($quotationPages) as $page) {
            $imagePath = PlanGeneratorQuotationState::extractImagePath($page['image'] ?? null);

            if ($imagePath === null) {
                continue;
            }

            $planGenerator->quotationPages()->create([
                'page_number' => (int) $page['page_number'],
                'image_path' => $imagePath,
                'sort_order' => $sortOrder++,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function formStateFromModel(PlanGenerator $planGenerator): array
    {
        $planGenerator->loadMissing(['columns', 'rows.cells', 'rateRows.cells', 'quotationPages']);

        $columnsById = $planGenerator->columns->keyBy('id');

        $columns = PlanGeneratorMatrixState::normalizeColumns(
            $planGenerator->columns->map(fn (PlanGeneratorColumn $column): array => [
                'column_key' => $column->column_key,
                'header_label' => $column->header_label,
            ])->all(),
        );

        $columnKeys = PlanGeneratorMatrixState::extractColumnKeys($columns);

        $rows = [];

        foreach ($planGenerator->rows as $row) {
            $cells = [];

            foreach ($columnKeys as $columnKey) {
                $cells[$columnKey] = PlanGeneratorMatrixState::emptyCell();
            }

            foreach ($row->cells as $cell) {
                $columnKey = $columnsById->get($cell->plan_generator_column_id)?->column_key;

                if ($columnKey === null) {
                    continue;
                }

                $cells[$columnKey] = [
                    'is_selected' => (bool) $cell->is_selected,
                    'coverage_amount' => $cell->coverage_amount !== null
                        ? (float) $cell->coverage_amount
                        : null,
                ];
            }

            $rows[PlanGeneratorMatrixState::newRowKey()] = [
                'benefit_label' => $row->benefit_label,
                'cells' => $cells,
            ];
        }

        $rateRows = [];

        foreach ($planGenerator->rateRows as $rateRow) {
            $cells = [];

            foreach ($columnKeys as $columnKey) {
                $cells[$columnKey] = PlanGeneratorMatrixState::emptyRateCell();
            }

            foreach ($rateRow->cells as $cell) {
                $columnKey = $columnsById->get($cell->plan_generator_column_id)?->column_key;

                if ($columnKey === null) {
                    continue;
                }

                $cells[$columnKey] = [
                    'rate_amount' => $cell->rate_amount !== null
                        ? (float) $cell->rate_amount
                        : null,
                ];
            }

            $rateRows[PlanGeneratorMatrixState::newRowKey()] = [
                'age_range_label' => $rateRow->age_range_label,
                'population' => $rateRow->population,
                'cells' => $cells,
            ];
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
            'rate_rows' => $rateRows,
            'quotation_page_count' => $planGenerator->quotation_page_count,
            'plan_page_number' => $planGenerator->plan_page_number,
            'quotation_pages' => PlanGeneratorQuotationState::syncImagePagesForQuotation(
                PlanGeneratorQuotationState::formPagesFromModels($planGenerator->quotationPages),
                (int) ($planGenerator->quotation_page_count ?? 0),
                filled($planGenerator->plan_page_number) ? (int) $planGenerator->plan_page_number : null,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $cells
     * @param  array<string, int>  $columnIdByKey
     */
    private static function syncBenefitCells(PlanGeneratorRow $row, array $cells, array $columnIdByKey): void
    {
        foreach ($cells as $columnKey => $cellData) {
            if (! is_array($cellData) || ! isset($columnIdByKey[$columnKey])) {
                continue;
            }

            $coverage = filled($cellData['coverage_amount'] ?? null)
                ? (float) $cellData['coverage_amount']
                : null;

            $row->cells()->create([
                'plan_generator_column_id' => $columnIdByKey[$columnKey],
                'is_selected' => (bool) ($cellData['is_selected'] ?? false),
                'coverage_amount' => $coverage,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $cells
     * @param  array<string, int>  $columnIdByKey
     */
    private static function syncRateCells(PlanGeneratorRateRow $rateRow, array $cells, array $columnIdByKey): void
    {
        foreach ($cells as $columnKey => $cellData) {
            if (! is_array($cellData) || ! isset($columnIdByKey[$columnKey])) {
                continue;
            }

            $rateAmount = filled($cellData['rate_amount'] ?? null)
                ? (float) $cellData['rate_amount']
                : null;

            $rateRow->cells()->create([
                'plan_generator_column_id' => $columnIdByKey[$columnKey],
                'rate_amount' => $rateAmount,
            ]);
        }
    }
}
