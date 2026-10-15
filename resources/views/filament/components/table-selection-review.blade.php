{{--
    Aviso de la vista de seleccionadas en una tabla de listado (App\Filament\Concerns\ReviewsTableSelection).
    $nouns = ['singular' => 'cuota', 'plural' => 'cuotas']; todos los registros de estas tablas son femeninos.
--}}
@php
    $plural = $nouns['plural'];
    $countLabel = $selectedCount === 1
        ? '1 '.$nouns['singular'].' seleccionada'
        : number_format($selectedCount, 0, ',', '.').' '.$plural.' seleccionadas';
@endphp
<div data-table-selection-review>
    <style>
        .tsr { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px; border-radius: 14px; border: 1px solid #bfdbfe; background: #eff6ff; color: #1e3a8a; }
        .tsr.is-all { border-color: #e5e7eb; background: #f9fafb; color: #374151; }
        .dark .tsr { border-color: rgb(59 130 246 / 40%); background: rgb(30 58 138 / 25%); color: rgb(191 219 254); }
        .dark .tsr.is-all { border-color: rgb(255 255 255 / 10%); background: rgb(255 255 255 / 4%); color: rgb(209 213 219); }
        .tsr-text { display: flex; align-items: flex-start; gap: 10px; font-size: 14px; line-height: 1.45; min-width: 0; }
        .tsr-text svg { flex: 0 0 auto; margin-top: 2px; }
        .tsr-actions { display: flex; flex-wrap: wrap; gap: 8px; }
    </style>

    @if ($isShowingSelectedOnly)
        <div class="tsr" role="status">
            <div class="tsr-text">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                <span>
                    <strong>Mostrando solo {{ $selectedCount === 1 ? 'la '.$nouns['singular'].' seleccionada' : 'las '.$countLabel }}</strong>
                    para que las revises. Los filtros y las pestañas no se aplican en esta vista. Escribe en el buscador para seguir agregando.
                </span>
            </div>
            <div class="tsr-actions">
                <x-filament::button color="gray" size="sm" icon="heroicon-m-list-bullet" wire:click="showAllWhileSelectingRecords" wire:loading.attr="disabled" wire:target="showAllWhileSelectingRecords">
                    Ver todas las {{ $plural }}
                </x-filament::button>
                <x-filament::button color="danger" outlined size="sm" icon="heroicon-m-x-mark" wire:click="clearTableSelection" wire:loading.attr="disabled" wire:target="clearTableSelection">
                    Quitar selección
                </x-filament::button>
            </div>
        </div>
    @elseif ($isShowingAllWhileSelecting)
        <div class="tsr is-all" role="status">
            <div class="tsr-text">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                <span>Tienes <strong>{{ $countLabel }}</strong>. La tabla muestra todas las {{ $plural }}.</span>
            </div>
            <div class="tsr-actions">
                <x-filament::button color="primary" size="sm" icon="heroicon-m-check-circle" wire:click="showSelectedRecordsOnly" wire:loading.attr="disabled" wire:target="showSelectedRecordsOnly">
                    Ver solo las seleccionadas
                </x-filament::button>
            </div>
        </div>
    @endif
</div>
