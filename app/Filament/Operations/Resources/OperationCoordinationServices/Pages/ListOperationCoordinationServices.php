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

    protected static ?string $title = 'Cuadro de Control de Servicios Medicos';

    public function mount(): void
    {
        parent::mount();

        $this->expandRequestedTableGroup();
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
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'todas' => Tab::make('Todas')
                ->badge(OperationCoordinationServicesTable::applyHideFullyFinalizedScope(OperationsSupplierScope::coordinationServiceQuery())->count())
                ->badgeColor('gray')
                ->extraAttributes([
                    'class' => 'fi-supplier-status-tab-pill',
                ])
                ->modifyQueryUsing(fn (Builder $query): Builder => OperationCoordinationServicesTable::applyHideFullyFinalizedScope($query)),
            'en_gestion' => Tab::make('EN GESTION')
                ->badge(OperationsSupplierScope::coordinationServiceQuery()->where('status', 'EN GESTION')->count())
                ->badgeColor(Color::hex('#ffc107'))
                ->extraAttributes([
                    'class' => 'fi-supplier-status-tab-pill',
                ])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'EN GESTION')),
            'pendiente' => Tab::make('PENDIENTE')
                ->badge(OperationsSupplierScope::coordinationServiceQuery()->where('status', 'PENDIENTE')->count())
                ->badgeColor(Color::hex('#ffcc00'))
                ->extraAttributes([
                    'class' => 'fi-supplier-status-tab-pill',
                ])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'PENDIENTE')),
            'pendiente_resultados' => Tab::make('PENDIENTE POR RESULTADOS')
                ->badge(OperationsSupplierScope::coordinationServiceQuery()->where('status', 'PENDIENTE POR RESULTADOS')->count())
                ->badgeColor(Color::hex('#ffcc00'))
                ->extraAttributes([
                    'class' => 'fi-supplier-status-tab-pill',
                ])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'PENDIENTE POR RESULTADOS')),
            'finalizado' => Tab::make('FINALIZADO')
                ->badge(OperationsSupplierScope::coordinationServiceQuery()->where('status', 'FINALIZADO')->count())
                ->badgeColor(Color::hex('#28cd41'))
                ->extraAttributes([
                    'class' => 'fi-supplier-status-tab-pill',
                ])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'FINALIZADO')),
            'cancelada' => Tab::make('CANCELADA')
                ->badge(OperationsSupplierScope::coordinationServiceQuery()->whereIn('status', ['CANCELADA', 'CANCELADO'])->count())
                ->badgeColor(Color::hex('#ff3b30'))
                ->extraAttributes([
                    'class' => 'fi-supplier-status-tab-pill',
                ])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', ['CANCELADA', 'CANCELADO'])),
            'novedad_admon' => Tab::make('NOVEDAD ADMON')
                ->badge(OperationsSupplierScope::coordinationServiceQuery()->whereIn('status', ['NOVEDAD ADMON', 'NOVEDAD ADMON ESTUDIO'])->count())
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
                'class' => 'fi-supplier-convenio-tabs-ios fi-supplier-status-tabs-ios',
            ])
            ->tabs($tabs)
            ->hidden(empty($tabs));
    }
}
