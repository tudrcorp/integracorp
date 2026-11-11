<div class="space-y-3 text-sm">
    <div class="flex flex-wrap items-center gap-2">
        <span class="rounded-md bg-gray-100 px-2 py-1 font-medium text-gray-600 line-through dark:bg-white/10 dark:text-gray-300">{{ $range->age_init }} a {{ $range->age_end }} años</span>
        <span class="text-gray-400">→</span>
        <span class="rounded-md bg-primary-50 px-2 py-1 font-semibold text-primary-700 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-400/30">{{ $label }} años</span>
    </div>

    @if ($impact['blocking'] !== [])
        <div class="space-y-1 rounded-lg bg-rose-50 px-3 py-2 text-rose-800 ring-1 ring-inset ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-200 dark:ring-rose-400/30">
            <p class="font-semibold">No se puede guardar así:</p>
            <ul class="list-disc space-y-0.5 ps-5">
                @foreach ($impact['blocking'] as $reason)
                    <li>{{ $reason }}</li>
                @endforeach
            </ul>
            @if ($impact['left_out'] !== [])
                <p class="pt-1 font-semibold">Quedarían sin rango{{ $impact['left_out_total'] > count($impact['left_out']) ? ' (primeros '.count($impact['left_out']).' de '.$impact['left_out_total'].')' : '' }}:</p>
                <ul class="list-disc space-y-0.5 ps-5 text-xs">
                    @foreach ($impact['left_out'] as $person)
                        <li>{{ $person }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @elseif (! $impact['changed'])
        <p class="text-gray-500 dark:text-gray-400">Son las mismas edades de hoy: no hay nada que cambiar.</p>
    @else
        <div class="rounded-lg bg-emerald-50 px-3 py-2 text-emerald-800 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-400/30">
            Se puede guardar: ningún afiliado vigente queda sin rango.
            @if ($impact['newly_covered'] > 0)
                <strong>{{ $impact['newly_covered'] }} afiliado(s) que hoy no tienen rango quedarían cubiertos.</strong>
            @endif
        </div>
    @endif

    @if ($impact['usage'] !== [])
        <div>
            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Dónde se usa este rango (todo tomará las edades nuevas)</p>
            <ul class="divide-y divide-gray-950/5 rounded-lg ring-1 ring-gray-950/5 dark:divide-white/10 dark:ring-white/10">
                @foreach ($impact['usage'] as $row)
                    <li class="flex items-start justify-between gap-3 px-3 py-1.5">
                        <span class="text-gray-700 dark:text-gray-200">{{ $row['label'] }}@if ($row['detail'])<span class="block text-xs text-gray-500 dark:text-gray-400">{{ $row['detail'] }}</span>@endif</span>
                        <span class="font-semibold text-gray-950 dark:text-white">{{ $row['count'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <p class="text-xs text-gray-500 dark:text-gray-400">Textos copiados del rango que se actualizan en la misma operación: {{ $impact['text_copies'] }}. Los precios no cambian.</p>
</div>
