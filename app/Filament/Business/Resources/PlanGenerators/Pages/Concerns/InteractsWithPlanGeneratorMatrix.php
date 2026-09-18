<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\PlanGenerators\Pages\Concerns;

use App\Support\PlanGenerators\PlanGeneratorMatrixState;
use App\Support\PlanGenerators\PlanGeneratorQuotationState;
use Illuminate\Support\Str;

/**
 * Puente entre el editor de matrices y el guardado de una página del recurso.
 *
 * Las acciones del editor (agregar/quitar beneficios, rangos y columnas) viven
 * en `InteractsWithPlanGeneratorMatrixRows`, porque también las necesita la
 * tabla para derivar una cotización en una modal. Acá queda solo lo que es
 * propio de un formulario de página: normalizar el estado y prepararlo para
 * `PlanGeneratorPersistence`.
 */
trait InteractsWithPlanGeneratorMatrix
{
    use InteractsWithPlanGeneratorMatrixRows;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
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
