<div
    x-data="{ open: $wire.entangle('isDayModalOpen'), workspace: @js($modalWorkspace ?: 'offices') }"
    @tdg-modal-workspace-changed.window="workspace = $event.detail.workspace"
    x-show="open"
    x-cloak
    x-transition.opacity
    class="fixed inset-0 z-[80] flex items-center justify-center p-4 sm:p-6"
>
@if ($isDayModalOpen)
    @php
        $canEditTdgCalendar = $this->canEditCurrentTdgCalendar();
        $tdgCalendarWorkspaces = $this->tdgCalendarWorkspaces();
    @endphp
    <div class="absolute inset-0 bg-slate-950/55 backdrop-blur-[2px]" wire:click="closeDayModal"></div>

    <section class="relative z-[81] flex w-full max-w-6xl max-h-[92vh] flex-col overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-2xl dark:border-white/10 dark:bg-slate-900">
        <header class="shrink-0 border-b border-slate-200/80 px-5 py-4 dark:border-white/10">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">
                        {{ \Carbon\Carbon::parse($selectedDate)->translatedFormat('l d \\d\\e F Y') }}
                    </p>
                    <h3 class="mt-1 text-base font-semibold text-slate-900 dark:text-slate-100">
                        {{ $this->corporateCalendarHeading() }}
                    </h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        @if (count($tdgCalendarWorkspaces) === 1 && in_array('guards', $tdgCalendarWorkspaces, true))
                            2.1, 2.2 diurnas y guardia nocturna.
                        @elseif (count($tdgCalendarWorkspaces) === 1 && in_array('offices', $tdgCalendarWorkspaces, true))
                            Centro Lido, Farmadoc y Home Office.
                        @else
                            Oficinas y agenda de trabajo por departamentos.
                        @endif
                    </p>
                    @unless ($canEditTdgCalendar)
                        <p class="mt-3 rounded-xl border border-slate-200/80 bg-slate-50 px-3 py-2 text-xs text-slate-600 dark:border-white/10 dark:bg-slate-950/40 dark:text-slate-300">
                            {{ $this->tdgCalendarReadOnlyNotice() }}
                        </p>
                    @endunless
                </div>

                <button type="button" wire:click="closeDayModal" class="inline-flex items-center justify-center rounded-full p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white">
                    <x-filament::icon icon="heroicon-o-x-mark" class="size-5" />
                </button>
            </div>

            @if (count($tdgCalendarWorkspaces) > 1)
            <div class="mt-4 grid gap-2 sm:grid-cols-2" role="tablist" aria-label="Espacios de la agenda TDG">
                @foreach (array_filter([
                'offices' => ['icon' => 'heroicon-o-building-office-2', 'title' => 'Guardias oficinas', 'hint' => 'Centro Lido, Farmadoc y Home Office'],
                'guards' => ['icon' => 'heroicon-o-shield-check', 'title' => 'Guardias operaciones', 'hint' => '2.1, 2.2 diurnas y guardia nocturna'],
                'departments' => ['icon' => 'heroicon-o-squares-2x2', 'title' => 'Guardias sistema', 'hint' => 'Colaboradores de sistemas por área'],
                ], fn (mixed $meta, string $workspaceKey): bool => in_array($workspaceKey, $tdgCalendarWorkspaces, true), ARRAY_FILTER_USE_BOTH) as $workspaceKey => $workspaceMeta)
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
            @endif
        </header>

        <div class="min-h-0 flex-1 overflow-y-auto p-5">
            @if ($canEditTdgCalendar)
            <div class="relative mb-4" x-show="workspace === 'departments'" x-cloak>
                <x-filament::icon icon="heroicon-o-magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input type="search" wire:model.live.debounce.300ms="collaboratorSearch" placeholder="Buscar colaborador por nombre o correo..." class="w-full rounded-xl border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm dark:border-white/10 dark:bg-slate-800 dark:text-slate-100">
            </div>
            @endif

            @if (in_array('offices', $tdgCalendarWorkspaces, true))
            <div @if (count($tdgCalendarWorkspaces) > 1) x-show="workspace === 'offices'" x-cloak @endif>
            @if ($canEditTdgCalendar)
            <div wire:key="tdg-office-picker-{{ $selectedDate }}" x-data="tdgOfficePicker(@js($this->officePickerConfig()))" wire:loading.class="pointer-events-none opacity-60" wire:target="saveDayAssignments">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <template x-for="office in offices" :key="office.value">
                        <div :class="activeOffice === office.value
                            ? 'rounded-2xl border border-[#4E8EA2] bg-slate-50/70 p-4 ring-2 ring-[#7BBDE8] dark:border-[#7BBDE8]/70 dark:bg-slate-950/40'
                            : 'rounded-2xl border border-slate-200/80 bg-slate-50/70 p-4 dark:border-white/10 dark:bg-slate-950/40'">
                            <button type="button" class="w-full text-left" @click="activeOffice = office.value">
                                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400" x-text="office.home ? 'Desde casa' : 'Oficina'"></p>
                                <h4 class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100" x-text="office.label"></h4>
                                <p x-show="office.home" x-cloak class="mt-1 text-[11px] leading-snug text-slate-500 dark:text-slate-400">Quien queda aquí trabaja desde casa y no asiste a ninguna oficina ese día.</p>
                                <p x-show="activeOffice === office.value" x-cloak class="mt-2 text-[10px] font-semibold uppercase tracking-[0.08em] text-[#0A4174] dark:text-[#BDD8E9]">Asignando aquí</p>
                            </button>

                            <div class="mt-4 rounded-xl border border-slate-200/80 bg-white/90 p-3 dark:border-white/10 dark:bg-slate-900/80">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                                        Colaboradores asignados
                                        <span x-show="peopleFor(office.value).length > 0" x-cloak class="ml-1 rounded-full bg-emerald-100 px-1.5 py-0.5 text-[10px] text-emerald-800 dark:bg-emerald-500/30 dark:text-emerald-100" x-text="peopleFor(office.value).length"></span>
                                    </p>
                                    <button type="button" x-show="peopleFor(office.value).length > 0" x-cloak @click="clearOffice(office.value)" class="rounded-lg px-2 py-1 text-[10px] font-semibold text-rose-600 transition hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-500/10">
                                        Limpiar todos
                                    </button>
                                </div>

                                <div class="mt-2 space-y-2">
                                    <template x-for="person in peopleFor(office.value)" :key="office.value + '-' + person.id">
                                        <div class="flex items-center gap-3 rounded-xl border border-emerald-400 bg-emerald-100 px-2 py-2 dark:border-emerald-400/60 dark:bg-emerald-600/25">
                                            <template x-if="person.avatar_url">
                                                <img :src="person.avatar_url" :alt="person.name" loading="lazy" decoding="async" class="tdg-office-assigned-avatar size-8 shrink-0 rounded-full border border-slate-200/80 object-cover dark:border-white/15">
                                            </template>
                                            <template x-if="!person.avatar_url">
                                                <span class="size-8 inline-flex shrink-0 items-center justify-center rounded-full border border-slate-200/80 bg-slate-100 text-[10px] font-semibold text-slate-700 dark:border-white/15 dark:bg-slate-800 dark:text-slate-100" x-text="person.initials"></span>
                                            </template>
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-xs font-semibold text-slate-900 dark:text-slate-100" x-text="person.name"></p>
                                                <p class="truncate text-[10px] text-slate-500 dark:text-slate-400" x-text="person.email || 'Sin correo'"></p>
                                            </div>
                                            <button type="button" @click="removePerson(office.value, person.id)" class="rounded-lg px-2 py-1 text-[10px] font-semibold text-rose-600 transition hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-500/10">
                                                Quitar
                                            </button>
                                        </div>
                                    </template>
                                    <p x-show="peopleFor(office.value).length === 0" class="text-xs text-slate-500 dark:text-slate-400" x-text="office.home ? 'Nadie en casa. Haz clic en quienes no irán a ninguna oficina.' : 'Sin asignar. Haz clic en uno o varios colaboradores de la lista.'"></p>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="mt-4 rounded-2xl border border-slate-200/80 bg-white p-4 dark:border-white/10 dark:bg-slate-950/40">
                    <p class="text-xs font-semibold text-slate-700 dark:text-slate-200">
                        Lista para <span x-text="activeOfficeLabel()"></span>
                    </p>
                    <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400" x-text="pickerHint()"></p>
                    <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Elige la oficina en la tarjeta y marca a las personas. Se guarda al pulsar Guardar día.</p>

                    <div class="relative mt-3">
                        <x-filament::icon icon="heroicon-o-magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                        <input type="search" x-model.debounce.150ms="search" autocomplete="off" placeholder="Buscar colaborador por nombre o correo..." class="w-full rounded-xl border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm dark:border-white/10 dark:bg-slate-800 dark:text-slate-100">
                    </div>

                    <div class="mt-3 grid max-h-72 gap-2 overflow-y-auto pr-1 sm:grid-cols-2">
                        <template x-for="person in visibleCollaborators()" :key="'picker-' + person.id">
                            <button type="button" @click="assign(person.id)" class="flex w-full items-center gap-3 rounded-xl border border-slate-200/80 bg-white px-3 py-2 text-left transition hover:border-emerald-300 hover:bg-emerald-50 dark:border-white/10 dark:bg-slate-900/70 dark:hover:border-emerald-400/40 dark:hover:bg-emerald-600/10">
                                <span class="tdg-office-picker-initials size-10 inline-flex shrink-0 items-center justify-center rounded-full border border-slate-200/80 bg-slate-100 text-[11px] font-semibold text-slate-700 dark:border-white/15 dark:bg-slate-800 dark:text-slate-100" x-text="person.initials"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-xs font-semibold text-slate-800 dark:text-slate-100" x-text="person.name"></span>
                                    <span class="block truncate text-[11px] text-slate-500 dark:text-slate-400" x-text="person.email || 'Sin correo'"></span>
                                </span>
                            </button>
                        </template>
                    </div>
                    <p x-show="visibleCollaborators().length === 0" x-cloak class="mt-3 rounded-xl border border-dashed border-slate-300/80 px-3 py-4 text-center text-xs text-slate-500 dark:border-white/15 dark:text-slate-400" x-text="emptyPickerMessage()"></p>
                </div>
            </div>
            @else
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($this->officeOptions as $officeValue => $officeLabel)
                @php
                $selectedCollaborators = $this->resolveSelectedOfficeCollaborators($officeValue);
                $selectedCount = count($selectedCollaborators);
                $isHomeOffice = $officeValue === \App\Enums\TdgCalendarOffice::HomeOffice->value;
                @endphp
                <div wire:key="tdg-office-card-{{ $officeValue }}" class="rounded-2xl border border-slate-200/80 bg-slate-50/70 p-4 dark:border-white/10 dark:bg-slate-950/40">
                    <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">{{ $isHomeOffice ? 'Desde casa' : 'Oficina' }}</p>
                    <h4 class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $officeLabel }}</h4>
                    @if ($isHomeOffice)
                    <p class="mt-1 text-[11px] leading-snug text-slate-500 dark:text-slate-400">Quien queda aquí trabaja desde casa y no asiste a ninguna oficina ese día.</p>
                    @endif

                    <div class="mt-4 rounded-xl border border-slate-200/80 bg-white/90 p-3 dark:border-white/10 dark:bg-slate-900/80">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                                Colaboradores asignados
                                @if ($selectedCount > 0)
                                <span class="ml-1 rounded-full bg-emerald-100 px-1.5 py-0.5 text-[10px] text-emerald-800 dark:bg-emerald-500/30 dark:text-emerald-100">{{ $selectedCount }}</span>
                                @endif
                            </p>
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
                            </div>
                            @endforeach
                        </div>
                        @else
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                            {{ $isHomeOffice ? 'Nadie marcado para trabajar desde casa.' : 'Sin asignar.' }}
                        </p>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
            @endif

            @if ($canEditTdgCalendar)
            @include('filament.business.pages.partials.tdg-office-replication-panel')
            @endif
            </div>
            @endif

            @if (in_array('guards', $tdgCalendarWorkspaces, true))
            <div @if (count($tdgCalendarWorkspaces) > 1) x-show="workspace === 'guards'" x-cloak @endif>
            @if ($canEditTdgCalendar)
            <div wire:key="tdg-guard-picker-{{ $selectedDate }}" x-data="tdgGuardPicker(@js($this->guardPickerConfig()))" wire:loading.class="pointer-events-none opacity-60" wire:target="saveDayAssignments">
                <div class="mb-4 rounded-2xl border border-amber-200/80 bg-amber-50/80 px-4 py-3 dark:border-amber-400/30 dark:bg-amber-500/10">
                    <label class="inline-flex items-center gap-2 text-sm font-semibold text-amber-900 dark:text-amber-100">
                        <input type="checkbox" class="rounded border-amber-400 text-amber-600 focus:ring-amber-400/50" :checked="sameCollaborator" @change="commitSame($event.target.checked)">
                        Usar el mismo colaborador para guardias 2.1 y 2.2 (horario diurno)
                    </label>
                    <p class="mt-1 text-xs text-amber-800/90 dark:text-amber-100/80">
                        La guardia nocturna se asigna de forma independiente. Elige el horario y marca a la persona. Se guarda al pulsar Guardar día.
                    </p>
                </div>

                <div class="grid gap-4 lg:grid-cols-3">
                    <template x-for="shift in shifts" :key="shift.value">
                        <div :class="cardClass(shift)">
                            <button type="button" class="w-full text-left" @click="selectShift(shift.value)">
                                <p class="text-xs font-semibold uppercase tracking-[0.12em]" :class="shift.nocturnal ? 'text-indigo-600 dark:text-indigo-300' : 'text-violet-600 dark:text-violet-300'" x-text="shift.nocturnal ? 'Guardia nocturna' : 'Guardia operaciones'"></p>
                                <h4 class="mt-1 text-sm font-semibold leading-snug text-slate-900 dark:text-slate-100" x-text="shift.label"></h4>
                                <p x-show="activeShift === shift.value && !isLocked(shift.value)" x-cloak class="mt-2 text-[10px] font-semibold uppercase tracking-[0.08em] text-[#0A4174] dark:text-[#BDD8E9]">Asignando aquí</p>
                                <p x-show="isLocked(shift.value)" x-cloak class="mt-2 text-[10px] text-amber-800 dark:text-amber-100">Sigue a la guardia 2.1.</p>
                            </button>
                            <div class="mt-4 rounded-xl border border-slate-200/80 bg-white/90 p-3 dark:border-white/10 dark:bg-slate-900/80">
                                <p class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">Colaborador en guardia</p>
                                <template x-if="personFor(shift.value)">
                                    <div class="mt-2 flex items-center gap-3">
                                        <template x-if="personFor(shift.value).avatar_url">
                                            <img :src="personFor(shift.value).avatar_url" :alt="personFor(shift.value).name" loading="lazy" decoding="async" class="tdg-guard-assigned-avatar size-12 shrink-0 rounded-full border border-slate-200/80 object-cover dark:border-white/15">
                                        </template>
                                        <template x-if="!personFor(shift.value).avatar_url">
                                            <span class="size-12 inline-flex shrink-0 items-center justify-center rounded-full border border-slate-200/80 bg-slate-100 text-sm font-semibold text-slate-700 dark:border-white/15 dark:bg-slate-800 dark:text-slate-100" x-text="personFor(shift.value).initials"></span>
                                        </template>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100" x-text="personFor(shift.value).name"></p>
                                            <p class="truncate text-[11px] text-slate-500 dark:text-slate-400" x-text="personFor(shift.value).email || 'Sin correo'"></p>
                                        </div>
                                        <button type="button" x-show="!isLocked(shift.value)" x-cloak @click="clearShift(shift.value)" class="rounded-lg px-2 py-1 text-[11px] font-semibold text-rose-600 transition hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-500/10">
                                            Quitar
                                        </button>
                                    </div>
                                </template>
                                <p x-show="!personFor(shift.value)" class="mt-2 text-xs text-slate-500 dark:text-slate-400">Sin asignar.</p>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="mt-4 rounded-2xl border border-slate-200/80 bg-white p-4 dark:border-white/10 dark:bg-slate-950/40" x-show="!isLocked(activeShift)">
                    <p class="text-xs font-semibold text-slate-700 dark:text-slate-200">
                        Lista para <span x-text="activeShiftLabel()"></span>
                    </p>
                    <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400" x-text="pickerHint()"></p>
                    <div class="relative mt-3">
                        <x-filament::icon icon="heroicon-o-magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                        <input type="search" x-model.debounce.150ms="search" autocomplete="off" placeholder="Buscar colaborador por nombre o correo..." class="w-full rounded-xl border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm dark:border-white/10 dark:bg-slate-800 dark:text-slate-100">
                    </div>
                    <div class="mt-3 grid max-h-72 gap-2 overflow-y-auto pr-1 sm:grid-cols-2">
                        <template x-for="person in visibleCollaborators()" :key="'guard-picker-' + person.id">
                            <button type="button" @click="assign(person.id)" class="flex w-full items-center gap-3 rounded-xl border border-slate-200/80 bg-white px-3 py-2 text-left transition hover:border-violet-300 hover:bg-violet-50/60 dark:border-white/10 dark:bg-slate-900/70 dark:hover:border-violet-400/40">
                                <span class="tdg-guard-picker-initials size-10 inline-flex shrink-0 items-center justify-center rounded-full border border-slate-200/80 bg-slate-100 text-[11px] font-semibold text-slate-700 dark:border-white/15 dark:bg-slate-800 dark:text-slate-100" x-text="person.initials"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-xs font-semibold text-slate-800 dark:text-slate-100" x-text="person.name"></span>
                                    <span class="block truncate text-[11px] text-slate-500 dark:text-slate-400" x-text="person.email || 'Sin correo'"></span>
                                </span>
                            </button>
                        </template>
                    </div>
                    <p x-show="visibleCollaborators().length === 0" x-cloak class="mt-3 rounded-xl border border-dashed border-slate-300/80 px-3 py-4 text-center text-xs text-slate-500 dark:border-white/15 dark:text-slate-400" x-text="emptyPickerMessage()"></p>
                </div>
            </div>
            @else
            <div class="grid gap-4 lg:grid-cols-3">
                @foreach ($this->guardShiftOptions as $shiftValue => $shiftLabel)
                @php
                $guardShift = \App\Enums\TdgCalendarGuardShift::tryFrom($shiftValue);
                $selectedId = $guardAssignmentsForm[$shiftValue] ?? null;
                $selectedCollaborator = $this->resolveSelectedCollaborator(is_numeric($selectedId) ? (int) $selectedId : null);
                $isNocturnal = $guardShift?->isNocturnalShift() ?? false;
                @endphp
                <div wire:key="tdg-guard-card-{{ $shiftValue }}" class="rounded-2xl border border-slate-200/80 bg-slate-50/70 p-4 dark:border-white/10 dark:bg-slate-950/40 {{ $isNocturnal ? 'ring-1 ring-indigo-200/80 dark:ring-indigo-500/30' : '' }}">
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
                        </div>
                        @else
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Sin asignar.</p>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
            @endif

            @if ($canEditTdgCalendar)
            @include('filament.business.pages.partials.tdg-guard-replication-panel')
            @endif
            </div>
            @endif

            @if (in_array('departments', $tdgCalendarWorkspaces, true))
            <div @if (count($tdgCalendarWorkspaces) > 1) x-show="workspace === 'departments'" x-cloak @endif>
            @if ($canEditTdgCalendar)
            <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">
                Selecciona los departamentos del día y asigna uno o más colaboradores del área de <span class="font-semibold text-slate-700 dark:text-slate-200">sistemas</span> que los atenderán.
            </p>
            @endif

            <div class="flex flex-wrap gap-2">
                @foreach ($this->departmentOptions as $departmentValue => $departmentLabel)
                @php
                $meta = $this->departmentCatalog[$departmentValue] ?? [];
                $isSelected = in_array($departmentValue, $departmentAssignmentsForm, true);
                @endphp
                @if ($canEditTdgCalendar)
                <button type="button" wire:click="toggleDepartment('{{ $departmentValue }}')" class="{{ $isSelected ? ($meta['chip_class'] ?? 'tdg-dept-chip tdg-dept-chip--default is-selected') : ($meta['idle_chip_class'] ?? 'tdg-dept-chip tdg-dept-chip--default is-idle') }}">
                    <span class="{{ $meta['dot_class'] ?? 'tdg-dept-chip__dot tdg-dept-chip__dot--default' }}"></span>
                    <span>{{ $departmentLabel }}</span>
                </button>
                @elseif ($isSelected)
                <span class="{{ $meta['chip_class'] ?? 'tdg-dept-chip tdg-dept-chip--default is-selected' }}">
                    <span class="{{ $meta['dot_class'] ?? 'tdg-dept-chip__dot tdg-dept-chip__dot--default' }}"></span>
                    <span>{{ $departmentLabel }}</span>
                </span>
                @endif
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
                            @if ($canEditTdgCalendar && $selectedCount > 0)
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
                                @if ($canEditTdgCalendar)
                                <button type="button" wire:click="removeDepartmentCollaborator('{{ $departmentValue }}', {{ $selectedCollaborator['id'] }})" class="rounded-lg px-2 py-1 text-[10px] font-semibold text-rose-600 transition hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-500/10">
                                    Quitar
                                </button>
                                @endif
                            </div>
                            @endforeach
                        </div>
                        @else
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                            @if ($canEditTdgCalendar)
                            Sin asignar. Elige colaboradores del área de sistemas en la lista.
                            @else
                            Sin asignar.
                            @endif
                        </p>
                        @endif
                    </div>

                    @if ($canEditTdgCalendar)
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
                    @endif
                </div>
                @endforeach
            </div>

            @if ($canEditTdgCalendar)
            @include('filament.business.pages.partials.tdg-department-replication-panel')
            @endif
            @else
            <p class="mt-4 rounded-xl border border-dashed border-slate-200/80 px-4 py-6 text-center text-xs text-slate-500 dark:border-white/10 dark:text-slate-400">
                @if ($canEditTdgCalendar)
                Selecciona al menos un departamento para asignar colaboradores de sistemas.
                @else
                Sin departamentos asignados.
                @endif
            </p>
            @endif
            </div>
            @endif
        </div>

        <footer class="shrink-0 border-t border-slate-200/80 px-5 py-4 dark:border-white/10">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
                <button type="button" wire:click="closeDayModal" class="inline-flex items-center justify-center rounded-xl border border-slate-200/80 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                    {{ $canEditTdgCalendar ? 'Cancelar' : 'Cerrar' }}
                </button>
                @if ($canEditTdgCalendar)
                <button type="button" wire:click="saveDayAssignments" wire:loading.attr="disabled" wire:target="saveDayAssignments" class="inline-flex items-center justify-center rounded-xl bg-[#0A4174] px-5 py-2 text-sm font-semibold text-[#BDD8E9] transition hover:bg-[#49769F]">
                    <x-filament::icon icon="heroicon-o-arrow-path" wire:loading wire:target="saveDayAssignments" class="mr-1 size-4 animate-spin" />
                    <span>Guardar día</span>
                </button>
                @endif
            </div>
        </footer>
    </section>
