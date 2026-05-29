<div class="relative z-10 mb-4 rounded-2xl border border-slate-200/80 bg-white/90 p-3 shadow-sm dark:border-white/10 dark:bg-slate-900/80">
    <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">
        Filtrar agenda
    </p>

    <div class="grid gap-3 md:grid-cols-2">
        <div class="md:col-span-2">
            <label class="mb-1 block text-[11px] font-semibold text-slate-600 dark:text-slate-300">Mostrar en calendario</label>
            <select
                wire:model.live="agendaFilterCategory"
                class="w-full rounded-xl border border-slate-200/80 bg-white px-3 py-2 text-sm text-slate-800 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100"
            >
                <option value="">Agenda completa</option>
                <option value="offices">Solo oficinas</option>
                <option value="guards">Solo guardias</option>
                <option value="departments">Solo departamentos</option>
            </select>
        </div>

        @if ($agendaFilterCategory === 'offices')
            <div class="md:col-span-2">
                <label class="mb-1 block text-[11px] font-semibold text-slate-600 dark:text-slate-300">Oficina</label>
                <select
                    wire:model.live="agendaFilterOffice"
                    class="w-full rounded-xl border border-slate-200/80 bg-white px-3 py-2 text-sm text-slate-800 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100"
                >
                    <option value="">Todas las oficinas</option>
                    @foreach ($this->officeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        @if ($agendaFilterCategory === 'guards')
            <div class="md:col-span-2">
                <label class="mb-1 block text-[11px] font-semibold text-slate-600 dark:text-slate-300">Horario de guardia</label>
                <select
                    wire:model.live="agendaFilterGuardShift"
                    class="w-full rounded-xl border border-slate-200/80 bg-white px-3 py-2 text-sm text-slate-800 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100"
                >
                    <option value="">Todos los horarios</option>
                    @foreach ($this->guardShiftOptions as $value => $label)
                        <option value="{{ $value }}">{{ \App\Enums\TdgCalendarGuardShift::tryFrom($value)?->shortLabel() ?? $label }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        @if ($agendaFilterCategory === 'departments')
            <div class="md:col-span-2">
                <label class="mb-1 block text-[11px] font-semibold text-slate-600 dark:text-slate-300">Colaborador de sistemas</label>
                <select
                    wire:model.live="agendaFilterSystemsColaborador"
                    class="w-full rounded-xl border border-cyan-200/80 bg-white px-3 py-2 text-sm text-slate-800 dark:border-cyan-400/30 dark:bg-slate-950 dark:text-slate-100"
                >
                    <option value="">Todos los colaboradores de sistemas</option>
                    @foreach ($this->systemsColaboradorFilterOptions as $colaboradorId => $colaboradorName)
                        <option value="{{ $colaboradorId }}">{{ $colaboradorName }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-[10px] text-slate-500 dark:text-slate-400">
                    Filtra el mes por la agenda de atención de un integrante del área de sistemas.
                </p>
            </div>

            <div class="md:col-span-2">
                <label class="mb-1 block text-[11px] font-semibold text-slate-600 dark:text-slate-300">Departamento atendido</label>
                <select
                    wire:model.live="agendaFilterDepartment"
                    class="w-full rounded-xl border border-slate-200/80 bg-white px-3 py-2 text-sm text-slate-800 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100"
                >
                    <option value="">Todos los departamentos</option>
                    @foreach ($this->departmentFilterOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    </div>

    @if ($this->hasActiveAgendaFilters)
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <span class="text-[11px] text-slate-500 dark:text-slate-400">Filtro activo:</span>
            @php
                $categoryLabels = [
                    'offices' => 'Oficinas',
                    'guards' => 'Guardias',
                    'departments' => 'Departamentos',
                ];
            @endphp
            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-800 dark:bg-slate-700 dark:text-slate-100">
                {{ $categoryLabels[$agendaFilterCategory] ?? $agendaFilterCategory }}
            </span>
            @if ($agendaFilterCategory === 'offices' && $agendaFilterOffice !== '')
                <span class="rounded-full bg-cyan-100 px-2.5 py-1 text-[11px] font-semibold text-cyan-900 dark:bg-cyan-500/20 dark:text-cyan-100">
                    {{ $this->officeOptions[$agendaFilterOffice] ?? $agendaFilterOffice }}
                </span>
            @endif
            @if ($agendaFilterCategory === 'guards' && $agendaFilterGuardShift !== '')
                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-semibold text-amber-900 dark:bg-amber-500/20 dark:text-amber-100">
                    {{ \App\Enums\TdgCalendarGuardShift::tryFrom($agendaFilterGuardShift)?->shortLabel() ?? $agendaFilterGuardShift }}
                </span>
            @endif
            @if ($agendaFilterCategory === 'departments' && $agendaFilterSystemsColaborador !== '' && $this->agendaFilterSystemsColaboradorLabel)
                <span class="rounded-full bg-cyan-100 px-2.5 py-1 text-[11px] font-semibold text-cyan-900 dark:bg-cyan-500/20 dark:text-cyan-100">
                    {{ $this->agendaFilterSystemsColaboradorLabel }}
                </span>
            @endif
            @if ($agendaFilterCategory === 'departments' && $agendaFilterDepartment !== '')
                @php $deptMeta = $this->departmentCatalog[$agendaFilterDepartment] ?? []; @endphp
                <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-semibold {{ $deptMeta['chip_class'] ?? '' }}">
                    <span class="inline-flex size-2 rounded-full {{ $deptMeta['dot_class'] ?? 'bg-slate-400' }}"></span>
                    {{ $deptMeta['display_label'] ?? $deptMeta['short_label'] ?? $agendaFilterDepartment }}
                </span>
            @endif
            <button
                type="button"
                wire:click="clearAgendaFilters"
                class="rounded-full border border-slate-200/80 px-2.5 py-1 text-[11px] font-semibold text-slate-600 transition hover:bg-slate-100 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/10"
            >
                Limpiar filtros
            </button>
        </div>
    @endif
</div>
