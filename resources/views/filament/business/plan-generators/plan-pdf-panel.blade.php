@php
    /** @var \App\Models\PlanGenerator $record */
    use App\Services\PlanGeneratorPdfService;

    $codeLabel = PlanGeneratorPdfService::codeLabel($record);
    $previewUrl = route('business.plan-generators.pdf.preview', $record);
    $downloadUrl = route('business.plan-generators.pdf.download', $record);
@endphp

<section
    class="rounded-[1.35rem] border border-slate-200/80 bg-white/90 p-5 ring-1 ring-black/[0.04] dark:border-white/10 dark:bg-white/[0.03] dark:ring-white/[0.05]">
    <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-slate-900 dark:text-white">Plan generado (PDF)</p>
            <p class="mt-1 max-w-2xl text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                Vista previa de la matriz de beneficios y coberturas. Las descargas quedan auditadas.
            </p>
        </div>
        <span
            class="mt-2 inline-flex shrink-0 items-center rounded-full bg-emerald-100 px-2.5 py-1 text-[0.65rem] font-semibold uppercase tracking-wide text-emerald-900 dark:bg-emerald-500/20 dark:text-emerald-100 sm:mt-0">
            {{ $codeLabel }}
        </span>
    </div>

    <div
        class="relative mt-4 overflow-hidden rounded-xl border border-slate-200/80 bg-slate-100/80 dark:border-white/10 dark:bg-slate-900/40"
        x-data="{ pdfPreviewLoaded: false }">
        <div
            x-show="!pdfPreviewLoaded"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 bg-slate-100/95 px-4 text-center dark:bg-slate-900/95"
            role="status"
            aria-live="polite">
            <svg
                class="h-10 w-10 shrink-0 animate-spin text-emerald-600 dark:text-emerald-400"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Generando vista previa del PDF</p>
            <p class="max-w-xs text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                El documento se está preparando; en breve podrá verlo aquí.
            </p>
        </div>
        <iframe
            title="Vista previa plan generado"
            src="{{ $previewUrl }}"
            class="relative z-0 h-[min(70vh,520px)] w-full min-h-[320px] border-0"
            @load="pdfPreviewLoaded = true"></iframe>
    </div>

    <div class="mt-4">
        <a
            href="{{ $downloadUrl }}"
            target="_blank"
            rel="noopener"
            class="inline-flex min-h-[2.75rem] w-full items-center justify-center gap-2 rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M4 12V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v5" />
            </svg>
            Descargar PDF
        </a>
    </div>
</section>
