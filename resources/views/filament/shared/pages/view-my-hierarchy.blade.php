<x-filament-panels::page>
    <div class="space-y-4">
        <div
            class="rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 p-4 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:border-white/10 dark:from-gray-900/90 dark:to-slate-950/95 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)] sm:p-5"
        >
            <div class="mb-4 space-y-1">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">
                    Jerarquía comercial
                </p>
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    Master → General → Agente → Subagente. Despliega equipos de master, generales o subagentes en fila
                    horizontal; desliza cuando hay más de cinco nodos.
                </p>
            </div>

            {!! $this->getHierarchyDiagram() !!}
        </div>
    </div>
</x-filament-panels::page>
