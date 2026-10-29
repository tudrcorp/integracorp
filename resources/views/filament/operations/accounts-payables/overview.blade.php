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
    @php
        /** @var array<string, mixed> $loaded */
        $loadedTotals = $loaded['totals'];
        $previousTotals = $loaded['previous']['totals'];
        $delta = $loaded['delta'];
        $periodLabel = $loaded['is_current']
            ? 'del 1 al '.$loaded['until']->format('j').' de '.mb_strtolower($loaded['label'], 'UTF-8')
            : 'en '.mb_strtolower($loaded['label'], 'UTF-8');
        $previousPeriodLabel = $loaded['previous']['is_partial']
            ? mb_strtolower($loaded['previous']['label'], 'UTF-8').', del 1 al '.$loaded['previous']['until']->format('j')
            : mb_strtolower($loaded['previous']['label'], 'UTF-8').' completo';
        $signed = static fn (float $value, int $decimals = 2): string => ($value > 0 ? '+' : ($value < 0 ? '−' : '')).number_format(abs($value), $decimals, ',', '.');
    @endphp

    <div
        x-data="{
            open: false,
            storageKey: 'cxp-resumen-abierto',
            init() {
                try { this.open = window.localStorage.getItem(this.storageKey) === '1'; } catch (error) { this.open = false; }
            },
            toggle() {
                this.open = ! this.open;
                try { window.localStorage.setItem(this.storageKey, this.open ? '1' : '0'); } catch (error) {}
            },
        }"
        class="space-y-3"
    >
        <button
            type="button"
            x-on:click="toggle()"
            x-bind:aria-expanded="open ? 'true' : 'false'"
            aria-controls="cxp-resumen"
            class="flex w-full flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-left shadow-sm transition hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-white/10 dark:bg-white/5"
        >
            <span class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <span class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                    <x-filament::icon icon="heroicon-m-chart-bar" class="size-5 text-primary-600 dark:text-primary-400" /> Resumen de cuentas por pagar
                </span>
                <span x-show="! open" class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="rounded-full bg-rose-50 px-2 py-0.5 font-medium text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">Por pagar: {{ $money($overview['pending']['usd'], $overview['pending']['ves']) }}</span>
                    <span class="rounded-full bg-sky-50 px-2 py-0.5 font-medium text-sky-700 dark:bg-sky-500/10 dark:text-sky-300">Cargadas en {{ mb_strtolower($loaded['label'], 'UTF-8') }}: {{ $money($loadedTotals['usd'], $loadedTotals['ves']) }}</span>
                </span>
            </span>
            <span class="flex items-center gap-1 text-xs font-medium text-slate-500 dark:text-slate-400">
                <span x-text="open ? 'Ocultar' : 'Mostrar'">Mostrar</span>
                <x-filament::icon icon="heroicon-m-chevron-down" class="size-4 transition" x-bind:class="open && 'rotate-180'" />
            </span>
        </button>

        <div id="cxp-resumen" x-show="open" x-collapse x-cloak class="space-y-3">

            <div class="relative rounded-2xl border border-sky-200 bg-white p-4 shadow-sm dark:border-sky-500/30 dark:bg-white/5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-300">
                        <x-filament::icon icon="heroicon-m-document-arrow-up" class="size-4" /> Facturas cargadas
                    </span>

                    <div class="flex flex-wrap items-center gap-3">
                        <label class="flex items-center gap-2 text-xs font-medium text-slate-600 dark:text-slate-300">
                            <x-filament::input.checkbox wire:model.live="compareLoadedMonth" />
                            Comparar con el mes anterior
                        </label>

                        <x-filament::input.wrapper class="w-44">
                            <x-filament::input.select wire:model.live="loadedMonth" aria-label="Mes de carga">
                                @foreach ($monthOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                </div>

                <div class="mt-3 grid items-start gap-4 transition-opacity sm:grid-cols-2" wire:loading.class="opacity-50" wire:target="loadedMonth, compareLoadedMonth">
                    <button
                        type="button"
                        wire:click="shortcut('loaded:{{ $loaded['month'] }}')"
                        class="group flex flex-col items-start gap-1 rounded-xl text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                        title="Ver en la tabla las facturas cargadas {{ $periodLabel }}"
                    >
                        <span class="text-2xl font-bold text-slate-900 dark:text-white">{{ $money($loadedTotals['usd'], $loadedTotals['ves']) }}</span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">
                            {{ $loadedTotals['count'] }} {{ $loadedTotals['count'] === 1 ? 'factura cargada' : 'facturas cargadas' }} {{ $periodLabel }}
                        </span>
                        <span class="text-xs font-medium text-primary-600 group-hover:text-primary-700 dark:text-primary-400">Ver estas facturas en la tabla</span>
                    </button>

                    @if ($compareLoadedMonth)
                        <div class="flex flex-col items-start gap-1 rounded-xl bg-slate-50 p-3 dark:bg-white/5">
                            <span class="text-xs text-slate-500 dark:text-slate-400">Frente a {{ $previousPeriodLabel }}</span>
                            <span class="text-lg font-semibold text-slate-700 dark:text-slate-200">{{ $money($previousTotals['usd'], $previousTotals['ves']) }} · {{ $previousTotals['count'] }} {{ $previousTotals['count'] === 1 ? 'factura' : 'facturas' }}</span>

                            <span class="flex flex-wrap items-center gap-2 text-xs">
                                @if ($delta['usd'] === 0.0 && $delta['count'] === 0)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 font-semibold text-slate-600 dark:bg-white/10 dark:text-slate-300">Sin cambios</span>
                                @else
                                    <span @class([
                                        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-semibold',
                                        'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300' => $delta['usd'] >= 0,
                                        'bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-slate-300' => $delta['usd'] < 0,
                                    ])>
                                        <x-filament::icon :icon="$delta['usd'] >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down'" class="size-4" />
                                        US$ {{ $signed($delta['usd']) }}
                                        @if ($delta['usd_percent'] !== null)
                                            ({{ $signed($delta['usd_percent'], 1) }} %)
                                        @endif
                                    </span>
                                    <span class="text-slate-500 dark:text-slate-400">{{ $signed((float) $delta['count'], 0) }} {{ abs($delta['count']) === 1 ? 'factura' : 'facturas' }}</span>
                                @endif
                            </span>

                            @if ($loaded['previous']['is_partial'])
                                <span class="text-xs text-slate-400 dark:text-slate-500">
                                    {{ $loaded['previous']['label'] }} completo: {{ $money($loaded['previous']['full_totals']['usd'], $loaded['previous']['full_totals']['ves']) }} · {{ $loaded['previous']['full_totals']['count'] }} {{ $loaded['previous']['full_totals']['count'] === 1 ? 'factura' : 'facturas' }}
                                </span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

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
    </div>
</x-filament-widgets::widget>
