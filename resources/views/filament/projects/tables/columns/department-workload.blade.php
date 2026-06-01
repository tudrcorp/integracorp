@php
    use App\Support\Filament\ProjectManagement\ProjectManagementDepartmentTable;

    $record = $getRecord();
    $workload = ProjectManagementDepartmentTable::workloadMeta($record);
    $color = ProjectManagementDepartmentTable::resolveColor($record);

    $barToneClass = match ($workload['tone']) {
        'success' => 'bg-success-500',
        'warning' => 'bg-warning-500',
        'muted' => 'bg-gray-400',
        default => 'bg-primary-500',
    };
@endphp

<div
    class="fi-projects-department-workload min-w-[12rem] max-w-[14rem] space-y-2 py-1"
    style="--department-color: {{ $color }};"
>
    <div class="flex items-center justify-between gap-2 text-[11px]">
        <span class="min-w-0 font-medium text-gray-600 dark:text-gray-300">{{ $workload['label'] }}</span>
        @if ($workload['percent'] !== null)
            <span class="fi-projects-department-workload__percent shrink-0 rounded-lg px-2 py-0.5 text-xs font-bold tabular-nums text-gray-950 dark:text-white">
                {{ $workload['percent'] }}%
            </span>
        @endif
    </div>

    <div class="h-2.5 overflow-hidden rounded-full bg-gray-200/80 shadow-inner dark:bg-white/10">
        @if ($workload['percent'] !== null)
            <div
                class="{{ $barToneClass }} h-full rounded-full transition-all duration-500"
                style="width: {{ $workload['percent'] }}%; box-shadow: 0 0 10px color-mix(in srgb, var(--department-color) 30%, transparent);"
            ></div>
        @else
            <div
                class="h-full rounded-full opacity-50"
                style="width: 18%; background: var(--department-color);"
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
