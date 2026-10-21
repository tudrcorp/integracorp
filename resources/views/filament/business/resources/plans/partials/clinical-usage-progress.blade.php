@php
    /** @var array{total: int, complete: int, pending: int, not_clinical: int, new: int} $summary */
    $percent = $summary['total'] > 0 ? (int) floor(($summary['complete'] / $summary['total']) * 100) : 0;
    $ready = $summary['total'] > 0 && $summary['pending'] === 0;
@endphp

<div class="space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="min-w-0">
            <p class="text-sm font-semibold text-gray-950 dark:text-white">
                @if ($summary['total'] === 0)
                    Este plan aún no tiene beneficios
                @elseif ($ready)
                    Todo listo: {{ $summary['complete'] }} de {{ $summary['total'] }} beneficios configurados
                @else
                    {{ $summary['complete'] }} de {{ $summary['total'] }} beneficios listos · faltan {{ $summary['pending'] }}
                @endif
            </p>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                @if ($summary['total'] === 0)
                    Use «Agregar beneficio» o «Copiar de otro plan» para empezar.
                @elseif ($ready)
                    Revise y pulse «Guardar uso clínico». El médico verá los servicios en cuanto quede guardado.
                @else
                    Las filas en ámbar le dicen qué falta. El médico no verá los servicios de tipo 1 hasta que todas estén listas.
                @endif
            </p>
        </div>

        <div class="flex flex-wrap gap-1.5 text-xs">
            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/30">{{ $summary['complete'] }} listos</span>
            @if ($summary['pending'] > 0)
                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 font-medium text-amber-800 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/30">{{ $summary['pending'] }} pendientes</span>
            @endif
            @if ($summary['not_clinical'] > 0)
                <span class="inline-flex items-center gap-1 rounded-full bg-gray-50 px-2.5 py-1 font-medium text-gray-600 ring-1 ring-inset ring-gray-500/20 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">{{ $summary['not_clinical'] }} no aplican</span>
            @endif
            @if ($summary['new'] > 0)
                <span class="inline-flex items-center gap-1 rounded-full bg-sky-50 px-2.5 py-1 font-medium text-sky-700 ring-1 ring-inset ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-400/30">{{ $summary['new'] }} nuevos sin guardar</span>
            @endif
        </div>
    </div>

    <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">
        <div @class([
            'h-full rounded-full transition-all duration-500',
            'bg-emerald-500' => $ready,
            'bg-amber-400' => ! $ready,
        ]) style="width: {{ $percent }}%"></div>
    </div>

    @if ($hasUnsavedWork)
        <div class="flex items-start gap-2 rounded-lg bg-sky-50 px-3 py-2 text-xs text-sky-800 ring-1 ring-inset ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-200 dark:ring-sky-400/30">
            <span aria-hidden="true">ℹ</span>
            <span>
                Tiene cambios sin guardar{{ $copiedFrom ? ' (copia de '.$copiedFrom.')' : '' }}.
                Nada se escribe hasta que pulse «Guardar uso clínico». Si sale ahora, se pierden.
            </span>
        </div>
    @endif

    <ol class="grid gap-2 text-xs text-gray-600 sm:grid-cols-3 dark:text-gray-300">
        <li class="flex items-start gap-2 rounded-lg bg-gray-50 px-3 py-2 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
            <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-primary-600 text-[0.65rem] font-bold text-white">1</span>
            <span><strong class="text-gray-900 dark:text-white">Parta de un plan parecido.</strong> «Copiar de otro plan» llena las filas en segundos.</span>
        </li>
        <li class="flex items-start gap-2 rounded-lg bg-gray-50 px-3 py-2 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
            <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-primary-600 text-[0.65rem] font-bold text-white">2</span>
            <span><strong class="text-gray-900 dark:text-white">Ajuste lo que difiera.</strong> Servicio, servicio macro y cupo en la misma fila.</span>
        </li>
        <li class="flex items-start gap-2 rounded-lg bg-gray-50 px-3 py-2 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
            <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-primary-600 text-[0.65rem] font-bold text-white">3</span>
            <span><strong class="text-gray-900 dark:text-white">Guarde.</strong> Todo entra junto o nada entra, y queda en el historial.</span>
        </li>
    </ol>
</div>
