@php
    use App\Support\Filament\ProjectManagement\ProjectManagementActivityTable;

    $record = $getRecord();
    $assignment = ProjectManagementActivityTable::assignmentSummary($record);
    $color = ProjectManagementActivityTable::resolveColor($record);

    $toneClass = match ($assignment['tone']) {
        'success' => 'text-success-600 dark:text-success-400',
        'info' => 'text-info-600 dark:text-info-400',
        'primary' => 'text-primary-600 dark:text-primary-400',
        default => 'text-gray-500 dark:text-gray-400',
    };
@endphp

<div class="fi-projects-activity-assignment min-w-[10rem] space-y-1 py-1 ps-4">
    <div class="flex items-center gap-2">
        <div
            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl border"
            style="border-color: {{ $color }}44; background: color-mix(in srgb, {{ $color }} 12%, transparent);"
        >
            <x-filament::icon :icon="$assignment['icon']" class="size-4" style="color: {{ $color }};" />
        </div>

        <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">
                {{ $assignment['label'] }}
            </p>
            <p class="text-[11px] font-medium {{ $toneClass }}">
                {{ $assignment['subtitle'] }}
            </p>
        </div>
    </div>
</div>
