@php
    use App\Support\Filament\ProjectManagement\ProjectManagementGroupTable;

    $record = $getRecord();
    $color = ProjectManagementGroupTable::resolveColor($record);
    $members = ProjectManagementGroupTable::membersMeta($record);

    $toneClass = match ($members['tone']) {
        'success' => 'text-success-600 dark:text-success-400',
        'info' => 'text-info-600 dark:text-info-400',
        default => 'text-gray-500 dark:text-gray-400',
    };
@endphp

<div class="fi-projects-group-members min-w-[11rem] space-y-2 py-1 ps-1">
    <div class="flex items-center justify-between gap-2">
        <div class="min-w-0">
            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">Integrantes</p>
            <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $members['label'] }}</p>
        </div>

        <span
            class="inline-flex shrink-0 items-center justify-center rounded-xl border px-2 py-1 text-xs font-bold tabular-nums text-gray-950 dark:text-white"
            style="border-color: {{ $color }}44; background: color-mix(in srgb, {{ $color }} 14%, transparent);"
        >
            {{ $members['total'] }}
        </span>
    </div>

    @if ($members['total'] > 0)
        <x-collaborator-avatar-stack
            class="fi-projects-group-members__stack"
            align="start"
            :avatars="$members['visible_members']"
            :overflow-count="$members['overflow_count']"
            tooltip-title="Integrantes del equipo"
            :tooltip-items="$members['tooltip_items']"
        />

        <p class="line-clamp-2 text-[11px] font-medium leading-4 {{ $toneClass }}">
            {{ $members['subtitle'] }}
        </p>
    @else
        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50/80 px-3 py-2.5 text-center dark:border-white/15 dark:bg-white/[0.03]">
            <x-filament::icon icon="heroicon-o-user-plus" class="mx-auto size-5 text-gray-400 dark:text-gray-500" />
            <p class="mt-1 text-[11px] font-medium text-gray-500 dark:text-gray-400">Sin colaboradores</p>
        </div>
    @endif
</div>
