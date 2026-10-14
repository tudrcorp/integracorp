<?php

namespace App\Filament\Operations\Resources\OperationCoordinationServices\Pages;

use App\Filament\Operations\Resources\OperationCoordinationServices\OperationCoordinationServiceResource;
use App\Filament\Operations\Resources\OperationCoordinationServices\Tables\OperationCoordinationServicesTable;
use App\Support\Filament\Operations\OperationsSupplierScope;
use App\Support\Operations\CoordinationServiceItemsManager;
use App\Support\Operations\CoordinationServiceTabCounts;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Enums\PaginationMode;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Js;

class ListOperationCoordinationServices extends ListRecords
{
    protected static string $resource = OperationCoordinationServiceResource::class;

    protected static ?string $title = 'Cuadro de Control de Servicios Médicos';

    /**
     * @var array<string, int>|null
     */
    protected ?array $tabCounts = null;

    public function mount(): void
    {
        parent::mount();

        $this->expandRequestedTableGroup();
    }

    /**
     * Vacía la memoria de ítems clínicos sólo cuando Filament vuelve a leer los
     * registros (primera carga o después de una acción que escribió). Hacerlo en
     * `modifyQueryUsing` la borraba a mitad del render, porque Filament reusa esa
     * consulta para la casilla de cada grupo y para los conteos.
     */
    public function getTableRecords(): Collection|Paginator|CursorPaginator
    {
        $isFreshRead = $this->cachedTableRecords === null;

        if ($isFreshRead) {
            CoordinationServiceItemsManager::flushClinicalItemsCache();
        }

        $records = parent::getTableRecords();

        if ($isFreshRead) {
            OperationCoordinationServicesTable::rememberCaseRegistrationRanges(
                OperationCoordinationServicesTable::caseRegistrationRanges($this->getFilteredTableQuery(), $records),
            );
        }

        return $records;
    }

    /**
     * Sin filtros ni búsqueda (el analista vació el estatus), el total del
     * paginador es exactamente el conteo de servicios por gestionar: se reutiliza
     * en vez de repetir la consulta de ocho subconsultas. Con cualquier filtro,
     * búsqueda (incluido «Ver solo casos eliminados») o «ver todo», Filament
     * cuenta como siempre.
     */
    protected function paginateTableQuery(Builder $query): Paginator|CursorPaginator
    {
        $perPage = $this->getTableRecordsPerPage();

        if (! $this->canReuseTabCountForPagination($perPage)) {
            return parent::paginateTableQuery($query);
        }

        return $query
            ->paginate(
                perPage: (int) $perPage,
                pageName: $this->getTablePaginationPageName(),
                total: $this->tabCounts()['todas'],
            )
            ->onEachSide(0);
    }

    private function canReuseTabCountForPagination(int|string|null $perPage): bool
    {
        if (! is_numeric($perPage) || (int) $perPage < 1) {
            return false;
        }

        if ($this->getTable()->getPaginationMode() !== PaginationMode::Default) {
            return false;
        }

        if ($this->hasActiveTableFilters()) {
            return false;
        }

        if (filled($this->getTableSearch())) {
            return false;
        }

        return array_filter($this->getTableColumnSearches(), fn (mixed $search): bool => filled($search)) === [];
    }

