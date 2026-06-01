@props([
    'activity',
    'variant' => 'table',
])

@php
    use App\Support\Filament\ProjectManagement\ProjectManagementActivityTable;

    $due = ProjectManagementActivityTable::dueMeta($activity);

    $statusGradient = 'linear-gradient(90deg, #22c55e 0%, #eab308 50%, #ef4444 100%)';
    $percentBadgeColor = match (true) {
        ($due['percent'] ?? 0) >= 90 => '#ef4444',
        ($due['percent'] ?? 0) >= 70 => '#eab308',
        default => '#22c55e',
    };

    $wrapperClass = match ($variant) {
        'kanban-list-card' => 'kanban-list-due kanban-list-due--card w-full space-y-3 text-[11px]',
        'kanban-list' => 'kanban-list-due w-full space-y-2',
        default => 'fi-projects-activity-due w-full min-w-[18rem] max-w-[20rem] space-y-2 py-1',
    };
@endphp

<div {{ $attributes->class([$wrapperClass]) }}>
    <div class="flex items-start justify-between gap-2 text-[11px]">
        <div @class([
            'min-w-0',
            'space-y-1' => $variant === 'kanban-list-card',
            'space-y-0.5' => $variant !== 'kanban-list-card',
        ])>
            <span class="block font-medium text-gray-600 dark:text-gray-300">{{ $due['label'] }}</span>
            @if (filled($due['progress_detail'] ?? null))
                <span @class([
                    'block text-gray-500 dark:text-gray-400',
                    'text-[10px]' => $variant !== 'kanban-list-card',
                    'text-[11px] leading-snug' => $variant === 'kanban-list-card',
                ])>{{ $due['progress_detail'] }}</span>
            @endif
        </div>
        @if ($due['percent'] !== null)
            <span
                class="shrink-0 rounded-lg px-2 py-1 text-xs font-bold tabular-nums text-gray-950 dark:text-white"
                style="background: color-mix(in srgb, {{ $percentBadgeColor }} 18%, transparent);"
            >
                {{ $due['percent'] }}%
            </span>
        @endif
    </div>

    <div
        class="relative h-2.5 overflow-hidden rounded-full bg-gray-200/80 shadow-inner dark:bg-white/10"
        role="progressbar"
        @if ($due['percent'] !== null)
            aria-valuenow="{{ $due['percent'] }}"
            aria-valuemin="0"
            aria-valuemax="100"
            aria-valuetext="{{ $due['percent'] }}% del plazo consumido hasta el {{ $due['due_label'] ?? 'límite' }}"
        @endif
        aria-label="Consumo del plazo hasta la fecha límite"
    >
        <div
            class="absolute inset-0 rounded-full opacity-25 dark:opacity-30"
            style="background: {{ $statusGradient }};"
        ></div>

        @if ($due['percent'] !== null)
            <div
                class="relative h-full rounded-full transition-all duration-500 ease-out"
                style="width: {{ $due['percent'] }}%; background: {{ $statusGradient }}; box-shadow: 0 0 10px color-mix(in srgb, {{ ($due['percent'] ?? 0) >= 70 ? '#ef4444' : '#22c55e' }} 35%, transparent);"
            ></div>
        @else
            <div
                class="relative h-full rounded-full opacity-70"
                style="width: 25%; background: {{ $statusGradient }};"
            ></div>
        @endif
    </div>

    <div @class([
        'flex flex-wrap items-center justify-between gap-x-3 gap-y-1 text-[11px] text-gray-500 dark:text-gray-400',
        'hidden sm:flex' => in_array($variant, ['kanban-list', 'kanban-list-card'], true),
    ])>
        <div class="flex flex-wrap gap-x-3 gap-y-1">
            <span class="inline-flex items-center gap-1">
                <x-filament::icon icon="heroicon-m-flag" class="size-3.5" />
                Límite {{ $due['due_label'] ?? '—' }}
            </span>
            <span class="inline-flex items-center gap-1">
                <x-filament::icon icon="heroicon-m-play" class="size-3.5" />
                Inicio {{ $due['created_label'] ?? '—' }}
            </span>
        </div>

        @if ($variant !== 'kanban-list')
            <span class="inline-flex items-center gap-2 text-[10px] font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                <span class="inline-flex items-center gap-1">
                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                    0%
                </span>
                <span class="inline-flex items-center gap-1">
                    <span class="size-1.5 rounded-full bg-amber-400"></span>
                    70%
                </span>
                <span class="inline-flex items-center gap-1">
                    <span class="size-1.5 rounded-full bg-red-500"></span>
                    100%
                </span>
            </span>
        @endif
    </div>

    @if (in_array($variant, ['kanban-list', 'kanban-list-card'], true))
        <div class="flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-gray-500 dark:text-gray-400 sm:hidden">
            <span class="inline-flex items-center gap-1">
                <x-filament::icon icon="heroicon-m-flag" class="size-3.5" />
                Límite {{ $due['due_label'] ?? '—' }}
            </span>
            <span class="inline-flex items-center gap-1">
                <x-filament::icon icon="heroicon-m-play" class="size-3.5" />
                Inicio {{ $due['created_label'] ?? '—' }}
            </span>
        </div>
    @endif
</div>
