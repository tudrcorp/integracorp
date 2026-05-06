<div class="space-y-4">
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
        <div class="text-sm font-semibold text-slate-900 dark:text-slate-100">
            {{ $doctorNurseLabel }}
        </div>
        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            Puedes abrir la vista previa en una pestaña nueva o descargar el PDF.
        </div>
    </div>

    <div class="flex flex-wrap gap-2">
        <a
            href="{{ $pdfPreviewUrl }}"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center justify-center gap-2 rounded-full bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700 active:scale-[0.98]"
        >
            <x-heroicon-o-document class="h-4 w-4"/>
            Vista previa
        </a>

        <a
            href="{{ $pdfDownloadUrl }}"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center justify-center gap-2 rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 active:scale-[0.98]"
        >
            <x-heroicon-o-arrow-down-tray class="h-4 w-4"/>
            Descargar PDF
        </a>
    </div>
</div>

