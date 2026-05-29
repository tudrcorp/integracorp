@php
    use App\Support\Filament\ProjectManagement\ProjectManagementProjectTable;

    $status = ProjectManagementProjectTable::statusMeta((string) $record->status);
    $delayDays = ProjectManagementProjectTable::delayDays($record);
    $color = ProjectManagementProjectTable::resolveColor($record);
    $icon = ProjectManagementProjectTable::resolveIcon($record);
@endphp

<div class="overflow-hidden rounded-2xl border border-gray-200/90 bg-gradient-to-br from-slate-50 via-white to-slate-100 p-4 shadow-sm dark:border-white/10 dark:from-slate-900/90 dark:via-slate-950 dark:to-slate-900/80">
    <div class="flex items-start gap-3">
        <div
            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border shadow-inner"
            style="border-color: {{ $color }}55; background: color-mix(in srgb, {{ $color }} 16%, transparent);"
        >
            <x-filament::icon :icon="$icon" class="size-6" style="color: {{ $color }};" />
        </div>

        <div class="min-w-0 flex-1">
            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                Proyecto seleccionado
            </p>
            <p class="mt-0.5 truncate text-base font-semibold text-gray-950 dark:text-white">
                {{ $record->name }}
            </p>
            <p class="mt-1 line-clamp-2 text-xs text-gray-500 dark:text-gray-400">
                {{ filled($record->description) ? $record->description : 'Sin descripción registrada.' }}
            </p>
        </div>
    </div>

    <div class="mt-4 grid gap-2 sm:grid-cols-3">
        <div class="rounded-xl border border-gray-200/80 bg-white/70 px-3 py-2 dark:border-white/10 dark:bg-white/5">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Estatus actual</p>
            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $status['label'] }}</p>
        </div>

        <div class="rounded-xl border border-gray-200/80 bg-white/70 px-3 py-2 dark:border-white/10 dark:bg-white/5">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Fecha fin</p>
            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                {{ $record->end_date?->format('d/m/Y') ?? 'Sin definir' }}
            </p>
        </div>

        <div @class([
            'rounded-xl border px-3 py-2',
            'border-danger-500/30 bg-danger-500/10' => $delayDays !== null,
            'border-gray-200/80 bg-white/70 dark:border-white/10 dark:bg-white/5' => $delayDays === null,
        ])>
            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Retraso</p>
            <p @class([
                'mt-1 text-sm font-semibold',
                'text-danger-600 dark:text-danger-400' => $delayDays !== null,
                'text-gray-900 dark:text-white' => $delayDays === null,
            ])>
                @if ($delayDays !== null)
                    {{ $delayDays }} día{{ $delayDays === 1 ? '' : 's' }}
                @else
                    Al día
                @endif
            </p>
        </div>
    </div>

    <p class="mt-3 text-xs leading-5 text-gray-500 dark:text-gray-400">
        El cambio de estatus se refleja de inmediato en la tabla, el cronograma y los accesos rápidos del panel.
    </p>
</div>
