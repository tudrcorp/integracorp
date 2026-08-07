@php
    $years = $years ?? ['current' => now()->year, 'previous' => now()->year - 1, 'through_month' => now()->month];
    $totals = $totals ?? [
        'current' => ['agents' => 0, 'agencies' => 0, 'affiliations_count' => 0, 'affiliations_amount' => 0],
        'previous' => ['agents' => 0, 'agencies' => 0, 'affiliations_count' => 0, 'affiliations_amount' => 0],
        'delta' => ['agents_pct' => 0, 'agencies_pct' => 0, 'affiliations_count_pct' => 0, 'affiliations_amount_pct' => 0],
    ];
    $states = $states ?? [];
    $mapPaths = $mapPaths ?? [];
    $monthLabels = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    $throughLabel = $monthLabels[(int) ($years['through_month'] ?? 1)] ?? '';

    $normalizeGeo = static function (string $value): string {
        return (string) \Illuminate\Support\Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/\s+/', ' ')
            ->replace('vargas', 'la guaira')
            ->replace('distrito federal', 'distrito capital')
            ->trim();
    };

    $statesByKey = [];
    $amountByGeo = [];
    $topState = null;
    $topAmount = -1.0;

    foreach ($states as $state) {
        $geoKey = (string) ($state['geo_key'] ?? $state['state'] ?? '');
        $key = $normalizeGeo($geoKey);
        $statesByKey[$key] = $state;
        $amount = (float) ($state['current']['affiliations_amount'] ?? 0);
        $amountByGeo[$key] = $amount;

        if ($amount > $topAmount) {
            $topAmount = $amount;
            $topState = $state;
        }
    }

    if ($topState === null && $states !== []) {
        $topState = $states[0];
    }

    $topGeoKey = (string) ($topState['geo_key'] ?? ($mapPaths[0]['geo_key'] ?? 'Miranda'));
    $maxAmount = max(1.0, ...array_values($amountByGeo ?: [1.0]));

    $fillFor = static function (string $geoKey) use ($amountByGeo, $maxAmount, $normalizeGeo): string {
        $key = $normalizeGeo($geoKey);
        $amount = $amountByGeo[$key] ?? 0.0;

        if ($amount <= 0.0) {
            return 'rgb(203, 213, 225)'; // slate-300 · sin actividad
        }

        $t = min(1, max(0, $amount / $maxAmount));

        // Escala distintiva: cyan → teal → sky → violet
        $stops = [
            [0.0, [103, 232, 249]],
            [0.35, [45, 212, 191]],
            [0.65, [14, 165, 233]],
            [1.0, [124, 58, 237]],
        ];

        $from = $stops[0];
        $to = $stops[count($stops) - 1];
        for ($i = 0; $i < count($stops) - 1; $i++) {
            if ($t >= $stops[$i][0] && $t <= $stops[$i + 1][0]) {
                $from = $stops[$i];
                $to = $stops[$i + 1];
                break;
            }
        }

        $span = max(0.0001, $to[0] - $from[0]);
        $local = ($t - $from[0]) / $span;
        $r = (int) round($from[1][0] + ($to[1][0] - $from[1][0]) * $local);
        $g = (int) round($from[1][1] + ($to[1][1] - $from[1][1]) * $local);
        $b = (int) round($from[1][2] + ($to[1][2] - $from[1][2]) * $local);

        return "rgb({$r}, {$g}, {$b})";
    };

    $centroids = [];
    foreach ($mapPaths as $path) {
        $centroids[$normalizeGeo((string) $path['geo_key'])] = [
            'cx' => (float) $path['cx'],
            'cy' => (float) $path['cy'],
        ];
    }

    $formatInt = static fn (mixed $value): string => number_format((int) $value, 0, ',', '.');
    $formatUsd = static fn (mixed $value): string => 'US$ '.number_format((float) $value, 0, ',', '.');
    $formatDelta = static function (mixed $pct): array {
        if ($pct === null) {
            return ['trend' => 'new', 'label' => 'sin base ant.'];
        }
        $pct = (float) $pct;
        if ($pct > 0) {
            return ['trend' => 'up', 'label' => '+'.number_format($pct, 1, ',', '.').'%'];
        }
        if ($pct < 0) {
            return ['trend' => 'down', 'label' => number_format($pct, 1, ',', '.').'%'];
        }

        return ['trend' => 'flat', 'label' => '0%'];
    };

    $panelRows = [
        [
            'key' => 'agents',
            'label' => 'Agentes',
            'value' => $formatInt($topState['current']['agents'] ?? 0),
            'delta' => $formatDelta($topState['delta']['agents_pct'] ?? null),
            'kind' => 'yoy',
        ],
        [
            'key' => 'agencies',
            'label' => 'Agencias',
            'value' => $formatInt($topState['current']['agencies'] ?? 0),
            'delta' => $formatDelta($topState['delta']['agencies_pct'] ?? null),
            'kind' => 'yoy',
        ],
        [
            'key' => 'affiliations_count',
            'label' => 'Afiliaciones',
            'value' => $formatInt($topState['current']['affiliations_count'] ?? 0),
            'delta' => $formatDelta($topState['delta']['affiliations_count_pct'] ?? null),
            'kind' => 'yoy',
        ],
        [
            'key' => 'affiliations_amount',
            'label' => 'Monto US$',
            'value' => $formatUsd($topState['current']['affiliations_amount'] ?? 0),
            'delta' => $formatDelta($topState['delta']['affiliations_amount_pct'] ?? null),
            'kind' => 'yoy',
        ],
        [
            'key' => 'providers_juridical',
            'label' => 'Prov. jurídicos',
            'value' => $formatInt($topState['providers']['juridical'] ?? 0),
            'delta' => ['trend' => 'flat', 'label' => 'Afiliado'],
            'kind' => 'stock',
        ],
        [
            'key' => 'providers_natural',
            'label' => 'Prov. naturales',
            'value' => $formatInt($topState['providers']['natural'] ?? 0),
            'delta' => ['trend' => 'flat', 'label' => 'Activo'],
            'kind' => 'stock',
        ],
        [
            'key' => 'providers_total',
            'label' => 'Proveedores',
            'value' => $formatInt($topState['providers']['total'] ?? 0),
            'delta' => ['trend' => 'flat', 'label' => 'stock'],
            'kind' => 'stock',
        ],
    ];

    $yoyLabel = 'YTD '.$years['current'].' vs YTD '.$years['previous'];
