@php
    use App\Support\Filament\ProjectManagement\ProjectManagementGroupTable;

    $record = $getRecord();
    $color = ProjectManagementGroupTable::resolveColor($record);
    $members = ProjectManagementGroupTable::membersMeta($record);
    $description = ProjectManagementGroupTable::normalizeDescriptionText((string) $record->description);
@endphp

<div class="fi-projects-group-identity flex w-full max-w-full min-w-0 items-center gap-3 overflow-hidden py-1">
    <div
        class="h-11 w-1.5 shrink-0 rounded-full shadow-sm"
        style="background: linear-gradient(180deg, {{ $color }}, {{ $color }}99);"
    ></div>

    <div
        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border shadow-inner"
        style="border-color: {{ $color }}55; background: color-mix(in srgb, {{ $color }} 18%, transparent);"
    >
        <x-filament::icon icon="heroicon-o-user-group" class="size-5" style="color: {{ $color }};" />
    </div>

    <div class="min-w-0 flex-1 overflow-hidden">
        <div class="flex min-w-0 flex-wrap items-center gap-2">
            <p class="min-w-0 break-words text-sm font-semibold leading-snug text-gray-950 line-clamp-2 dark:text-white">
                {{ $record->name }}
            </p>

            <span
                class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                style="background: color-mix(in srgb, {{ $color }} 16%, transparent); color: {{ $color }};"
            >
                {{ $members['label'] }}
            </span>
        </div>

        <p class="fi-projects-group-identity__description mt-0.5 line-clamp-2 text-xs leading-5 text-gray-500 dark:text-gray-400">
            {{ filled($description) ? $description : 'Sin descripción registrada.' }}
        </p>

        <p class="mt-1.5 text-[11px] font-medium text-gray-400 dark:text-gray-500">
            Equipo operativo · ID #{{ $record->id }}
        </p>
    </div>
</div>
