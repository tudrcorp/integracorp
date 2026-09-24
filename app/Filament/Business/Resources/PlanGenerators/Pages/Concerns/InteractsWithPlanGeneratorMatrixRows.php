<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\PlanGenerators\Pages\Concerns;

use App\Models\Benefit;
use App\Support\PlanGenerators\PlanGeneratorMatrixState;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Acciones Livewire del editor de matrices del generador de planes.
 *
 * El mismo editor (`stacked-matrices-editor`) se usa en dos contextos: el
 * formulario de una página (Crear / Editar, estado en `data`) y la modal que
 * deriva una cotización desde la tabla (estado en `mountedActions.N.data`). Por
 * eso cada método recibe la ruta de estado sobre la que debe trabajar, con
 * `data` como valor por defecto para no romper llamadas previas.
 */
trait InteractsWithPlanGeneratorMatrixRows
{
    /**
     * Rutas de estado admitidas.
     *
     * La ruta llega desde el navegador y termina en `data_set()` sobre el
     * componente Livewire: sin esta lista blanca, un cliente malicioso podría
     * escribir cualquier propiedad pública del componente.
     */
    private const MATRIX_STATE_PATHS = '/^(data|mountedActions\.\d+\.data)$/';

    public function addMatrixRow(?string $statePath = null): void
    {
        $statePath = $this->resolvePlanGeneratorMatrixStatePath($statePath);

        if ($statePath === null) {
            return;
        }

        $rows = $this->getPlanGeneratorMatrixState($statePath, 'rows');
        $columns = $this->getPlanGeneratorMatrixState($statePath, 'columns');

        $rows[PlanGeneratorMatrixState::newRowKey()] = [
            'benefit_label' => '',
            'cells' => PlanGeneratorMatrixState::emptyCellsForColumns($columns),
        ];

        $this->setPlanGeneratorMatrixState($statePath, 'rows', $rows);
    }

    /**
     * Marca o quita «Incluido» en todos los beneficios y columnas de una vez.
     * El monto de cobertura de cada celda se conserva.
     */
    public function setAllBenefitsIncluded(?string $statePath = null, mixed $included = true): void
    {
        $statePath = $this->resolvePlanGeneratorMatrixStatePath($statePath);

        if ($statePath === null) {
            return;
        }

        $markIncluded = filter_var($included, FILTER_VALIDATE_BOOLEAN);

        $this->setPlanGeneratorMatrixState(
            $statePath,
            'rows',
            PlanGeneratorMatrixState::withBenefitsIncluded(
                $this->getPlanGeneratorMatrixState($statePath, 'rows'),
                $this->getPlanGeneratorMatrixState($statePath, 'columns'),
                $markIncluded,
            ),
        );
    }

    public function createPlanGeneratorBenefit(string $rowKey, ?string $description, ?string $statePath = null): void
    {
        $statePath = $this->resolvePlanGeneratorMatrixStatePath($statePath);

        if ($statePath === null) {
            return;
        }

        $description = strtoupper(trim((string) $description));

        if ($description === '') {
            return;
        }

        $rows = $this->getPlanGeneratorMatrixState($statePath, 'rows');

        if (! array_key_exists($rowKey, $rows)) {
            return;
        }

        $alreadyUsed = collect($rows)
            ->reject(fn (mixed $row, string $key): bool => $key === $rowKey)
            ->contains(fn (mixed $row): bool => strtoupper(trim((string) ($row['benefit_label'] ?? ''))) === $description);

        if ($alreadyUsed) {
            Notification::make()
                ->title('Beneficio duplicado')
                ->body('Ese beneficio ya está asignado a otra fila del plan.')
                ->warning()
                ->send();

            return;
        }

        $benefit = Benefit::query()->firstOrCreate(
            ['description' => $description],
            [
                'code' => 'TDEC-BN-'.str_pad((string) ((Benefit::max('id') ?? 0) + 1), 4, '0', STR_PAD_LEFT),
                'status' => 'ACTIVO',
                'created_by' => Auth::user()?->name,
            ],
        );

        $rows[$rowKey]['benefit_label'] = (string) $benefit->description;

        $this->setPlanGeneratorMatrixState($statePath, 'rows', $rows);
    }

    public function removeMatrixRow(string $rowKey, ?string $statePath = null): void
    {
        $statePath = $this->resolvePlanGeneratorMatrixStatePath($statePath);

        if ($statePath === null) {
            return;
        }

        $rows = $this->getPlanGeneratorMatrixState($statePath, 'rows');

        if (! array_key_exists($rowKey, $rows)) {
            return;
        }

        unset($rows[$rowKey]);

        $this->setPlanGeneratorMatrixState($statePath, 'rows', $rows);
    }

