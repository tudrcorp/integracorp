@php
    /**
     * @var list<array{geo_key: string, d: string, cx: float, cy: float}> $paths
     * @var array<string, array<string, mixed>> $layers
     */
    $layerIcons = [
        'all' => 'heroicon-m-building-office-2',
        'juridico' => 'heroicon-m-building-office',
        'natural' => 'heroicon-m-user',
        'aliado' => 'heroicon-m-briefcase',
        'active' => 'heroicon-m-check-badge',
        'without_letter' => 'heroicon-m-document-minus',
    ];

    /**
     * Dónde va el número de los estados pequeños del centro-norte (coordenadas del viewBox 1000 × 780):
     * fuera del estado, sobre el mar, unido por una línea guía. El resto va en su centro.
     *
     * @var array<string, array{0: float, 1: float}>
     */
    $labelSpots = [
        'Distrito Capital' => [440, 66],
        'La Guaira' => [505, 62],
        'Aragua' => [462, 198],
    ];
@endphp

<x-filament-widgets::widget class="prov-map">
    <style>
        .prov-map-state {
            transition: fill 700ms cubic-bezier(.22, 1, .36, 1), opacity 250ms ease, stroke-width 150ms ease;
            cursor: pointer;
            outline: none;
        }

        .prov-map-state:focus-visible { stroke-width: 3; }

        @media (prefers-reduced-motion: reduce) {
            .prov-map-state { transition: none !important; }
        }
    </style>

    <x-filament::section>
        <div
            x-data="{
                layers: @js($layers),
                states: @js((object) $states),
                types: @js($types),
                statuses: @js($statuses),
                agreements: @js($agreements),
                steps: @js($steps),
                layer: 'all',
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
                format(value) { return this.nf.format(value ?? 0); },
                pill(key) { return 16 + this.format(this.value(key)).length * 9; },
                share(key, layer = null) { const source = layer ? this.layers[layer] : this.current; return source.total > 0 ? this.nf1.format(this.value(key, layer) * 100 / source.total) + ' %' : '0 %'; },
                get ranking() { return Object.entries(this.current.values).filter(([, value]) => value > 0).slice(0, 10); },
                rank(key) { const index = Object.entries(this.current.values).filter(([, value]) => value > 0).findIndex(([state]) => state === key); return index >= 0 ? index + 1 : null; },
                get active() { return Object.values(this.current.values).filter((value) => value > 0).length; },
                detail(key) { return this.states[key] ?? { types: {}, status: {}, agreements: {} }; },
                entries(object) { return Object.entries(object ?? {}).sort((a, b) => b[1] - a[1]); },
                move(event) { const box = this.$refs.mapHost.getBoundingClientRect(); this.tip = { x: event.clientX - box.left, y: event.clientY - box.top }; },
                pick(key) { this.selected = this.selected === key ? null : key; },
                filtersFor(key) {
                    const filters = { geo: key };
                    if (['juridico', 'natural', 'aliado'].includes(this.layer)) { filters.type = this.layer; }
                    if (this.layer === 'active') { filters.active = true; }
                    if (this.layer === 'without_letter') { filters.letter = 'without'; }
                    return filters;
                },
                open(key) { this.$wire.dispatch('provider-network-detail', { filters: this.filtersFor(key), title: this.current.label + ' en ' + key }); },
                openAbroad(country) { this.$wire.dispatch('provider-network-detail', { filters: { abroad: country }, title: 'Proveedores en ' + country }); },
            }"
            x-intersect.once="shown = true"
            class="grid grid-cols-1 gap-6 xl:grid-cols-12"
        >
            <div class="xl:col-span-8">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-gray-950 dark:text-white">Red de proveedores en Venezuela</h3>
                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400" x-text="current.label + ' · ' + format(current.total) + ' en ' + active + ' estados · foto a hoy'"></p>
                    </div>

                    <div class="inline-flex flex-wrap gap-1 rounded-xl bg-gray-100 p-1 dark:bg-white/5" role="tablist" aria-label="Capa del mapa">
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
                    <svg viewBox="0 0 1000 780" class="h-auto max-h-[36rem] w-full" role="img" aria-label="Mapa de Venezuela con proveedores por estado">
                        @foreach ($paths as $index => $path)
                            <path
                                d="{{ $path['d'] }}"
                                tabindex="0"
                                role="button"
                                aria-label="{{ $path['geo_key'] }}"
                                class="prov-map-state stroke-white dark:stroke-gray-900"
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

                        {{-- Números por estado: pastillas legibles; los estados pequeños del centro llevan la etiqueta afuera, con línea guía. --}}
                        @foreach ($paths as $path)
                            @php
                                $spot = $labelSpots[$path['geo_key']] ?? null;
                                $lx = $spot[0] ?? $path['cx'];
                                $ly = $spot[1] ?? $path['cy'];
                            @endphp
                            <g class="pointer-events-none" x-show="value(@js($path['geo_key'])) > 0" x-cloak>
                                @if ($spot !== null)
                                    <line x1="{{ $path['cx'] }}" y1="{{ $path['cy'] }}" x2="{{ $lx }}" y2="{{ $ly }}" class="stroke-gray-500 dark:stroke-gray-400" stroke-width="1.5" stroke-dasharray="3 3"></line>
                                    <circle cx="{{ $path['cx'] }}" cy="{{ $path['cy'] }}" r="3.5" class="fill-gray-600 dark:fill-gray-300"></circle>
                                @endif
                                <rect
                                    x-bind:x="{{ $lx }} - pill(@js($path['geo_key'])) / 2"
                                    y="{{ $ly - 13 }}"
                                    x-bind:width="pill(@js($path['geo_key']))"
                                    height="26"
                                    rx="13"
                                    class="fill-white stroke-gray-300 dark:fill-gray-900 dark:stroke-white/20"
                                    stroke-width="1"
                                    style="filter: drop-shadow(0 1px 1.5px rgba(0,0,0,.25));"
                                ></rect>
                                <text
                                    x="{{ $lx }}"
                                    y="{{ $ly + 1 }}"
                                    text-anchor="middle"
                                    dominant-baseline="middle"
                                    class="fill-gray-950 dark:fill-white"
                                    style="font-size: 15px; font-weight: 700; font-variant-numeric: tabular-nums;"
                                    x-text="format(value(@js($path['geo_key'])))"
                                ></text>
                            </g>
                        @endforeach
                    </svg>

                    <div
                        x-show="hovered"
                        x-cloak
                        x-transition.opacity.duration.150ms
                        class="pointer-events-none absolute z-20 w-60 rounded-xl bg-gray-950 px-3.5 py-3 text-white shadow-xl ring-1 ring-white/10 dark:bg-white dark:text-gray-950 dark:ring-gray-950/10"
                        x-bind:style="{ left: Math.min(Math.max(tip.x, 120), $refs.mapHost.offsetWidth - 120) + 'px', top: tip.y + 'px', transform: 'translate(-50%, calc(-100% - 14px))' }"
                    >
                        <p class="text-xs font-medium opacity-70" x-text="hovered"></p>
                        <p class="mt-1 text-lg font-semibold tabular-nums" x-text="format(value(hovered)) + ' ' + current.short.toLowerCase()"></p>
                        <p class="text-xs opacity-80" x-text="share(hovered) + ' del total de la capa'"></p>
                        <template x-for="[type, meta] in Object.entries(types)" x-bind:key="'tip-' + type">
                            <p class="flex justify-between text-xs opacity-90">
                                <span x-text="meta.plural"></span>
                                <span class="tabular-nums" x-text="format(detail(hovered).types[type] ?? 0)"></span>
                            </p>
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
                        @if ($unknown > 0)
                            <button type="button" class="text-gray-500 underline decoration-dotted underline-offset-2 hover:text-gray-700 dark:text-gray-400" x-on:click="$wire.dispatch('provider-network-detail', { filters: { geo: null, abroad: null, missing_location: true }, title: 'Proveedores sin ubicación' })">
                                · {{ number_format($unknown, 0, ',', '.') }} sin estado registrado
                            </button>
                        @endif
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
                                <p class="text-sm text-gray-500 dark:text-gray-400" x-text="format(value(selected, 'all')) + ' proveedores · ' + share(selected, 'all') + ' de la red'"></p>
                            </div>
                            <button type="button" x-on:click="selected = null" class="rounded-lg p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/10" aria-label="Cerrar ficha">
                                <x-filament::icon icon="heroicon-m-x-mark" class="size-5" />
                            </button>
                        </div>

                        <div class="mt-4 grid grid-cols-3 gap-2">
                            <template x-for="[type, meta] in Object.entries(types)" x-bind:key="'card-' + type">
                                <div class="rounded-xl bg-white p-2.5 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                                    <p class="truncate text-[11px] text-gray-500 dark:text-gray-400" x-text="meta.plural"></p>
                                    <p class="text-xl font-semibold tabular-nums" x-bind:style="'color: ' + meta.color" x-text="format(detail(selected).types[type] ?? 0)"></p>
                                </div>
                            </template>
                        </div>

                        <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Estatus</p>
                        <ul class="mt-1.5 space-y-1">
                            <template x-for="[status, total] in entries(detail(selected).status)" x-bind:key="'st-' + status">
                                <li class="flex items-center gap-2 text-sm">
                                    <span class="size-2.5 shrink-0 rounded-full" x-bind:style="'background: ' + (statuses[status]?.color ?? 'var(--ops-muted)')"></span>
                                    <span class="min-w-0 flex-1 truncate text-gray-700 dark:text-gray-200" x-text="statuses[status]?.label ?? status"></span>
                                    <span class="font-semibold tabular-nums text-gray-950 dark:text-white" x-text="format(total)"></span>
                                </li>
                            </template>
                        </ul>

                        <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Tipo de convenio</p>
                        <ul class="mt-1.5 space-y-1">
                            <template x-for="[agreement, total] in entries(detail(selected).agreements)" x-bind:key="'ag-' + agreement">
                                <li class="flex items-center gap-2 text-sm">
                                    <span class="size-2.5 shrink-0 rounded-full" x-bind:style="'background: ' + (agreements[agreement]?.color ?? 'var(--ops-muted)')"></span>
                                    <span class="min-w-0 flex-1 truncate text-gray-700 dark:text-gray-200" x-text="agreements[agreement]?.label ?? agreement"></span>
                                    <span class="font-semibold tabular-nums text-gray-950 dark:text-white" x-text="format(total)"></span>
                                </li>
                            </template>
                        </ul>

                        <x-filament::button class="mt-4 w-full" color="gray" size="sm" icon="heroicon-m-list-bullet" x-on:click="open(selected)">
                            Ver los proveedores de este estado
                        </x-filament::button>
                    </div>
                </template>

                {{-- Ranking de la capa elegida --}}
                <template x-if="!selected">
                    <div>
                        <p class="text-sm font-semibold text-gray-950 dark:text-white" x-text="'Estados con más ' + current.short.toLowerCase()"></p>
                        <p class="text-xs text-gray-500 dark:text-gray-400" x-text="'Total: ' + format(current.total)"></p>

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
                                            <div class="ops-bar-fill h-full rounded-full" x-bind:style="'width: ' + (shown && max > 0 ? amount * 100 / max : 0) + '%; background: ' + current.color"></div>
                                        </div>
                                    </button>
                                </li>
                            </template>
                        </ol>

                        <template x-if="ranking.length === 0">
                            <p class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">No hay proveedores en esta capa.</p>
                        </template>

                        @if ($abroad !== [])
                            <div class="mt-5 rounded-xl bg-gray-50 p-3 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                                <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <x-filament::icon icon="heroicon-m-globe-americas" class="size-4" />
                                    Fuera de Venezuela
                                </p>
                                <ul class="mt-2 space-y-1">
                                    @foreach ($abroad as $country)
                                        <li>
                                            <button type="button" x-on:click="openAbroad(@js($country['country']))" class="flex w-full items-baseline justify-between gap-2 rounded-lg px-1 py-0.5 text-sm transition hover:bg-white dark:hover:bg-white/5">
                                                <span class="text-gray-700 dark:text-gray-200">{{ $country['country'] }}</span>
                                                <span class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($country['total'], 0, ',', '.') }}</span>
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </template>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
