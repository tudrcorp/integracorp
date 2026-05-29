@php
    use App\Support\Filament\ProjectManagement\ProjectManagementSubprojectTable;

    $record = $getRecord();
    $workload = ProjectManagementSubprojectTable::workloadMeta($record);
    $color = ProjectManagementSubprojectTable::resolveColor($record);

    $barToneClass = match ($workload['tone']) {
        'success' => 'bg-success-500',
        'warning' => 'bg-warning-500',
        'muted' => 'bg-gray-400',
        default => 'bg-primary-500',
    };
@endphp

<div class="min-w-[12rem] space-y-2 py-1">
    <div class="flex items-center justify-between gap-2 text-[11px]">
        <span class="font-medium text-gray-600 dark:text-gray-300">{{ $workload['label'] }}</span>
        @if ($workload['percent'] !== null)
            <span class="font-semibold text-gray-950 dark:text-white">{{ $workload['percent'] }}%</span>
        @endif
    </div>

    <div class="h-2 overflow-hidden rounded-full bg-gray-200/80 dark:bg-white/10">
        @if ($workload['percent'] !== null)
            <div
                class="{{ $barToneClass }} h-full rounded-full transition-all duration-500"
                style="width: {{ $workload['percent'] }}%;"
            ></div>
        @else
            <div
                class="h-full rounded-full opacity-60"
                style="width: 20%; background: {{ $color }};"
            ></div>
        @endif
    </div>

    <div class="flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-gray-500 dark:text-gray-400">
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
