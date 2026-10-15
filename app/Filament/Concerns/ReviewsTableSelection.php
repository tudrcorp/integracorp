<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\View as ViewComponent;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Builder;

/**
 * Revisión de la selección en una tabla de listado (`ListRecords`).
 *
 * El analista busca, marca, vuelve a buscar y sigue marcando: la selección se conserva entre
 * búsquedas. Cuando deja de buscar (el buscador pasa de tener texto a quedar vacío), la tabla
 * muestra solo lo seleccionado, sin filtros ni pestañas, para revisarlo de un vistazo. Lo que se
 * calcule con la consulta filtrada (resúmenes, reportes, «Descargar CSV») sigue esa misma vista.
 *
 * - «Ver todas» se mantiene hasta pedir de nuevo solo lo seleccionado o quitar la selección,
 *   aunque vuelva a buscar.
 * - Marcar sin haber buscado no reduce la tabla (pasar de página no debe colapsar la lista).
 * - El alcance del usuario no se toca: vive en la consulta base del recurso, no en los filtros.
 *
 * La selección llega por `currentSelectionLivewireProperty`: Filament copia lo marcado en el
 * navegador a `$tableSelection` en cada cambio y viaja con la siguiente petición. Por sí solo,
 * Filament solo envía la selección al ejecutar una acción en lote.
 */
trait ReviewsTableSelection
{
    public bool $showAllWhileSelecting = false;

    public bool $reviewingSelection = false;

    /** @var array<int, int|string> */
    public array $tableSelection = [];

    /**
     * Cómo se nombran los registros en el aviso: «la cuota seleccionada», «las 3 cuotas seleccionadas».
     *
     * @return array{singular: string, plural: string}
     */
    abstract protected function selectionReviewNouns(): array;

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->currentSelectionLivewireProperty('tableSelection')
            ->deselectAllRecordsWhenFiltered(false);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                ViewComponent::make('filament.components.table-selection-review')
                    ->viewData(fn (): array => [
                        'isShowingSelectedOnly' => $this->isShowingSelectedOnly(),
                        'isShowingAllWhileSelecting' => ! $this->isShowingSelectedOnly()
                            && $this->selectedTableRecordIds() !== []
                            && $this->isTableSearchEmpty(),
                        'selectedCount' => count($this->selectedTableRecordIds()),
                        'nouns' => $this->selectionReviewNouns(),
                    ]),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }

    public function isShowingSelectedOnly(): bool
    {
        return $this->reviewingSelection
            && ! $this->showAllWhileSelecting
            && $this->isTableSearchEmpty()
            && $this->selectedTableRecordIds() !== [];
    }

    /**
     * @return list<int>
     */
    public function selectedTableRecordIds(): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $key): int => (int) $key, $this->tableSelection),
            static fn (int $id): bool => $id > 0,
        )));
    }

    public function updatingTableSearch(mixed $value): void
    {
        $isClearing = trim((string) $value) === '' && ! $this->isTableSearchEmpty();

        if ($isClearing && ! $this->showAllWhileSelecting) {
            $this->reviewingSelection = true;
        }
    }

    public function updatedTableSelection(): void
    {
        if ($this->selectedTableRecordIds() === []) {
            $this->showAllWhileSelecting = false;
            $this->reviewingSelection = false;
        }
    }

    public function showAllWhileSelectingRecords(): void
    {
        $this->showAllWhileSelecting = true;
        $this->reviewingSelection = false;
        $this->resetPage($this->getTablePaginationPageName());
    }

    public function showSelectedRecordsOnly(): void
    {
        $this->showAllWhileSelecting = false;
        $this->reviewingSelection = true;
        $this->resetPage($this->getTablePaginationPageName());
    }

    public function clearTableSelection(): void
    {
        $this->tableSelection = [];
        $this->selectedTableRecords = [];
        $this->showAllWhileSelecting = false;
        $this->reviewingSelection = false;
        $this->resetPage($this->getTablePaginationPageName());
        $this->dispatch('deselectAllTableRecords');
    }

    /**
     * En la vista de seleccionadas se ignoran los filtros: lo marcado pudo elegirse con otros. También al
     * resolver una fila para sus acciones; si no, una acción sobre una seleccionada fuera del filtro fallaría.
     */
    protected function applyFiltersToTableQuery(Builder $query, bool $isResolvingRecord = false): Builder
    {
        if (! $this->isShowingSelectedOnly()) {
            return parent::applyFiltersToTableQuery($query, $isResolvingRecord);
        }

        return $isResolvingRecord ? $query : $query->whereKey($this->selectedTableRecordIds());
    }

    /**
     * Las pestañas (Activas, Pendientes…) también se ignoran en la vista de seleccionadas.
     */
    protected function modifyQueryWithActiveTab(Builder $query, bool $isResolvingRecord = false): Builder
    {
        if ($this->isShowingSelectedOnly()) {
            return $query;
        }

        return parent::modifyQueryWithActiveTab($query, $isResolvingRecord);
    }

    protected function isTableSearchEmpty(): bool
    {
        return trim((string) $this->tableSearch) === '';
    }
}
