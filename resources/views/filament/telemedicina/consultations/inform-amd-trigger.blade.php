<div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-sky-200/80 bg-sky-50/80 p-4 dark:border-sky-500/20 dark:bg-sky-950/30">
    <div>
        <p class="text-sm font-semibold text-slate-900 dark:text-white">Informe AMD</p>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            Registre la consulta inicial, genere el Informe Médico Largo o cargue un archivo relacionado con la AMD.
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <button
            type="button"
            wire:click="mountAction('informAmd')"
            wire:loading.attr="disabled"
            wire:target="mountAction('informAmd')"
            class="inline-flex min-h-[2.75rem] shrink-0 items-center justify-center gap-2 rounded-2xl bg-sky-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700 active:scale-[0.98] disabled:opacity-60 dark:bg-sky-500 dark:hover:bg-sky-400"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span wire:loading.remove wire:target="mountAction('informAmd')">Informar AMD</span>
            <span wire:loading wire:target="mountAction('informAmd')">Abriendo…</span>
        </button>
        <button
            type="button"
            wire:click="mountAction('uploadAmdFile')"
            wire:loading.attr="disabled"
            wire:target="mountAction('uploadAmdFile')"
            class="inline-flex min-h-[2.75rem] shrink-0 items-center justify-center gap-2 rounded-2xl border border-sky-300 bg-white px-5 py-2.5 text-sm font-semibold text-sky-700 shadow-sm transition hover:bg-sky-50 active:scale-[0.98] disabled:opacity-60 dark:border-sky-500/40 dark:bg-sky-950/40 dark:text-sky-200 dark:hover:bg-sky-900/50"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
            </svg>
            <span wire:loading.remove wire:target="mountAction('uploadAmdFile')">Cargar Archivo AMD</span>
            <span wire:loading wire:target="mountAction('uploadAmdFile')">Abriendo…</span>
        </button>
    </div>
</div>
