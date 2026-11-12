@php
    $live = $preview['live'];
    $money = fn (float $value): string => 'US$ '.number_format($value, 2, ',', '.');
    $rowsChange = collect($preview['rows'])->contains(fn (array $row): bool => $row['before'] !== $row['after']);
@endphp

<div class="space-y-3 text-sm">
    @if ($preview['blocking'])
        <div class="rounded-lg bg-rose-50 px-3 py-2 text-rose-800 ring-1 ring-inset ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-200 dark:ring-rose-400/30">
            <strong>No se puede recalcular:</strong> {{ $preview['blocking'] }}
        </div>
    @elseif (! $live['differs'] && ! ($preview['rows_updatable'] && $rowsChange))
        <div class="rounded-lg bg-emerald-50 px-3 py-2 text-emerald-800 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-400/30">
            Los totales guardados ya cuadran con la población activa. No hay nada que recalcular.
        </div>
    @endif

    <div class="overflow-x-auto rounded-lg ring-1 ring-gray-950/5 dark:ring-white/10">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs text-gray-500 dark:bg-white/5 dark:text-gray-400">
                <tr>
                    <th class="px-3 py-2 font-medium">Dato</th>
                    <th class="px-3 py-2 font-medium">Guardado hoy</th>
                    <th class="px-3 py-2 font-medium">Con la población activa</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-950/5 dark:divide-white/10">
                @foreach ([
                    ['Población', $live['stored']['persons'].' pers.', $live['persons'].' pers.', $live['stored']['persons'] !== $live['persons']],
                    ['Tarifa anual', $money($live['stored']['fee_anual']), $money($live['fee_anual']), abs($live['stored']['fee_anual'] - $live['fee_anual']) >= 0.01],
                    ['Cuota ('.mb_strtolower((string) $record->payment_frequency).')', $money($live['stored']['total_amount']), $money($live['total_amount']), abs($live['stored']['total_amount'] - $live['total_amount']) >= 0.01],
                ] as [$label, $before, $after, $changes])
                    <tr>
                        <td class="px-3 py-2 font-medium text-gray-700 dark:text-gray-200">{{ $label }}</td>
                        <td class="px-3 py-2 text-gray-500 dark:text-gray-400 {{ $changes ? 'line-through' : '' }}">{{ $before }}</td>
                        <td class="px-3 py-2 {{ $changes ? 'font-semibold text-amber-700 dark:text-amber-300' : 'text-gray-950 dark:text-white' }}">{{ $after }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($live['without_fee'] > 0)
        <p class="rounded-lg bg-amber-50 px-3 py-2 text-amber-900 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-400/30">
            {{ $live['without_fee'] }} afiliado(s) activo(s) tienen tarifa 0: cuentan en la población pero no suman a la tarifa. Revíselos si deberían pagar.
        </p>
    @endif

    @if ($preview['rows'] !== [])
        <div>
            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Personas por fila de plan</p>
            <ul class="divide-y divide-gray-950/5 rounded-lg ring-1 ring-gray-950/5 dark:divide-white/10 dark:ring-white/10">
                @foreach ($preview['rows'] as $row)
                    <li class="flex items-center justify-between px-3 py-1.5">
                        <span class="text-gray-700 dark:text-gray-200">{{ $row['label'] }} · {{ $money($row['fee']) }}</span>
                        <span>{{ $row['before'] }} → <strong>{{ $preview['rows_updatable'] ? $row['after'] : $row['before'] }}</strong></span>
                    </li>
                @endforeach
            </ul>
            @if (! $preview['rows_updatable'] && $preview['unresolved_total'] > 0)
                <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">
                    Las filas de plan no se tocarán: {{ $preview['unresolved_total'] }} afiliado(s) activo(s) no caen en ninguna fila (sin edad o fuera de rango){{ $preview['unresolved'] !== [] ? ': '.implode(', ', $preview['unresolved']).($preview['unresolved_total'] > count($preview['unresolved']) ? '…' : '') : '' }}.
                </p>
            @endif
        </div>
    @endif

    @isset($billing)
        <div class="mt-3 text-sm">
            @if ($billing['blocker'])
                <p class="rounded-lg bg-rose-50 px-3 py-2 text-rose-800 ring-1 ring-inset ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-200 dark:ring-rose-400/30">{{ $billing['blocker'] }}</p>
            @elseif ($billing['collections'] !== [])
                <p class="font-medium text-gray-950 dark:text-white">Avisos POR PAGAR del año que se alinean:</p>
                <ul class="mt-1 divide-y divide-gray-950/5 rounded-lg ring-1 ring-gray-950/5 dark:divide-white/10 dark:ring-white/10">
                    @foreach ($billing['collections'] as $collection)
                        <li class="flex items-center justify-between px-3 py-1.5" wire:key="rt-collection-{{ $collection['id'] }}">
                            <span class="text-gray-700 dark:text-gray-200">{{ $collection['invoice'] }} · vence {{ $collection['due'] ?? '—' }}</span>
                            <span>{{ $money($collection['before_cents'] / 100) }} → <strong>{{ $money($collection['after_cents'] / 100) }}</strong> · {{ $collection['persons_after'] }} pers.</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-gray-500 dark:text-gray-400">Los avisos POR PAGAR del año ya cuadran con la población activa.</p>
            @endif
            @foreach ($billing['skipped'] as $note)
                <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">{{ $note }}</p>
            @endforeach
        </div>
    @endisset
</div>
