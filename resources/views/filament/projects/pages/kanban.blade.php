<x-filament-panels::page>
    @php
        $kanbanSelectClass = 'kanban-filter-select peer w-full appearance-none rounded-2xl border border-gray-200 bg-white py-2.5 pl-3.5 pr-10 text-sm font-medium text-gray-950 shadow-sm transition [color-scheme:light] hover:border-primary-300 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-white/12 dark:bg-gray-900 dark:text-white dark:shadow-[inset_0_1px_0_rgba(255,255,255,0.06)] dark:[color-scheme:dark] dark:hover:border-indigo-400/35 dark:focus:border-indigo-400/70 dark:focus:ring-indigo-500/30';
        $kanbanInputClass = 'w-full rounded-2xl border border-gray-200 bg-white py-2.5 pl-10 pr-3.5 text-sm font-medium text-gray-950 shadow-sm transition placeholder:text-gray-400 hover:border-primary-300 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-white/12 dark:bg-gray-900 dark:text-white dark:shadow-[inset_0_1px_0_rgba(255,255,255,0.06)] dark:placeholder:text-slate-500 dark:hover:border-indigo-400/35 dark:focus:border-indigo-400/70 dark:focus:ring-indigo-500/30';
    @endphp

    <div class="space-y-6">
        <section class="rounded-3xl border border-gray-200 bg-white p-4 shadow-sm ring-1 ring-gray-950/5 md:p-6 dark:border-white/10 dark:bg-gray-950 dark:shadow-2xl dark:shadow-black/40 dark:ring-white/10">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-gray-500 dark:text-slate-400">Tasks</p>
                    <h2 class="text-3xl font-semibold text-gray-950 dark:text-white">Kanban</h2>
                    <div class="flex flex-wrap items-center gap-2 text-xs uppercase tracking-wide">
                        <button
                            type="button"
                            wire:click="setViewMode('list')"
                            @class([
                                'rounded-full border px-3 py-1 transition duration-200',
                                'border-primary-200 bg-primary-50 font-medium text-primary-700 dark:border-indigo-500/40 dark:bg-indigo-500/20 dark:text-indigo-200' => $viewMode === 'list',
                                'border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300 hover:bg-gray-100 dark:border-white/10 dark:bg-white/5 dark:text-slate-400 dark:hover:border-white/20 dark:hover:bg-white/10' => $viewMode !== 'list',
                            ])
                        >
                            Listas
                        </button>
                        <button
                            type="button"
                            wire:click="setViewMode('board')"
                            @class([
                                'rounded-full border px-3 py-1 transition duration-200',
                                'border-primary-200 bg-primary-50 font-medium text-primary-700 dark:border-indigo-500/40 dark:bg-indigo-500/20 dark:text-indigo-200' => $viewMode === 'board',
                                'border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300 hover:bg-gray-100 dark:border-white/10 dark:bg-white/5 dark:text-slate-400 dark:hover:border-white/20 dark:hover:bg-white/10' => $viewMode !== 'board',
                            ])
                        >
                            Tablero
                        </button>
                        <button
                            type="button"
                            wire:click="setViewMode('timeline')"
                            @class([
                                'rounded-full border px-3 py-1 transition duration-200',
                                'border-primary-200 bg-primary-50 font-medium text-primary-700 dark:border-indigo-500/40 dark:bg-indigo-500/20 dark:text-indigo-200' => $viewMode === 'timeline',
                                'border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300 hover:bg-gray-100 dark:border-white/10 dark:bg-white/5 dark:text-slate-400 dark:hover:border-white/20 dark:hover:bg-white/10' => $viewMode !== 'timeline',
                            ])
                        >
                            Cronograma
                        </button>
                        <button
                            type="button"
                            wire:click="setViewMode('files')"
                            @class([
                                'rounded-full border px-3 py-1 transition duration-200',
                                'border-primary-200 bg-primary-50 font-medium text-primary-700 dark:border-indigo-500/40 dark:bg-indigo-500/20 dark:text-indigo-200' => $viewMode === 'files',
                                'border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300 hover:bg-gray-100 dark:border-white/10 dark:bg-white/5 dark:text-slate-400 dark:hover:border-white/20 dark:hover:bg-white/10' => $viewMode !== 'files',
                            ])
                        >
                            Archivos
                        </button>
                    </div>
                </div>

                <a
                    href="{{ $this->createActivityUrl }}"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-primary-200 bg-primary-50 px-4 py-2 text-sm font-semibold text-primary-700 transition hover:bg-primary-100 dark:border-indigo-400/35 dark:bg-indigo-500/20 dark:text-indigo-100 dark:hover:bg-indigo-500/30"
                >
                    <x-heroicon-m-plus class="h-4 w-4" />
                    Nueva actividad
                </a>
            </div>

            <div class="mt-6 rounded-2xl border border-gray-100 bg-gray-50/90 p-3 md:p-4 dark:border-white/8 dark:bg-white/[0.02]">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-12 xl:items-end">
                    <x-projects.kanban-filter-field label="Buscar" col-span="xl:col-span-3">
                        <x-heroicon-m-magnifying-glass class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-gray-400 dark:text-slate-500" />
                        <input
                            type="search"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Buscar por título, proyecto o descripción..."
                            class="{{ $kanbanInputClass }}"
                        />
                    </x-projects.kanban-filter-field>

                    <x-projects.kanban-filter-field label="Estatus" col-span="xl:col-span-2" :select="true">
                        <select wire:model.live="statusFilter" class="{{ $kanbanSelectClass }}">
                            <option value="all">Todos</option>
                            @foreach ($this->statusOptions as $statusKey => $statusLabel)
                                <option value="{{ $statusKey }}">{{ $statusLabel }}</option>
                            @endforeach
                        </select>
                    </x-projects.kanban-filter-field>

                    <x-projects.kanban-filter-field label="Visibilidad" col-span="xl:col-span-2" :select="true">
                        <select wire:model.live="archivedFilter" class="{{ $kanbanSelectClass }}">
                            @foreach ($this->archivedFilterOptions as $archivedKey => $archivedLabel)
                                <option value="{{ $archivedKey }}">{{ $archivedLabel }}</option>
                            @endforeach
                        </select>
                    </x-projects.kanban-filter-field>

                    <x-projects.kanban-filter-field label="Proyecto" col-span="xl:col-span-2" :select="true">
                        <select wire:model.live="projectFilter" class="{{ $kanbanSelectClass }}">
                            <option value="all">Todos los proyectos</option>
                            @forelse ($this->projectOptions as $projectId => $projectName)
                                <option value="{{ $projectId }}">{{ $projectName }}</option>
                            @empty
                                <option value="" disabled>No hay proyectos creados</option>
                            @endforelse
                        </select>
                    </x-projects.kanban-filter-field>

                    <x-projects.kanban-filter-field label="Ordenar" col-span="xl:col-span-2" :select="true">
                        <select wire:model.live="sortBy" class="{{ $kanbanSelectClass }}">
                            @foreach ($this->sortOptions as $sortKey => $sortLabel)
                                <option value="{{ $sortKey }}">{{ $sortLabel }}</option>
                            @endforeach
                        </select>
                    </x-projects.kanban-filter-field>

                    <div class="flex xl:col-span-1 xl:justify-end">
                        <button
                            type="button"
                            wire:click="resetFilters"
                            @class([
                                'inline-flex h-[42px] w-full items-center justify-center gap-1.5 rounded-2xl border px-3 text-xs font-semibold transition duration-200 xl:w-auto',
                                'border-primary-200 bg-primary-50 text-primary-700 hover:bg-primary-100 dark:border-indigo-400/40 dark:bg-indigo-500/20 dark:text-indigo-100 dark:hover:bg-indigo-500/30' => $this->hasActiveFilters,
                                'border-gray-200 bg-white text-gray-600 hover:border-gray-300 hover:bg-gray-50 dark:border-white/12 dark:bg-white/[0.04] dark:text-slate-300 dark:hover:border-white/20 dark:hover:bg-white/[0.08]' => ! $this->hasActiveFilters,
                            ])
                        >
                            <x-heroicon-m-arrow-path class="size-3.5" />
                            Limpiar
                        </button>
                    </div>
                </div>
            </div>
        </section>

        @if ($viewMode === 'board')
        <section class="kanban-board grid gap-4 xl:grid-cols-4">
            @foreach ($this->statusOptions as $statusKey => $statusLabel)
                @php
                    $columnActivities = $this->groupedActivities[$statusKey] ?? collect();
                    $tone = $this->columnTone($statusKey);
                @endphp

                <article
                    class="kanban-column relative rounded-2xl border border-gray-200 bg-gray-50/90 p-3 shadow-sm dark:border-white/10 dark:bg-[#11131A] dark:shadow-xl dark:shadow-black/30"
                    data-kanban-status="{{ $statusKey }}"
                >
                    <header class="mb-3 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full {{ $tone['bar'] }}"></span>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $statusLabel }}</h3>
                        </div>
                        <span
                            class="kanban-column-count rounded-full border px-2.5 py-1 text-xs {{ $tone['badge'] }}"
                            data-kanban-count
                        >
                            {{ $columnActivities->count() }} {{ $columnActivities->count() === 1 ? 'actividad' : 'actividades' }}
                        </span>
                    </header>

                    <div
                        class="kanban-column-list min-h-[8rem] space-y-3 overflow-y-auto overflow-x-hidden pr-1"
                        data-kanban-column
                        data-status="{{ $statusKey }}"
                    >
                        @forelse ($columnActivities as $activity)
                            @php
                                $activityColor = \App\Support\Filament\ProjectManagement\ProjectManagementActivityTable::resolveColor($activity);
                            @endphp
                            <div
                                class="kanban-activity-card group relative overflow-hidden rounded-2xl border p-4 pl-5 shadow-sm dark:shadow-none"
                                style="--activity-color: {{ $activityColor }};"
                                data-activity-id="{{ $activity->id }}"
                            >
                                <div
                                    class="kanban-drag-grip pointer-events-none absolute inset-x-0 top-0 flex justify-center pt-1.5 opacity-0 transition-opacity duration-150 group-hover:opacity-100"
                                    aria-hidden="true"
                                >
                                    <span class="h-1 w-8 rounded-full bg-gray-300/90 dark:bg-white/25"></span>
                                </div>
                                <div
                                    class="absolute inset-y-0 left-0 w-1.5"
                                    style="background: linear-gradient(180deg, {{ $activityColor }}, {{ $activityColor }}99);"
                                ></div>

                                <div class="kanban-card-surface">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex flex-wrap items-center gap-1">
                                        <span
                                            @class([
                                                'kanban-priority-badge inline-flex shrink-0 items-center rounded border px-1 py-px text-[9px] font-extrabold uppercase leading-none tracking-[0.14em] shadow-sm ring-1 ring-inset',
                                                'kanban-priority-badge--' . $activity->priority,
                                            ])
                                            style="--activity-color: {{ $activityColor }};"
                                        >
                                            {{ match ($activity->priority) {
                                                'high' => 'Alta',
                                                'medium' => 'Media',
                                                'low' => 'Baja',
                                                default => 'N/A',
                                            } }}
                                        </span>
                                        @if ($activity->isArchivedFromKanban())
                                            <span class="inline-flex shrink-0 items-center gap-0.5 rounded border border-gray-200/90 bg-gray-100/90 px-1 py-px text-[9px] font-bold uppercase leading-none tracking-wide text-gray-600 dark:border-white/15 dark:bg-white/10 dark:text-slate-300">
                                                <x-heroicon-m-archive-box class="size-2.5" />
                                                Archivada
                                            </span>
                                        @endif
                                    </div>
                                    <x-heroicon-m-ellipsis-horizontal class="h-3.5 w-3.5 text-gray-400 dark:text-slate-500" />
                                </div>

                                <div class="mt-3">
                                    <x-projects.kanban-project-affiliation
                                        :project="$activity->project"
                                        :subproject="$activity->subproject"
                                    />
                                </div>

                                <div class="mt-2 flex items-start gap-2">
                                    <span
                                        class="mt-1 size-2 shrink-0 rounded-full ring-2 ring-gray-200 dark:ring-white/20"
                                        style="background: {{ $activityColor }}; box-shadow: 0 0 10px color-mix(in srgb, {{ $activityColor }} 65%, transparent);"
                                    ></span>
                                    <h4 class="line-clamp-2 text-sm font-semibold leading-5 text-gray-950 group-hover:text-primary-700 dark:text-white dark:group-hover:text-indigo-100">
                                        {{ $activity->title }}
                                    </h4>
                                </div>

                                <p class="mt-1.5 line-clamp-2 text-[10px] leading-4 text-gray-500 dark:text-slate-400">
                                    {{ $activity->description ?: 'Sin descripción registrada.' }}
                                </p>

                                @if ($activity->status === 'done')
                                    @php
                                        $executionSummary = \App\Support\Filament\ProjectManagement\ProjectManagementActivityTable::kanbanDoneExecutionSummary($activity);
                                    @endphp
                                    @if ($executionSummary)
                                        <div
                                            class="mt-4 space-y-2 rounded-xl border px-3 py-2.5 {{ $executionSummary['within_range'] ? 'border-emerald-200/80 bg-emerald-50/70 dark:border-emerald-500/25 dark:bg-emerald-500/10' : 'border-red-200/80 bg-red-50/70 dark:border-red-500/25 dark:bg-red-500/10' }}"
                                        >
                                            <div class="flex items-start justify-between gap-2 text-[10px] leading-tight text-gray-600 dark:text-slate-300">
                                                <span>
                                                    <span class="block font-semibold uppercase tracking-wide text-gray-500 dark:text-slate-400">Inicio</span>
                                                    <span class="tabular-nums">{{ $executionSummary['started_label'] }}</span>
                                                </span>
                                                <span class="text-right">
                                                    <span class="block font-semibold uppercase tracking-wide text-gray-500 dark:text-slate-400">Fin</span>
                                                    <span class="tabular-nums">{{ $executionSummary['finished_label'] }}</span>
                                                </span>
                                            </div>
                                            @if ($executionSummary['optimal_label'])
                                                <p class="text-center text-[10px] text-gray-600 dark:text-slate-300">
                                                    <span class="font-semibold text-gray-700 dark:text-slate-200">Plazo óptimo</span>
                                                    <span class="mx-1 text-gray-400 dark:text-slate-500">·</span>
                                                    <span class="tabular-nums font-medium">{{ $executionSummary['optimal_label'] }}</span>
                                                </p>
                                            @endif
                                            <p class="text-center">
                                                <span class="block text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-slate-400">Ejecución</span>
                                                <span class="text-sm font-bold tabular-nums {{ $executionSummary['within_range'] ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300' }}">
                                                    {{ $executionSummary['elapsed_label'] }}
                                                </span>
                                            </p>
                                        </div>
                                    @endif
                                    @unless ($activity->isArchivedFromKanban())
                                        <div class="mt-2 flex justify-center">
                                            <button
                                                type="button"
                                                wire:click.stop="archiveActivityFromKanban({{ $activity->id }})"
                                                wire:confirm="¿Archivar esta actividad? Dejará de mostrarse en el Kanban, pero seguirá disponible en el proyecto."
                                                class="inline-flex items-center gap-1 rounded-lg border border-gray-200/90 bg-white/80 px-2 py-1 text-[10px] font-semibold text-gray-600 transition hover:border-gray-300 hover:bg-gray-100 hover:text-gray-800 dark:border-white/15 dark:bg-white/5 dark:text-slate-300 dark:hover:border-white/25 dark:hover:bg-white/10 dark:hover:text-white"
                                                title="Ocultar del Kanban"
                                            >
                                                <x-heroicon-m-archive-box class="h-3.5 w-3.5" />
                                                Archivar
                                            </button>
                                        </div>
                                    @endunless
                                @else
                                    <x-projects.kanban-activity-assignees :activity="$activity" />

                                    <div class="mt-4 space-y-1.5 text-[11px] text-gray-500 dark:text-slate-400">
                                        <div class="flex justify-end">
                                            <span class="inline-flex items-center gap-1 whitespace-nowrap">
                                                <x-heroicon-m-flag class="h-3.5 w-3.5" />
                                                {{ $activity->due_date?->translatedFormat('d M') ?? 'Sin fecha' }}
                                            </span>
                                        </div>
                                        <div class="flex items-center justify-end gap-2">
                                            <a
                                                href="{{ \App\Filament\Projects\Resources\ProjectManagement\Activities\ActivityResource::getUrl('view', ['record' => $activity], 'projects') }}"
                                                class="inline-flex items-center gap-1 rounded-lg px-1 py-0.5 font-medium text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-white"
                                                title="Ver actividad"
                                            >
                                                <x-heroicon-m-eye class="h-3.5 w-3.5" />
                                                Ver
                                            </a>
                                            <button
                                                type="button"
                                                wire:click.stop="mountAction('addActivityNote', { activityId: {{ $activity->id }} })"
                                                class="inline-flex items-center gap-1 rounded-lg px-1 py-0.5 font-medium text-gray-500 transition hover:bg-gray-100 hover:text-amber-700 dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-amber-200"
                                                title="Agregar notas"
                                            >
                                                <x-heroicon-m-chat-bubble-left-ellipsis class="h-3.5 w-3.5" />
                                                Notas
                                            </button>
                                            <button
                                                type="button"
                                                wire:click.stop="mountAction('uploadActivityDocument', { activityId: {{ $activity->id }} })"
                                                class="inline-flex items-center gap-1 rounded-lg px-1 py-0.5 font-medium text-gray-500 transition hover:bg-gray-100 hover:text-sky-700 dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-sky-200"
                                                title="Cargar documentos"
                                            >
                                                <x-heroicon-m-arrow-up-tray class="h-3.5 w-3.5" />
                                                Docs
                                            </button>
                                            <a
                                                href="{{ \App\Filament\Projects\Resources\ProjectManagement\Activities\ActivityResource::getUrl('edit', ['record' => $activity], 'projects') }}"
                                                class="inline-flex items-center gap-1 rounded-lg px-1 py-0.5 font-medium text-primary-600 transition hover:bg-primary-50 hover:text-primary-700 dark:text-indigo-300 dark:hover:bg-indigo-500/15 dark:hover:text-indigo-100"
                                                title="Editar actividad"
                                            >
                                                <x-heroicon-m-arrow-right-circle class="h-3.5 w-3.5" />
                                                Editar
                                            </a>
                                        </div>
                                    </div>
                                @endif
                                </div>
                            </div>
                        @empty
                            <div class="kanban-column-empty rounded-2xl border border-dashed border-gray-300 bg-white px-4 py-8 text-center dark:border-white/15 dark:bg-white/[0.03]">
                                <p class="text-sm font-medium text-gray-600 dark:text-slate-300">Sin actividades</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-slate-500">Arrastra una actividad aquí</p>
                            </div>
                        @endforelse
                    </div>
                </article>
            @endforeach
        </section>
        @elseif ($viewMode === 'timeline')
            <x-projects.kanban-timeline :timeline="$this->timelinePayload" />
        @elseif ($viewMode === 'list')
            <div class="kanban-activities-table overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm ring-1 ring-gray-950/5 dark:border-white/10 dark:bg-gray-950 dark:ring-white/10">
                {{ $this->getTable()->render() }}
            </div>
        @else
            @include('components.projects.kanban-files', [
                'files' => $this->filesPayload,
                'category' => $filesCategory,
                'layout' => $filesLayout,
                'pinnedFileIds' => $this->pinnedFileIds,
            ])
        @endif
    </div>

    <x-filament-actions::modals />

    @if ($viewMode === 'board')
    <style>
        .kanban-activity-card {
            border-color: color-mix(in srgb, var(--activity-color) 30%, rgb(226 232 240));
            background: linear-gradient(135deg, color-mix(in srgb, var(--activity-color) 8%, #ffffff), #ffffff);
            cursor: grab;
            touch-action: manipulation;
            transition:
                transform 260ms cubic-bezier(0.25, 1, 0.5, 1),
                box-shadow 260ms cubic-bezier(0.25, 1, 0.5, 1),
                border-color 220ms ease;
        }

        .kanban-activity-card:hover:not(.kanban-card-fallback):not(.kanban-card-chosen) {
            border-color: color-mix(in srgb, var(--activity-color) 45%, rgb(191 219 254));
            box-shadow: 0 8px 22px -12px color-mix(in srgb, var(--activity-color) 45%, transparent);
            transform: translateY(-3px);
        }

        .kanban-activity-card:active:not(.kanban-card-fallback) {
            cursor: grabbing;
            transform: scale(0.992) translateY(-1px);
            transition-duration: 120ms;
        }

        .kanban-column {
            transition:
                opacity 320ms cubic-bezier(0.25, 1, 0.5, 1),
                box-shadow 320ms cubic-bezier(0.25, 1, 0.5, 1),
                background 320ms cubic-bezier(0.25, 1, 0.5, 1),
                outline 320ms cubic-bezier(0.25, 1, 0.5, 1);
        }

        .kanban-column-list {
            --kanban-visible-cards: 4;
            --kanban-card-slot-height: 12.25rem;
            --kanban-column-gap: 0.75rem;
            max-height: calc(
                (var(--kanban-card-slot-height) * var(--kanban-visible-cards)) +
                (var(--kanban-column-gap) * (var(--kanban-visible-cards) - 1))
            );
            scroll-behavior: smooth;
            scrollbar-width: thin;
            scrollbar-color: rgba(148, 163, 184, 0.45) transparent;
        }

        .kanban-column-list::-webkit-scrollbar {
            width: 6px;
        }

        .kanban-column-list::-webkit-scrollbar-thumb {
            border-radius: 9999px;
            background: rgba(148, 163, 184, 0.45);
        }

        .kanban-column-list::-webkit-scrollbar-thumb:hover {
            background: rgba(100, 116, 139, 0.65);
        }

        .dark .kanban-column-list {
            scrollbar-color: rgba(100, 116, 139, 0.5) transparent;
        }

        .dark .kanban-column-list::-webkit-scrollbar-thumb {
            background: rgba(100, 116, 139, 0.5);
        }

        .dark .kanban-activity-card {
            border-color: color-mix(in srgb, var(--activity-color) 40%, transparent);
            background: linear-gradient(135deg, color-mix(in srgb, var(--activity-color) 14%, #161923), #161923);
        }

        .dark .kanban-activity-card:hover:not(.kanban-card-fallback) {
            border-color: color-mix(in srgb, var(--activity-color) 55%, transparent);
        }

        .kanban-priority-badge {
            border-color: color-mix(in srgb, var(--activity-color) 55%, transparent);
            background: color-mix(in srgb, var(--activity-color) 24%, #ffffff);
            color: color-mix(in srgb, var(--activity-color) 72%, #0f172a);
            --tw-ring-color: color-mix(in srgb, var(--activity-color) 30%, transparent);
            box-shadow: 0 1px 2px color-mix(in srgb, var(--activity-color) 28%, transparent);
        }

        .dark .kanban-priority-badge {
            border-color: rgb(100 116 139 / 0.45);
            background: rgb(30 41 59 / 0.9);
            color: rgb(241 245 249);
            --tw-ring-color: rgb(100 116 139 / 0.35);
            box-shadow: 0 0 0 1px rgb(255 255 255 / 0.06);
        }

        .dark .kanban-priority-badge--high {
            border-color: rgb(248 113 113 / 0.55);
            background: rgb(127 29 29 / 0.72);
            color: rgb(254 202 202);
            --tw-ring-color: rgb(248 113 113 / 0.35);
        }

        .dark .kanban-priority-badge--medium {
            border-color: rgb(251 191 36 / 0.5);
            background: rgb(120 53 15 / 0.55);
            color: rgb(253 230 138);
            --tw-ring-color: rgb(251 191 36 / 0.3);
        }

        .dark .kanban-priority-badge--low {
            border-color: rgb(100 116 139 / 0.45);
            background: rgb(30 41 59 / 0.85);
            color: rgb(203 213 225);
            --tw-ring-color: rgb(100 116 139 / 0.3);
        }

        .kanban-filter-select option {
            background-color: #ffffff;
            color: #0f172a;
        }

        .dark .kanban-filter-select option {
            background-color: #161923;
            color: #f8fafc;
        }

        body.kanban-is-dragging {
            cursor: grabbing !important;
            user-select: none;
        }

        body.kanban-is-dragging .kanban-drag-grip {
            opacity: 0 !important;
        }

        body.kanban-is-dragging .kanban-column:not(.kanban-column--target) {
            opacity: 0.62;
        }

        body.kanban-is-dragging .kanban-column--target {
            outline: 2px solid rgb(99 102 241);
            outline-offset: 3px;
            background: color-mix(in srgb, rgb(99 102 241) 7%, transparent);
            box-shadow: 0 0 28px -10px rgba(99, 102, 241, 0.38);
        }

        body.kanban-is-dragging .kanban-column--target .kanban-column-list {
            background: color-mix(in srgb, rgb(99 102 241) 5%, transparent);
            border-radius: 0.75rem;
            transition: background 280ms cubic-bezier(0.25, 1, 0.5, 1);
        }

        body.kanban-is-dragging .kanban-column--target .kanban-column-empty {
            opacity: 0.45;
            transition: opacity 280ms ease;
        }

        .kanban-card-chosen:not(.kanban-card-fallback) {
            opacity: 1 !important;
            transform: scale(0.98) !important;
            border-style: dashed !important;
            border-width: 2px !important;
            border-color: color-mix(in srgb, var(--activity-color) 40%, #94a3b8) !important;
            background: color-mix(in srgb, var(--activity-color) 4%, #f8fafc) !important;
            box-shadow: inset 0 1px 10px rgba(15, 23, 42, 0.04) !important;
            transition:
                transform 240ms cubic-bezier(0.25, 1, 0.5, 1),
                background 240ms ease,
                border-color 240ms ease,
                box-shadow 240ms ease !important;
        }

        .kanban-card-chosen:not(.kanban-card-fallback) .kanban-card-surface {
            opacity: 0.28;
            transition: opacity 240ms ease;
        }

        .kanban-card-ghost.kanban-activity-card {
            opacity: 1 !important;
            height: auto !important;
            min-height: 5.5rem !important;
            margin: 0 0 0.75rem !important;
            padding: 0 !important;
            overflow: hidden !important;
            border: 2px dashed color-mix(in srgb, var(--activity-color) 38%, #cbd5e1) !important;
            background: color-mix(in srgb, var(--activity-color) 3%, #f1f5f9) !important;
            box-shadow: none !important;
            transform: scale(0.99) !important;
            transition:
                transform 220ms cubic-bezier(0.25, 1, 0.5, 1),
                border-color 220ms ease,
                background 220ms ease !important;
        }

        .kanban-card-ghost.kanban-activity-card > * {
            visibility: hidden !important;
        }

        .kanban-card-fallback {
            cursor: grabbing !important;
            opacity: 1 !important;
            border-width: 2px !important;
            border-color: color-mix(in srgb, var(--activity-color) 50%, #6366f1) !important;
            box-shadow:
                0 0 0 1px rgba(255, 255, 255, 0.55),
                0 0 0 3px color-mix(in srgb, var(--activity-color) 28%, #6366f1),
                0 10px 24px -10px rgba(15, 23, 42, 0.28),
                0 20px 40px -16px color-mix(in srgb, var(--activity-color) 32%, transparent) !important;
            z-index: 10000 !important;
            will-change: transform;
            pointer-events: none !important;
            transform: translateY(-4px) scale(1.045) rotate(0deg);
            transition: box-shadow 280ms cubic-bezier(0.25, 1, 0.5, 1);
        }

        .dark .kanban-card-fallback {
            box-shadow:
                0 0 0 1px rgba(255, 255, 255, 0.06),
                0 0 0 3px color-mix(in srgb, var(--activity-color) 38%, #818cf8),
                0 12px 28px -10px rgba(0, 0, 0, 0.45),
                0 22px 44px -16px color-mix(in srgb, var(--activity-color) 38%, transparent) !important;
        }

        .kanban-card-just-dropped {
            animation: kanban-card-settle 420ms cubic-bezier(0.25, 1, 0.5, 1);
        }

        @keyframes kanban-card-settle {
            0% {
                transform: scale(1.025) translateY(-2px);
            }

            55% {
                transform: scale(0.995) translateY(0);
            }

            100% {
                transform: scale(1) translateY(0);
            }
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
    <script>
        (() => {
            let kanbanSortables = [];
            let isKanbanDragging = false;
            let kanbanWireId = null;
            let kanbanPointerMoveHandler = null;
            let kanbanPickupOriginX = 0;
            let kanbanMotionFrame = null;
            let kanbanCurrentTilt = 0;
            let kanbanCurrentScale = 1.045;
            let kanbanCurrentLift = -4;
            let kanbanTargetTilt = 0;
            let kanbanTargetScale = 1.045;
            let kanbanTargetLift = -4;
            let kanbanTargetColumn = null;

            const kanbanLerp = (current, target, factor) => current + ((target - current) * factor);

            const emptyColumnMarkup = `
                <div class="kanban-column-empty rounded-2xl border border-dashed border-gray-300 bg-white px-4 py-8 text-center dark:border-white/15 dark:bg-white/[0.03]">
                    <p class="text-sm font-medium text-gray-600 dark:text-slate-300">Sin actividades</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-slate-500">Arrastra una actividad aquí</p>
                </div>
            `;

            const getKanbanWire = () => {
                if (! kanbanWireId) {
                    kanbanWireId = document.querySelector('.kanban-board')?.closest('[wire\\:id]')?.getAttribute('wire:id') ?? null;
                }

                return kanbanWireId ? Livewire.find(kanbanWireId) : null;
            };

            const clearColumnTargets = () => {
                if (kanbanTargetColumn) {
                    kanbanTargetColumn.classList.remove('kanban-column--target');
                    kanbanTargetColumn = null;
                }
            };

            const setColumnTarget = (column) => {
                if (kanbanTargetColumn === column) {
                    return;
                }

                clearColumnTargets();

                if (column) {
                    column.classList.add('kanban-column--target');
                    kanbanTargetColumn = column;
                }
            };

            const stopPickupMotion = () => {
                if (kanbanMotionFrame) {
                    cancelAnimationFrame(kanbanMotionFrame);
                    kanbanMotionFrame = null;
                }
            };

            const applyFallbackTransform = () => {
                const fallback = getDragFallback();

                if (! fallback) {
                    return;
                }

                fallback.style.transform = `translateY(${kanbanCurrentLift}px) scale(${kanbanCurrentScale}) rotate(${kanbanCurrentTilt}deg)`;
            };

            const runPickupMotion = () => {
                kanbanCurrentTilt = kanbanLerp(kanbanCurrentTilt, kanbanTargetTilt, 0.16);
                kanbanCurrentScale = kanbanLerp(kanbanCurrentScale, kanbanTargetScale, 0.14);
                kanbanCurrentLift = kanbanLerp(kanbanCurrentLift, kanbanTargetLift, 0.14);

                applyFallbackTransform();

                if (! isKanbanDragging) {
                    stopPickupMotion();

                    return;
                }

                kanbanMotionFrame = requestAnimationFrame(runPickupMotion);
            };

            const cleanupDragArtifacts = () => {
                document.body.classList.remove('kanban-is-dragging');
                clearColumnTargets();
                isKanbanDragging = false;
                stopPickupMotion();

                kanbanCurrentTilt = 0;
                kanbanCurrentScale = 1.045;
                kanbanCurrentLift = -4;
                kanbanTargetTilt = 0;
                kanbanTargetScale = 1.045;
                kanbanTargetLift = -4;

                if (kanbanPointerMoveHandler) {
                    window.removeEventListener('pointermove', kanbanPointerMoveHandler);
                    kanbanPointerMoveHandler = null;
                }

                document.querySelectorAll('.kanban-card-fallback, .kanban-card-chosen, .kanban-card-ghost').forEach((element) => {
                    element.classList.remove('kanban-card-fallback', 'kanban-card-chosen', 'kanban-card-ghost', 'sortable-fallback', 'sortable-chosen', 'sortable-ghost');
                    element.style.removeProperty('transform');
                });
            };

            const getDragFallback = () => document.querySelector('.kanban-card-fallback');

            const startPickupTiltTracking = (event) => {
                kanbanPickupOriginX = event.clientX ?? 0;
                kanbanCurrentTilt = 0;
                kanbanCurrentScale = 1.02;
                kanbanCurrentLift = -1;
                kanbanTargetTilt = 0;
                kanbanTargetScale = 1.045;
                kanbanTargetLift = -4;

                stopPickupMotion();
                runPickupMotion();

                kanbanPointerMoveHandler = (pointerEvent) => {
                    const deltaX = (pointerEvent.clientX ?? 0) - kanbanPickupOriginX;
                    const velocity = Math.abs(pointerEvent.movementX ?? 0);

                    kanbanTargetTilt = Math.max(-3.5, Math.min(3.5, deltaX * 0.028));
                    kanbanTargetScale = 1.045 + Math.min(0.012, velocity * 0.0015);
                    kanbanTargetLift = -4 - Math.min(2, velocity * 0.08);
                };

                window.addEventListener('pointermove', kanbanPointerMoveHandler, { passive: true });
            };

            const syncKanbanColumnEmptyStates = () => {
                document.querySelectorAll('[data-kanban-column]').forEach((list) => {
                    const cards = list.querySelectorAll(':scope > .kanban-activity-card');
                    const existingEmpty = list.querySelector(':scope > .kanban-column-empty');

                    if (cards.length === 0) {
                        if (! existingEmpty) {
                            list.insertAdjacentHTML('beforeend', emptyColumnMarkup);
                        }
                    } else if (existingEmpty) {
                        existingEmpty.remove();
                    }
                });
            };

            const updateKanbanCounts = () => {
                document.querySelectorAll('.kanban-column').forEach((column) => {
                    const list = column.querySelector('[data-kanban-column]');
                    const count = list?.querySelectorAll(':scope > .kanban-activity-card').length ?? 0;
                    const badge = column.querySelector('[data-kanban-count]');

                    if (badge) {
                        badge.textContent = `${count} ${count === 1 ? 'actividad' : 'actividades'}`;
                    }
                });
            };

            const destroyKanbanSortables = () => {
                kanbanSortables.forEach((sortable) => sortable.destroy());
                kanbanSortables = [];
            };

            const initKanbanSortables = () => {
                if (typeof Sortable === 'undefined' || isKanbanDragging) {
                    return;
                }

                destroyKanbanSortables();

                document.querySelectorAll('[data-kanban-column]').forEach((columnEl) => {
                    kanbanSortables.push(new Sortable(columnEl, {
                        group: 'kanban-activities',
                        animation: 140,
                        easing: 'cubic-bezier(0.25, 1, 0.5, 1)',
                        draggable: '.kanban-activity-card',
                        filter: 'a, button, input, textarea, select, label',
                        preventOnFilter: true,
                        delay: 120,
                        delayOnTouchOnly: true,
                        touchStartThreshold: 6,
                        forceFallback: true,
                        fallbackOnBody: true,
                        fallbackTolerance: 0,
                        fallbackClass: 'kanban-card-fallback',
                        ghostClass: 'kanban-card-ghost',
                        chosenClass: 'kanban-card-chosen',
                        emptyInsertThreshold: 24,
                        swapThreshold: 0.65,
                        invertSwap: true,
                        onChoose: () => {
                            isKanbanDragging = true;
                            document.body.classList.add('kanban-is-dragging');
                        },
                        onStart: (event) => {
                            isKanbanDragging = true;
                            document.body.classList.add('kanban-is-dragging');
                            startPickupTiltTracking(event.originalEvent ?? event);
                        },
                        onUnchoose: () => {
                            window.requestAnimationFrame(cleanupDragArtifacts);
                        },
                        onMove: (event) => {
                            if (event.from !== event.to) {
                                setColumnTarget(event.to.closest('.kanban-column'));
                            } else {
                                clearColumnTargets();
                            }

                            return true;
                        },
                        onEnd: (event) => {
                            const activityId = Number(event.item.dataset.activityId ?? 0);
                            const newStatus = event.to.dataset.status ?? '';
                            const oldStatus = event.from.dataset.status ?? '';
                            const statusChanged = activityId > 0 && newStatus !== '' && newStatus !== oldStatus;

                            cleanupDragArtifacts();
                            syncKanbanColumnEmptyStates();
                            updateKanbanCounts();

                            if (! statusChanged) {
                                return;
                            }

                            event.item.classList.add('kanban-card-just-dropped');
                            window.setTimeout(() => event.item.classList.remove('kanban-card-just-dropped'), 420);

                            getKanbanWire()?.call('moveActivity', activityId, newStatus);
                        },
                    }));
                });
            };

            document.addEventListener('livewire:init', () => {
                initKanbanSortables();

                Livewire.hook('morph.updated', ({ el }) => {
                    if (isKanbanDragging) {
                        return;
                    }

                    if (! el?.querySelector?.('.kanban-board') && ! el?.classList?.contains?.('kanban-board')) {
                        return;
                    }

                    kanbanWireId = null;
                    window.requestAnimationFrame(initKanbanSortables);
                });
            });

            window.addEventListener('pointercancel', cleanupDragArtifacts);
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    cleanupDragArtifacts();
                }
            });

            if (window.Livewire) {
                initKanbanSortables();
            }
        })();
    </script>
    @endif
</x-filament-panels::page>