@endphp

<x-filament-widgets::widget class="fi-metrics-ve-map">
    <div
        class="fi-metrics-ve-map__shell"
        wire:ignore
        x-data="{
            statesByKey: @js($statesByKey),
            centroids: @js($centroids),
            years: @js($years),
            hoverKey: null,
            pinnedKey: @js($topGeoKey),
            leader: { x1: 0, y1: 0, x2: 920, y2: 0 },
            normalizeKey(value) {
                return String(value || '')
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .toLowerCase()
                    .replace(/\s+/g, ' ')
                    .trim()
                    .replace(/^vargas$/, 'la guaira')
                    .replace(/^distrito federal$/, 'distrito capital');
            },
            get activeKey() {
                return this.hoverKey || this.pinnedKey;
            },
            get activeState() {
                const key = this.normalizeKey(this.activeKey || '');
                return this.statesByKey[key] || null;
            },
            get yoyLabel() {
                return `YTD ${this.years.current} vs YTD ${this.years.previous}`;
            },
            formatInt(value) {
                return new Intl.NumberFormat('es-VE').format(Number(value || 0));
            },
            formatUsd(value) {
                return `US$ ${new Intl.NumberFormat('es-VE', { maximumFractionDigits: 0 }).format(Number(value || 0))}`;
            },
            formatPct(value) {
                return new Intl.NumberFormat('es-VE', { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(Number(value || 0));
            },
            deltaMeta(pct) {
                if (pct === null || pct === undefined) return { trend: 'new', label: 'sin base ant.' };
                if (pct > 0) return { trend: 'up', label: `+${this.formatPct(pct)}%` };
                if (pct < 0) return { trend: 'down', label: `${this.formatPct(pct)}%` };
                return { trend: 'flat', label: '0%' };
            },
            rowValue(key) {
                const state = this.activeState;
                if (!state) return '—';
                if (key === 'affiliations_amount') return this.formatUsd(state.current.affiliations_amount);
                if (key === 'providers_juridical') return this.formatInt(state.providers?.juridical || 0);
                if (key === 'providers_natural') return this.formatInt(state.providers?.natural || 0);
                if (key === 'providers_total') return this.formatInt(state.providers?.total || 0);
                return this.formatInt(state.current[key] || 0);
            },
            rowDelta(key) {
                if (key === 'providers_juridical') return { trend: 'flat', label: 'Afiliado' };
                if (key === 'providers_natural') return { trend: 'flat', label: 'Activo' };
                if (key === 'providers_total') return { trend: 'flat', label: 'stock' };
                const state = this.activeState;
                if (!state) return this.deltaMeta(null);
                const map = {
                    agents: 'agents_pct',
                    agencies: 'agencies_pct',
                    affiliations_count: 'affiliations_count_pct',
                    affiliations_amount: 'affiliations_amount_pct',
                };
                return this.deltaMeta(state.delta[map[key]]);
            },
            isActive(geoKey) {
                return this.normalizeKey(geoKey) === this.normalizeKey(this.activeKey || '');
            },
            hover(geoKey) {
                this.hoverKey = geoKey;
                this.updateLeader();
            },
            unhover(geoKey) {
                if (this.normalizeKey(this.hoverKey) === this.normalizeKey(geoKey)) {
                    this.hoverKey = null;
                    this.updateLeader();
                }
            },
            select(geoKey) {
                this.pinnedKey = geoKey;
                this.hoverKey = null;
                this.updateLeader();
            },
            updateLeader() {
                const key = this.normalizeKey(this.activeKey || '');
                const point = this.centroids[key];
                if (!point) return;
                this.leader = { x1: point.cx, y1: point.cy, x2: 920, y2: point.cy };
            },
            init() {
                this.updateLeader();
                const root = this.$refs.mapRoot;
                if (!root) return;

                root.querySelectorAll('[data-geo-key]').forEach((el) => {
                    const geoKey = el.getAttribute('data-geo-key');
                    el.addEventListener('pointerenter', () => this.hover(geoKey));
                    el.addEventListener('pointerleave', () => this.unhover(geoKey));
                    el.addEventListener('click', () => this.select(geoKey));
                    el.addEventListener('keydown', (event) => {
                        if (event.key === 'Enter' || event.key === ' ') {
                            event.preventDefault();
                            this.select(geoKey);
                        }
                    });
                });
            },
        }"
    >
        <header class="fi-metrics-module fi-metrics-ve-map__header">
            <p class="fi-metrics-module__eyebrow">Métricas / KPI</p>
            <h2 class="fi-metrics-module__title">Actividad nacional</h2>
            <p class="fi-metrics-module__subtitle">
                Resumen YTD {{ $years['current'] }} vs YTD {{ $years['previous'] }}
                (hasta {{ $throughLabel }}) · agentes, agencias y afiliaciones por estado del afiliado.
            </p>
        </header>

        <div class="fi-metrics-ve-map__kpis" role="group" aria-label="Totales nacionales año en curso">
            @foreach ([
                ['key' => 'agents', 'label' => 'Agentes', 'format' => 'int', 'delta' => 'agents_pct'],
                ['key' => 'agencies', 'label' => 'Agencias', 'format' => 'int', 'delta' => 'agencies_pct'],
                ['key' => 'affiliations_count', 'label' => 'Afiliaciones', 'format' => 'int', 'delta' => 'affiliations_count_pct'],
                ['key' => 'affiliations_amount', 'label' => 'Monto US$', 'format' => 'usd', 'delta' => 'affiliations_amount_pct'],
            ] as $kpi)
                @php
                    $value = $totals['current'][$kpi['key']] ?? 0;
                    $delta = $totals['delta'][$kpi['delta']] ?? null;
                    $trend = $delta === null ? 'new' : ($delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat'));
                @endphp
                <div class="fi-metrics-ve-map__kpi" data-trend="{{ $trend }}">
                    <p class="fi-metrics-ve-map__kpi-label">{{ $kpi['label'] }}</p>
                    <p class="fi-metrics-ve-map__kpi-value">
                        @if ($kpi['format'] === 'usd')
                            US$ {{ number_format((float) $value, 0, ',', '.') }}
                        @else
                            {{ number_format((int) $value, 0, ',', '.') }}
                        @endif
                    </p>
                    <p class="fi-metrics-ve-map__kpi-delta">
                        @if ($delta === null)
                            sin base {{ $years['previous'] }}
                        @elseif ($delta > 0)
                            +{{ number_format((float) $delta, 1, ',', '.') }}% vs {{ $years['previous'] }}
                        @elseif ($delta < 0)
                            {{ number_format((float) $delta, 1, ',', '.') }}% vs {{ $years['previous'] }}
                        @else
                            0% vs {{ $years['previous'] }}
                        @endif
                    </p>
                </div>
            @endforeach
        </div>

        <div class="fi-metrics-ve-map__layout">
            <div class="fi-metrics-ve-map__canvas-wrap" x-ref="mapRoot">
                <svg
                    class="fi-metrics-ve-map__svg"
                    viewBox="0 0 1000 780"
                    role="img"
                    aria-label="Mapa de Venezuela por estado"
                >
                    <g class="fi-metrics-ve-map__states">
                        @foreach ($mapPaths as $path)
                            @php
                                $geoKey = (string) ($path['geo_key'] ?? '');
                            @endphp
                            <path
                                class="fi-metrics-ve-map__state"
                                data-geo-key="{{ $geoKey }}"
                                d="{{ $path['d'] }}"
                                fill="{{ $fillFor($geoKey) }}"
                                tabindex="0"
                                role="button"
                                aria-label="{{ $geoKey }}"
                                :class="{
                                    'is-active': isActive(@js($geoKey)),
                                    'is-dimmed': activeKey && !isActive(@js($geoKey)),
                                }"
                            ></path>
                        @endforeach
                    </g>

                    <line
                        class="fi-metrics-ve-map__leader"
                        :x1="leader.x1"
                        :y1="leader.y1"
                        :x2="leader.x2"
                        :y2="leader.y2"
                    ></line>
                    <circle
                        class="fi-metrics-ve-map__anchor"
                        :cx="leader.x1"
                        :cy="leader.y1"
                        r="5"
                    ></circle>
                </svg>

                <div class="fi-metrics-ve-map__legend" aria-hidden="true">
                    <span>Menor monto</span>
                    <div class="fi-metrics-ve-map__legend-bar"></div>
                    <span>Mayor monto US$</span>
                </div>
            </div>

            <aside class="fi-metrics-ve-map__panel" aria-live="polite">
                <p class="fi-metrics-ve-map__card-eyebrow" x-text="yoyLabel">{{ $yoyLabel }}</p>
                <h3 class="fi-metrics-ve-map__card-title" x-text="activeState ? activeState.state : @js($topState['state'] ?? $topGeoKey)">
                    {{ $topState['state'] ?? $topGeoKey }}
                </h3>
                <p class="fi-metrics-ve-map__panel-hint">
                    Pasa el cursor sobre un estado o haz clic para fijarlo.
                </p>

                <ul class="fi-metrics-ve-map__card-rows">
                    @foreach ($panelRows as $row)
                        <li class="fi-metrics-ve-map__card-row">
                            <span class="fi-metrics-ve-map__card-row-label">{{ $row['label'] }}</span>
                            <span
                                class="fi-metrics-ve-map__card-row-value"
                                x-text="rowValue(@js($row['key']))"
                            >{{ $row['value'] }}</span>
                            <span
                                class="fi-metrics-ve-map__card-row-delta"
                                data-trend="{{ $row['delta']['trend'] }}"
                                :data-trend="rowDelta(@js($row['key'])).trend"
                                x-text="rowDelta(@js($row['key'])).label"
                            >{{ $row['delta']['label'] }}</span>
                        </li>
                    @endforeach
                </ul>

                <p class="fi-metrics-ve-map__card-pin" x-show="pinnedKey && !hoverKey" x-cloak>
                    Estado fijado · pasa el cursor para explorar otros
                </p>
            </aside>
        </div>
    </div>
</x-filament-widgets::widget>