    public function addRateRow(?string $statePath = null): void
    {
        $statePath = $this->resolvePlanGeneratorMatrixStatePath($statePath);

        if ($statePath === null) {
            return;
        }

        $rateRows = $this->getPlanGeneratorMatrixState($statePath, 'rate_rows');
        $columns = $this->getPlanGeneratorMatrixState($statePath, 'columns');

        $rateRows[PlanGeneratorMatrixState::newRowKey()] = [
            'age_range_label' => '',
            'population' => null,
            'cells' => PlanGeneratorMatrixState::emptyRateCellsForColumns($columns),
        ];

        $this->setPlanGeneratorMatrixState($statePath, 'rate_rows', $rateRows);
    }

    public function removeRateRow(string $rowKey, ?string $statePath = null): void
    {
        $statePath = $this->resolvePlanGeneratorMatrixStatePath($statePath);

        if ($statePath === null) {
            return;
        }

        $rateRows = $this->getPlanGeneratorMatrixState($statePath, 'rate_rows');

        if (! array_key_exists($rowKey, $rateRows)) {
            return;
        }

        unset($rateRows[$rowKey]);

        $this->setPlanGeneratorMatrixState($statePath, 'rate_rows', $rateRows);
    }

    /**
     * Agrega una columna del plan desde el propio editor de matrices.
     *
     * La etiqueta arranca con un texto por defecto y no vacía a propósito:
     * `normalizeColumns()` descarta las columnas sin encabezado, así que una
     * columna recién creada sin nombre desaparecería de la pantalla antes de
     * que el analista pudiera escribirle uno.
     */
    public function addMatrixColumn(?string $statePath = null): void
    {
        $statePath = $this->resolvePlanGeneratorMatrixStatePath($statePath);

        if ($statePath === null) {
            return;
        }

        $columns = $this->getPlanGeneratorMatrixState($statePath, 'columns');

        $columns[] = [
            'column_key' => (string) Str::uuid(),
            'header_label' => 'NUEVA COLUMNA',
        ];

        $this->setPlanGeneratorMatrixState($statePath, 'columns', array_values($columns));
        $this->syncMatrixCellsFromColumns($statePath);
    }

    public function removeMatrixColumn(string $columnKey, ?string $statePath = null): void
    {
        $statePath = $this->resolvePlanGeneratorMatrixStatePath($statePath);

        if ($statePath === null) {
            return;
        }

        $columns = array_values(array_filter(
            $this->getPlanGeneratorMatrixState($statePath, 'columns'),
            fn (mixed $column): bool => ! is_array($column)
                || (string) ($column['column_key'] ?? '') !== $columnKey,
        ));

        $this->setPlanGeneratorMatrixState($statePath, 'columns', $columns);
        $this->syncMatrixCellsFromColumns($statePath);
    }

    public function syncMatrixCellsFromColumns(?string $statePath = null): void
    {
        $statePath = $this->resolvePlanGeneratorMatrixStatePath($statePath);

        if ($statePath === null) {
            return;
        }

        $columns = $this->getPlanGeneratorMatrixState($statePath, 'columns');

        $this->setPlanGeneratorMatrixState($statePath, 'rows', PlanGeneratorMatrixState::ensureRowsHaveCells(
            $this->getPlanGeneratorMatrixState($statePath, 'rows'),
            $columns,
        ));

        $this->setPlanGeneratorMatrixState($statePath, 'rate_rows', PlanGeneratorMatrixState::ensureRateRowsHaveCells(
            $this->getPlanGeneratorMatrixState($statePath, 'rate_rows'),
            $columns,
        ));
    }

    protected function resolvePlanGeneratorMatrixStatePath(?string $statePath): ?string
    {
        $statePath = trim((string) ($statePath ?? 'data'));

        if ($statePath === '') {
            $statePath = 'data';
        }

        return preg_match(self::MATRIX_STATE_PATHS, $statePath) === 1
            ? $statePath
            : null;
    }

    /**
     * @return array<string|int, mixed>
     */
    protected function getPlanGeneratorMatrixState(string $statePath, string $key): array
    {
        return (array) (data_get($this, $statePath.'.'.$key) ?? []);
    }

    /**
     * @param  array<string|int, mixed>  $value
     */
    protected function setPlanGeneratorMatrixState(string $statePath, string $key, array $value): void
    {
        data_set($this, $statePath.'.'.$key, $value);
    }
}
