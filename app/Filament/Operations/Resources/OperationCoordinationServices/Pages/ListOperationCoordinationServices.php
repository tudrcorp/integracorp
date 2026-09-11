<?php

namespace App\Filament\Operations\Resources\OperationCoordinationServices\Pages;

use App\Filament\Operations\Resources\OperationCoordinationServices\OperationCoordinationServiceResource;
use App\Filament\Operations\Resources\OperationCoordinationServices\Tables\OperationCoordinationServicesTable;
use App\Support\Filament\Operations\OperationsSupplierScope;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Builder;
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
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            ...parent::getPageClasses(),
            'fi-coordination-control-page',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
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
     * el de «Todas» —el caro, con ocho subconsultas— se memoiza por petición.
     *
     * @return array<string, int>
     */
    protected function tabCounts(): array
    {
        if ($this->tabCounts !== null) {
            return $this->tabCounts;
        }

        $byStatus = OperationsSupplierScope::coordinationServiceQuery()
            ->toBase()
            ->selectRaw('UPPER(TRIM(status)) AS estatus, COUNT(*) AS total')
            ->groupBy('estatus')
            ->pluck('total', 'estatus')
            ->all();

        $sum = fn (array $statuses): int => (int) array_sum(array_map(
            fn (string $status): int => (int) ($byStatus[$status] ?? 0),
            $statuses,
        ));

        return $this->tabCounts = [
            'todas' => OperationCoordinationServicesTable::applyHideFullyFinalizedScope(
                OperationsSupplierScope::coordinationServiceQuery()
            )->count(),
            'en_gestion' => $sum(['EN GESTION']),
            'pendiente' => $sum(['PENDIENTE']),
            'pendiente_resultados' => $sum(['PENDIENTE POR RESULTADOS']),
            'finalizado' => $sum(['FINALIZADO']),
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

        return [
            'todas' => Tab::make('Todas')
                ->badge($counts['todas'])
                ->badgeColor('gray')
                ->extraAttributes([
                    'class' => 'fi-supplier-status-tab-pill',
                ])
                ->modifyQueryUsing(fn (Builder $query): Builder => OperationCoordinationServicesTable::applyHideFullyFinalizedScope($query)),
            'en_gestion' => Tab::make('EN GESTION')
                ->badge($counts['en_gestion'])
                ->badgeColor(Color::hex('#ffc107'))
                ->extraAttributes([
                    'class' => 'fi-supplier-status-tab-pill',
                ])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'EN GESTION')),
            'pendiente' => Tab::make('PENDIENTE')
                ->badge($counts['pendiente'])
                ->badgeColor(Color::hex('#ffcc00'))
                ->extraAttributes([
                    'class' => 'fi-supplier-status-tab-pill',
                ])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'PENDIENTE')),
            'pendiente_resultados' => Tab::make('PENDIENTE POR RESULTADOS')
                ->badge($counts['pendiente_resultados'])
                ->badgeColor(Color::hex('#ffcc00'))
                ->extraAttributes([
                    'class' => 'fi-supplier-status-tab-pill',
                ])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'PENDIENTE POR RESULTADOS')),
            'finalizado' => Tab::make('FINALIZADO')
                ->badge($counts['finalizado'])
                ->badgeColor(Color::hex('#28cd41'))
                ->extraAttributes([
                    'class' => 'fi-supplier-status-tab-pill',
                ])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'FINALIZADO')),
            'cancelada' => Tab::make('CANCELADA')
                ->badge($counts['cancelada'])
                ->badgeColor(Color::hex('#ff3b30'))
                ->extraAttributes([
                    'class' => 'fi-supplier-status-tab-pill',
                ])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', ['CANCELADA', 'CANCELADO'])),
            'novedad_admon' => Tab::make('NOVEDAD ADMON')
                ->badge($counts['novedad_admon'])
                ->badgeColor(Color::hex('#ff3b30'))
                ->extraAttributes([
                    'class' => 'fi-supplier-status-tab-pill',
                ])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', ['NOVEDAD ADMON', 'NOVEDAD ADMON ESTUDIO'])),
        ];
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
