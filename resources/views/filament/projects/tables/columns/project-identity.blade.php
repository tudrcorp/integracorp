@php
    use App\Support\Filament\ProjectManagement\ProjectManagementProjectTable;

    $record = $getRecord();
    $color = ProjectManagementProjectTable::resolveColor($record);
    $icon = ProjectManagementProjectTable::resolveIcon($record);
    $status = ProjectManagementProjectTable::statusMeta((string) $record->status);
    $isOverdue = ProjectManagementProjectTable::isOverdue($record);
@endphp

<div class="flex min-w-0 items-center gap-3 py-1">
    <div
        class="h-11 w-1.5 shrink-0 rounded-full shadow-sm"
        style="background: linear-gradient(180deg, {{ $color }}, {{ $color }}99);"
    ></div>

    <div
        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border shadow-inner"
        style="border-color: {{ $color }}55; background: color-mix(in srgb, {{ $color }} 18%, transparent);"
    >
        <x-filament::icon :icon="$icon" class="size-5" style="color: {{ $color }};" />
    </div>

    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
            <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">
                {{ $record->name }}
            </p>

            <span
                class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                style="background: color-mix(in srgb, {{ $color }} 20%, transparent); color: {{ $color }};"
            >
                {{ $color }}
            </span>

            @if ($isOverdue)
                <span class="inline-flex items-center rounded-full bg-danger-500/15 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-danger-600 dark:text-danger-400">
                    Vencido
                </span>
            @endif
        </div>

        <p class="mt-0.5 line-clamp-1 text-xs text-gray-500 dark:text-gray-400">
            {{ filled($record->description) ? $record->description : 'Sin descripción registrada.' }}
        </p>

        <p class="mt-1 text-[11px] font-medium text-gray-400 dark:text-gray-500">
            Estatus: <span class="text-gray-600 dark:text-gray-300">{{ $status['label'] }}</span>
        </p>
    </div>
</div>
