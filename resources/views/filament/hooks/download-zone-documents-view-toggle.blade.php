@php
    $page = \Livewire\Livewire::current();
    $isGrid = is_object($page) && (($page->documentsView ?? 'list') === 'grid');
    $nextView = $isGrid ? 'list' : 'grid';
    $label = $isGrid ? 'Lista' : 'Vista previa';
    $icon = $isGrid ? 'heroicon-m-bars-3' : 'heroicon-m-squares-2x2';
    $title = $isGrid ? 'Cambiar a lista' : 'Cambiar a vista previa con imagen';
@endphp

<div class="fi-download-zone-view-toggle shrink-0">
    <x-filament::button
        color="gray"
        size="sm"
        :icon="$icon"
        :title="$title"
        wire:click="setDocumentsView('{{ $nextView }}')"
    >
        {{ $label }}
    </x-filament::button>
</div>
