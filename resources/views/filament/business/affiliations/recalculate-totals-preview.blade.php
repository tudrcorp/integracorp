@php
    $live = $preview['live'];
    $billing = $preview['billing'];
    $money = fn (float $value): string => 'US$ '.number_format($value, 2, ',', '.');
    $comparisonRows = [
        [
            'rowLabel' => 'Población',
            'before' => $live['stored']['persons'].' pers.',
            'after' => $live['persons'].' pers.',
            'changes' => $live['stored']['persons'] !== $live['persons'],
        ],
        [
            'rowLabel' => 'Tarifa anual',
            'before' => $money($live['stored']['fee_anual']),
            'after' => $money($live['fee_anual']),
            'changes' => abs($live['stored']['fee_anual'] - $live['fee_anual']) >= 0.01,
        ],
        [
            'rowLabel' => 'Cuota ('.mb_strtolower((string) $record->payment_frequency).')',
            'before' => $money($live['stored']['total_amount']),
            'after' => $money($live['total_amount']),
            'changes' => abs($live['stored']['total_amount'] - $live['total_amount']) >= 0.01,
        ],
    ];
@endphp

<div class="space-y-3 text-sm" wire:ignore>
    @if ($preview['blocking'])
        <div class="rounded-lg bg-rose-50 px-3 py-2 text-rose-800 ring-1 ring-inset ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-200 dark:ring-rose-400/30">
            <strong>No se puede recalcular:</strong> {{ $preview['blocking'] }}
        </div>
    @elseif (! $live['differs'] && ($billing['collections'] ?? []) === [])
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
                @foreach ($comparisonRows as $comparisonRow)
                    <tr>
                        <td class="px-3 py-2 font-medium text-gray-700 dark:text-gray-200">{{ $comparisonRow['rowLabel'] }}</td>
                        <td class="px-3 py-2 text-gray-500 dark:text-gray-400 {{ $comparisonRow['changes'] ? 'line-through' : '' }}">{{ $comparisonRow['before'] }}</td>
                        <td class="px-3 py-2 {{ $comparisonRow['changes'] ? 'font-semibold text-amber-700 dark:text-amber-300' : 'text-gray-950 dark:text-white' }}">{{ $comparisonRow['after'] }}</td>
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

    <div class="mt-3 text-sm">
        @if ($billing['blocker'] ?? null)
            <p class="rounded-lg bg-rose-50 px-3 py-2 text-rose-800 ring-1 ring-inset ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-200 dark:ring-rose-400/30">{{ $billing['blocker'] }}</p>
        @elseif (($billing['collections'] ?? []) !== [])
            <p class="font-medium text-gray-950 dark:text-white">Avisos POR PAGAR del año que se alinean en Administración:</p>
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
        @foreach ($billing['skipped'] ?? [] as $note)
            <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">{{ $note }}</p>
        @endforeach
    </div>
</div>
