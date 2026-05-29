@props([
    'project' => null,
    'subproject' => null,
])

@php
    $subprojectName = $subproject?->name;
    $hasHierarchy = filled($subprojectName);

    if ($project !== null) {
        $projectColor = \App\Support\Filament\ProjectManagement\ProjectManagementProjectTable::resolveColor($project);
        $projectIcon = \App\Support\Filament\ProjectManagement\ProjectManagementProjectTable::resolveIcon($project);
        $projectName = $project->name;
    } else {
        $projectColor = '#6366f1';
        $projectIcon = 'heroicon-o-folder';
        $projectName = 'Sin proyecto';
    }
@endphp

<div
    @class([
        'kanban-affiliation',
        'kanban-affiliation--hierarchy' => $hasHierarchy,
    ])
    style="--affiliation-color: {{ $projectColor }};"
>
    @if ($hasHierarchy)
        <div class="kanban-affiliation__stack overflow-hidden rounded-xl border shadow-sm">
            <div class="kanban-affiliation__parent flex items-center gap-2 px-2.5 py-2">
                <x-filament::icon :icon="$projectIcon" class="kanban-affiliation__parent-icon size-3.5 shrink-0" />
                <span class="kanban-affiliation__parent-label min-w-0 truncate text-[10px] font-bold uppercase tracking-[0.12em]">
                    {{ $projectName }}
                </span>
            </div>

            <div class="kanban-affiliation__child flex items-stretch gap-2 px-2.5 py-2">
                <span class="kanban-affiliation__connector flex w-3 shrink-0 flex-col items-center" aria-hidden="true">
                    <span class="kanban-affiliation__connector-line mt-0.5 w-px flex-1 rounded-full"></span>
                    <x-heroicon-m-chevron-right class="kanban-affiliation__connector-arrow size-2.5 shrink-0 -rotate-90" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="kanban-affiliation__child-kicker text-[9px] font-semibold uppercase tracking-[0.14em]">
                        Subproyecto
                    </p>
                    <p class="kanban-affiliation__child-label mt-0.5 truncate text-[11px] font-semibold leading-tight">
                        {{ $subprojectName }}
                    </p>
                </div>
            </div>
        </div>
    @else
        <span class="kanban-affiliation__single inline-flex max-w-full items-center gap-1.5 rounded-full border px-2 py-1 text-[11px] font-medium">
            <x-filament::icon :icon="$projectIcon" class="size-3.5 shrink-0" />
            <span class="truncate">{{ $projectName }}</span>
        </span>
    @endif
</div>

<style>
    .kanban-affiliation--hierarchy .kanban-affiliation__stack {
        border-color: color-mix(in srgb, var(--affiliation-color) 28%, transparent);
        background: linear-gradient(
            180deg,
            color-mix(in srgb, var(--affiliation-color) 10%, #ffffff),
            color-mix(in srgb, var(--affiliation-color) 4%, #ffffff)
        );
    }

    .kanban-affiliation__parent {
        border-bottom: 1px solid color-mix(in srgb, var(--affiliation-color) 18%, transparent);
        background: color-mix(in srgb, var(--affiliation-color) 14%, #ffffff);
        color: color-mix(in srgb, var(--affiliation-color) 82%, #0f172a);
    }

    .kanban-affiliation__parent-icon {
        color: var(--affiliation-color);
    }

    .kanban-affiliation__child {
        background: color-mix(in srgb, var(--affiliation-color) 5%, #ffffff);
        color: rgb(51, 65, 85);
    }

    .kanban-affiliation__child-kicker {
        color: rgb(100, 116, 139);
    }

    .kanban-affiliation__child-label {
        color: rgb(30, 41, 59);
    }

    .kanban-affiliation__connector-line {
        background: color-mix(in srgb, var(--affiliation-color) 45%, #cbd5e1);
    }

    .kanban-affiliation__connector-arrow {
        color: color-mix(in srgb, var(--affiliation-color) 70%, #64748b);
    }

    .kanban-affiliation__single {
        border-color: color-mix(in srgb, var(--affiliation-color) 30%, #e2e8f0);
        background: color-mix(in srgb, var(--affiliation-color) 10%, #ffffff);
        color: color-mix(in srgb, var(--affiliation-color) 75%, #0f172a);
    }

    .kanban-affiliation__single .fi-icon {
        color: var(--affiliation-color);
    }

    :is(.dark, .dark *) .kanban-affiliation--hierarchy .kanban-affiliation__stack {
        border-color: color-mix(in srgb, var(--affiliation-color) 32%, transparent);
        background: linear-gradient(
            180deg,
            color-mix(in srgb, var(--affiliation-color) 14%, #12161f),
            color-mix(in srgb, var(--affiliation-color) 6%, #0f131a)
        );
    }

    :is(.dark, .dark *) .kanban-affiliation__parent {
        border-bottom-color: color-mix(in srgb, var(--affiliation-color) 22%, transparent);
        background: color-mix(in srgb, var(--affiliation-color) 18%, #12161f);
        color: color-mix(in srgb, var(--affiliation-color) 55%, #e2e8f0);
    }

    :is(.dark, .dark *) .kanban-affiliation__child {
        background: color-mix(in srgb, var(--affiliation-color) 8%, #0f131a);
        color: rgb(226, 232, 240);
    }

    :is(.dark, .dark *) .kanban-affiliation__child-kicker {
        color: rgb(148, 163, 184);
    }

    :is(.dark, .dark *) .kanban-affiliation__child-label {
        color: rgb(248, 250, 252);
    }

    :is(.dark, .dark *) .kanban-affiliation__connector-line {
        background: color-mix(in srgb, var(--affiliation-color) 50%, #334155);
    }

    :is(.dark, .dark *) .kanban-affiliation__connector-arrow {
        color: color-mix(in srgb, var(--affiliation-color) 65%, #94a3b8);
    }

    :is(.dark, .dark *) .kanban-affiliation__single {
        border-color: color-mix(in srgb, var(--affiliation-color) 35%, transparent);
        background: color-mix(in srgb, var(--affiliation-color) 14%, #12161f);
        color: color-mix(in srgb, var(--affiliation-color) 50%, #e2e8f0);
    }
</style>
