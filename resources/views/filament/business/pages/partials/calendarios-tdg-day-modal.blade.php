<div
    x-data="{ open: $wire.entangle('isDayModalOpen'), workspace: 'offices' }"
    @tdg-modal-workspace-changed.window="workspace = $event.detail.workspace"
    x-show="open"
    x-cloak
    x-transition.opacity
    class="fixed inset-0 z-[80] flex items-center justify-center p-4 sm:p-6"
>
    <div class="absolute inset-0 bg-slate-950/55 backdrop-blur-[2px]" wire:click="closeDayModal"></div>

    <section class="relative z-[81] flex w-full max-w-6xl max-h-[92vh] flex-col overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-2xl dark:border-white/10 dark:bg-slate-900">
        <header class="shrink-0 border-b border-slate-200/80 px-5 py-4 dark:border-white/10">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">
                        {{ \Carbon\Carbon::parse($selectedDate)->translatedFormat('l d \\d\\e F Y') }}
                    </p>
                    <h3 class="mt-1 text-base font-semibold text-slate-900 dark:text-slate-100">
                        Calendario TDG
                    </h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Oficinas, guardias de operaciones y agenda de trabajo por departamentos.
                    </p>
                </div>

                <button type="button" wire:click="closeDayModal" class="inline-flex items-center justify-center rounded-full p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white">
                    <x-filament::icon icon="heroicon-o-x-mark" class="size-5" />
                </button>
            </div>

            <div class="mt-4 grid gap-2 sm:grid-cols-3" role="tablist" aria-label="Espacios de la agenda TDG">
                @foreach ([
                'offices' => ['icon' => 'heroicon-o-building-office-2', 'title' => 'Guardias oficinas', 'hint' => 'Centro Lido y Farmadoc'],
                'guards' => ['icon' => 'heroicon-o-shield-check', 'title' => 'Guardas operaciones', 'hint' => '2.1, 2.2 diurnas y guardia nocturna'],
                'departments' => ['icon' => 'heroicon-o-squares-2x2', 'title' => 'Guardias sistema', 'hint' => 'Colaboradores de sistemas por área'],
                ] as $workspaceKey => $workspaceMeta)
                <button
                    type="button"
                    @click="workspace = '{{ $workspaceKey }}'"
                    :class="workspace === '{{ $workspaceKey }}'
                        ? 'rounded-2xl border px-3 py-3 text-left transition border-[#4E8EA2]/80 bg-[#BDD8E9]/90 shadow-[0_8px_22px_rgba(78,142,162,0.25)] dark:border-[#7BBDE8]/70 dark:bg-[#0A4174]/55'
                        : 'rounded-2xl border px-3 py-3 text-left transition border-slate-200/80 bg-white/80 hover:border-[#7BBDE8]/80 hover:bg-[#BDD8E9]/35 dark:border-white/10 dark:bg-slate-900/70 dark:hover:border-[#6EA2B3]/60 dark:hover:bg-[#0A4174]/25'"
                    role="tab"
                    :aria-selected="workspace === '{{ $workspaceKey }}'"
                >
                    <div class="flex items-center gap-2">
                        <span class="inline-flex size-8 items-center justify-center rounded-xl bg-[#7BBDE8]/40 text-[#0A4174] dark:bg-[#4E8EA2]/35 dark:text-[#BDD8E9]">
                            <x-filament::icon :icon="$workspaceMeta['icon']" class="size-4" />
                        </span>
                        <span>
                            <span class="block text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $workspaceMeta['title'] }}</span>
                            <span class="mt-0.5 block text-[11px] text-slate-500 dark:text-slate-400">{{ $workspaceMeta['hint'] }}</span>
                        </span>
                    </div>
                </button>
                @endforeach
            </div>
        </header>

        <div class="min-h-0 flex-1 overflow-y-auto p-5">
            <div class="relative mb-4">
                <x-filament::icon icon="heroicon-o-magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input type="search" wire:model.live.debounce.300ms="collaboratorSearch" placeholder="Buscar colaborador por nombre o correo..." class="w-full rounded-xl border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm dark:border-white/10 dark:bg-slate-800 dark:text-slate-100">
            </div>

            <div x-show="workspace === 'offices'" x-cloak>
            <div class="grid gap-4 lg:grid-cols-3">
                @foreach ($this->officeOptions as $officeValue => $officeLabel)
                @php
                $selectedCollaborators = $this->resolveSelectedOfficeCollaborators($officeValue);
                $selectedCount = count($selectedCollaborators);
                @endphp
                <div wire:key="tdg-office-card-{{ $officeValue }}" class="rounded-2xl border border-slate-200/80 bg-slate-50/70 p-4 dark:border-white/10 dark:bg-slate-950/40">
                    <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Oficina</p>
                    <h4 class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $officeLabel }}</h4>

                    <div class="mt-4 rounded-xl border border-slate-200/80 bg-white/90 p-3 dark:border-white/10 dark:bg-slate-900/80">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                                Colaboradores asignados
                                @if ($selectedCount > 0)
                                <span class="ml-1 rounded-full bg-emerald-100 px-1.5 py-0.5 text-[10px] text-emerald-800 dark:bg-emerald-500/30 dark:text-emerald-100">{{ $selectedCount }}</span>
                                @endif
                            </p>
                            @if ($selectedCount > 0)
                            <button type="button" wire:click="clearOfficeCollaborators('{{ $officeValue }}')" class="rounded-lg px-2 py-1 text-[10px] font-semibold text-rose-600 transition hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-500/10">
                                Limpiar todos
                            </button>
                            @endif
                        </div>

                        @if ($selectedCount > 0)
                        <div class="mt-2 space-y-2">
                            @foreach ($selectedCollaborators as $selectedCollaborator)
                            <div wire:key="office-selected-{{ $officeValue }}-{{ $selectedCollaborator['id'] }}" class="flex items-center gap-3 rounded-xl border border-emerald-400 bg-emerald-100 px-2 py-2 dark:border-emerald-400/60 dark:bg-emerald-600/25">
                                @include('filament.business.pages.partials.tdg-colaborador-avatar', ['colaborador' => $selectedCollaborator, 'size' => 'sm'])
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $selectedCollaborator['name'] }}</p>
                                    <p class="truncate text-[10px] text-slate-500 dark:text-slate-400">{{ $selectedCollaborator['email'] ?: 'Sin correo' }}</p>
                                </div>
                                <button type="button" wire:click="removeOfficeCollaborator('{{ $officeValue }}', {{ $selectedCollaborator['id'] }})" class="rounded-lg px-2 py-1 text-[10px] font-semibold text-rose-600 transition hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-500/10">
                                    Quitar
                                </button>
                            </div>
                            @endforeach
                        </div>
                        @else
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                            Sin asignar. Haz clic en uno o varios colaboradores de la lista.
                        </p>
                        @endif
                    </div>

                    <p class="mt-3 text-[10px] text-slate-500 dark:text-slate-400">
                        Clic para agregar o quitar. Si ya está en otra oficina hoy, no aparece aquí.
                    </p>

                    <div class="mt-2 max-h-52 space-y-2 overflow-y-auto pr-1">
                        @forelse ($this->filteredCollaboratorOptionsForOffice($officeValue) as $collaborator)
                        @php
                        $isSelected = $this->isOfficeCollaboratorSelected($officeValue, (int) $collaborator['id']);
                        @endphp
                        <button type="button" wire:key="office-picker-{{ $officeValue }}-{{ $collaborator['id'] }}" wire:click="assignOfficeCollaborator('{{ $officeValue }}', {{ $collaborator['id'] }})" class="flex w-full items-center gap-3 rounded-xl border px-3 py-2 text-left transition
                                        {{ $isSelected
                                            ? 'border-emerald-500 bg-emerald-100 shadow-[0_6px_16px_rgba(16,185,129,0.22)] dark:border-emerald-400/60 dark:bg-emerald-600/25'
                                            : 'border-slate-200/80 bg-white hover:border-emerald-300 hover:bg-emerald-50 dark:border-white/10 dark:bg-slate-900/70 dark:hover:border-emerald-400/40 dark:hover:bg-emerald-600/10' }}">
                            @include('filament.business.pages.partials.tdg-colaborador-avatar', ['colaborador' => $collaborator])
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-xs font-semibold text-slate-800 dark:text-slate-100">{{ $collaborator['name'] }}</span>
                                <span class="block truncate text-[11px] text-slate-500 dark:text-slate-400">{{ $collaborator['email'] ?: 'Sin correo' }}</span>
                            </span>
                            @if ($isSelected)
                            <x-filament::icon icon="heroicon-o-check-circle" class="size-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                            @endif
                        </button>
                        @empty
                        <p class="rounded-xl border border-dashed border-slate-300/80 px-3 py-4 text-center text-xs text-slate-500 dark:border-white/15 dark:text-slate-400">
                            @if (trim($collaboratorSearch) !== '')
                            No hay colaboradores disponibles para esta búsqueda.
                            @else
                            Todos los colaboradores visibles ya están asignados en otras oficinas o en esta sede.
                            @endif
                        </p>
                        @endforelse
                    </div>
                </div>
                @endforeach
            </div>

            @include('filament.business.pages.partials.tdg-office-replication-panel')
            </div>

            <div x-show="workspace === 'guards'" x-cloak>
            <div class="mb-4 rounded-2xl border border-amber-200/80 bg-amber-50/80 px-4 py-3 dark:border-amber-400/30 dark:bg-amber-500/10">
                <label class="inline-flex items-center gap-2 text-sm font-semibold text-amber-900 dark:text-amber-100">
                    <input type="checkbox" wire:model.live="useSameGuardCollaborator" class="rounded border-amber-400 text-amber-600 focus:ring-amber-400/50">
                    Usar el mismo colaborador para guardias 2.1 y 2.2 (horario diurno)
                </label>
                <p class="mt-1 text-xs text-amber-800/90 dark:text-amber-100/80">
                    La guardia nocturna se asigna de forma independiente. Usa el buscador del modal para filtrar colaboradores.
                </p>
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                @foreach ($this->guardShiftOptions as $shiftValue => $shiftLabel)
                @php
                $guardShift = \App\Enums\TdgCalendarGuardShift::tryFrom($shiftValue);
                $selectedId = $guardAssignmentsForm[$shiftValue] ?? null;
                $selectedCollaborator = $this->resolveSelectedCollaborator(is_numeric($selectedId) ? (int) $selectedId : null);
                $isSecondaryLocked = $useSameGuardCollaborator && $shiftValue === \App\Enums\TdgCalendarGuardShift::IlsCapitado->value;
                $isNocturnal = $guardShift?->isNocturnalShift() ?? false;
                @endphp
                <div wire:key="tdg-guard-card-{{ $shiftValue }}" class="rounded-2xl border border-slate-200/80 bg-slate-50/70 p-4 dark:border-white/10 dark:bg-slate-950/40 {{ $isSecondaryLocked ? 'opacity-90' : '' }} {{ $isNocturnal ? 'ring-1 ring-indigo-200/80 dark:ring-indigo-500/30' : '' }}">
                    <p @class([
                        'text-xs font-semibold uppercase tracking-[0.12em]',
                        'text-indigo-600 dark:text-indigo-300' => $isNocturnal,
                        'text-violet-600 dark:text-violet-300' => ! $isNocturnal,
                    ])>
                        {{ $isNocturnal ? 'Guardia nocturna' : 'Guardia operaciones' }}
                    </p>
                    <h4 class="mt-1 text-sm font-semibold leading-snug text-slate-900 dark:text-slate-100">{{ $shiftLabel }}</h4>

                    <div class="mt-4 rounded-xl border border-slate-200/80 bg-white/90 p-3 dark:border-white/10 dark:bg-slate-900/80">
                        <p class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">Colaborador en guardia</p>
                        @if ($selectedCollaborator)
                        <div class="mt-2 flex items-center gap-3">
                            @include('filament.business.pages.partials.tdg-colaborador-avatar', ['colaborador' => $selectedCollaborator, 'size' => 'lg'])
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $selectedCollaborator['name'] }}</p>
                                <p class="truncate text-[11px] text-slate-500 dark:text-slate-400">{{ $selectedCollaborator['email'] ?: 'Sin correo' }}</p>
                            </div>
                            @if (! $isSecondaryLocked)
                            <button type="button" wire:click="clearGuardCollaborator('{{ $shiftValue }}')" class="rounded-lg px-2 py-1 text-[11px] font-semibold text-rose-600 transition hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-500/10">
                                Quitar
                            </button>
                            @endif
                        </div>
                        @else
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Sin asignar.</p>
                        @endif
                    </div>

                    @if (! $isSecondaryLocked)
                    <div class="mt-3 max-h-72 space-y-2 overflow-y-auto pr-1">
                        @forelse ($this->filteredCollaboratorOptionsForGuardShift($shiftValue) as $collaborator)
                        @php
                        $isSelected = (int) ($guardAssignmentsForm[$shiftValue] ?? 0) === (int) $collaborator['id'];
                        @endphp
                        <button type="button" wire:key="guard-picker-{{ $shiftValue }}-{{ $collaborator['id'] }}" wire:click="assignGuardCollaborator('{{ $shiftValue }}', {{ $collaborator['id'] }})" class="flex w-full items-center gap-3 rounded-xl border px-3 py-2 text-left transition
                                            {{ $isSelected
                                                ? 'border-violet-400 bg-violet-50/90 shadow-[0_6px_16px_rgba(139,92,246,0.18)] dark:border-violet-400/50 dark:bg-violet-500/15'
                                                : 'border-slate-200/80 bg-white hover:border-violet-300 hover:bg-violet-50/60 dark:border-white/10 dark:bg-slate-900/70 dark:hover:border-violet-400/40' }}">
                            @include('filament.business.pages.partials.tdg-colaborador-avatar', ['colaborador' => $collaborator])
                            <span class="min-w-0">
                                <span class="block truncate text-xs font-semibold text-slate-800 dark:text-slate-100">{{ $collaborator['name'] }}</span>
                                <span class="block truncate text-[11px] text-slate-500 dark:text-slate-400">{{ $collaborator['email'] ?: 'Sin correo' }}</span>
                            </span>
                        </button>
                        @empty
                        <p class="rounded-xl border border-dashed border-slate-300/80 px-3 py-4 text-center text-xs text-slate-500 dark:border-white/15 dark:text-slate-400">
                            @if (trim($collaboratorSearch) !== '')
                            No hay colaboradores para esta búsqueda.
                            @else
                            No hay más colaboradores disponibles para este horario.
                            @endif
                        </p>
                        @endforelse
                    </div>
                    @endif
                </div>
                @endforeach
            </div>

            @include('filament.business.pages.partials.tdg-guard-replication-panel')
            </div>

            <div x-show="workspace === 'departments'" x-cloak>
            <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">
                Selecciona los departamentos del día y asigna uno o más colaboradores del área de <span class="font-semibold text-slate-700 dark:text-slate-200">sistemas</span> que los atenderán.
            </p>

            <div class="flex flex-wrap gap-2">
                @foreach ($this->departmentOptions as $departmentValue => $departmentLabel)
                @php
                $meta = $this->departmentCatalog[$departmentValue] ?? [];
                $isSelected = in_array($departmentValue, $departmentAssignmentsForm, true);
                @endphp
                <button type="button" wire:click="toggleDepartment('{{ $departmentValue }}')" class="{{ $isSelected ? ($meta['chip_class'] ?? 'tdg-dept-chip tdg-dept-chip--default is-selected') : ($meta['idle_chip_class'] ?? 'tdg-dept-chip tdg-dept-chip--default is-idle') }}">
                    <span class="{{ $meta['dot_class'] ?? 'tdg-dept-chip__dot tdg-dept-chip__dot--default' }}"></span>
                    <span>{{ $departmentLabel }}</span>
                </button>
                @endforeach
            </div>

            @if ($departmentAssignmentsForm !== [])
            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                @foreach ($departmentAssignmentsForm as $departmentValue)
                @php
                $meta = $this->departmentCatalog[$departmentValue] ?? [];
                $selectedCollaborators = $this->resolveSelectedDepartmentCollaborators($departmentValue);
                $selectedCount = count($selectedCollaborators);
                @endphp
                <div wire:key="tdg-department-card-{{ $departmentValue }}" class="rounded-2xl border border-slate-200/80 bg-slate-50/70 p-4 dark:border-white/10 dark:bg-slate-950/40">
                    <div class="flex items-start gap-2">
                        <span class="{{ $meta['dot_class'] ?? 'tdg-dept-chip__dot tdg-dept-chip__dot--default' }} mt-1"></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Departamento</p>
                            <h4 class="mt-0.5 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $meta['label'] ?? $departmentValue }}</h4>
                        </div>
                    </div>

                    <div class="mt-4 rounded-xl border border-slate-200/80 bg-white/90 p-3 dark:border-white/10 dark:bg-slate-900/80">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                                Colaboradores de sistemas
                                @if ($selectedCount > 0)
                                <span class="ml-1 rounded-full bg-emerald-100 px-1.5 py-0.5 text-[10px] text-emerald-800 dark:bg-emerald-500/30 dark:text-emerald-100">{{ $selectedCount }}</span>
                                @endif
                            </p>
                            @if ($selectedCount > 0)
                            <button type="button" wire:click="clearDepartmentCollaborators('{{ $departmentValue }}')" class="rounded-lg px-2 py-1 text-[10px] font-semibold text-rose-600 transition hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-500/10">
                                Limpiar todos
                            </button>
                            @endif
                        </div>

                        @if ($selectedCount > 0)
                        <div class="mt-2 space-y-2">
                            @foreach ($selectedCollaborators as $selectedCollaborator)
                            <div wire:key="department-selected-{{ $departmentValue }}-{{ $selectedCollaborator['id'] }}" class="flex items-center gap-3 rounded-xl border border-emerald-400 bg-emerald-100 px-2 py-2 dark:border-emerald-400/60 dark:bg-emerald-600/25">
                                @include('filament.business.pages.partials.tdg-colaborador-avatar', ['colaborador' => $selectedCollaborator, 'size' => 'sm'])
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $selectedCollaborator['name'] }}</p>
                                    <p class="truncate text-[10px] text-slate-500 dark:text-slate-400">{{ $selectedCollaborator['email'] ?: 'Sin correo' }}</p>
                                </div>
                                <button type="button" wire:click="removeDepartmentCollaborator('{{ $departmentValue }}', {{ $selectedCollaborator['id'] }})" class="rounded-lg px-2 py-1 text-[10px] font-semibold text-rose-600 transition hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-500/10">
                                    Quitar
                                </button>
                            </div>
                            @endforeach
                        </div>
                        @else
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                            Sin asignar. Elige colaboradores del área de sistemas en la lista.
                        </p>
                        @endif
                    </div>

                    <p class="mt-3 text-[10px] text-slate-500 dark:text-slate-400">
                        Clic para agregar o quitar. Varios colaboradores pueden atender el mismo departamento.
                    </p>

                    <div class="mt-2 max-h-44 space-y-2 overflow-y-auto pr-1">
                        @forelse ($this->filteredCollaboratorOptionsForDepartment($departmentValue) as $collaborator)
                        @php
                        $isCollaboratorSelected = $this->isDepartmentCollaboratorSelected($departmentValue, (int) $collaborator['id']);
                        @endphp
                        <button type="button" wire:key="department-picker-{{ $departmentValue }}-{{ $collaborator['id'] }}" wire:click="assignDepartmentCollaborator('{{ $departmentValue }}', {{ $collaborator['id'] }})" class="flex w-full items-center gap-3 rounded-xl border px-3 py-2 text-left transition
                                        {{ $isCollaboratorSelected
                                            ? 'border-emerald-500 bg-emerald-100 shadow-[0_6px_16px_rgba(16,185,129,0.22)] dark:border-emerald-400/60 dark:bg-emerald-600/25'
                                            : 'border-slate-200/80 bg-white hover:border-emerald-300 hover:bg-emerald-50 dark:border-white/10 dark:bg-slate-900/70 dark:hover:border-emerald-400/40 dark:hover:bg-emerald-600/10' }}">
                            @include('filament.business.pages.partials.tdg-colaborador-avatar', ['colaborador' => $collaborator])
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-xs font-semibold text-slate-800 dark:text-slate-100">{{ $collaborator['name'] }}</span>
                                <span class="block truncate text-[11px] text-slate-500 dark:text-slate-400">{{ $collaborator['email'] ?: 'Sin correo' }}</span>
                            </span>
                        </button>
                        @empty
                        <p class="rounded-xl border border-dashed border-slate-200/80 px-3 py-4 text-center text-xs text-slate-500 dark:border-white/10 dark:text-slate-400">
                            No hay colaboradores activos en el departamento de sistemas.
                        </p>
                        @endforelse
                    </div>
                </div>
                @endforeach
            </div>

            @include('filament.business.pages.partials.tdg-department-replication-panel')
            @else
            <p class="mt-4 rounded-xl border border-dashed border-slate-200/80 px-4 py-6 text-center text-xs text-slate-500 dark:border-white/10 dark:text-slate-400">
                Selecciona al menos un departamento para asignar colaboradores de sistemas.
            </p>
            @endif
            </div>
        </div>

        <footer class="shrink-0 border-t border-slate-200/80 px-5 py-4 dark:border-white/10">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
                <button type="button" wire:click="closeDayModal" class="inline-flex items-center justify-center rounded-xl border border-slate-200/80 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                    Cancelar
                </button>
                <button type="button" wire:click="saveDayAssignments" wire:loading.attr="disabled" wire:target="saveDayAssignments" class="inline-flex items-center justify-center rounded-xl bg-[#0A4174] px-5 py-2 text-sm font-semibold text-[#BDD8E9] transition hover:bg-[#49769F]">
                    <x-filament::icon icon="heroicon-o-arrow-path" wire:loading wire:target="saveDayAssignments" class="mr-1 size-4 animate-spin" />
                    <span>Guardar día</span>
                </button>
            </div>
        </footer>
    </section>
</div>
