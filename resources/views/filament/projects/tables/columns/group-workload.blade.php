@php
    use App\Support\Filament\ProjectManagement\ProjectManagementGroupTable;

    $record = $getRecord();
    $workload = ProjectManagementGroupTable::workloadMeta($record);
    $color = ProjectManagementGroupTable::resolveColor($record);

    $barToneClass = match ($workload['tone']) {
        'success' => 'bg-success-500',
        'warning' => 'bg-warning-500',
        'muted' => 'bg-gray-400',
        default => 'bg-primary-500',
    };
@endphp

<div class="fi-projects-group-workload min-w-[12rem] max-w-[14rem] space-y-2 py-1">
    <div class="flex items-center justify-between gap-2 text-[11px]">
        <span class="min-w-0 font-medium text-gray-600 dark:text-gray-300">{{ $workload['label'] }}</span>
        @if ($workload['percent'] !== null)
            <span
                class="shrink-0 rounded-lg px-2 py-0.5 text-xs font-bold tabular-nums text-gray-950 dark:text-white"
                style="background: color-mix(in srgb, {{ $color }} 16%, transparent);"
            >
                {{ $workload['percent'] }}%
            </span>
        @endif
    </div>

    <div class="h-2.5 overflow-hidden rounded-full bg-gray-200/80 shadow-inner dark:bg-white/10">
        @if ($workload['percent'] !== null)
            <div
                class="{{ $barToneClass }} h-full rounded-full transition-all duration-500"
                style="width: {{ $workload['percent'] }}%; box-shadow: 0 0 10px color-mix(in srgb, {{ $color }} 30%, transparent);"
            ></div>
        @else
            <div
                class="h-full rounded-full opacity-50"
                style="width: 18%; background: {{ $color }};"
            ></div>
        @endif
    </div>

    <div class="flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-gray-500 dark:text-gray-400">
        <span class="inline-flex items-center gap-1">
            <x-filament::icon icon="heroicon-m-clipboard-document-check" class="size-3.5" />
            {{ $workload['total'] }} asignadas
        </span>
        <span class="inline-flex items-center gap-1">
            <x-filament::icon icon="heroicon-m-check-circle" class="size-3.5" />
            {{ $workload['done'] }} cerradas
        </span>
        <span class="inline-flex items-center gap-1">
            <x-filament::icon icon="heroicon-m-arrow-path" class="size-3.5" />
            {{ $workload['open'] }} abiertas
        </span>
    </div>
</div>
