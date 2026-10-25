@php
    /** @var list<array<string, mixed>> $tiles */
@endphp

<x-filament-widgets::widget class="ops-kpi-tiles">
    @if (! empty($team))
        {{-- Tamaño del equipo: no depende del período, por eso va aparte y compacto. --}}
        <div class="mb-3 flex flex-wrap items-center gap-x-5 gap-y-1 px-1 text-sm text-gray-500 dark:text-gray-400">
            <span class="text-xs font-medium uppercase tracking-wide">{{ $teamLabel ?? 'Equipo' }}</span>
            @foreach ($team as $member)
                <span class="inline-flex items-center gap-1.5">
                    <x-filament::icon :icon="$member['icon']" class="size-4 text-gray-400 dark:text-gray-500" />
                    <span class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ $member['prefix'] ?? '' }}{{ number_format((float) $member['value'], $member['decimals'] ?? 0, ',', '.') }}</span>
                    {{ $member['label'] }}
                </span>
            @endforeach
        </div>
    @endif

    <div class="grid grid-cols-2 gap-3 {{ $tilesGrid ?? 'md:grid-cols-3 2xl:grid-cols-6' }}">
        @foreach ($tiles as $index => $tile)
            @php
                $delta = $tile['delta'];
                $deltaColor = match ($tile['improved']) {
                    true => 'var(--ops-good-ink)',
                    false => 'var(--ops-bad-ink)',
                    default => 'var(--ops-muted)',
                };
                $deltaIcon = match ($delta['direction']) {
                    'up' => 'heroicon-m-arrow-trending-up',
                    'down' => 'heroicon-m-arrow-trending-down',
                    default => 'heroicon-m-minus',
                };
                $prefix = $tile['prefix'] ?? '';
                $formatted = $prefix.number_format((float) $tile['value'], $tile['decimals'], ',', '.').$tile['suffix'];
            @endphp

            <div
                wire:key="ops-kpi-{{ $tile['key'] }}-{{ $periodKey }}-{{ $tile['value'] }}"
                x-data="{
                    shown: false,
                    display: @js($formatted),
                    target: @js((float) $tile['value']),
                    decimals: @js($tile['decimals']),
                    suffix: @js($tile['suffix']),
                    prefix: @js($prefix),
                    run() {
                        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || this.target === 0) { return; }
                        const formatter = new Intl.NumberFormat('es-VE', { minimumFractionDigits: this.decimals, maximumFractionDigits: this.decimals });
                        const started = performance.now();
                        const duration = 1100;
                        const step = (now) => {
                            const progress = Math.min(1, (now - started) / duration);
                            const eased = 1 - Math.pow(1 - progress, 4);
                            this.display = this.prefix + formatter.format(this.target * eased) + this.suffix;
                            if (progress < 1) { requestAnimationFrame(step); }
                        };
                        requestAnimationFrame(step);
                    },
                }"
                x-intersect.once="shown = true; run()"
                x-bind:class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-2'"
                style="transition-delay: {{ $index * 70 }}ms"
                class="ops-reveal relative flex min-h-[9.5rem] flex-col justify-between overflow-hidden rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10"
            >
                <div class="flex items-start justify-between gap-2">
                    <span class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $tile['label'] }}</span>
                    <span
                        class="inline-flex size-7 shrink-0 items-center justify-center rounded-lg bg-gray-50 text-gray-400 dark:bg-white/5 dark:text-gray-500"
                        x-tooltip="{ content: @js($tile['hint']), theme: $store.theme }"
                    >
                        <x-filament::icon :icon="$tile['icon']" class="size-4" />
                    </span>
                </div>

                <div class="mt-2 text-3xl font-semibold tracking-tight text-gray-950 dark:text-white" x-text="display">{{ $formatted }}</div>

                @if (! empty($tile['note']))
                    <p class="mt-1 text-[11px] font-medium text-amber-600 dark:text-amber-400">{{ $tile['note'] }}</p>
                @endif

                <div class="mt-2 flex items-end justify-between gap-2">
                    @if (! empty($tile['snapshot']))
                        <span class="inline-flex items-center gap-1 text-xs font-medium text-gray-400 dark:text-gray-500">
                            <x-filament::icon icon="heroicon-m-camera" class="size-4" />
                            Foto a hoy
                        </span>
                    @elseif ($compare)
                        <span
                            class="inline-flex items-center gap-1 text-xs font-semibold"
                            style="color: {{ $deltaColor }}"
                            x-tooltip="{ content: @js('Período anterior ('.$previousLabel.'): '.$prefix.number_format((float) $tile['previous'], $tile['decimals'], ',', '.').$tile['suffix']), theme: $store.theme }"
                        >
                            <x-filament::icon :icon="$deltaIcon" class="size-4" />
                            {{ $delta['text'] }}
                            <span class="font-normal text-gray-400 dark:text-gray-500">vs ant.</span>
                        </span>
                    @else
                        <span></span>
                    @endif

                    @if ($tile['sparkline'])
                        <svg viewBox="0 0 100 32" preserveAspectRatio="none" class="h-8 w-24 shrink-0 overflow-visible" aria-hidden="true">
                            <path d="{{ $tile['sparkline'] }} L100 32 L0 32 Z" style="fill: var(--ops-series1); opacity: .10"></path>
                            <path
                                d="{{ $tile['sparkline'] }}"
                                fill="none"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                vector-effect="non-scaling-stroke"
                                style="stroke: var(--ops-series1)"
                            ></path>
                        </svg>
                    @elseif ($tile['progress'] !== null)
                        <div class="h-1.5 w-24 shrink-0 overflow-hidden rounded-full" style="background: var(--ops-track)">
                            <div
                                class="ops-bar-fill h-full rounded-full"
                                x-bind:style="'width: ' + (shown ? {{ $tile['progress'] }} : 0) + '%; background: var(--ops-series1)'"
                            ></div>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</x-filament-widgets::widget>
