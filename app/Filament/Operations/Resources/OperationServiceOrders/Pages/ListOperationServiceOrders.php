<?php

namespace App\Filament\Operations\Resources\OperationServiceOrders\Pages;

use App\Filament\Operations\Resources\OperationServiceOrders\OperationServiceOrderResource;
use App\Services\OperationServiceOrderTableReportPdfService;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\View as ViewComponent;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\View as ViewFactory;

class ListOperationServiceOrders extends ListRecords
{
    protected static string $resource = OperationServiceOrderResource::class;

    protected static ?string $title = 'Gestión de Ordenes de Servicio';

    /**
     * «Ver todas las órdenes»: la tabla completa se mantiene hasta que el analista pida de nuevo solo
     * las seleccionadas o quite la selección, aunque vuelva a buscar y a vaciar el buscador.
     */
    public bool $showAllWhileSelecting = false;

    /**
     * Se enciende cuando el analista deja de buscar (el buscador pasa de tener texto a quedar vacío) o
     * pide «Ver solo las seleccionadas». Marcar órdenes sin haber buscado no cambia la tabla: si no,
     * al pasar de página la lista se reduciría de golpe a lo marcado.
     */
    public bool $reviewingSelection = false;

    /**
     * Copia viva de lo marcado en la tabla. Filament la mantiene al día desde el navegador
     * (`currentSelectionLivewireProperty`) y viaja con cada petición, así el servidor sabe qué
     * mostrar al vaciar el buscador. Filament solo envía `selectedTableRecords` en las acciones en lote.
     *
     * @var array<int, int|string>
     */
    public array $serviceOrderSelection = [];

    public function table(Table $table): Table
    {
        return parent::table($table)->currentSelectionLivewireProperty('serviceOrderSelection');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                ViewComponent::make('filament.operations.service-orders.selection-review')
                    ->viewData(fn (): array => [
                        'isShowingSelectedOnly' => $this->isShowingSelectedOnly(),
                        'selectedCount' => count($this->selectedServiceOrderIds()),
                        'isShowingAllWhileSelecting' => ! $this->isShowingSelectedOnly() && $this->selectedServiceOrderIds() !== [] && $this->isTableSearchEmpty(),
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
            && $this->selectedServiceOrderIds() !== [];
    }

    public function updatingTableSearch(mixed $value): void
    {
        $isClearing = trim((string) $value) === '' && ! $this->isTableSearchEmpty();

        if ($isClearing && ! $this->showAllWhileSelecting) {
            $this->reviewingSelection = true;
        }
    }

    /**
     * @return list<int>
     */
    public function selectedServiceOrderIds(): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $key): int => (int) $key, $this->serviceOrderSelection),
            static fn (int $id): bool => $id > 0,
        )));
    }

    public function updatedServiceOrderSelection(): void
    {
        if ($this->selectedServiceOrderIds() === []) {
            $this->showAllWhileSelecting = false;
            $this->reviewingSelection = false;
        }
    }

    public function showAllServiceOrders(): void
    {
        $this->showAllWhileSelecting = true;
        $this->reviewingSelection = false;
        $this->resetPage($this->getTablePaginationPageName());
    }

    public function showSelectedServiceOrdersOnly(): void
    {
        $this->showAllWhileSelecting = false;
        $this->reviewingSelection = true;
        $this->resetPage($this->getTablePaginationPageName());
    }

    public function clearServiceOrderSelection(): void
    {
        $this->serviceOrderSelection = [];
        $this->selectedTableRecords = [];
        $this->showAllWhileSelecting = false;
        $this->reviewingSelection = false;
        $this->resetPage($this->getTablePaginationPageName());
        $this->dispatch('deselectAllTableRecords');
    }

    /**
     * En la vista de seleccionadas se ignoran los filtros: lo marcado pudo elegirse con otros filtros y
     * debe verse completo. También al resolver una fila para sus acciones, que si no fallaría.
     * El alcance del usuario (proveedor) no se toca: vive en la consulta base de la tabla.
     */
    protected function applyFiltersToTableQuery(Builder $query, bool $isResolvingRecord = false): Builder
    {
        if (! $this->isShowingSelectedOnly()) {
            return parent::applyFiltersToTableQuery($query, $isResolvingRecord);
        }

        return $isResolvingRecord ? $query : $query->whereKey($this->selectedServiceOrderIds());
    }

    private function isTableSearchEmpty(): bool
    {
        return trim((string) $this->tableSearch) === '';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('report_orders_by_patient')
                ->label('Reporte por paciente')
                ->icon(Heroicon::OutlinedUserGroup)
                ->color('info')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('info'),
                ])
                ->modalHeading('Reporte detallado por paciente')
                ->modalDescription(fn (): string => $this->isShowingSelectedOnly()
                    ? 'Agrupa por paciente solo las '.count($this->selectedServiceOrderIds()).' órdenes seleccionadas.'
                    : 'Agrupa las órdenes del filtro/búsqueda actual por paciente para validar la cantidad de servicios.')
                ->modalWidth(Width::SevenExtraLarge)
                ->modalIcon(Heroicon::OutlinedUserGroup)
                ->modalIconColor('info')
                ->modalContent(fn (): View => $this->tableReportModalContent(
                    OperationServiceOrderTableReportPdfService::TYPE_BY_PATIENT,
                    'Reporte detallado por paciente',
                    'Órdenes de servicio',
                ))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->action(fn () => null),
            Action::make('report_orders_by_service')
                ->label('Reporte por servicio')
                ->icon(Heroicon::OutlinedChartBar)
                ->color('success')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('success'),
                ])
                ->modalHeading('Reporte por tipo de servicio')
                ->modalDescription(fn (): string => $this->isShowingSelectedOnly()
                    ? 'Contabiliza por tipo de servicio solo las '.count($this->selectedServiceOrderIds()).' órdenes seleccionadas.'
                    : 'Contabiliza el número de servicios/órdenes del filtro/búsqueda actual por tipo de servicio.')
                ->modalWidth(Width::SevenExtraLarge)
                ->modalIcon(Heroicon::OutlinedChartBar)
                ->modalIconColor('success')
                ->modalContent(fn (): View => $this->tableReportModalContent(
                    OperationServiceOrderTableReportPdfService::TYPE_BY_SERVICE,
                    'Reporte por tipo de servicio',
                    'Conteo de servicios realizados',
                ))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->action(fn () => null),
        ];
    }

    private function tableReportModalContent(string $type, string $title, string $subtitle): View
    {
        $ids = $this->getFilteredTableQuery()
            ->clone()
            ->reorder()
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            Notification::make()
                ->warning()
                ->title('Sin registros para el reporte')
                ->body('No hay órdenes con el filtro o búsqueda actual. Ajusta los filtros e intenta de nuevo.')
                ->send();
        }

        $token = OperationServiceOrderTableReportPdfService::storeIdsAndGetToken($ids);

        return ViewFactory::make('filament.operations.service-orders.table-report-modal', [
            'title' => $title,
            'subtitle' => $subtitle,
            'pdfPreviewUrl' => route('operations.operation-service-orders.report.preview', [
                'token' => $token,
                'type' => $type,
            ]),
            'pdfDownloadUrl' => route('operations.operation-service-orders.report.download', [
                'token' => $token,
                'type' => $type,
            ]),
        ]);
    }
}
