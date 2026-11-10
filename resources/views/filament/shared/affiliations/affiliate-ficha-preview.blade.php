{{--
    Vista previa de la ficha del afiliado. La llave cambia con la opción elegida:
    Livewire reemplaza el bloque, Alpine arranca de nuevo y el indicador vuelve
    a mostrarse hasta que el PDF termina de cargar en el iframe.

    La descarga es un <a> plano a propósito: los paneles son SPA y un botón de
    Filament le agregaría wire:navigate, que pinta el PDF como texto.
--}}
<div
    wire:key="affiliate-ficha-{{ $fichaScopeKey }}"
    x-data="{ loaded: false, slow: false }"
    x-init="setTimeout(() => { if (! loaded) { slow = true } }, 45000)">
    <div class="relative overflow-hidden rounded-xl border border-slate-200/80 bg-slate-100/80 dark:border-white/10 dark:bg-slate-900/40">
        <div
            x-show="! loaded"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 bg-slate-100/95 px-4 text-center dark:bg-slate-900/95"
            role="status"
            aria-live="polite">
            <svg class="h-10 w-10 shrink-0 animate-spin text-emerald-600 dark:text-emerald-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Generando la ficha · {{ $fichaScopeLabel }}</p>
            <p class="max-w-xs text-xs leading-relaxed text-slate-500 dark:text-slate-400" x-show="! slow">
                Reunimos los datos y armamos el PDF. Suele tardar unos segundos.
            </p>
            <p class="max-w-xs text-xs leading-relaxed text-slate-500 dark:text-slate-400" x-show="slow" x-cloak>
                Está tardando más de lo normal. Puede descargar el PDF con el botón de abajo, o cerrar y abrir la ficha de nuevo.
            </p>
        </div>
        <iframe
            title="Vista previa de la ficha del afiliado"
            src="{{ $fichaPreviewUrl }}"
            class="relative z-0 w-full border-0"
            style="display: block; height: min(58vh, 560px); min-height: 340px; background: #ffffff;"
            x-on:load="loaded = true"></iframe>
    </div>

    <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-xs text-slate-500 dark:text-slate-400">La vista previa y la descarga quedan registradas en la auditoría.</p>
        <a
            href="{{ $fichaDownloadUrl }}"
            target="_blank"
            rel="noopener"
            class="inline-flex min-h-[2.75rem] items-center justify-center gap-2 rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M4 12V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v5" />
            </svg>
            Descargar PDF
        </a>
    </div>
</div>
