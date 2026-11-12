@php
    $money = fn ($v) => 'US$ '.number_format((float) $v, 2, ',', '.');
    $totals = (array) ($movement->effects['totals'] ?? []);
@endphp
<div class="space-y-4 text-sm">
    <dl class="grid grid-cols-2 gap-3">
        <div><dt class="text-xs uppercase text-gray-500 dark:text-gray-400">Fecha efectiva</dt><dd class="font-semibold">{{ $movement->effective_date?->format('d/m/Y') }}</dd></div>
        <div><dt class="text-xs uppercase text-gray-500 dark:text-gray-400">Registrado por</dt><dd class="font-semibold">{{ $movement->created_by_name }} · {{ $movement->created_at?->format('d/m/Y H:i') }}</dd></div>
        <div><dt class="text-xs uppercase text-gray-500 dark:text-gray-400">Tarifa anual de la persona</dt><dd class="font-semibold">{{ $money($movement->annual_fee) }} · cuota {{ $money($movement->period_fee) }}</dd></div>
        <div><dt class="text-xs uppercase text-gray-500 dark:text-gray-400">Prorrateo</dt><dd class="font-semibold">{{ $movement->prorated_days }} de {{ $movement->period_days }} días · {{ $money($movement->prorated_amount) }}</dd></div>
    </dl>
    <p><span class="text-xs uppercase text-gray-500 dark:text-gray-400">Motivo:</span> {{ $movement->reason }}</p>

    @if (isset($totals['before'], $totals['after']))
        <div class="rounded-lg ring-1 ring-gray-950/5 dark:ring-white/10">
            <p class="border-b border-gray-950/5 px-3 py-2 font-semibold dark:border-white/10">Afiliación</p>
            <p class="px-3 py-2">Población {{ $totals['before']['persons'] }} → <strong>{{ $totals['after']['persons'] }}</strong> · Tarifa anual {{ $money($totals['before']['fee_anual']) }} → <strong>{{ $money($totals['after']['fee_anual']) }}</strong> · Cuota {{ $money($totals['before']['total_amount']) }} → <strong>{{ $money($totals['after']['total_amount']) }}</strong></p>
        </div>
    @endif

    <div class="rounded-lg ring-1 ring-gray-950/5 dark:ring-white/10">
        <p class="border-b border-gray-950/5 px-3 py-2 font-semibold dark:border-white/10">Avisos de cobro (lo que verá el cliente)</p>
        @forelse ($movement->collectionLines as $line)
            <div class="flex justify-between gap-3 px-3 py-1.5 {{ $line->reverted ? 'text-gray-400 line-through' : '' }}">
                <span>{{ $line->collection?->collection_invoice_number }} · {{ $line->description }}</span>
                <span class="font-semibold">{{ $money($line->amount) }}</span>
            </div>
        @empty
            <p class="px-3 py-2 text-gray-500">{{ $movement->billing_note ?? 'No hubo avisos que ajustar.' }}</p>
        @endforelse
        @if ((float) $movement->pending_credit > 0)
            <p class="px-3 py-2 text-amber-700 dark:text-amber-300">Crédito pendiente: {{ $money($movement->pending_credit) }} (se descuenta en el próximo aviso que se emita).</p>
        @endif
    </div>

    <div class="rounded-lg ring-1 ring-gray-950/5 dark:ring-white/10">
        <p class="border-b border-gray-950/5 px-3 py-2 font-semibold dark:border-white/10">Operaciones</p>
        <p class="px-3 py-2">
            @php($patients = (array) ($movement->effects['patients'] ?? []))
            {{ $patients === [] ? 'Sin paciente de telemedicina ligado en esta afiliación.' : count($patients).' paciente(s) de telemedicina: '.collect($patients)->map(fn ($p) => '#'.$p['id'].' '.$p['before'].' → '.$p['after'])->implode(', ') }}
        </p>
    </div>

    @if ($movement->reverted_at)
        <div class="rounded-lg bg-gray-50 px-3 py-2 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
            <strong>Revertido</strong> por {{ $movement->reverted_by_name }} el {{ $movement->reverted_at->format('d/m/Y H:i') }}. Motivo: {{ $movement->revert_reason }}
        </div>
    @endif
</div>
