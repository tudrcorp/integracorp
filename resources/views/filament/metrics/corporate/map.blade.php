@php
    /**
     * @var list<array{geo_key: string, d: string, cx: float, cy: float}> $paths
     * @var array<string, array<string, mixed>> $layers
     */
    $layerIcons = [
        'income' => 'heroicon-m-banknotes',
        'affiliations' => 'heroicon-m-identification',
        'network' => 'heroicon-m-users',
        'services' => 'heroicon-m-heart',
    ];
@endphp

<x-filament-widgets::widget class="corp-map">
    <style>
        .corp-map-state {
            transition: fill 700ms cubic-bezier(.22, 1, .36, 1), opacity 250ms ease, stroke-width 150ms ease;
            cursor: pointer;
            outline: none;
        }

        .corp-map-state:focus-visible {
            stroke-width: 3;
        }

        @media (prefers-reduced-motion: reduce) {
            .corp-map-state { transition: none !important; }
        }
    </style>

    <x-filament::section>
        <div
            wire:key="corp-map-{{ $periodKey }}"
            x-data="{
                layers: @js($layers),
                steps: @js($steps),
                layer: 'income',
                hovered: null,
                selected: null,
                shown: false,
                tip: { x: 0, y: 0 },
                nf: new Intl.NumberFormat('es-VE', { maximumFractionDigits: 0 }),
                nf1: new Intl.NumberFormat('es-VE', { maximumFractionDigits: 1 }),
                get current() { return this.layers[this.layer]; },
                value(key, layer = null) { const source = layer ? this.layers[layer] : this.current; return source.values[key] ?? 0; },
                get max() { const values = Object.values(this.current.values); return values.length ? Math.max(...values) : 0; },
                level(key) {
                    const value = this.value(key);
                    if (!this.shown || value <= 0 || this.max <= 0) { return 0; }
                    return Math.max(1, Math.min(this.steps, Math.ceil(Math.sqrt(value / this.max) * this.steps)));
                },
                fill(key) { const level = this.level(key); return level ? 'var(--ops-heat-' + level + ')' : 'var(--ops-track)'; },
                format(value, layer = null) { const source = layer ? this.layers[layer] : this.current; return (source.money ? 'US$ ' : '') + this.nf.format(value ?? 0); },
                share(key, layer = null) { const source = layer ? this.layers[layer] : this.current; return source.total > 0 ? this.nf1.format(this.value(key, layer) * 100 / source.total) + ' %' : '0 %'; },
                delta(key, layer = null) {
                    const source = layer ? this.layers[layer] : this.current;
                    if (!source.previous) { return null; }
                    const current = source.values[key] ?? 0;
                    const previous = source.previous[key] ?? 0;
                    if (previous <= 0) { return current > 0 ? { text: 'Nuevo', up: true } : null; }
                    const change = (current - previous) * 100 / previous;
                    if (Math.abs(change) < 0.05) { return { text: 'Sin cambios', up: null }; }
                    return { text: (change > 0 ? '+' : '−') + this.nf1.format(Math.abs(change)) + ' %', up: change > 0 };
                },
                get ranking() { return Object.entries(this.current.values).filter(([, value]) => value > 0).slice(0, 8); },
                rank(key) { const index = Object.entries(this.current.values).filter(([, value]) => value > 0).findIndex(([state]) => state === key); return index >= 0 ? index + 1 : null; },
                get active() { return Object.values(this.current.values).filter((value) => value > 0).length; },
                move(event) { const box = this.$refs.mapHost.getBoundingClientRect(); this.tip = { x: event.clientX - box.left, y: event.clientY - box.top }; },
                pick(key) { this.selected = this.selected === key ? null : key; },
                openSales(key) { this.$wire.dispatch('operations-dashboard-detail', { dimension: 'sale_state', value: key }); },
            }"
            x-intersect.once="shown = true"
            class="grid grid-cols-1 gap-6 xl:grid-cols-12"
        >
            <div class="xl:col-span-8">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-gray-950 dark:text-white">Venezuela por estado</h3>
                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400" x-text="current.hint + (current.snapshot ? ' Foto a hoy.' : ' ' + @js($periodLabel) + '.')"></p>
                    </div>

                    <div class="inline-flex flex-wrap gap-1 rounded-xl bg-gray-100 p-1 dark:bg-white/5" role="tablist" aria-label="Métrica del mapa">
                        @foreach ($layers as $key => $layer)
                            <button
                                type="button"
                                role="tab"
                                x-on:click="layer = @js($key); hovered = null"
                                x-bind:aria-selected="layer === @js($key)"
                                x-bind:class="layer === @js($key) ? 'bg-white text-gray-950 shadow-sm dark:bg-white/10 dark:text-white' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                                class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium transition"
                            >
                                <x-filament::icon :icon="$layerIcons[$key] ?? 'heroicon-m-map'" class="size-4" />
                                {{ $layer['short'] }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="relative mt-4" x-ref="mapHost" x-on:mousemove="move($event)" x-on:mouseleave="hovered = null">
                    <svg viewBox="0 0 1000 780" class="h-auto max-h-[34rem] w-full" role="img" aria-label="Mapa de Venezuela por estado">
                        @foreach ($paths as $index => $path)
                            <path
                                d="{{ $path['d'] }}"
                                tabindex="0"
                                role="button"
                                aria-label="{{ $path['geo_key'] }}"
                                class="corp-map-state stroke-white dark:stroke-gray-900"
                                x-bind:style="'fill: ' + fill(@js($path['geo_key'])) + '; transition-delay: ' + (shown ? {{ $index * 18 }} : 0) + 'ms'"
                                x-bind:stroke-width="selected === @js($path['geo_key']) ? 3.5 : 1.2"
                                x-bind:opacity="(hovered && hovered !== @js($path['geo_key'])) || (selected && selected !== @js($path['geo_key']) && !hovered) ? 0.55 : 1"
                                x-on:mouseenter="hovered = @js($path['geo_key'])"
                                x-on:focus="hovered = @js($path['geo_key'])"
                                x-on:blur="hovered = null"
                                x-on:click="pick(@js($path['geo_key']))"
                                x-on:keydown.enter.prevent="pick(@js($path['geo_key']))"
                            ></path>
                        @endforeach
                    </svg>

                    <div
                        x-show="hovered"
                        x-cloak
                        x-transition.opacity.duration.150ms
                        class="pointer-events-none absolute z-20 w-56 rounded-xl bg-gray-950 px-3.5 py-3 text-white shadow-xl ring-1 ring-white/10 dark:bg-white dark:text-gray-950 dark:ring-gray-950/10"
                        x-bind:style="{ left: Math.min(Math.max(tip.x, 112), $refs.mapHost.offsetWidth - 112) + 'px', top: tip.y + 'px', transform: 'translate(-50%, calc(-100% - 14px))' }"
                    >
                        <p class="text-xs font-medium opacity-70" x-text="hovered"></p>
                        <p class="mt-1 text-lg font-semibold tabular-nums" x-text="format(value(hovered))"></p>
                        <p class="text-xs opacity-80" x-text="current.label.toLowerCase() + ' · ' + share(hovered) + ' del total'"></p>
                        <template x-if="rank(hovered)">
                            <p class="text-xs opacity-80" x-text="'Puesto ' + rank(hovered) + ' de ' + active + ' estados'"></p>
                        </template>
                        <template x-if="delta(hovered)">
                            <p class="text-xs opacity-80" x-text="delta(hovered).text + ' frente a ' + @js($previousLabel)"></p>
                        </template>
                        <p class="mt-2 border-t border-white/10 pt-1.5 text-[11px] opacity-60 dark:border-gray-950/10">Clic para ver la ficha del estado</p>
                    </div>

                    <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                        <span>Menos</span>
                        <span class="flex h-2.5 overflow-hidden rounded-full">
                            @for ($step = 1; $step <= $steps; $step++)
                                <span class="w-6" style="background: var(--ops-heat-{{ $step }})"></span>
                            @endfor
                        </span>
                        <span>Más</span>
                        <span class="text-gray-400 dark:text-gray-500">· Escala de raíz cuadrada: los estados pequeños no desaparecen junto al mayor.</span>
                    </div>
                </div>
            </div>

            <div class="xl:col-span-4">
                {{-- Ficha del estado elegido --}}
                <template x-if="selected">
                    <div x-transition.opacity.duration.200ms class="rounded-2xl bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Ficha del estado</p>
                                <h4 class="text-lg font-semibold text-gray-950 dark:text-white" x-text="selected"></h4>
                            </div>
                            <button type="button" x-on:click="selected = null" class="rounded-lg p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/10" aria-label="Cerrar ficha">
                                <x-filament::icon icon="heroicon-m-x-mark" class="size-5" />
                            </button>
                        </div>

                        <dl class="mt-4 space-y-3">
                            @foreach ($layers as $key => $layer)
                                <div class="rounded-xl bg-white p-3 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                                    <dt class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                                        <x-filament::icon :icon="$layerIcons[$key] ?? 'heroicon-m-map'" class="size-4" />
                                        {{ $layer['label'] }}{{ $layer['snapshot'] ? ' · hoy' : '' }}
                                    </dt>
                                    <dd class="mt-1 flex items-baseline justify-between gap-2">
                                        <span class="text-xl font-semibold tabular-nums text-gray-950 dark:text-white" x-text="format(value(selected, @js($key)), @js($key))"></span>
                                        <span class="text-xs tabular-nums text-gray-500 dark:text-gray-400" x-text="share(selected, @js($key)) + ' del total'"></span>
                                    </dd>
                                    <template x-if="delta(selected, @js($key))">
                                        <p
                                            class="mt-0.5 text-xs font-semibold"
                                            x-bind:style="'color: ' + (delta(selected, @js($key)).up === null ? 'var(--ops-muted)' : (delta(selected, @js($key)).up ? 'var(--ops-good-ink)' : 'var(--ops-bad-ink)'))"
                                            x-text="delta(selected, @js($key)).text + ' vs ant.'"
                                        ></p>
                                    </template>
                                </div>
                            @endforeach
                        </dl>

                        <x-filament::button
                            class="mt-4 w-full"
                            color="gray"
                            size="sm"
                            icon="heroicon-m-list-bullet"
                            x-on:click="openSales(selected)"
                        >
                            Ver los pagos de este estado
                        </x-filament::button>
                    </div>
                </template>

                {{-- Ranking de la capa elegida --}}
                <template x-if="!selected">
                    <div>
                        <p class="text-sm font-semibold text-gray-950 dark:text-white" x-text="'Estados con más ' + current.short.toLowerCase()"></p>
                        <p class="text-xs text-gray-500 dark:text-gray-400" x-text="'Total: ' + format(current.total) + (current.snapshot ? ' · hoy' : '')"></p>

                        <ol class="mt-4 space-y-1">
                            <template x-for="([state, amount], index) in ranking" x-bind:key="layer + state">
                                <li>
                                    <button
                                        type="button"
                                        x-on:click="pick(state)"
                                        x-on:mouseenter="hovered = null"
                                        class="w-full rounded-lg px-2 py-1.5 text-start transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:hover:bg-white/5"
                                    >
                                        <div class="flex items-baseline gap-3 text-sm">
                                            <span class="w-5 shrink-0 text-xs tabular-nums text-gray-400 dark:text-gray-500" x-text="index + 1"></span>
                                            <span class="min-w-0 flex-1 truncate text-gray-700 dark:text-gray-200" x-text="state"></span>
                                            <span class="shrink-0 font-semibold tabular-nums text-gray-950 dark:text-white" x-text="format(amount)"></span>
                                            <span class="w-14 shrink-0 text-end text-xs tabular-nums text-gray-500 dark:text-gray-400" x-text="share(state)"></span>
                                        </div>
                                        <div class="ms-8 mt-1 h-1.5 overflow-hidden rounded-full" style="background: var(--ops-track)">
                                            <div class="ops-bar-fill h-full rounded-full" x-bind:style="'width: ' + (shown && max > 0 ? amount * 100 / max : 0) + '%; background: var(--ops-series1)'"></div>
                                        </div>
                                    </button>
                                </li>
                            </template>
                        </ol>

                        <template x-if="ranking.length === 0">
                            <p class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">No hay datos para esta métrica en el período.</p>
                        </template>

                        <template x-if="current.unknown > 0">
                            <p class="mt-3 ps-10 text-xs text-gray-500 dark:text-gray-400" x-text="'Sin estado registrado: ' + format(current.unknown)"></p>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
