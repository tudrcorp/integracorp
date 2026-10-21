@php
    $value = $record !== null && filled($record->specific_business_unit) ? (string) $record->specific_business_unit : null;
@endphp

<div class="grid gap-2 sm:grid-cols-2">
    <div class="rounded-lg bg-gray-50 px-3 py-2 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
        <p class="text-[0.65rem] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
            {{ $record !== null ? 'Hoy tiene' : 'Seleccionados' }}
        </p>
        <p class="mt-0.5 text-sm font-semibold text-gray-950 dark:text-white">
            @if ($record === null)
                {{ $count }} afiliado(s)
            @else
                {{ $value ?? 'Sin unidad específica' }}
            @endif
        </p>
        @if ($locked)
            <p class="mt-0.5 text-xs text-primary-600 dark:text-primary-400">
                Fijada a mano por {{ $record->specific_business_unit_locked_by ?: 'un analista' }}
                @if (filled($record->specific_business_unit_locked_at))
                    el {{ \Illuminate\Support\Carbon::parse($record->specific_business_unit_locked_at)->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                @endif
            </p>
        @elseif ($record !== null)
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Sigue a la afiliación</p>
        @endif
    </div>

    <div class="rounded-lg bg-gray-50 px-3 py-2 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
        <p class="text-[0.65rem] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">La afiliación tiene</p>
        <p class="mt-0.5 text-sm font-semibold text-gray-950 dark:text-white">{{ $ownerValue ?? 'Sin unidad específica' }}</p>
    </div>
</div>
