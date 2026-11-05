@php
    /**
     * KPIs de Gestión de Cobranza (Dashboard de Gestión de Cobranza), a hoy, en tres filas:
     *  1. cobrado en el mes, resumen de cobrado del año y morosidad del mes (comparable con otro);
     *  2. todo lo vencido por antigüedad, lo que vence en 7 días y en 15 días;
     *  3. lo que vence en 30, 45 y 60 días.
     *
     * @var array<string, mixed> $collectedTile
     * @var array<string, mixed> $yearTile
     * @var array<string, mixed> $rateTile
     * @var array<string, string> $monthOptions
     * @var list<array<string, mixed>> $tiles
     * @var list<array<string, mixed>> $windows
     */
    $money = static fn (float $amount): string => 'US$ '.number_format($amount, 2, ',', '.');
    $plural = static fn (int $count, string $one, string $many): string => $count === 1 ? '1 '.$one : number_format($count, 0, ',', '.').' '.$many;
    $percent = static fn (float $rate): string => number_format($rate, 2, ',', '.').' %';
    $card = 'relative block overflow-hidden rounded-2xl bg-white px-4 py-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10';
    $yearAccent = '#0d9488';
    $rateAccent = '#be123c';
    $maxMonth = max(1, ...array_map(fn (array $month): float => (float) $month['amount'], $yearTile['months']));
@endphp

