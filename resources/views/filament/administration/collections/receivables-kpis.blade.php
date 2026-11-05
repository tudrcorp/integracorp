@php
    /**
     * KPIs de cobranza (Dashboard de Cobranza), a hoy, en tres filas:
     *  1. lo que vence en 15, 30, 45 y 60 días;
     *  2. total por cobrar, vencido en los últimos 30 días y lo que vence en 7 días;
     *  3. recibos (avisos de cobro) emitidos del mes y del año.
     * Cada monto va separado en individuales y corporativas; los montos por cobrar abren
     * Cuentas por cobrar con su filtro de vencimiento.
     *
     * @var list<array<string, mixed>> $windows
     * @var list<array<string, mixed>> $tiles
     * @var list<array<string, mixed>> $noticeTiles
     */
    $money = static fn (float $amount): string => 'US$ '.number_format($amount, 2, ',', '.');
    $plural = static fn (int $count, string $one, string $many): string => $count === 1 ? '1 '.$one : number_format($count, 0, ',', '.').' '.$many;
    $types = ['individual' => ['Individuales', '100%'], 'corporate' => ['Corporativas', '45%']];
@endphp

<x-filament-widgets::widget class="cxc-receivables-kpis">
    <div class="flex flex-col gap-4">
        @if ($windows !== [])
            <section class="flex flex-col gap-2">
                <div class="flex flex-wrap items-center justify-between gap-2 px-1">
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <x-filament::icon icon="heroicon-m-calendar-days" class="size-4" />
                        Por vencer · desde hoy
                    </span>
                    <span class="text-[11px] text-gray-400 dark:text-gray-500">Haga clic en una tarjeta para verla en Cuentas por cobrar</span>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($windows as $index => $tile)
                        @include('filament.administration.collections.partials.money-tile', ['tile' => $tile, 'index' => $index, 'compact' => true])
                    @endforeach
                </div>
            </section>
        @endif

        <section class="flex flex-col gap-2">
            <span class="inline-flex items-center gap-1.5 px-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                <x-filament::icon icon="heroicon-m-banknotes" class="size-4" />
                Cuentas por cobrar
            </span>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                @foreach ($tiles as $index => $tile)
                    @include('filament.administration.collections.partials.money-tile', ['tile' => $tile, 'index' => $index + 4])
                @endforeach
            </div>
        </section>

        <section class="flex flex-col gap-2">
            <span class="inline-flex items-center gap-1.5 px-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                <x-filament::icon icon="heroicon-m-document-text" class="size-4" />
                Recibos emitidos
            </span>

            @if (($noticeTiles ?? []) !== [])
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    @foreach ($noticeTiles as $index => $tile)
                        @php
                            $formattedCount = number_format($tile['count'], 0, ',', '.');
                            $individual = $tile['split']['individual'];
                            $corporate = $tile['split']['corporate'];
                            $individualShare = $tile['count'] > 0 ? round($individual['count'] * 100 / $tile['count'], 1) : 0;
                        @endphp

                        <div
                            title="{{ $tile['title'] }}"
                            wire:key="cxc-kpi-{{ $tile['key'] }}-{{ $tile['count'] }}"
                            x-data="{
                                shown: false,
                                display: @js($formattedCount),
                                target: @js($tile['count']),
                                run() {
                                    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || this.target === 0) { return; }
                                    const formatter = new Intl.NumberFormat('es-VE', { maximumFractionDigits: 0 });
                                    const started = performance.now();
                                    const step = (now) => {
                                        const progress = Math.min(1, (now - started) / 1100);
                                        this.display = formatter.format(Math.round(this.target * (1 - Math.pow(1 - progress, 4))));
                                        if (progress < 1) { requestAnimationFrame(step); }
                                    };
                                    requestAnimationFrame(step);
                                },
                            }"
                            x-intersect.once="shown = true; run()"
                            x-bind:class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-2'"
                            style="transition: opacity .45s ease, transform .45s ease; transition-delay: {{ ($index + 7) * 60 }}ms; border-top: 3px solid {{ $tile['accent'] }};"
                            class="relative overflow-hidden rounded-2xl bg-white px-4 py-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $tile['label'] }}</span>
                                <span
                                    class="inline-flex size-7 shrink-0 items-center justify-center rounded-lg"
                                    style="color: {{ $tile['accent'] }}; background: color-mix(in srgb, {{ $tile['accent'] }} 14%, transparent);"
                                >
                                    <x-filament::icon :icon="$tile['icon']" class="size-4" />
                                </span>
                            </div>

                            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-0.5">
                                <span class="text-2xl font-semibold tracking-tight text-gray-950 dark:text-white" style="font-variant-numeric: tabular-nums;">
                                    <span x-text="display">{{ $formattedCount }}</span>
                                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $tile['count'] === 1 ? 'aviso de cobro' : 'avisos de cobro' }}</span>
                                </span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $tile['detail'] }}</span>
                            </div>

                            <div class="mt-2 flex h-1 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10" aria-hidden="true">
                                @if ($tile['count'] > 0)
                                    <div class="h-full" x-bind:style="'width: ' + (shown ? {{ $individualShare }} : 0) + '%; background: {{ $tile['accent'] }}; transition: width .9s ease;'"></div>
                                    <div class="h-full" x-bind:style="'width: ' + (shown ? {{ 100 - $individualShare }} : 0) + '%; background: color-mix(in srgb, {{ $tile['accent'] }} 45%, transparent); transition: width .9s ease;'"></div>
                                @endif
                            </div>

                            <div class="mt-3 grid grid-cols-2 gap-3">
                                @foreach ($types as $type => [$typeLabel, $shade])
                                    @php
                                        $part = $tile['split'][$type];
                                    @endphp
                                    <div class="min-w-0 rounded-xl px-3 py-2 ring-1 ring-gray-950/5 dark:ring-white/10" style="background: color-mix(in srgb, {{ $tile['accent'] }} 5%, transparent);">
                                        <span class="flex items-center gap-1.5 text-xs font-medium text-gray-600 dark:text-gray-300">
                                            <span class="inline-block size-2 shrink-0 rounded-full" style="background: color-mix(in srgb, {{ $tile['accent'] }} {{ $shade }}, transparent);"></span>
                                            {{ $typeLabel }}
                                        </span>
                                        <span class="mt-0.5 block text-base font-semibold text-gray-950 dark:text-white" style="font-variant-numeric: tabular-nums;">{{ $plural($part['count'], 'aviso de cobro', 'avisos de cobro') }}</span>
                                        <span class="block text-[11px] text-gray-500 dark:text-gray-400" style="font-variant-numeric: tabular-nums;">{{ $money($part['amount']) }} · {{ $plural($part['affiliations'], 'afiliación', 'afiliaciones') }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</x-filament-widgets::widget>
