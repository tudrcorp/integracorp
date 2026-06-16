<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\PlanGenerators\Pages\Concerns;

use App\Support\PlanGenerators\PlanGeneratorMatrixState;
use App\Support\PlanGenerators\PlanGeneratorQuotationState;
use Illuminate\Support\Str;

trait InteractsWithPlanGeneratorMatrix
{
    public function addMatrixRow(): void
    {
        $rowKey = PlanGeneratorMatrixState::newRowKey();
        $columns = (array) ($this->data['columns'] ?? []);

        $this->data['rows'][$rowKey] = [
            'benefit_label' => '',
            'cells' => PlanGeneratorMatrixState::emptyCellsForColumns($columns),
        ];
    }

    public function removeMatrixRow(string $rowKey): void
    {
        $rows = (array) ($this->data['rows'] ?? []);

        if (! array_key_exists($rowKey, $rows)) {
            return;
        }

        unset($rows[$rowKey]);
        $this->data['rows'] = $rows;
    }

    public function addRateRow(): void
    {
        $rowKey = PlanGeneratorMatrixState::newRowKey();
        $columns = (array) ($this->data['columns'] ?? []);

        $this->data['rate_rows'][$rowKey] = [
            'age_range_label' => '',
            'population' => null,
            'cells' => PlanGeneratorMatrixState::emptyRateCellsForColumns($columns),
        ];
    }

    public function removeRateRow(string $rowKey): void
    {
        $rateRows = (array) ($this->data['rate_rows'] ?? []);

        if (! array_key_exists($rowKey, $rateRows)) {
            return;
        }

        unset($rateRows[$rowKey]);
        $this->data['rate_rows'] = $rateRows;
    }

    public function syncMatrixCellsFromColumns(): void
    {
        $columns = (array) ($this->data['columns'] ?? []);

        $this->data['rows'] = PlanGeneratorMatrixState::ensureRowsHaveCells(
            (array) ($this->data['rows'] ?? []),
            $columns,
        );

        $this->data['rate_rows'] = PlanGeneratorMatrixState::ensureRateRowsHaveCells(
            (array) ($this->data['rate_rows'] ?? []),
            $columns,
        );
    }

    protected function normalizeMatrixFormState(array $data): array
    {
        $columns = (array) ($data['columns'] ?? []);

        foreach ($columns as $index => $column) {
            if (! is_array($column)) {
                continue;
            }

            if (! filled($column['column_key'] ?? null)) {
                $columns[$index]['column_key'] = (string) Str::uuid();
            }
        }

        $data['columns'] = PlanGeneratorMatrixState::normalizeColumns($columns);
        $data['rows'] = PlanGeneratorMatrixState::ensureRowsHaveCells(
            (array) ($data['rows'] ?? []),
            $columns,
        );
        $data['rate_rows'] = PlanGeneratorMatrixState::ensureRateRowsHaveCells(
            (array) ($data['rate_rows'] ?? []),
            $columns,
        );

        $pageCount = filled($data['quotation_page_count'] ?? null)
            ? (int) $data['quotation_page_count']
            : 0;

        $planPageNumber = filled($data['plan_page_number'] ?? null)
            ? (int) $data['plan_page_number']
            : null;

        $data['quotation_pages'] = PlanGeneratorQuotationState::syncImagePagesForQuotation(
            (array) ($data['quotation_pages'] ?? []),
            $pageCount,
            $planPageNumber,
        );

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function matrixFormStateForPersistence(): array
    {
        $state = $this->form->getState();

        $state['columns'] = (array) ($this->data['columns'] ?? $state['columns'] ?? []);
        $state['rows'] = (array) ($this->data['rows'] ?? $state['rows'] ?? []);
        $state['rate_rows'] = (array) ($this->data['rate_rows'] ?? $state['rate_rows'] ?? []);
        $state['quotation_pages'] = (array) ($this->data['quotation_pages'] ?? $state['quotation_pages'] ?? []);

        return $this->normalizeMatrixFormState($state);
    }
}
