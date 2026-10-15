{{--
    Aviso de la vista de seleccionadas en la tabla de órdenes de servicio
    (ListOperationServiceOrders::isShowingSelectedOnly). La selección llega por `serviceOrderSelection`.
--}}
<div data-os-selection-review>
    <style>
        .os-selreview { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px; border-radius: 14px; border: 1px solid #bfdbfe; background: #eff6ff; color: #1e3a8a; }
        .os-selreview.is-all { border-color: #e5e7eb; background: #f9fafb; color: #374151; }
        .dark .os-selreview { border-color: rgb(59 130 246 / 40%); background: rgb(30 58 138 / 25%); color: rgb(191 219 254); }
        .dark .os-selreview.is-all { border-color: rgb(255 255 255 / 10%); background: rgb(255 255 255 / 4%); color: rgb(209 213 219); }
        .os-selreview-text { display: flex; align-items: flex-start; gap: 10px; font-size: 14px; line-height: 1.45; min-width: 0; }
        .os-selreview-text svg { flex: 0 0 auto; margin-top: 2px; }
        .os-selreview-actions { display: flex; flex-wrap: wrap; gap: 8px; }
    </style>

    @if ($isShowingSelectedOnly)
        <div class="os-selreview" role="status">
            <div class="os-selreview-text">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                <span>
                    <strong>Mostrando solo {{ $selectedCount === 1 ? 'la orden seleccionada' : 'las '.number_format($selectedCount, 0, ',', '.').' órdenes seleccionadas' }}</strong>
                    para que las revises. Los filtros no se aplican en esta vista. Escribe en el buscador para seguir agregando.
                </span>
            </div>
            <div class="os-selreview-actions">
                <x-filament::button color="gray" size="sm" icon="heroicon-m-list-bullet" wire:click="showAllServiceOrders" wire:loading.attr="disabled" wire:target="showAllServiceOrders">
                    Ver todas las órdenes
                </x-filament::button>
                <x-filament::button color="danger" outlined size="sm" icon="heroicon-m-x-mark" wire:click="clearServiceOrderSelection" wire:loading.attr="disabled" wire:target="clearServiceOrderSelection">
                    Quitar selección
                </x-filament::button>
            </div>
        </div>
    @elseif ($isShowingAllWhileSelecting)
        <div class="os-selreview is-all" role="status">
            <div class="os-selreview-text">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                <span>Tienes <strong>{{ $selectedCount === 1 ? '1 orden seleccionada' : number_format($selectedCount, 0, ',', '.').' órdenes seleccionadas' }}</strong>. La tabla muestra todas las órdenes.</span>
            </div>
            <div class="os-selreview-actions">
                <x-filament::button color="primary" size="sm" icon="heroicon-m-check-circle" wire:click="showSelectedServiceOrdersOnly" wire:loading.attr="disabled" wire:target="showSelectedServiceOrdersOnly">
                    Ver solo las seleccionadas
                </x-filament::button>
            </div>
        </div>
    @endif
</div>

