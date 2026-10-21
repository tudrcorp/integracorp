@php
    /** @var \Illuminate\Database\Eloquent\Model $record */
    /** @var list<string> $blockers */
    $fact = 'text-[0.65rem] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
    $value = 'mt-0.5 text-sm font-semibold text-gray-950 dark:text-white';
@endphp

<div class="space-y-3">
    @if ($blockers !== [])
        <div class="rounded-lg bg-rose-50 px-3 py-3 text-sm text-rose-900 ring-1 ring-inset ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-200 dark:ring-rose-400/30">
            <p class="font-semibold">Este pago no se puede eliminar</p>
            <ul class="mt-1.5 list-disc space-y-1 ps-5">
                @foreach ($blockers as $blocker)
                    <li>{{ $blocker }}</li>
                @endforeach
            </ul>
            <p class="mt-2 text-xs">Cuando Administración lo resuelva, vuelva aquí: la acción se habilitará sola.</p>
        </div>
    @else
        <div class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-400/30">
            El pago desaparece del historial y deja de contar como pendiente. No toca ventas, cobranza ni comisiones: no tiene ninguna.
            El comprobante se resguarda en un archivo privado y todo queda en la traza con su nota.
        </div>
    @endif

    <div class="grid gap-2 sm:grid-cols-3">
        <div class="rounded-lg bg-gray-50 px-3 py-2 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
            <p class="{{ $fact }}">Cargado</p>
            <p class="{{ $value }}">{{ $record->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">Por {{ $record->created_by ?: '—' }}</p>
        </div>
        <div class="rounded-lg bg-gray-50 px-3 py-2 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
            <p class="{{ $fact }}">Monto</p>
            <p class="{{ $value }}">
                @if ((float) $record->pay_amount_usd > 0)
                    {{ number_format((float) $record->pay_amount_usd, 2, ',', '.') }} US$
                @endif
                @if ((float) $record->pay_amount_ves > 0)
                    Bs. {{ number_format((float) $record->pay_amount_ves, 2, ',', '.') }}
                @endif
                @if ((float) $record->pay_amount_usd <= 0 && (float) $record->pay_amount_ves <= 0)
                    —
                @endif
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $record->payment_method ?: '—' }}</p>
        </div>
        <div class="rounded-lg bg-gray-50 px-3 py-2 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
            <p class="{{ $fact }}">Referencia</p>
            <p class="{{ $value }}">{{ $references ?? '—' }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $voucher }}</p>
        </div>
    </div>
</div>
