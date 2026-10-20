@props([
    'pdfPreviewUrl' => null,
    'pdfDownloadUrl' => null,
    'documentLabel' => 'Orden de servicio',
    'documentTitle' => 'Vista previa del PDF',
])

<div class="fi-scoped space-y-4">
    <div class="overflow-hidden rounded-3xl border border-gray-200/80 bg-white/80 shadow-sm backdrop-blur-md dark:border-white/10 dark:bg-gray-900/70">
        <div class="flex items-center justify-between gap-3 border-b border-gray-200/80 px-4 py-3 dark:border-white/10">
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ $documentLabel }}</p>
                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $documentTitle }}</p>
            </div>
            <span class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-medium text-sky-700 dark:bg-sky-500/20 dark:text-sky-300">
                PDF
            </span>
        </div>

        @if (filled($pdfPreviewUrl))
            {{-- El src se asigna desde Alpine para que el listener de carga exista antes de que empiece la petición. --}}
            <div
                class="relative bg-gray-50/80 p-3 dark:bg-gray-950/60"
                x-data="{ loading: true, slow: false }"
                x-init="
                    setTimeout(() => { if (loading) { slow = true } }, 8000);
                    $refs.pdfFrame.src = @js($pdfPreviewUrl.'#toolbar=1');
                "
            >
                <div
                    x-show="loading"
                    x-transition.opacity.duration.200ms
                    class="absolute inset-3 z-10 flex flex-col items-center justify-center gap-3 rounded-2xl bg-white/95 dark:bg-gray-900/95"
                    role="status"
                    aria-live="polite"
                    data-pdf-preview-loading
                >
                    <svg class="size-9 animate-spin text-sky-600 dark:text-sky-400" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                        <path class="opacity-90" d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
                    </svg>
                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Generando {{ \Illuminate\Support\Str::lower($documentLabel) }}…</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400" x-show="! slow">Esto toma solo unos segundos.</p>
                    <p class="max-w-sm text-center text-xs text-amber-700 dark:text-amber-300" x-show="slow" x-cloak>
                        Está tardando más de lo normal. Puede esperar o usar «Abrir en pestaña».
                    </p>
                </div>

                <iframe
                    x-ref="pdfFrame"
                    x-on:load="loading = false"
                    title="Vista previa PDF {{ $documentLabel }}"
                    class="h-[min(72vh,820px)] w-full rounded-2xl border-0 bg-white dark:bg-gray-900"
                ></iframe>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2 px-4 py-3">
                <a
                    href="{{ $pdfPreviewUrl }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center rounded-full border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                >
                    Abrir en pestaña
                </a>
                @if (filled($pdfDownloadUrl))
                    <a
                        href="{{ $pdfDownloadUrl }}"
                        class="inline-flex items-center rounded-full bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-500"
                    >
                        Descargar PDF
                    </a>
                @endif
            </div>
        @else
            <div class="px-4 py-10 text-center">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Documento no disponible</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">El PDF aún no ha sido generado para este registro.</p>
            </div>
        @endif
    </div>
</div>