    /**
     * Algún filtro aplicado con valor. Recorre el estado aplicado (no el del
     * formulario diferido): es el que decide qué filas trae la consulta.
     */
    private function hasActiveTableFilters(): bool
    {
        $hasValue = function (mixed $value) use (&$hasValue): bool {
            if (is_array($value)) {
                foreach ($value as $item) {
                    if ($hasValue($item)) {
                        return true;
                    }
                }

                return false;
            }

            return filled($value) && $value !== false;
        };

        return $hasValue($this->tableFilters ?? []);
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            ...parent::getPageClasses(),
            'fi-coordination-control-page',
        ];
    }

    /**
     * Mismo encabezado que los demás listados de Operaciones. Los números salen
     * de los conteos de las pestañas, que ya están en caché: no suma consultas.
     */
    public function getHeading(): string|Htmlable
    {
        $counts = $this->tabCounts();

        return new HtmlString(view('filament.operations.partials.list-header', [
            'icon' => 'heroicon-o-clipboard-document-list',
            'eyebrow' => 'Operaciones · Coordinación de servicios',
            'title' => 'Cuadro de control de servicios médicos',
            'total' => $counts['todas'],
            'totalHint' => 'Servicios con algo por gestionar',
            'description' => 'Laboratorios, estudios, especialistas, medicamentos y servicios agrupados por caso. Se muestran los servicios EN GESTION y PENDIENTE: use «Filtros» para ver otros estatus o acotar por fecha, servicio, proveedor o tipo de afiliado.',
            'stats' => [
                ['label' => 'Pendientes', 'value' => $counts['pendiente'], 'icon' => 'heroicon-m-clock', 'tone' => 'warning', 'hint' => 'Servicios que nadie ha empezado a gestionar'],
                ['label' => 'En gestión', 'value' => $counts['en_gestion'], 'icon' => 'heroicon-m-arrow-path', 'tone' => 'info', 'hint' => 'Servicios con orden o cotización en curso'],
                ['label' => 'Por resultados', 'value' => $counts['pendiente_resultados'], 'icon' => 'heroicon-m-document-magnifying-glass', 'tone' => 'primary', 'hint' => 'Servicios a la espera de resultados'],
                ['label' => 'Novedad admon', 'value' => $counts['novedad_admon'], 'icon' => 'heroicon-m-exclamation-triangle', 'tone' => 'danger', 'hint' => 'Servicios con novedad administrativa por resolver'],
            ],
        ])->render());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('registerDirectService')
                ->label('Registrar servicio directo')
                ->icon(Heroicon::OutlinedPlusCircle)
                ->color('success')
                ->url(fn (): string => RegisterDirectService::getUrl())
                ->visible(fn (): bool => RegisterDirectService::canAccess()),
        ];
    }

    private function expandRequestedTableGroup(): void
    {
        $groupTitle = trim((string) request()->query('expand_group', ''));

        if ($groupTitle === '') {
            return;
        }

        $groupLiteral = Js::from($groupTitle);

        $this->js(<<<JS
            (() => {
                const group = {$groupLiteral};
                const tryExpand = () => {
                    const roots = document.querySelectorAll('.fi-ta');

                    for (const root of roots) {
                        const data = window.Alpine?.\$data(root);

                        if (! data || typeof data.toggleCollapseGroup !== 'function') {
                            continue;
                        }

                        if (typeof data.isGroupCollapsed === 'function' && data.isGroupCollapsed(group)) {
                            data.toggleCollapseGroup(group);
                        }

                        const headers = root.querySelectorAll('.fi-ta-group-header');

                        for (const header of headers) {
                            const text = (header.textContent || '').replace(/\\s+/g, ' ').trim();

                            if (text.includes(group) || text.includes(group.split(' · ')[0] || '')) {
                                header.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                break;
                            }
                        }

                        return true;
                    }

                    return false;
                };

                requestAnimationFrame(() => {
                    if (! tryExpand()) {
                        setTimeout(tryExpand, 250);
                        setTimeout(tryExpand, 750);
                    }
                });
            })();
        JS);
    }

    /**
     * Conteos del encabezado por estatus, en un único GROUP BY. El total de
     * servicios por gestionar —el caro, con ocho subconsultas— se memoiza por
     * petición y se guarda en caché entre peticiones
     * ({@see CoordinationServiceTabCounts}); también lo reutiliza el paginador.
     *
     * @return array<string, int>
     */
    protected function tabCounts(): array
    {
        if ($this->tabCounts !== null) {
            return $this->tabCounts;
        }

        return $this->tabCounts = CoordinationServiceTabCounts::remember(fn (): array => $this->computeTabCounts());
    }

    /**
     * @return array<string, int>
     */
    private function computeTabCounts(): array
    {
        /*
         * Solo cuentan servicios con algo por gestionar: los finalizados (todos
         * sus ítems cerrados) quedan fuera del encabezado.
         */
        $byStatus = OperationCoordinationServicesTable::applyHideFullyFinalizedScope(OperationsSupplierScope::coordinationServiceQuery())
            ->toBase()
            ->selectRaw('UPPER(TRIM(status)) AS estatus, COUNT(*) AS total')
            ->groupBy('estatus')
            ->pluck('total', 'estatus')
            ->all();

        $sum = fn (array $statuses): int => (int) array_sum(array_map(
            fn (string $status): int => (int) ($byStatus[$status] ?? 0),
            $statuses,
        ));

        return [
            'todas' => (int) array_sum(array_map('intval', $byStatus)),
            'en_gestion' => $sum(['EN GESTION']),
            'pendiente' => $sum(['PENDIENTE']),
            'pendiente_resultados' => $sum(['PENDIENTE POR RESULTADOS']),
            'cancelada' => $sum(['CANCELADA', 'CANCELADO']),
            'novedad_admon' => $sum(['NOVEDAD ADMON', 'NOVEDAD ADMON ESTUDIO']),
        ];
    }
}
