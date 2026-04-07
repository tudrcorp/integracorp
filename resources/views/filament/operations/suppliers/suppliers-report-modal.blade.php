@props([
    'pdfPreviewUrl' => null,
    'pdfDownloadUrl' => null,
])

<div class="fi-scoped space-y-5" wire:ignore.self>
    <div
        class="overflow-hidden rounded-3xl border border-gray-200/80 bg-white/80 shadow-sm backdrop-blur-md dark:border-white/10 dark:bg-gray-900/70"
    >
        <div
            class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200/80 px-4 py-3 dark:border-white/10"
        >
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">Proveedores</p>
                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Vista previa del PDF</p>
            </div>
            <span
                class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-medium text-sky-700 dark:bg-sky-500/20 dark:text-sky-300"
            >
                PDF
            </span>
        </div>

        <div class="bg-gray-50/80 p-3 dark:bg-gray-950/60">
            <iframe
                title="Vista previa reporte de proveedores"
                src="{{ $pdfPreviewUrl }}#toolbar=1"
                class="h-[min(72vh,820px)] w-full rounded-2xl border-0 bg-white dark:bg-gray-900"
                loading="lazy"
            ></iframe>
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
            <button
                type="button"
                wire:click="moveSupplierReportToDownloadZone"
                wire:loading.attr="disabled"
                wire:target="moveSupplierReportToDownloadZone"
                class="inline-flex items-center rounded-full bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-amber-500 disabled:cursor-not-allowed disabled:opacity-60"
            >
                <span wire:loading.remove wire:target="moveSupplierReportToDownloadZone">Mover a Zona de Descarga</span>
                <span wire:loading wire:target="moveSupplierReportToDownloadZone">Publicando…</span>
            </button>
        </div>
    </div>

    <div
        class="rounded-3xl border border-gray-200/80 bg-white/80 p-4 shadow-sm dark:border-white/10 dark:bg-gray-900/70"
    >
        <p class="mb-3 text-sm font-semibold text-gray-800 dark:text-gray-100">Enviar por correo</p>
        <form wire:submit.prevent="sendSupplierReportPdf" class="flex flex-col">
            <div class="min-w-0 w-full">
                <label for="supplier-report-email" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                    Correo del destinatario
                </label>
                <input
                    id="supplier-report-email"
                    type="email"
                    wire:model="supplierReportEmail"
                    autocomplete="email"
                    placeholder="correo@ejemplo.com"
                    class="fi-input block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm outline-none transition duration-75 placeholder:text-gray-400 focus:border-primary-500 focus:ring-2 focus:ring-inset focus:ring-primary-500 disabled:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-white dark:placeholder:text-gray-500 dark:focus:border-primary-500 dark:disabled:bg-transparent"
                />
                @error('supplierReportEmail')
                    <p class="mt-1 text-xs text-danger-600 dark:text-danger-400">{{ $message }}</p>
                @enderror
            </div>
            <button
                type="submit"
                class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-full bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 sm:mt-6 sm:w-auto sm:self-start dark:focus:ring-offset-gray-900"
            >
                Enviar PDF
            </button>
        </form>
    </div>
</div>
