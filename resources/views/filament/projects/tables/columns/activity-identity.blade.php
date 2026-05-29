@php
    use App\Support\Filament\ProjectManagement\ProjectManagementActivityInfolistDisplay;
    use App\Support\Filament\ProjectManagement\ProjectManagementActivityTable;

    $record = $getRecord();
    $color = ProjectManagementActivityTable::resolveColor($record);
    $status = ProjectManagementActivityTable::statusMeta((string) $record->status);
    $priority = ProjectManagementActivityTable::priorityMeta((string) $record->priority);
    $isOverdue = ProjectManagementActivityTable::isOverdue($record);
    $projectName = $record->project?->name ?? '—';
    $subprojectName = $record->subproject?->name;
    $description = ProjectManagementActivityInfolistDisplay::normalizeDescriptionText((string) $record->description);
@endphp

<div class="fi-projects-activity-identity flex w-full max-w-full min-w-0 items-center gap-3 overflow-hidden py-1">
    <div
        class="h-11 w-1.5 shrink-0 rounded-full shadow-sm"
        style="background: linear-gradient(180deg, {{ $color }}, {{ $color }}99);"
    ></div>

    <div
        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border shadow-inner"
        style="border-color: {{ $color }}55; background: color-mix(in srgb, {{ $color }} 18%, transparent);"
    >
        <x-filament::icon icon="heroicon-o-clipboard-document-check" class="size-5" style="color: {{ $color }};" />
    </div>

    <div class="min-w-0 flex-1 overflow-hidden">
        <div class="flex min-w-0 flex-wrap items-center gap-2">
            <p class="min-w-0 break-words text-sm font-semibold leading-snug text-gray-950 line-clamp-2 dark:text-white">
                {{ $record->title }}
            </p>

            @if ($isOverdue)
                <span class="inline-flex items-center rounded-full bg-danger-500/15 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-danger-600 dark:text-danger-400">
                    Vencida
                </span>
            @endif

            <span
                class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                style="background: color-mix(in srgb, {{ $color }} 16%, transparent); color: {{ $color }};"
            >
                {{ $priority['label'] }}
            </span>
        </div>

        <p class="fi-projects-activity-identity__description mt-0.5 line-clamp-2 text-xs leading-5 text-gray-500 dark:text-gray-400">
            {{ filled($description) ? $description : 'Sin descripción registrada.' }}
        </p>

        <div class="mt-1.5 flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 text-[11px] font-medium text-gray-400 dark:text-gray-500">
            @if (filled($subprojectName))
                <span class="inline-flex min-w-0 max-w-full items-center gap-1.5 text-gray-500 dark:text-gray-400">
                    <span class="truncate text-gray-600 dark:text-gray-300">{{ $projectName }}</span>
                    <x-heroicon-m-chevron-right class="size-3 shrink-0 text-gray-400 dark:text-gray-500" />
                    <span class="truncate font-semibold text-gray-700 dark:text-gray-200">{{ $subprojectName }}</span>
                </span>
            @else
                <span class="text-gray-600 dark:text-gray-300">{{ $projectName }}</span>
            @endif
            <span class="text-gray-300 dark:text-gray-600" aria-hidden="true">·</span>
            <span>{{ $status['label'] }}</span>
        </div>
    </div>
</div>
