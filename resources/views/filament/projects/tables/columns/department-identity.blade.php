@php
    use App\Support\Filament\ProjectManagement\ProjectManagementDepartmentTable;

    $record = $getRecord();
    $color = ProjectManagementDepartmentTable::resolveColor($record);
    $workload = ProjectManagementDepartmentTable::workloadMeta($record);
    $description = ProjectManagementDepartmentTable::normalizeDescriptionText((string) $record->description);
@endphp

<div
    class="fi-projects-department-identity flex w-full max-w-full min-w-0 items-center gap-3 overflow-hidden py-1"
    style="--department-color: {{ $color }};"
>
    <div class="fi-projects-department-identity__accent h-11 w-1.5 shrink-0 rounded-full shadow-sm"></div>

    <div class="fi-projects-department-identity__icon flex size-11 shrink-0 items-center justify-center rounded-2xl border shadow-inner">
        <x-filament::icon icon="heroicon-o-building-office-2" class="size-5" />
    </div>

    <div class="min-w-0 flex-1 overflow-hidden">
        <div class="flex min-w-0 flex-wrap items-center gap-2">
            <p class="min-w-0 break-words text-sm font-semibold leading-snug text-gray-950 line-clamp-2 dark:text-white">
                {{ $record->name }}
            </p>

            <span class="fi-projects-department-identity__badge">
                ID #{{ $record->id }}
            </span>

            @if ($workload['total'] > 0)
                <span @class([
                    'fi-projects-department-identity__flag',
                    'fi-projects-department-identity__flag--active' => $workload['open'] > 0,
                    'fi-projects-department-identity__flag--done' => $workload['open'] === 0,
                ])>
                    {{ $workload['total'] }} {{ $workload['total'] === 1 ? 'actividad' : 'actividades' }}
                </span>
            @endif
        </div>

        <p class="fi-projects-department-identity__description mt-0.5 line-clamp-2 text-xs leading-5 text-gray-500 dark:text-gray-400">
            {{ filled($description) ? $description : 'Sin descripción registrada.' }}
        </p>

        <p class="mt-1.5 text-[11px] font-medium text-gray-400 dark:text-gray-500">
            Unidad organizacional · asignación de actividades por departamento
        </p>
    </div>
</div>