<x-filament-widgets::widget class="cxc-management-kpis">
    <div class="flex flex-col gap-4">
        <section class="flex flex-col gap-2">
            <span class="inline-flex items-center gap-1.5 px-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                <x-filament::icon icon="heroicon-m-check-badge" class="size-4" />
                Cobrado
            </span>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                @include('filament.administration.collections.partials.money-tile', ['tile' => $collectedTile, 'index' => 0])

                {{-- Resumen del año: total, parte individual y corporativa, y una barra por mes. --}}
                <div class="{{ $card }}" style="border-top: 3px solid {{ $yearAccent }};">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $yearTile['label'] }}</span>
                        <span class="inline-flex size-7 shrink-0 items-center justify-center rounded-lg" style="color: {{ $yearAccent }}; background: color-mix(in srgb, {{ $yearAccent }} 14%, transparent);">
                            <x-filament::icon icon="heroicon-o-chart-bar" class="size-4" />
                        </span>
                    </div>

                    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-0.5">
                        <span class="text-2xl font-semibold tracking-tight text-gray-950 dark:text-white" style="font-variant-numeric: tabular-nums;">{{ $money($yearTile['amount']) }}</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $plural($yearTile['count'], 'pago', 'pagos') }}</span>
                    </div>

                    <div class="mt-2 flex items-end gap-1" style="height: 44px;" aria-label="Cobrado por mes">
                        @foreach ($yearTile['months'] as $month)
                            @php
                                $height = $month['amount'] > 0 ? max(4, round($month['amount'] * 100 / $maxMonth)) : 2;
                                $isCurrent = $month['month'] === $yearTile['current_month'];
                                $isFuture = $month['month'] > $yearTile['current_month'];
                            @endphp
                            <div class="flex h-full flex-1 flex-col items-center justify-end" title="{{ $month['label'] }}: {{ $money($month['amount']) }} · {{ $plural($month['count'], 'pago', 'pagos') }}">
                                <div class="w-full rounded-sm" style="height: {{ $height }}%; background: color-mix(in srgb, {{ $yearAccent }} {{ $isCurrent ? '100%' : ($isFuture ? '15%' : '55%') }}, transparent);"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-1 flex gap-1 text-center text-[11px] text-gray-400 dark:text-gray-500">
                        @foreach ($yearTile['months'] as $month)
                            <span class="flex-1 {{ $month['month'] === $yearTile['current_month'] ? 'font-semibold text-gray-700 dark:text-gray-200' : '' }}">{{ mb_substr($month['label'], 0, 1) }}</span>
                        @endforeach
                    </div>

                    <div class="mt-2 space-y-1.5">
                        @foreach (['individual' => ['Individuales', '100%'], 'corporate' => ['Corporativas', '45%']] as $type => [$typeLabel, $shade])
                            @php
                                $part = $yearTile['split'][$type];
                            @endphp
                            <div class="flex items-center justify-between gap-3 text-xs leading-4">
                                <span class="flex min-w-0 items-center gap-1.5">
                                    <span class="inline-block size-2 shrink-0 rounded-full" style="background: color-mix(in srgb, {{ $yearAccent }} {{ $shade }}, transparent);"></span>
                                    <span class="min-w-0">
                                        <span class="block font-medium text-gray-600 dark:text-gray-300">{{ $typeLabel }}</span>
                                        <span class="block text-[11px] text-gray-400 dark:text-gray-500">{{ $plural($part['affiliations'], 'afiliación', 'afiliaciones') }} · {{ $plural($part['count'], 'pago', 'pagos') }}</span>
                                    </span>
                                </span>
                                <span class="shrink-0 font-semibold text-gray-950 dark:text-white" style="font-variant-numeric: tabular-nums; white-space: nowrap;">{{ $money((float) $part['amount']) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Morosidad del mes elegido, comparable con otro mes. --}}
                @php
                    $current = $rateTile['current'];
                    $compare = $rateTile['compare'];
                    $delta = $rateTile['delta'];
                    $deltaColor = $delta > 0 ? '#be123c' : ($delta < 0 ? '#15803d' : '#64748b');
                    $range = $current['to'] === null
                        ? 'Aún no hay cuotas vencidas este mes'
                        : 'Cuotas con vencimiento del '.\Carbon\CarbonImmutable::parse($current['from'])->format('d/m').' al '.\Carbon\CarbonImmutable::parse($current['to'])->format('d/m/Y');
                    $select = 'rounded-lg border-0 bg-gray-50 py-1 pl-2 text-xs font-medium text-gray-700 ring-1 ring-gray-950/10 focus:ring-2 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10';
                @endphp
                <div class="{{ $card }}" style="border-top: 3px solid {{ $rateAccent }};" wire:loading.class="opacity-60" wire:target="rateMonth,compareMonth">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400" title="De las cuotas que vencían en el mes, qué parte del monto sigue sin pagar">Índice de morosidad</span>
                        <span class="inline-flex size-7 shrink-0 items-center justify-center rounded-lg" style="color: {{ $rateAccent }}; background: color-mix(in srgb, {{ $rateAccent }} 14%, transparent);">
                            <x-filament::icon icon="heroicon-o-arrow-trending-up" class="size-4" />
                        </span>
                    </div>

                    <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                        <select wire:model.live="rateMonth" class="{{ $select }}" style="padding-right: 1.75rem;" aria-label="Mes de la morosidad">
                            @foreach ($monthOptions as $value => $label)
                                <option value="{{ $value }}" @selected($value === $current['month'])>{{ $label }}</option>
                            @endforeach
                        </select>
                        <span>vs</span>
                        <select wire:model.live="compareMonth" class="{{ $select }}" style="padding-right: 1.75rem;" aria-label="Mes de comparación">
                            @foreach ($monthOptions as $value => $label)
                                <option value="{{ $value }}" @selected($value === $compare['month'])>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-1 flex flex-wrap items-baseline gap-x-3 gap-y-0.5">
                        <span class="text-2xl font-semibold tracking-tight text-gray-950 dark:text-white" style="font-variant-numeric: tabular-nums;">{{ $percent($current['rate']) }}</span>
                        <span class="text-xs font-semibold" style="color: {{ $deltaColor }};" title="{{ $compare['label'] }}: {{ $percent($compare['rate']) }}">
                            {{ $delta > 0 ? '▲ +' : ($delta < 0 ? '▼ ' : '= ') }}{{ number_format($delta, 2, ',', '.') }} pts
                            <span class="font-normal text-gray-400 dark:text-gray-500">vs {{ $compare['label'] }} ({{ $percent($compare['rate']) }})</span>
                        </span>
                    </div>
                    <p class="text-[11px] text-gray-500 dark:text-gray-400">{{ $money($current['unpaid_amount']) }} sin pagar de {{ $money($current['due_amount']) }} · {{ $range }}</p>

                    <div class="mt-2 flex h-1 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10" aria-hidden="true">
                        <div class="h-full" style="width: {{ min(100, $current['rate']) }}%; background: {{ $rateAccent }};"></div>
                    </div>

                    <div class="mt-2 space-y-1.5">
                        @foreach ($rateTile['by_type'] as $position => $part)
                            <div class="flex items-center justify-between gap-3 text-xs leading-4">
                                <span class="flex min-w-0 items-center gap-1.5">
                                    <span class="inline-block size-2 shrink-0 rounded-full" style="background: color-mix(in srgb, {{ $rateAccent }} {{ $position === 0 ? '100%' : '45%' }}, transparent);"></span>
                                    <span class="min-w-0">
                                        <span class="block font-medium text-gray-600 dark:text-gray-300">{{ $part['label'] }}</span>
                                        <span class="block text-[11px] text-gray-400 dark:text-gray-500">{{ $money($part['unpaid']) }} sin pagar de {{ $money($part['due']) }}</span>
                                    </span>
                                </span>
                                <span class="shrink-0 text-right" style="font-variant-numeric: tabular-nums; white-space: nowrap;">
                                    <span class="block font-semibold text-gray-950 dark:text-white">{{ $percent($part['rate']) }}</span>
                                    <span class="block text-[11px] text-gray-400 dark:text-gray-500">vs {{ $percent($part['compare_rate']) }}</span>
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <section class="flex flex-col gap-2">
            <div class="flex flex-wrap items-center justify-between gap-2 px-1">
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    <x-filament::icon icon="heroicon-m-banknotes" class="size-4" />
                    Cuentas por cobrar
                </span>
                <span class="text-[11px] text-gray-400 dark:text-gray-500">Haga clic en una tarjeta para verla en Gestión de Cobranza</span>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                @foreach ($tiles as $index => $tile)
                    @include('filament.administration.collections.partials.money-tile', ['tile' => $tile, 'index' => $index + 3, 'compact' => str_starts_with((string) $tile['key'], 'due-') && $tile['key'] !== 'due-soon'])
                @endforeach
            </div>
        </section>

        @if ($windows !== [])
            <section class="flex flex-col gap-2">
                <span class="inline-flex items-center gap-1.5 px-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    <x-filament::icon icon="heroicon-m-calendar-days" class="size-4" />
                    Por vencer · desde hoy
                </span>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                    @foreach ($windows as $index => $tile)
                        @include('filament.administration.collections.partials.money-tile', ['tile' => $tile, 'index' => $index + 6, 'compact' => true])
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-filament-widgets::widget>
