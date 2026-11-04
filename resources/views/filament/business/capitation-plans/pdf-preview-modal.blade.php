@props([
    'pdfPreviewUrl' => null,
    'pdfDownloadUrl' => null,
    'documentLabel' => null,
    'blockedMessage' => null,
])

<div class="fi-scoped space-y-5" wire:ignore.self>
    @if (filled($blockedMessage))
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
            {{ $blockedMessage }}
        </div>
    @else
        <div class="overflow-hidden rounded-3xl border border-gray-200/80 bg-white/80 shadow-sm backdrop-blur-md dark:border-white/10 dark:bg-gray-900/70">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200/80 px-4 py-3 dark:border-white/10">
                <div class="min-w-0">
                    <p class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">Planes de capitado</p>
                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Vista previa del PDF</p>
                    @if (filled($documentLabel))
                        <p class="mt-0.5 max-w-md truncate text-xs text-gray-500 dark:text-gray-400" title="{{ $documentLabel }}">{{ $documentLabel }}</p>
                    @endif
                </div>
                <span class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-medium text-sky-800 dark:bg-sky-500/20 dark:text-sky-300">PDF</span>
            </div>

            <div class="bg-gray-50/80 p-3 dark:bg-gray-950/60" wire:ignore>
                <div
                    class="relative min-h-[min(72vh,820px)]"
                    x-data="{ loading: true, failed: false }"
                    x-init="$nextTick(() => {
                        const iframe = $refs.pdfPreview;
                        if (! iframe) { return; }
                        const timer = setTimeout(() => { loading = false; failed = true; }, 60000);
                        iframe.addEventListener('load', () => { clearTimeout(timer); loading = false; }, { once: true });
                    })"
                >
                    <div
                        x-show="loading"
                        x-transition.opacity
                        class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 rounded-2xl bg-white/95 px-6 text-center dark:bg-gray-950/95"
                        role="status"
                        aria-live="polite"
                    >
                        <svg class="h-10 w-10 shrink-0 animate-spin text-sky-600 dark:text-sky-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <div class="max-w-sm space-y-1">
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Generando el PDF…</p>
                            <p class="text-xs leading-relaxed text-gray-600 dark:text-gray-400">Toma unos segundos. Si los planes no han cambiado, se abre al instante.</p>
                        </div>
                    </div>
                    <div
                        x-show="failed"
                        x-cloak
                        class="absolute inset-x-0 top-0 z-20 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
                    >
                        La vista previa está tardando más de lo normal. Puede abrirla en otra pestaña o descargarla con los botones de abajo.
                    </div>
                    <iframe
                        x-ref="pdfPreview"
                        title="Vista previa del PDF de planes de capitado"
                        src="{{ $pdfPreviewUrl }}#toolbar=1"
                        class="relative z-0 h-[min(72vh,820px)] w-full rounded-2xl border-0 bg-white dark:bg-gray-900"
                    ></iframe>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-gray-200/80 px-4 py-3 dark:border-white/10">
                <a href="{{ $pdfPreviewUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center rounded-full border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">
                    Abrir en pestaña
                </a>
                <a href="{{ $pdfDownloadUrl }}" class="inline-flex items-center rounded-full bg-sky-700 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-sky-600">
                    Descargar PDF
                </a>
            </div>
        </div>
    @endif
</div>
