@php
    /** @var int $pageNumber */
    $pageNumber = (int) ($pageNumber ?? 0);
@endphp

@if ($pageNumber > 0)
    <div class="mt-2 flex flex-wrap items-center gap-2">
        <x-filament::button
            type="button"
            size="sm"
            color="gray"
            icon="heroicon-m-photo"
            wire:click="openQuotationGalleryPicker({{ $pageNumber }})"
        >
            Elegir de la galería
        </x-filament::button>
        <span class="text-[11px] text-slate-500 dark:text-slate-400">
            Reutilice imágenes cargadas previamente en otros planes.
        </span>
    </div>
@endif
