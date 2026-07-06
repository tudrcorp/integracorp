<div class="relative z-10 mb-4 rounded-2xl border border-slate-200/80 bg-white/90 p-3 shadow-sm dark:border-white/10 dark:bg-slate-900/80">
    <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">
        Filtrar agenda
    </p>

    <div class="grid gap-3 md:grid-cols-2">
        <div class="md:col-span-2">
            <label class="mb-1 block text-[11px] font-semibold text-slate-600 dark:text-slate-300">Departamento</label>
            <select
                wire:model.live="corporateAgendaFilterDepartment"
                class="w-full rounded-xl border border-slate-200/80 bg-white px-3 py-2 text-sm text-slate-800 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100"
            >
                <option value="">Todos los departamentos</option>
                @foreach ($this->departmentOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($this->hasActiveCorporateAgendaFilters)
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <span class="text-[11px] text-slate-500 dark:text-slate-400">Filtro activo:</span>
            <span class="rounded-full bg-cyan-100 px-2.5 py-1 text-[11px] font-semibold text-cyan-900 dark:bg-cyan-500/20 dark:text-cyan-100">
                {{ $this->departmentOptions[$corporateAgendaFilterDepartment] ?? $corporateAgendaFilterDepartment }}
            </span>
            <button
                type="button"
                wire:click="clearCorporateAgendaFilters"
                class="rounded-full border border-slate-200/80 px-2.5 py-1 text-[11px] font-semibold text-slate-600 transition hover:bg-slate-100 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/10"
            >
                Limpiar filtros
            </button>
        </div>
    @endif
</div>
