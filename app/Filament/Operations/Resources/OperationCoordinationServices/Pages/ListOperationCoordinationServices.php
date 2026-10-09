<?php

namespace App\Filament\Operations\Resources\OperationCoordinationServices\Pages;

use App\Filament\Operations\Resources\OperationCoordinationServices\OperationCoordinationServiceResource;
use App\Filament\Operations\Resources\OperationCoordinationServices\Tables\OperationCoordinationServicesTable;
use App\Support\Filament\Operations\OperationsSupplierScope;
use App\Support\Operations\CoordinationServiceCaseDeletion;
use App\Support\Operations\CoordinationServiceItemsManager;
use App\Support\Operations\CoordinationServiceTabCounts;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Colors\Color;
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
     * En «Todas» sin búsqueda, el total del paginador es exactamente el conteo de
     * la pestaña: se reutiliza en vez de repetir la consulta de ocho subconsultas.
     * Con búsqueda, otra pestaña o «ver todo», Filament cuenta como siempre.
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

        if (($this->activeTab ?? $this->getDefaultActiveTab()) !== 'todas') {
            return false;
        }

        if (filled($this->getTableSearch())) {
            return false;
        }

        return array_filter($this->getTableColumnSearches(), fn (mixed $search): bool => filled($search)) === [];
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
            'description' => 'Laboratorios, estudios, especialistas, medicamentos y servicios agrupados por caso. Filtre por estado con las pestañas y despliegue un caso para gestionarlo.',
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
     * Conteos de las pestañas resueltos en dos consultas en lugar de siete.
     *
     * Cada `Tab::badge()` recibe el resultado ya calculado, así que las siete
     * cuentas se ejecutaban en cada render aunque sólo una pestaña estuviese
     * activa. Los seis conteos por estatus salen ahora de un único GROUP BY, y
     * el de «Todas» —el caro, con ocho subconsultas— se memoiza por petición y se
     * guarda en caché entre peticiones ({@see CoordinationServiceTabCounts}).
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
         * Las pestañas de trabajo solo cuentan servicios con algo por gestionar;
         * los finalizados (todos sus ítems cerrados) solo cuentan en FINALIZADO.
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
            CoordinationServiceCaseDeletion::DELETED_TAB => CoordinationServiceCaseDeletion::userCanDeleteCases()
                ? CoordinationServiceCaseDeletion::applyDeletedCasesScope(
                    OperationsSupplierScope::coordinationServiceQuery()
                )->count()
                : 0,
            'en_gestion' => $sum(['EN GESTION']),
            'pendiente' => $sum(['PENDIENTE']),
            'pendiente_resultados' => $sum(['PENDIENTE POR RESULTADOS']),
            'finalizado' => OperationCoordinationServicesTable::applyFullyFinalizedScope(
                OperationsSupplierScope::coordinationServiceQuery()
            )->count(),
            'cancelada' => $sum(['CANCELADA', 'CANCELADO']),
            'novedad_admon' => $sum(['NOVEDAD ADMON', 'NOVEDAD ADMON ESTUDIO']),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = $this->tabCounts();

        $tabs = [
            'todas' => Tab::make('Todas')
                ->badge($counts['todas'])
                ->badgeColor('gray')
                ->extraAttributes([
                    'class' => 'fi-supplier-status-tab-pill',
                ])
                ->modifyQueryUsing(fn (Builder $query): Builder => self::openServices($query)),
            'en_gestion' => Tab::make('EN GESTION')
                ->badge($counts['en_gestion'])
                ->badgeColor(Color::hex('#ffc107'))
                ->extraAttributes([
                    'class' => 'fi-supplier-status-tab-pill',
                ])
                ->modifyQueryUsing(fn (Builder $query): Builder => self::openServices($query)->where('status', 'EN GESTION')),
            'pendiente' => Tab::make('PENDIENTE')
                ->badge($counts['pendiente'])
                ->badgeColor(Color::hex('#ffcc00'))
                ->extraAttributes([
                    'class' => 'fi-supplier-status-tab-pill',
                ])
                ->modifyQueryUsing(fn (Builder $query): Builder => self::openServices($query)->where('status', 'PENDIENTE')),
            'pendiente_resultados' => Tab::make('PENDIENTE POR RESULTADOS')
                ->badge($counts['pendiente_resultados'])
                ->badgeColor(Color::hex('#ffcc00'))
                ->extraAttributes([
                    'class' => 'fi-supplier-status-tab-pill',
                ])
                ->modifyQueryUsing(fn (Builder $query): Builder => self::openServices($query)->where('status', 'PENDIENTE POR RESULTADOS')),
            'finalizado' => Tab::make('FINALIZADO')
                ->badge($counts['finalizado'])
                ->badgeColor(Color::hex('#28cd41'))
                ->extraAttributes([
                    'class' => 'fi-supplier-status-tab-pill',
                ])
                ->modifyQueryUsing(fn (Builder $query): Builder => OperationCoordinationServicesTable::applyFullyFinalizedScope($query)),
            'cancelada' => Tab::make('CANCELADA')
                ->badge($counts['cancelada'])
                ->badgeColor(Color::hex('#ff3b30'))
                ->extraAttributes([
                    'class' => 'fi-supplier-status-tab-pill',
                ])
                ->modifyQueryUsing(fn (Builder $query): Builder => self::openServices($query)->whereIn('status', ['CANCELADA', 'CANCELADO'])),
            'novedad_admon' => Tab::make('NOVEDAD ADMON')
                ->badge($counts['novedad_admon'])
                ->badgeColor(Color::hex('#ff3b30'))
                ->extraAttributes([
                    'class' => 'fi-supplier-status-tab-pill',
                ])
                ->modifyQueryUsing(fn (Builder $query): Builder => self::openServices($query)->whereIn('status', ['NOVEDAD ADMON', 'NOVEDAD ADMON ESTUDIO'])),
        ];

        /**
         * Los casos eliminados solo existen para quien puede eliminarlos: el
         * resto del panel no debe siquiera saber que la pestaña está ahí.
         */
        if (CoordinationServiceCaseDeletion::userCanDeleteCases()) {
            $tabs[CoordinationServiceCaseDeletion::DELETED_TAB] = Tab::make('ELIMINADOS')
                ->badge($counts[CoordinationServiceCaseDeletion::DELETED_TAB])
                ->badgeColor('gray')
                ->extraAttributes([
                    'class' => 'fi-supplier-status-tab-pill',
                ])
                ->modifyQueryUsing(fn (Builder $query): Builder => CoordinationServiceCaseDeletion::applyDeletedCasesScope($query));
        }

        return $tabs;
    }

    /**
     * Pestañas de trabajo: solo servicios con algo por gestionar. Los finalizados
     * (todos sus ítems cerrados) se consultan únicamente en la pestaña FINALIZADO.
     */
    private static function openServices(Builder $query): Builder
    {
        return OperationCoordinationServicesTable::applyHideFullyFinalizedScope($query);
    }

    public function getTabsContentComponent(): Component
    {
        $tabs = $this->getCachedTabs();

        return Tabs::make('Filtrar por estado')
            ->livewireProperty('activeTab')
            ->contained(false)
            ->extraAttributes([
                'class' => 'fi-status-filter-tabs-ios fi-supplier-status-tabs-ios',
            ])
            ->tabs($tabs)
            ->hidden(empty($tabs));
    }
}
