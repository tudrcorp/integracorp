@php
    use App\Support\Metrics\System\SystemEvolutionComparison as Cmp;

    $fmt = static fn (int|float $value, int $decimals = 0): string => Cmp::number($value, $decimals);
    $deltaClasses = static fn (array $delta, bool $lowerIsBetter = false): string => match (true) {
        $delta['direction'] === 'flat' => 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300',
        ($delta['direction'] === 'up') !== $lowerIsBetter => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
        default => 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
    };
    $deltaIcon = static fn (array $delta): string => match ($delta['direction']) {
        'up' => 'heroicon-m-arrow-trending-up',
        'down' => 'heroicon-m-arrow-trending-down',
        default => 'heroicon-m-minus',
    };
    $toneClasses = [
        'success' => 'text-emerald-600 dark:text-emerald-400',
        'warning' => 'text-amber-600 dark:text-amber-400',
        'info' => 'text-sky-600 dark:text-sky-400',
        'primary' => 'text-primary-600 dark:text-primary-400',
        'gray' => 'text-gray-500 dark:text-gray-400',
    ];
@endphp

<x-filament-panels::page>
    <div wire:init="loadReport" class="flex flex-col gap-6">
        @if (! $ready)
            {{-- Esqueleto mientras se lee el historial --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach (range(1, 6) as $placeholder)
                    <div class="h-32 animate-pulse rounded-xl bg-gray-100 dark:bg-white/5"></div>
                @endforeach
            </div>
            <div class="h-72 animate-pulse rounded-xl bg-gray-100 dark:bg-white/5"></div>
            <p class="text-center text-sm text-gray-500 dark:text-gray-400">Leyendo el historial del código y la base de datos…</p>
        @elseif (! $available)
            <x-filament::section>
                <div class="flex flex-col items-center justify-center gap-3 py-10 text-center">
                    <x-filament::icon icon="heroicon-o-code-bracket-square" class="size-10 text-gray-300 dark:text-gray-600" />
                    <p class="text-base font-semibold text-gray-950 dark:text-white">No hay historial de código disponible en este servidor</p>
                    <p class="max-w-xl text-sm text-gray-500 dark:text-gray-400">
                        Las estadísticas se calculan con el historial de git de la carpeta donde está instalado el sistema.
                        Si el servidor no tiene git instalado o la carpeta no es un repositorio, esta sección no puede medir el trabajo.
                        Pida a Sistemas que verifique que el comando <code class="rounded bg-gray-100 px-1 dark:bg-white/10">git</code> esté disponible para el usuario del servidor web.
                    </p>
                </div>
            </x-filament::section>
        @else
            @php
                /** @var array<string, mixed> $report */
                $r = $report;
            @endphp

            {{-- Barra de selección --}}
            <div class="flex flex-col gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 lg:flex-row lg:items-center lg:justify-between dark:bg-gray-900 dark:ring-white/10">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <label class="flex flex-col gap-1 text-xs font-medium text-gray-500 dark:text-gray-400">
                        Mes a analizar
                        <x-filament::input.wrapper prefix-icon="heroicon-m-calendar-days" class="w-64">
                            <x-filament::input.select wire:model.live="month">
                                @foreach ($monthOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </label>
                    <label class="flex flex-col gap-1 text-xs font-medium text-gray-500 dark:text-gray-400">
                        Comparar con
                        <x-filament::input.wrapper prefix-icon="heroicon-m-arrows-right-left" class="w-64">
                            <x-filament::input.select wire:model.live="window">
                                @foreach ($windowOptions as $option)
                                    <option value="{{ $option }}">Los {{ $option }} meses anteriores</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </label>
                    <x-filament::loading-indicator wire:loading wire:target="month,window" class="size-5 text-primary-500" />
                </div>

                <div class="flex flex-wrap items-center gap-2 text-xs">
                    @if ($r['inProgress'])
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-sky-50 px-3 py-1 font-medium text-sky-700 dark:bg-sky-500/15 dark:text-sky-300">
                            <x-filament::icon icon="heroicon-m-clock" class="size-4" />
                            Mes en curso · día {{ $r['cutDay'] }} de {{ $r['daysInMonth'] }}
                        </span>
                    @endif
                    @if ($r['totals']['last_commit_at'])
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1 text-gray-600 dark:bg-white/10 dark:text-gray-300">
                            <x-filament::icon icon="heroicon-m-arrow-up-tray" class="size-4" />
                            Última entrega: {{ \Carbon\CarbonImmutable::parse($r['totals']['last_commit_at'])->format('d/m/Y H:i') }}
                        </span>
                    @endif
                    @if ($r['totals']['head'])
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1 font-mono text-gray-600 dark:bg-white/10 dark:text-gray-300" title="Versión del código medida (commit desplegado)">
                            <x-filament::icon icon="heroicon-m-code-bracket" class="size-4" />
                            {{ $r['totals']['head'] }}
                        </span>
                    @endif
                </div>
            </div>

            <div wire:loading.class="opacity-60" wire:target="month,window" class="flex flex-col gap-6 transition-opacity">

                @if ($uncommitted['files'] > 0)
                    <div class="flex items-start gap-3 rounded-xl bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-400/20">
                        <x-filament::icon icon="heroicon-o-exclamation-triangle" class="mt-0.5 size-5 shrink-0" />
                        <p>
                            Hay <strong>{{ $fmt($uncommitted['files']) }} archivos</strong> con cambios en este servidor que todavía no se registraron en un commit
                            (+{{ $fmt($uncommitted['added']) }} / −{{ $fmt($uncommitted['deleted']) }} líneas). No cuentan en estas estadísticas hasta que se suban.
                        </p>
                    </div>
                @endif

                {{-- Resumen en lenguaje natural --}}
                <x-filament::section icon="heroicon-o-sparkles" :heading="'Resumen de '.$r['monthLabel']" :description="'Comparado con '.$r['windowLabel'].($r['inProgress'] ? '. Como el mes está en curso, las comparaciones son a la misma fecha (del 1 al '.$r['cutDay'].').' : '.')">
                    <ul class="flex flex-col gap-3">
                        @foreach ($r['insights'] as $insight)
                            <li class="flex items-start gap-3 text-sm leading-relaxed text-gray-700 dark:text-gray-200">
                                <x-filament::icon :icon="$insight['icon']" class="mt-0.5 size-5 shrink-0 {{ $toneClasses[$insight['tone']] ?? $toneClasses['gray'] }}" />
                                <span>{{ $insight['text'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-filament::section>

                {{-- Comparativa directa con el mes anterior --}}
                @php $pm = $r['previousMonth']; @endphp
                <x-filament::section
                    icon="heroicon-o-arrows-right-left"
                    :heading="'Comparativa con el mes anterior: '.$r['monthLabel'].' vs '.$r['previousLabel']"
                    :description="$pm['inProgress']
                        ? 'Como '.$r['monthLabel'].' está en curso, se compara a la misma fecha: del 1 al '.$r['cutDay'].' de cada mes. La columna gris muestra '.$r['previousLabel'].' completo como referencia.'
                        : 'Cada indicador de '.$r['monthLabel'].' frente al de '.$r['previousLabel'].', con la diferencia.'"
                >
                    <div class="flex flex-col gap-6">
                        <div class="flex flex-wrap gap-2 text-xs">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 font-medium text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                                <x-filament::icon icon="heroicon-m-arrow-trending-up" class="size-4" />
                                {{ $pm['improved'] }} {{ $pm['improved'] === 1 ? 'indicador subió' : 'indicadores subieron' }}
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 font-medium text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                                <x-filament::icon icon="heroicon-m-arrow-trending-down" class="size-4" />
                                {{ $pm['declined'] }} {{ $pm['declined'] === 1 ? 'bajó' : 'bajaron' }}
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1 font-medium text-gray-600 dark:bg-white/10 dark:text-gray-300">
                                <x-filament::icon icon="heroicon-m-minus" class="size-4" />
                                {{ $pm['same'] }} sin cambios
                            </span>
                        </div>

                        <div class="overflow-x-auto rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
                            <table class="w-full min-w-[52rem] text-sm">
                                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                                    <tr>
                                        <th class="px-3 py-2 text-start font-medium">Indicador</th>
                                        <th class="px-3 py-2 text-end font-medium text-primary-700 dark:text-primary-300">{{ $pm['currentLabel'] }}</th>
                                        <th class="px-3 py-2 text-end font-medium">{{ $pm['previousLabel'] }}</th>
                                        <th class="w-48 px-3 py-2 text-start font-medium">Comparación</th>
                                        <th class="px-3 py-2 text-end font-medium">Diferencia</th>
                                        <th class="px-3 py-2 text-end font-medium">Variación</th>
                                        @if ($pm['inProgress'])
                                            <th class="px-3 py-2 text-end font-medium text-gray-400 dark:text-gray-500">{{ $pm['previousFullLabel'] }}</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                    @foreach ($pm['rows'] as $row)
                                        <tr wire:key="sys-pm-{{ $row['key'] }}-{{ $r['month'] }}" class="hover:bg-gray-50/60 dark:hover:bg-white/5">
                                            <td class="px-3 py-2 text-gray-700 dark:text-gray-200" title="{{ $row['hint'] }}">{{ $row['label'] }}</td>
                                            <td class="px-3 py-2 text-end font-semibold tabular-nums text-gray-950 dark:text-white">{{ $fmt($row['current']) }}</td>
                                            <td class="px-3 py-2 text-end tabular-nums text-gray-600 dark:text-gray-300">{{ $fmt($row['previous']) }}</td>
                                            <td class="px-3 py-2">
                                                <div class="flex flex-col gap-1" aria-hidden="true">
                                                    <div class="h-1.5 rounded-full bg-primary-500" style="width: {{ max(2, $row['currentBar']) }}%"></div>
                                                    <div class="h-1.5 rounded-full bg-gray-300 dark:bg-white/25" style="width: {{ max(2, $row['previousBar']) }}%"></div>
                                                </div>
                                            </td>
                                            <td class="px-3 py-2 text-end tabular-nums {{ $row['difference'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : ($row['difference'] < 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-500 dark:text-gray-400') }}">
                                                {{ $row['difference'] > 0 ? '+' : ($row['difference'] < 0 ? '−' : '') }}{{ $fmt(abs($row['difference'])) }}
                                            </td>
                                            <td class="px-3 py-2 text-end">
                                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium {{ $deltaClasses($row['delta']) }}">
                                                    <x-filament::icon :icon="$deltaIcon($row['delta'])" class="size-3.5" />
                                                    {{ $row['delta']['text'] }}
                                                </span>
                                            </td>
                                            @if ($pm['inProgress'])
                                                <td class="px-3 py-2 text-end tabular-nums text-gray-400 dark:text-gray-500">{{ $fmt($row['previousFull']) }}</td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="flex flex-wrap items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                            <span class="inline-flex items-center gap-1.5"><span class="h-1.5 w-4 rounded-full bg-primary-500"></span> {{ $r['monthLabel'] }}</span>
                            <span class="inline-flex items-center gap-1.5"><span class="h-1.5 w-4 rounded-full bg-gray-300 dark:bg-white/25"></span> {{ $r['previousLabel'] }}</span>
                            <span>Subir no siempre es mejor: más líneas eliminadas puede ser limpieza de código.</span>
                        </p>

                        {{-- Módulos lado a lado --}}
                        <div class="flex flex-col gap-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Módulos: líneas cambiadas en cada mes</p>
                            @if ($pm['modules'] === [])
                                <p class="rounded-xl bg-gray-50 py-6 text-center text-sm text-gray-500 dark:bg-white/5 dark:text-gray-400">Ninguno de los dos meses tiene cambios en módulos.</p>
                            @else
                                <div class="grid grid-cols-1 gap-x-8 gap-y-3 xl:grid-cols-2">
                                    @foreach ($pm['modules'] as $module)
                                        <div wire:key="sys-pm-module-{{ $module['key'] }}-{{ $r['month'] }}" class="flex flex-col gap-1">
                                            <div class="flex items-center justify-between gap-3 text-sm">
                                                <span class="flex min-w-0 items-center gap-2 text-gray-700 dark:text-gray-200">
                                                    <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $module['color'] }}"></span>
                                                    <span class="truncate">{{ $module['label'] }}</span>
                                                </span>
                                                <span class="flex shrink-0 items-center gap-2 text-xs tabular-nums text-gray-500 dark:text-gray-400">
                                                    {{ $fmt($module['current']) }} vs {{ $fmt($module['previous']) }}
                                                    <span class="inline-flex rounded-full px-2 py-0.5 font-medium {{ $deltaClasses($module['delta']) }}">{{ $module['delta']['text'] }}</span>
                                                </span>
                                            </div>
                                            <div class="flex flex-col gap-0.5" aria-hidden="true">
                                                <div class="h-2 rounded-full" style="width: {{ max(1, $module['currentBar']) }}%; background-color: {{ $module['color'] }}"></div>
                                                <div class="h-2 rounded-full bg-gray-300 dark:bg-white/25" style="width: {{ max(1, $module['previousBar']) }}%"></div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if ($pm['newModules'] !== [] || $pm['pausedModules'] !== [])
                                <div class="flex flex-col gap-1 text-xs text-gray-600 dark:text-gray-300">
                                    @if ($pm['newModules'] !== [])
                                        <p><span class="font-semibold text-emerald-600 dark:text-emerald-400">Se empezó a trabajar en:</span> {{ implode(', ', $pm['newModules']) }} (sin cambios en {{ $r['previousLabel'] }}).</p>
                                    @endif
                                    @if ($pm['pausedModules'] !== [])
                                        <p><span class="font-semibold text-amber-600 dark:text-amber-400">Sin cambios este mes:</span> {{ implode(', ', $pm['pausedModules']) }} (sí tuvieron trabajo en {{ $r['previousLabel'] }}).</p>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </x-filament::section>

                {{-- Tarjetas --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($r['kpis'] as $kpi)
                        @php
                            $spark = $kpi['sparkline'];
                            $sparkMax = max(1, max($spark));
                            $sparkCount = count($spark);
                        @endphp
                        <div wire:key="sys-kpi-{{ $kpi['key'] }}-{{ $r['month'] }}-{{ $r['window'] }}" class="flex flex-col gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="flex items-center gap-1.5 text-sm font-medium text-gray-500 dark:text-gray-400" title="{{ $kpi['hint'] }}">
                                        <x-filament::icon :icon="$kpi['icon']" class="size-4 shrink-0" />
                                        {{ $kpi['label'] }}
                                        <x-filament::icon icon="heroicon-m-information-circle" class="size-3.5 shrink-0 text-gray-300 dark:text-gray-600" />
                                    </p>
                                    <p class="mt-1 text-3xl font-bold tracking-tight text-gray-950 tabular-nums dark:text-white">{{ $fmt($kpi['value']) }}</p>
                                </div>
                                {{-- Mini barras: meses comparados + mes elegido --}}
                                <div class="flex h-10 shrink-0 items-end gap-0.5" aria-hidden="true">
                                    @foreach ($spark as $index => $value)
                                        <span
                                            class="w-1.5 rounded-sm {{ $index === $sparkCount - 1 ? 'bg-primary-500' : 'bg-gray-200 dark:bg-white/15' }}"
                                            style="height: {{ max(6, (int) round($value * 100 / $sparkMax)) }}%"
                                        ></span>
                                    @endforeach
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-medium {{ $deltaClasses($kpi['vsPrevious']) }}">
                                    <x-filament::icon :icon="$deltaIcon($kpi['vsPrevious'])" class="size-3.5" />
                                    {{ $kpi['vsPrevious']['text'] }}
                                </span>
                                <span class="text-gray-500 dark:text-gray-400">vs {{ $r['previousLabel'] }}{{ $r['inProgress'] ? ' a la fecha' : '' }} ({{ $fmt($kpi['previous']) }})</span>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-medium {{ $deltaClasses($kpi['vsAverage']) }}">
                                    <x-filament::icon :icon="$deltaIcon($kpi['vsAverage'])" class="size-3.5" />
                                    {{ $kpi['vsAverage']['text'] }}
                                </span>
                                <span class="text-gray-500 dark:text-gray-400">vs promedio de {{ $r['window'] }} meses ({{ $fmt($kpi['average'], $kpi['average'] < 10 ? 1 : 0) }})</span>
                            </div>
                            @if ($kpi['projection'] !== null)
                                <p class="text-xs text-sky-700 dark:text-sky-300">
                                    Al ritmo actual cerraría el mes en ≈ {{ $fmt($kpi['projection']) }} · mejor mes comparado: {{ $fmt($kpi['best']) }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Evolución mensual --}}
                @php
                    $metricOptions = [
                        'commits' => 'Entregas (commits)',
                        'lines' => 'Líneas escritas y eliminadas',
                        'files_touched' => 'Archivos trabajados',
                        'files_created' => 'Archivos nuevos',
                        'migrations' => 'Migraciones',
                        'tables_created' => 'Tablas nuevas',
                        'tests_created' => 'Pruebas nuevas',
                        'lines_total' => 'Tamaño del código (líneas)',
                    ];
                @endphp
                <x-filament::section icon="heroicon-o-chart-bar" heading="Evolución mes a mes" description="Meses completos. La barra resaltada es el mes elegido; si está en curso, aparece rayada porque aún no cierra.">
                    <div
                        wire:key="sys-evolution-{{ $r['month'] }}-{{ $r['window'] }}"
                        x-data="{
                            metric: 'commits',
                            series: @js($r['series']),
                            value(row) { return this.metric === 'lines' ? row.added + row.deleted : row[this.metric]; },
                            get max() { return Math.max(1, ...this.series.map((row) => this.metric === 'lines' ? Math.max(row.added, row.deleted) : this.value(row))); },
                            get min() { return this.metric === 'lines_total' ? Math.min(...this.series.map((row) => row.lines_total)) * 0.9 : 0; },
                            height(value) { const range = this.max - this.min; return range <= 0 ? 0 : Math.max(2, Math.round((value - this.min) * 100 / range)); },
                            num(value) { return new Intl.NumberFormat('es-VE').format(value); },
                        }"
                        class="flex flex-col gap-4"
                    >
                        <div class="flex flex-wrap gap-2">
                            @foreach ($metricOptions as $key => $label)
                                <button
                                    type="button"
                                    x-on:click="metric = @js($key)"
                                    x-bind:class="metric === @js($key) ? 'bg-primary-600 text-white ring-primary-600 dark:bg-primary-500' : 'bg-white text-gray-700 ring-gray-950/10 hover:bg-gray-50 dark:bg-white/5 dark:text-gray-200 dark:ring-white/15'"
                                    class="rounded-full px-3 py-1 text-xs font-medium ring-1 transition"
                                >{{ $label }}</button>
                            @endforeach
                        </div>

                        <div class="overflow-x-auto">
                            <div class="flex h-64 min-w-[36rem] items-end gap-3 border-b border-gray-200 pb-1 dark:border-white/10">
                                <template x-for="row in series" :key="row.month">
                                    <div class="flex h-full flex-1 flex-col items-center justify-end gap-1">
                                        <span class="text-xs font-semibold tabular-nums text-gray-700 dark:text-gray-200" x-text="metric === 'lines' ? '+' + num(row.added) : num(value(row))"></span>
                                        <template x-if="metric !== 'lines'">
                                            <div
                                                class="w-full max-w-16 rounded-t-md transition-all duration-500"
                                                x-bind:class="row.selected ? 'bg-primary-500' : 'bg-gray-300 dark:bg-white/20'"
                                                x-bind:style="'height:' + height(value(row)) + '%' + (row.partial ? ';background-image:repeating-linear-gradient(45deg,rgba(255,255,255,.35) 0 6px,transparent 6px 12px)' : '')"
                                                x-bind:title="row.label + ': ' + num(value(row))"
                                            ></div>
                                        </template>
                                        <template x-if="metric === 'lines'">
                                            <div class="flex h-full w-full max-w-16 items-end gap-1">
                                                <div class="flex-1 rounded-t-md bg-emerald-500 transition-all duration-500" x-bind:class="row.selected ? '' : 'opacity-50'" x-bind:style="'height:' + height(row.added) + '%'" x-bind:title="'Escritas: ' + num(row.added)"></div>
                                                <div class="flex-1 rounded-t-md bg-rose-500 transition-all duration-500" x-bind:class="row.selected ? '' : 'opacity-50'" x-bind:style="'height:' + height(row.deleted) + '%'" x-bind:title="'Eliminadas: ' + num(row.deleted)"></div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                            <div class="flex min-w-[36rem] gap-3 pt-2">
                                <template x-for="row in series" :key="'label-' + row.month">
                                    <div class="flex-1 text-center text-xs" x-bind:class="row.selected ? 'font-semibold text-primary-600 dark:text-primary-400' : 'text-gray-500 dark:text-gray-400'">
                                        <span x-text="row.label"></span><span x-show="row.partial"> (en curso)</span>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <p x-show="metric === 'lines'" class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                            <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-emerald-500"></span> Escritas</span>
                            <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-rose-500"></span> Eliminadas</span>
                        </p>
                        <p x-show="metric === 'lines_total'" class="text-xs text-gray-500 dark:text-gray-400">
                            Líneas de código propias al cierre de cada mes (aproximado: se reconstruye restando al total de hoy lo que creció después).
                        </p>
                    </div>
                </x-filament::section>

                {{-- Tabla comparativa --}}
                <x-filament::section icon="heroicon-o-table-cells" heading="Comparativo detallado" description="Cada cifra del mes elegido frente a cada mes anterior y a su promedio. La estrella marca el mejor mes de la ventana.">
                    <div class="overflow-x-auto rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
                        <table class="w-full min-w-[56rem] text-sm">
                            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                                <tr>
                                    <th class="px-3 py-2 text-start font-medium">Indicador</th>
                                    @foreach ($r['table']['columns'] as $column)
                                        <th class="px-3 py-2 text-end font-medium {{ $column['selected'] ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300' : '' }}">{{ $column['label'] }}</th>
                                    @endforeach
                                    <th class="px-3 py-2 text-end font-medium">Promedio</th>
                                    <th class="px-3 py-2 text-end font-medium">Vs promedio</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                @foreach ($r['table']['rows'] as $row)
                                    <tr class="hover:bg-gray-50/60 dark:hover:bg-white/5">
                                        <td class="px-3 py-2 text-gray-700 dark:text-gray-200" title="{{ $row['hint'] }}">{{ $row['label'] }}</td>
                                        @foreach ($row['values'] as $index => $value)
                                            @php $isSelected = $r['table']['columns'][$index]['selected']; @endphp
                                            <td class="px-3 py-2 text-end tabular-nums {{ $isSelected ? 'bg-primary-50/60 font-semibold text-gray-950 dark:bg-primary-500/10 dark:text-white' : 'text-gray-600 dark:text-gray-300' }}">
                                                {{ $fmt($value) }}
                                                @if ($value > 0 && $value === $row['max'])
                                                    <span class="text-amber-500" title="Mejor mes de la ventana">★</span>
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="px-3 py-2 text-end tabular-nums text-gray-500 dark:text-gray-400">{{ $fmt($row['average'], abs($row['average']) < 10 ? 1 : 0) }}</td>
                                        <td class="px-3 py-2 text-end">
                                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium {{ $deltaClasses($row['delta']) }}">{{ $row['delta']['text'] }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($r['inProgress'])
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $r['monthLabel'] }} está en curso: su columna suma hasta hoy, mientras que los demás meses están completos.</p>
                    @endif
                </x-filament::section>

                {{-- Módulos --}}
                <x-filament::section icon="heroicon-o-squares-2x2" heading="Trabajo por módulo" :description="'Cuánto cambió cada módulo del negocio en '.$r['monthLabel'].' (líneas escritas + eliminadas) y cómo viene en los meses anteriores.'">
                    @if ($r['modules']['rows'] === [])
                        <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">No hay cambios en módulos en este período.</p>
                    @else
                        <div class="grid grid-cols-1 gap-6 2xl:grid-cols-2">
                            {{-- Peso de cada módulo en el mes --}}
                            <div class="flex flex-col gap-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Peso en {{ $r['monthLabel'] }}</p>
                                @foreach ($r['modules']['rows'] as $module)
                                    <div wire:key="sys-module-{{ $module['key'] }}-{{ $r['month'] }}" class="flex flex-col gap-1">
                                        <div class="flex items-center justify-between gap-3 text-sm">
                                            <span class="flex min-w-0 items-center gap-2 text-gray-700 dark:text-gray-200">
                                                <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $module['color'] }}"></span>
                                                <span class="truncate">{{ $module['label'] }}</span>
                                            </span>
                                            <span class="flex shrink-0 items-center gap-2 text-xs tabular-nums text-gray-500 dark:text-gray-400">
                                                <span title="Commits que tocaron el módulo">{{ $fmt($module['commits']) }} commits</span>
                                                <span>·</span>
                                                <span title="Archivos distintos del módulo con cambios">{{ $fmt($module['files']) }} archivos</span>
                                                <span class="w-12 text-end font-semibold text-gray-950 dark:text-white">{{ $fmt($module['share'], 1) }} %</span>
                                            </span>
                                        </div>
                                        <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                                            <div class="h-full rounded-full" style="width: {{ $module['share'] }}%; background-color: {{ $module['color'] }}"></div>
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            +{{ $fmt($module['added']) }} / −{{ $fmt($module['deleted']) }} líneas ·
                                            <span class="{{ $module['delta']['direction'] === 'up' ? 'text-emerald-600 dark:text-emerald-400' : ($module['delta']['direction'] === 'down' ? 'text-amber-600 dark:text-amber-400' : '') }}">{{ $module['delta']['text'] }}</span>
                                            vs su promedio ({{ $fmt($module['average']) }} líneas/mes)
                                        </p>
                                    </div>
                                @endforeach
                            </div>

                            {{-- Mapa de calor módulo × mes --}}
                            <div class="flex flex-col gap-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Evolución por módulo (líneas cambiadas por mes)</p>
                                <div class="overflow-x-auto">
                                    <table class="w-full min-w-[34rem] border-separate border-spacing-1 text-xs">
                                        <thead>
                                            <tr>
                                                <th class="text-start font-medium text-gray-500 dark:text-gray-400">Módulo</th>
                                                @foreach ($r['modules']['columns'] as $column)
                                                    <th class="text-center font-medium {{ $column['selected'] ? 'text-primary-600 dark:text-primary-400' : 'text-gray-500 dark:text-gray-400' }}">{{ $column['label'] }}</th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($r['modules']['rows'] as $module)
                                                <tr>
                                                    <td class="max-w-44 truncate pe-2 text-gray-700 dark:text-gray-200" title="{{ $module['label'] }}">{{ $module['label'] }}</td>
                                                    @foreach ($module['cells'] as $index => $cell)
                                                        <td
                                                            class="rounded-md px-1.5 py-1.5 text-center tabular-nums {{ $r['modules']['columns'][$index]['selected'] ? 'ring-2 ring-primary-500/60' : '' }} {{ $cell['intensity'] >= 0.55 ? 'text-white' : 'text-gray-700 dark:text-gray-200' }}"
                                                            style="{{ $cell['value'] > 0 ? 'background-color: color-mix(in srgb, '.$module['color'].' '.(int) round(12 + $cell['intensity'] * 88).'%, transparent);' : '' }}"
                                                            title="{{ $module['label'] }} · {{ $r['modules']['columns'][$index]['label'] }}: {{ $fmt($cell['value']) }} líneas"
                                                        >{{ $cell['value'] > 0 ? ($cell['value'] >= 1000 ? $fmt($cell['value'] / 1000, 1).'k' : $fmt($cell['value'])) : '·' }}</td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">El color más intenso marca el mes de más trabajo de cada módulo (la escala es propia de cada fila).</p>
                            </div>
                        </div>
                    @endif
                </x-filament::section>

                <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                    {{-- Capas técnicas --}}
                    <x-filament::section icon="heroicon-o-square-3-stack-3d" heading="Dónde se trabajó (capas del sistema)" description="Reparto del trabajo del mes por tipo de pieza técnica, frente a su promedio.">
                        <div class="flex flex-col gap-3">
                            @forelse ($r['layers'] as $layer)
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center justify-between gap-3 text-sm">
                                        <span class="text-gray-700 dark:text-gray-200" title="{{ $layer['hint'] }}">{{ $layer['label'] }}</span>
                                        <span class="flex shrink-0 items-center gap-2 text-xs tabular-nums text-gray-500 dark:text-gray-400">
                                            {{ $fmt($layer['files']) }} archivos
                                            <span class="inline-flex rounded-full px-2 py-0.5 font-medium {{ $deltaClasses($layer['delta']) }}">{{ $layer['delta']['text'] }}</span>
                                            <span class="w-12 text-end font-semibold text-gray-950 dark:text-white">{{ $fmt($layer['share'], 1) }} %</span>
                                        </span>
                                    </div>
                                    <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                                        <div class="h-full rounded-full bg-primary-500" style="width: {{ $layer['share'] }}%"></div>
                                    </div>
                                </div>
                            @empty
                                <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">Sin cambios en este mes.</p>
                            @endforelse
                        </div>
                    </x-filament::section>

                    {{-- Equipo --}}
                    <x-filament::section icon="heroicon-o-user-group" heading="Quién entregó" :description="'Entregas de código por autor en '.$r['monthLabel'].'.'">
                        <div class="flex flex-col gap-3">
                            @forelse ($r['authors'] as $author)
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-gray-700 dark:text-gray-200">{{ $author['name'] }}</span>
                                        <span class="text-xs tabular-nums text-gray-500 dark:text-gray-400">{{ $fmt($author['commits']) }} commits · <span class="font-semibold text-gray-950 dark:text-white">{{ $fmt($author['share'], 1) }} %</span></span>
                                    </div>
                                    <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                                        <div class="h-full rounded-full bg-teal-500" style="width: {{ $author['share'] }}%"></div>
                                    </div>
                                </div>
                            @empty
                                <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">Sin entregas en este mes.</p>
                            @endforelse
                        </div>
                    </x-filament::section>
                </div>

                {{-- Inventario --}}
                <x-filament::section icon="heroicon-o-cube-transparent" heading="Crecimiento del sistema (piezas)" :description="'Cuántas piezas tiene el sistema hoy, cuántas tenía al cierre de '.$r['monthLabel'].' y cuántas antes de los meses comparados. Código propio hoy: '.$fmt($r['totals']['lines']).' líneas en '.$fmt($r['totals']['files']).' archivos (aprox.).'">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-5">
                        @foreach ($r['inventory'] as $kind)
                            @php
                                $series = $kind['series'];
                                $seriesMax = max(1, max($series));
                                $seriesMin = min($series);
                                $range = max(1, $seriesMax - $seriesMin);
                                $points = [];
                                foreach ($series as $i => $value) {
                                    $x = count($series) > 1 ? round($i * 100 / (count($series) - 1), 2) : 0;
                                    $y = round(28 - (($value - $seriesMin) * 24 / $range), 2);
                                    $points[] = $x.','.$y;
                                }
                            @endphp
                            <div class="flex flex-col gap-2 rounded-xl bg-gray-50 p-3 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                                <p class="flex items-center gap-1.5 text-xs font-medium text-gray-500 dark:text-gray-400">
                                    <x-filament::icon :icon="$kind['icon']" class="size-4" />
                                    {{ $kind['label'] }}
                                </p>
                                <div class="flex items-end justify-between gap-2">
                                    <span class="text-2xl font-bold tabular-nums text-gray-950 dark:text-white">{{ $fmt($kind['end_of_month']) }}</span>
                                    <svg viewBox="0 0 100 30" class="h-8 w-24 text-primary-500" preserveAspectRatio="none" aria-hidden="true">
                                        <polyline points="{{ implode(' ', $points) }}" fill="none" stroke="currentColor" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    <span class="font-semibold text-emerald-600 dark:text-emerald-400">+{{ $fmt($kind['created']) }}</span> creados en el mes ·
                                    {{ $kind['growth']['text'] }} desde {{ $fmt($kind['before_window']) }}
                                    @if ($kind['today'] !== $kind['end_of_month'])
                                        · hoy {{ $fmt($kind['today']) }}
                                    @endif
                                </p>
                            </div>
                        @endforeach
                    </div>
                </x-filament::section>

                {{-- Base de datos --}}
                <x-filament::section icon="heroicon-o-circle-stack" heading="Base de datos" :description="'Cambios de estructura hechos en '.$r['monthLabel'].' y foto de la base a hoy.'">
                    <div class="flex flex-col gap-6">
                        @if ($r['database']['available'])
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <div class="rounded-xl bg-gray-50 p-3 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Tablas hoy</p>
                                    <p class="text-2xl font-bold tabular-nums text-gray-950 dark:text-white">{{ $fmt($r['database']['tables']) }}</p>
                                </div>
                                <div class="rounded-xl bg-gray-50 p-3 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Tamaño (datos + índices)</p>
                                    <p class="text-2xl font-bold tabular-nums text-gray-950 dark:text-white">{{ $r['database']['size_mb'] >= 1024 ? $fmt($r['database']['size_mb'] / 1024, 2).' GB' : $fmt($r['database']['size_mb'], 1).' MB' }}</p>
                                </div>
                                <div class="rounded-xl bg-gray-50 p-3 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400" title="Estimado del motor de base de datos">Registros (aprox.)</p>
                                    <p class="text-2xl font-bold tabular-nums text-gray-950 dark:text-white">{{ $fmt($r['database']['rows']) }}</p>
                                </div>
                            </div>
                        @endif

                        <div class="grid grid-cols-1 gap-6 2xl:grid-cols-3">
                            <div class="flex flex-col gap-3 2xl:col-span-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Migraciones de {{ $r['monthLabel'] }} ({{ count($r['migrations']) }})</p>
                                @if ($r['migrations'] === [])
                                    <p class="rounded-xl bg-gray-50 py-6 text-center text-sm text-gray-500 dark:bg-white/5 dark:text-gray-400">No hubo cambios de estructura en la base de datos este mes.</p>
                                @else
                                    <div class="max-h-[28rem] overflow-auto rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
                                        <table class="w-full min-w-[44rem] text-sm">
                                            <thead class="sticky top-0 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                                                <tr>
                                                    <th class="px-3 py-2 text-start font-medium">Fecha</th>
                                                    <th class="px-3 py-2 text-start font-medium">Cambio</th>
                                                    <th class="px-3 py-2 text-start font-medium">Módulo</th>
                                                    <th class="px-3 py-2 text-end font-medium" title="Columnas definidas">Col.</th>
                                                    <th class="px-3 py-2 text-end font-medium" title="Índices y llaves">Índ.</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                                @foreach ($r['migrations'] as $migration)
                                                    <tr>
                                                        <td class="whitespace-nowrap px-3 py-2 text-gray-500 dark:text-gray-400">{{ $migration['date_text'] }}</td>
                                                        <td class="px-3 py-2">
                                                            <p class="text-gray-800 dark:text-gray-100">{{ $migration['label'] }}</p>
                                                            <p class="mt-0.5 flex flex-wrap gap-1 text-xs">
                                                                @foreach ($migration['created'] as $table)
                                                                    <span class="rounded bg-emerald-50 px-1.5 py-0.5 font-mono text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300" title="Tabla nueva">+ {{ $table }}</span>
                                                                @endforeach
                                                                @foreach ($migration['altered'] as $table)
                                                                    <span class="rounded bg-sky-50 px-1.5 py-0.5 font-mono text-sky-700 dark:bg-sky-500/15 dark:text-sky-300" title="Tabla modificada">~ {{ $table }}</span>
                                                                @endforeach
                                                                @foreach ($migration['dropped'] as $table)
                                                                    <span class="rounded bg-rose-50 px-1.5 py-0.5 font-mono text-rose-700 dark:bg-rose-500/15 dark:text-rose-300" title="Tabla retirada">− {{ $table }}</span>
                                                                @endforeach
                                                            </p>
                                                        </td>
                                                        <td class="px-3 py-2">
                                                            <span class="inline-flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-300">
                                                                <span class="size-2 rounded-full" style="background-color: {{ $migration['module_color'] }}"></span>
                                                                {{ $migration['module_label'] }}
                                                            </span>
                                                        </td>
                                                        <td class="px-3 py-2 text-end tabular-nums text-gray-600 dark:text-gray-300">{{ $fmt($migration['columns']) }}</td>
                                                        <td class="px-3 py-2 text-end tabular-nums text-gray-600 dark:text-gray-300">{{ $fmt($migration['indexes']) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif

                                @if ($r['database']['monthByModule'] !== [])
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($r['database']['monthByModule'] as $module)
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-50 px-2.5 py-1 text-xs text-gray-600 ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
                                                <span class="size-2 rounded-full" style="background-color: {{ $module['color'] }}"></span>
                                                {{ $module['label'] }}: {{ $module['migrations'] }} migr. · {{ $module['tables'] }} tablas · {{ $module['columns'] }} col.
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <div class="flex flex-col gap-4">
                                @if ($r['database']['tablesSeries'] !== [])
                                    @php $tablesMax = max(1, max(array_column($r['database']['tablesSeries'], 'value'))); $tablesMin = min(array_column($r['database']['tablesSeries'], 'value')); @endphp
                                    <div class="flex flex-col gap-2">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Tablas al cierre de cada mes (aprox.)</p>
                                        @foreach ($r['database']['tablesSeries'] as $point)
                                            <div class="flex items-center gap-2 text-xs">
                                                <span class="w-14 shrink-0 text-gray-500 dark:text-gray-400">{{ $point['label'] }}</span>
                                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                                                    <div class="h-full rounded-full bg-primary-500" style="width: {{ max(4, (int) round(($point['value'] - $tablesMin * 0.9) * 100 / max(1, $tablesMax - $tablesMin * 0.9))) }}%"></div>
                                                </div>
                                                <span class="w-10 text-end tabular-nums text-gray-700 dark:text-gray-200">{{ $fmt($point['value']) }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                @if ($r['database']['largest'] !== [])
                                    <div class="flex flex-col gap-2">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Tablas más pesadas hoy</p>
                                        <ul class="divide-y divide-gray-100 rounded-xl ring-1 ring-gray-950/5 dark:divide-white/5 dark:ring-white/10">
                                            @foreach ($r['database']['largest'] as $table)
                                                <li class="flex items-center justify-between gap-2 px-3 py-1.5 text-xs">
                                                    <span class="min-w-0">
                                                        <span class="block truncate font-mono text-gray-800 dark:text-gray-100">{{ $table['table'] }}</span>
                                                        <span class="text-gray-400 dark:text-gray-500">{{ $table['module_label'] }} · {{ $fmt($table['rows']) }} registros</span>
                                                    </span>
                                                    <span class="shrink-0 tabular-nums text-gray-600 dark:text-gray-300">{{ $fmt($table['size_mb'], 1) }} MB</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </x-filament::section>

                {{-- Commits destacados --}}
                <x-filament::section icon="heroicon-o-star" heading="Entregas más grandes del mes" :description="'Los commits de '.$r['monthLabel'].' con más líneas cambiadas.'" collapsible>
                    @if ($r['topCommits'] === [])
                        <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">Sin entregas en este mes.</p>
                    @else
                        <ul class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($r['topCommits'] as $commit)
                                <li class="flex flex-col gap-1 py-2.5 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                                    <div class="min-w-0">
                                        <p class="text-sm text-gray-800 dark:text-gray-100" title="{{ $commit['subject'] }}">{{ $commit['subject_short'] }}</p>
                                        <p class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-gray-500 dark:text-gray-400">
                                            <span class="font-mono">{{ $commit['hash'] }}</span>
                                            <span>{{ $commit['date_text'] }}</span>
                                            <span>{{ $commit['author'] }}</span>
                                            <span class="inline-flex items-center gap-1"><span class="size-2 rounded-full" style="background-color: {{ $commit['module_color'] }}"></span>{{ $commit['module_label'] }}</span>
                                        </p>
                                    </div>
                                    <p class="shrink-0 text-xs tabular-nums">
                                        <span class="text-emerald-600 dark:text-emerald-400">+{{ $fmt($commit['added']) }}</span>
                                        <span class="text-rose-600 dark:text-rose-400">−{{ $fmt($commit['deleted']) }}</span>
                                        <span class="text-gray-500 dark:text-gray-400">· {{ $fmt($commit['files']) }} archivos</span>
                                    </p>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-filament::section>

                {{-- Cómo se mide --}}
                <details class="rounded-xl bg-white p-4 text-sm text-gray-600 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:text-gray-300 dark:ring-white/10">
                    <summary class="cursor-pointer font-medium text-gray-800 dark:text-gray-100">¿Cómo se mide?</summary>
                    <ul class="mt-3 list-disc space-y-1 ps-5">
                        <li>Se lee el historial de git de la versión desplegada (commits sin fusiones). Lo que no se ha subido en un commit no cuenta.</li>
                        <li>No cuentan librerías de terceros ni archivos generados (vendor, node_modules, public, builds, archivos .lock).</li>
                        <li>Un archivo movido o renombrado cuenta como retirado y nuevo.</li>
                        <li>El módulo de cada archivo se deduce de su nombre y su carpeta (por ejemplo, <em>OperationServiceOrder</em> → Operaciones). Lo que no pertenece a un módulo va a «Plataforma base».</li>
                        <li>Las migraciones se analizan por su contenido: tablas creadas (<code>Schema::create</code>), tablas modificadas (<code>Schema::table</code>), columnas e índices.</li>
                        <li>La foto de la base de datos (tablas, tamaño y registros) es la de hoy; el número de registros es el estimado del motor.</li>
                        <li>Las cifras se recalculan solas cada vez que se sube código nuevo; el botón «Recalcular» fuerza la lectura.</li>
                    </ul>
                </details>
            </div>
        @endif
    </div>
</x-filament-panels::page>