@endif
</div>

@script
<script>
    Alpine.data('tdgOfficePicker', (config) => {
        const collaboratorsById = {};

        for (const person of config.collaborators || []) {
            collaboratorsById[person.id] = person;
        }

        return {
            offices: config.offices || [],
            collaborators: config.collaborators || [],
            collaboratorsById,
            activeOffice: config.activeOffice || (config.offices?.[0]?.value ?? ''),
            search: '',
            assignments: config.assignments || {},

            idsFor(office) {
                const value = this.assignments ? this.assignments[office] : [];

                if (!Array.isArray(value)) {
                    return [];
                }

                return value.map((id) => Number(id)).filter((id) => id > 0);
            },

            cloneAssignments() {
                const next = {};

                for (const office of this.offices) {
                    next[office.value] = this.idsFor(office.value);
                }

                return next;
            },

            commit(next) {
                this.$wire.set('officeAssignmentsForm', JSON.parse(JSON.stringify(next)), false);
                this.assignments = next;
            },

            assign(id) {
                id = Number(id);

                if (!id || this.activeOffice === '') {
                    return;
                }

                const next = this.cloneAssignments();

                for (const office of this.offices) {
                    next[office.value] = next[office.value].filter((current) => current !== id);
                }

                next[this.activeOffice].push(id);
                this.commit(next);
            },

            removePerson(office, id) {
                id = Number(id);
                const next = this.cloneAssignments();
                next[office] = (next[office] || []).filter((current) => current !== id);
                this.commit(next);
            },

            clearOffice(office) {
                const next = this.cloneAssignments();
                next[office] = [];
                this.commit(next);
            },

            peopleFor(office) {
                return this.idsFor(office)
                    .map((id) => this.collaboratorsById[id])
                    .filter((person) => Boolean(person));
            },

            activeOfficeLabel() {
                const office = this.offices.find((item) => item.value === this.activeOffice);

                return office ? office.label : '';
            },

            pickerHint() {
                const office = this.offices.find((item) => item.value === this.activeOffice);

                if (office && office.home) {
                    return 'Clic para marcar quién trabaja desde casa. Si ya está en Centro Lido o Farmadoc hoy, no aparece aquí.';
                }

                return 'Clic para agregar. Si ya está en otra oficina o en Home Office hoy, no aparece aquí.';
            },

            visibleCollaborators() {
                const excluded = new Set();

                for (const office of this.offices) {
                    for (const id of this.idsFor(office.value)) {
                        excluded.add(id);
                    }
                }

                const term = this.normalize(this.search);
                const limit = term === '' ? 30 : 40;
                const matches = [];

                for (const person of this.collaborators) {
                    if (excluded.has(person.id)) {
                        continue;
                    }

                    if (term !== '' && !this.normalize(person.name).includes(term) && !this.normalize(person.email).includes(term)) {
                        continue;
                    }

                    matches.push(person);

                    if (matches.length >= limit) {
                        break;
                    }
                }

                return matches;
            },

            emptyPickerMessage() {
                if (this.normalize(this.search) !== '') {
                    return 'No hay colaboradores disponibles para esta búsqueda.';
                }

                return 'Todos los colaboradores visibles ya están asignados en otras oficinas o en esta sede.';
            },

            normalize(value) {
                return (value || '')
                    .toString()
                    .trim()
                    .toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '');
            },
        };
    });

    Alpine.data('tdgGuardPicker', (config) => {
        const collaboratorsById = {};

        for (const person of config.collaborators || []) {
            collaboratorsById[person.id] = person;
        }

        return {
            shifts: config.shifts || [],
            collaborators: config.collaborators || [],
            collaboratorsById,
            activeShift: config.activeShift || 'proveedores',
            search: '',
            sameCollaborator: Boolean(config.sameCollaborator),
            assignments: config.assignments || {},
            proveedores: 'proveedores',
            ils: 'ils_capitado',

            idFor(shift) {
                const value = Number(this.assignments ? this.assignments[shift] : 0);

                return value > 0 ? value : 0;
            },

            personFor(shift) {
                const id = this.idFor(shift);

                return id ? (this.collaboratorsById[id] || null) : null;
            },

            isDaytime(shift) {
                const item = this.shifts.find((entry) => entry.value === shift);

                return Boolean(item && item.daytime);
            },

            isLocked(shift) {
                return this.sameCollaborator && shift === this.ils;
            },

            selectShift(shift) {
                if (this.isLocked(shift)) {
                    this.activeShift = this.proveedores;

                    return;
                }

                this.activeShift = shift;
            },

            cloneAssignments() {
                const next = {};

                for (const shift of this.shifts) {
                    const id = this.idFor(shift.value);
                    next[shift.value] = id > 0 ? id : null;
                }

                return next;
            },

            commit(next) {
                this.$wire.set('guardAssignmentsForm', JSON.parse(JSON.stringify(next)), false);
                this.assignments = next;
            },

            commitSame(value) {
                this.sameCollaborator = value;
                this.$wire.set('useSameGuardCollaborator', value, false);

                if (!value) {
                    return;
                }

                const next = this.cloneAssignments();
                next[this.ils] = next[this.proveedores];
                this.commit(next);

                if (this.activeShift === this.ils) {
                    this.activeShift = this.proveedores;
                }
            },

            assign(id) {
                id = Number(id);

                if (!id || this.isLocked(this.activeShift)) {
                    return;
                }

                const next = this.cloneAssignments();

                for (const shift of this.shifts) {
                    if (shift.value === this.activeShift) {
                        continue;
                    }

                    if (this.sameCollaborator && this.isDaytime(this.activeShift) && shift.daytime) {
                        continue;
                    }

                    if (next[shift.value] === id) {
                        next[shift.value] = null;
                    }
                }

                next[this.activeShift] = id;

                if (this.sameCollaborator && this.activeShift === this.proveedores) {
                    next[this.ils] = id;
                }

                this.commit(next);
            },

            clearShift(shift) {
                if (this.isLocked(shift)) {
                    return;
                }

                const next = this.cloneAssignments();
                next[shift] = null;

                if (this.sameCollaborator && shift === this.proveedores) {
                    next[this.ils] = null;
                }

                this.commit(next);
            },

            cardClass(shift) {
                const active = this.activeShift === shift.value && !this.isLocked(shift.value);
                const base = 'rounded-2xl border bg-slate-50/70 p-4 dark:bg-slate-950/40';

                if (this.isLocked(shift.value)) {
                    return base + ' border-slate-200/80 opacity-90 dark:border-white/10';
                }

                if (active && shift.nocturnal) {
                    return base + ' border-indigo-400 ring-2 ring-indigo-300 dark:border-indigo-400/70';
                }

                if (active) {
                    return base + ' border-violet-400 ring-2 ring-violet-300 dark:border-violet-400/70';
                }

                if (shift.nocturnal) {
                    return base + ' border-slate-200/80 ring-1 ring-indigo-200/80 dark:border-white/10 dark:ring-indigo-500/30';
                }

                return base + ' border-slate-200/80 dark:border-white/10';
            },

            activeShiftLabel() {
                const shift = this.shifts.find((item) => item.value === this.activeShift);

                return shift ? shift.label : '';
            },

            pickerHint() {
                if (this.sameCollaborator && this.activeShift === this.proveedores) {
                    return 'Clic para asignar la guardia 2.1. La 2.2 queda con la misma persona. Si ya está en la guardia nocturna, sale de ahí.';
                }

                if (this.isDaytime(this.activeShift)) {
                    return 'Clic para asignar este horario. Si ya está en otra guardia hoy, no aparece aquí.';
                }

                return 'Clic para asignar la guardia nocturna. Si ya está en un horario diurno, sale de ahí.';
            },

            visibleCollaborators() {
                const excluded = new Set();
                const selected = this.idFor(this.activeShift);

                if (selected) {
                    excluded.add(selected);
                }

                for (const shift of this.shifts) {
                    if (shift.value === this.activeShift) {
                        continue;
                    }

                    if (this.sameCollaborator && this.isDaytime(this.activeShift) && shift.daytime) {
                        continue;
                    }

                    const id = this.idFor(shift.value);

                    if (id) {
                        excluded.add(id);
                    }
                }

                const term = this.normalize(this.search);
                const limit = term === '' ? 30 : 40;
                const matches = [];

                for (const person of this.collaborators) {
                    if (excluded.has(person.id)) {
                        continue;
                    }

                    if (term !== '' && !this.normalize(person.name).includes(term) && !this.normalize(person.email).includes(term)) {
                        continue;
                    }

                    matches.push(person);

                    if (matches.length >= limit) {
                        break;
                    }
                }

                return matches;
            },

            emptyPickerMessage() {
                if (this.normalize(this.search) !== '') {
                    return 'No hay colaboradores para esta búsqueda.';
                }

                return 'No hay más colaboradores disponibles para este horario.';
            },

            normalize(value) {
                return (value || '')
                    .toString()
                    .trim()
                    .toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '');
            },
        };
    });
</script>
@endscript
