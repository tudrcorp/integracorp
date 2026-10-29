@php
    /** @var array<string, mixed> $overview */
    $money = static function (float $usd, float $ves): string {
        $parts = [];

        if ($usd > 0 || $ves <= 0) {
            $parts[] = 'US$ '.number_format($usd, 2, ',', '.');
        }

        if ($ves > 0) {
            $parts[] = 'Bs. '.number_format($ves, 2, ',', '.');
        }

        return implode(' + ', $parts);
    };
    $card = 'group flex w-full flex-col items-start gap-1 rounded-2xl border bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 disabled:cursor-default dark:bg-white/5';
@endphp

<x-filament-widgets::widget>
    <div class="space-y-3">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <button type="button" wire:click="shortcut('pending')" class="{{ $card }} border-rose-200 dark:border-rose-500/30" title="Ver las facturas por pagar">
                <span class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-rose-700 dark:text-rose-300">
                    <x-filament::icon icon="heroicon-m-exclamation-circle" class="size-4" /> Por pagar
                </span>
                <span class="text-2xl font-bold text-slate-900 dark:text-white">{{ $money($overview['pending']['usd'], $overview['pending']['ves']) }}</span>
                <span class="text-xs text-slate-500 dark:text-slate-400">{{ $overview['pending']['count'] }} {{ $overview['pending']['count'] === 1 ? 'factura pendiente o en gestión' : 'facturas pendientes o en gestión' }}</span>
            </button>

            <button type="button" wire:click="shortcut('paid_month')" class="{{ $card }} border-emerald-200 dark:border-emerald-500/30" title="Ver lo pagado este mes">
                <span class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">
                    <x-filament::icon icon="heroicon-m-check-badge" class="size-4" /> Pagado en {{ \Illuminate\Support\Str::ucfirst(now()->locale('es')->translatedFormat('F')) }}
                </span>
                <span class="text-2xl font-bold text-slate-900 dark:text-white">{{ $money($overview['paid_month']['usd'], $overview['paid_month']['ves']) }}</span>
                <span class="text-xs text-slate-500 dark:text-slate-400">{{ $overview['paid_month']['count'] }} {{ $overview['paid_month']['count'] === 1 ? 'factura pagada' : 'facturas pagadas' }} este mes</span>
            </button>

            <button type="button" wire:click="shortcut('paid_without_receipt')" @class([$card, 'border-amber-200 dark:border-amber-500/30' => $overview['paid_without_receipt'] > 0, 'border-slate-200 dark:border-white/10' => $overview['paid_without_receipt'] === 0]) title="Ver las pagadas sin comprobante">
                <span class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">
                    <x-filament::icon icon="heroicon-m-paper-clip" class="size-4" /> Pagadas sin comprobante
                </span>
                <span class="text-2xl font-bold text-slate-900 dark:text-white">{{ $overview['paid_without_receipt'] }}</span>
                <span class="text-xs text-slate-500 dark:text-slate-400">{{ $overview['paid_without_receipt'] === 0 ? 'Todo pago tiene su soporte' : 'Adjunte el comprobante de pago' }}</span>
            </button>

            <button type="button" wire:click="shortcut('all')" class="{{ $card }} border-slate-200 dark:border-white/10" title="Quitar los filtros de las tarjetas">
                <span class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">
                    <x-filament::icon icon="heroicon-m-queue-list" class="size-4" /> Ver todas
                </span>
                <span class="text-sm text-slate-600 dark:text-slate-300">Quita los filtros de las tarjetas y muestra todas las facturas.</span>
            </button>
        </div>

        <div>
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Por pagar según el servicio</p>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($overview['pending_by_type'] as $type)
                    <button
                        type="button"
                        wire:click="shortcut('type:{{ $type['key'] }}')"
                        wire:key="ap-type-{{ $type['key'] }}"
                        class="{{ $card }} border-slate-200 dark:border-white/10"
                        title="Ver lo pendiente de {{ mb_strtolower($type['label'], 'UTF-8') }}"
                    >
                        <span class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                            <x-filament::icon :icon="$type['icon']" @class(['size-5', 'text-sky-600 dark:text-sky-300' => $type['color'] === 'info', 'text-primary-600 dark:text-primary-300' => $type['color'] === 'primary', 'text-emerald-600 dark:text-emerald-300' => $type['color'] === 'success', 'text-amber-600 dark:text-amber-300' => $type['color'] === 'warning']) />
                            {{ $type['label'] }}
                        </span>
                        <span class="text-lg font-bold text-slate-900 dark:text-white">{{ $money($type['usd'], $type['ves']) }}</span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ $type['count'] }} {{ $type['count'] === 1 ? 'factura' : 'facturas' }} por pagar</span>
                    </button>
                @endforeach
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
