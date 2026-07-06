<div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200/80 bg-slate-50/80 p-4 dark:border-white/10 dark:bg-white/[0.03]">
    <div>
        <p class="text-sm font-semibold text-slate-900 dark:text-white">Vista previa del plan (PDF)</p>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            Genera el documento con la matriz de beneficios y coberturas para compartir o imprimir.
        </p>
    </div>
    <button
        type="button"
        wire:click="mountAction('planPdfPreview')"
        wire:loading.attr="disabled"
        wire:target="mountAction('planPdfPreview')"
        class="inline-flex min-h-[2.75rem] shrink-0 items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 active:scale-[0.98] disabled:opacity-60 dark:bg-emerald-500 dark:hover:bg-emerald-400">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <span wire:loading.remove wire:target="mountAction('planPdfPreview')">Generar vista previa PDF</span>
        <span wire:loading wire:target="mountAction('planPdfPreview')">Abriendo…</span>
    </button>
</div>
