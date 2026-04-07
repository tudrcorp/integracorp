@props([
    'pdfPreviewUrl' => null,
    'pdfDownloadUrl' => null,
])

<div class="fi-scoped space-y-4">
    <div class="overflow-hidden rounded-3xl border border-gray-200/80 bg-white/80 shadow-sm backdrop-blur-md dark:border-white/10 dark:bg-gray-900/70">
        <div class="flex items-center justify-between gap-3 border-b border-gray-200/80 px-4 py-3 dark:border-white/10">
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">Orden de servicio</p>
                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Vista previa del PDF</p>
            </div>
            <span class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-medium text-sky-700 dark:bg-sky-500/20 dark:text-sky-300">
                PDF
            </span>
        </div>

        <div class="bg-gray-50/80 p-3 dark:bg-gray-950/60">
            <iframe
                title="Vista previa PDF orden de servicio"
                src="{{ $pdfPreviewUrl }}#toolbar=1"
                class="h-[min(72vh,820px)] w-full rounded-2xl border-0 bg-white dark:bg-gray-900"
                loading="lazy"
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
            <a
                href="{{ $pdfDownloadUrl }}"
                class="inline-flex items-center rounded-full bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-500"
            >
                Descargar PDF
            </a>
        </div>
    </div>
</div>
