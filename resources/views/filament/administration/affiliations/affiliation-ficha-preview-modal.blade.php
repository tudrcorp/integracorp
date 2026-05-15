@props([
    'pdfPreviewUrl' => null,
    'pdfDownloadUrl' => null,
    'recordLabel' => null,
])

<div class="fi-scoped space-y-5" wire:ignore.self>
    <div
        class="overflow-hidden rounded-3xl border border-gray-200/80 bg-white/80 shadow-sm backdrop-blur-md dark:border-white/10 dark:bg-gray-900/70"
    >
        <div
            class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200/80 px-4 py-3 dark:border-white/10"
        >
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">Ficha individual</p>
                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Vista previa del PDF</p>
                @if (filled($recordLabel))
                    <p class="mt-0.5 max-w-md truncate text-xs text-gray-500 dark:text-gray-400" title="{{ $recordLabel }}">
                        {{ $recordLabel }}
                    </p>
                @endif
            </div>
            <span
                class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300"
            >
                PDF
            </span>
        </div>

        <div
            class="bg-gray-50/80 p-3 dark:bg-gray-950/60"
            wire:ignore
        >
            <div
                class="relative min-h-[min(72vh,820px)]"
                x-data="{ pdfPreviewLoading: true }"
                x-init="$nextTick(() => {
                    const iframe = $refs.pdfPreview;
                    if (!iframe) {
                        return;
                    }
                    const done = () => { pdfPreviewLoading = false };
                    const safetyMs = 120000;
                    const t = setTimeout(done, safetyMs);
                    iframe.addEventListener(
                        'load',
                        () => {
                            clearTimeout(t);
                            done();
                        },
                        { once: true },
                    );
                })"
            >
                <div
                    x-show="pdfPreviewLoading"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 rounded-2xl bg-white/95 px-6 text-center shadow-inner dark:bg-gray-950/95"
                    role="status"
                    aria-live="polite"
                >
                    <svg
                        class="h-10 w-10 shrink-0 animate-spin text-emerald-600 dark:text-emerald-400"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        ></path>
                    </svg>
                    <div class="max-w-sm space-y-1">
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            Generando la ficha en PDF…
                        </p>
                        <p class="text-xs leading-relaxed text-gray-600 dark:text-gray-400">
                            Estamos generando el documento. La primera vez puede tardar más; las siguientes suelen abrir al instante mientras no cambien los datos de la afiliación ni de los familiares asociados.
                        </p>
                    </div>
                </div>
                <iframe
                    x-ref="pdfPreview"
                    title="Vista previa ficha afiliación individual"
                    src="{{ $pdfPreviewUrl }}#toolbar=1"
                    class="relative z-0 h-[min(72vh,820px)] w-full rounded-2xl border-0 bg-white dark:bg-gray-900"
                ></iframe>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-gray-200/80 px-4 py-3 dark:border-white/10">
            <a
                href="{{ $pdfPreviewUrl }}"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center rounded-full border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
            >
                Abrir en pestaña
            </a>
            <a
                href="{{ $pdfDownloadUrl }}"
                class="inline-flex items-center rounded-full bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-500"
            >
                Descargar PDF
            </a>
        </div>
    </div>
</div>
