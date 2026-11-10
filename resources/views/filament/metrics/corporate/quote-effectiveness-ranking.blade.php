@php
    /** @var array{volume: list<array<string, mixed>>, conversions: list<array<string, mixed>>, effectiveness: list<array<string, mixed>>} $rankings */
    $n = fn (int|float $value): string => number_format((float) $value, 0, ',', '.');
    $pct = fn (?float $value): string => $value === null ? '—' : number_format($value, 1, ',', '.').' %';
    $networkRate = $network['rate'];

    $tabs = [
        'effectiveness' => [
            'label' => 'Mejor efectividad',
            'hint' => 'Ordenado por tasa ajustada por volumen: una tasa con más cotizaciones detrás pesa más (10 de 30, un 33 %, va antes que 2 de 5, un 40 %). Mínimo '.$minQuotes.' cotizaciones en el período.',
        ],
        'conversions' => [
            'label' => 'Más convierten',
            'hint' => 'Ordenado por cotizaciones del período que ya son afiliación.',
        ],
        'volume' => [
            'label' => 'Más cotizan',
            'hint' => 'Ordenado por cotizaciones creadas en el período, con lo que lograron convertir.',
        ],
    ];
@endphp

<x-filament-widgets::widget class="corp-quote-effectiveness">
    <x-filament::section
        :heading="$heading"
        :description="'Cotizaciones del período ('.$periodLabel.') y cuántas ya son afiliación. Individuales y corporativas.'"
        icon="heroicon-o-trophy"
    >
        <div x-data="{ tab: 'effectiveness' }" class="space-y-4" wire:key="quote-effectiveness-{{ $dimension }}-{{ $periodKey }}">
            {{-- Promedio de la red: la vara contra la que se mide cada uno --}}
            <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-gray-50 px-3 py-2.5 text-xs ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                <p class="text-gray-600 dark:text-gray-300">
                    <span class="font-semibold text-gray-950 dark:text-white">Promedio de la red: {{ $pct($networkRate) }}</span>
                    · {{ $n($network['issued']) }} de {{ $n($network['created']) }} cotizaciones convertidas
                    · {{ $n($network['sellers']) }} {{ $sellerNoun }} cotizaron ({{ $n($network['eligible']) }} con {{ $minQuotes }} o más)
                </p>
                @if ($compare && $network['previous_rate'] !== null)
                    <p class="text-gray-500 dark:text-gray-400">antes {{ $pct($network['previous_rate']) }}</p>
                @endif
            </div>

            @if ($cohortOpen)
                <p class="flex items-start gap-2 text-[11px] text-gray-600 dark:text-gray-300">
                    <x-filament::icon icon="heroicon-m-clock" class="mt-px size-3.5 shrink-0" style="color: var(--ops-warning)" />
                    Período en maduración: las cotizaciones de los últimos 15 días aún pueden convertirse. Mire también «en curso» antes de juzgar a alguien.
                </p>
            @endif

            {{-- Pestañas --}}
            <div class="flex flex-wrap gap-1 rounded-xl bg-gray-100 p-1 dark:bg-white/5" role="tablist">
                @foreach ($tabs as $key => $tab)
                    <button
                        type="button"
                        role="tab"
                        x-on:click="tab = @js($key)"
                        x-bind:aria-selected="(tab === @js($key)).toString()"
                        x-bind:class="tab === @js($key) ? 'bg-white text-gray-950 shadow-sm dark:bg-gray-800 dark:text-white' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200'"
                        class="flex-1 whitespace-nowrap rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                    >
                        {{ $tab['label'] }}
                    </button>
                @endforeach
            </div>

            @foreach ($tabs as $key => $tab)
                @php
                    $rows = $rankings[$key];
                    $max = max(1, (int) collect($rows)->max('created'));
                @endphp
                <div x-show="tab === @js($key)" @if ($key !== 'effectiveness') x-cloak @endif>
                    <p class="mb-3 text-[11px] text-gray-500 dark:text-gray-400">{{ $tab['hint'] }}</p>

                    @if ($rows === [])
                        <div class="flex h-40 flex-col items-center justify-center gap-2 text-center">
                            <x-filament::icon icon="heroicon-o-trophy" class="size-8 text-gray-300 dark:text-gray-600" />
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $key === 'effectiveness' ? 'Nadie llegó a '.$minQuotes.' cotizaciones en este período.' : ($key === 'conversions' ? 'Ninguna cotización del período se ha convertido todavía.' : 'No hay cotizaciones en este período.') }}
                            </p>
                        </div>
                    @else
                        <ol class="space-y-3">
                            @foreach ($rows as $index => $row)
                                @php
                                    $lost = $row['anulated'] + $row['deleted'];
                                    $width = $row['created'] * 100 / $max;
                                    $part = fn (int $value): float => $row['created'] > 0 ? $value * 100 / $row['created'] : 0;
                                    $vsNetwork = $row['rate'] !== null && $networkRate !== null ? $row['rate'] - $networkRate : null;
                                    $rateColor = $vsNetwork === null || abs($vsNetwork) < 0.05 ? 'var(--ops-muted)' : ($vsNetwork > 0 ? 'var(--ops-good-ink)' : 'var(--ops-bad-ink)');
                                    $tooltip = $row['label'].': '.$n($row['created']).' cotizaciones ('.$n($row['individual_created']).' individuales, '.$n($row['corporate_created']).' corporativas). '
                                        .'Convertidas '.$n($row['issued']).' · en curso '.$n($row['open']).' · anuladas '.$n($row['anulated']).($row['deleted'] > 0 ? ' · eliminadas '.$n($row['deleted']) : '').'.'
                                        .($compare ? ' Período anterior: '.$n($row['previous_issued']).' de '.$n($row['previous_created']).' ('.$pct($row['previous_rate']).').' : '');
                                @endphp
                                <li wire:key="qe-{{ $dimension }}-{{ $key }}-{{ $row['key'] }}" x-tooltip="{ content: @js($tooltip), theme: $store.theme }">
                                    <div class="flex items-baseline justify-between gap-3 text-sm">
                                        <span class="flex min-w-0 items-baseline gap-2">
                                            <span class="w-5 shrink-0 text-right text-xs tabular-nums text-gray-400">{{ $index + 1 }}</span>
                                            <span class="min-w-0">
                                                <span class="block truncate font-medium text-gray-900 dark:text-white">{{ $row['label'] }}</span>
                                                @if (filled($row['sublabel']))
                                                    <span class="block truncate text-[11px] text-gray-500 dark:text-gray-400">{{ $row['sublabel'] }}</span>
                                                @endif
                                            </span>
                                        </span>
                                        <span class="shrink-0 text-right">
                                            <span class="block text-sm font-semibold tabular-nums" style="color: {{ $rateColor }}">{{ $pct($row['rate']) }}</span>
                                            <span class="block text-[11px] tabular-nums text-gray-500 dark:text-gray-400">{{ $n($row['issued']) }} de {{ $n($row['created']) }}</span>
                                        </span>
                                    </div>
                                    <div class="ms-7 mt-1.5 h-2.5 overflow-hidden rounded-full" style="background: var(--ops-track)">
                                        <div class="flex h-full" style="width: {{ round($width, 2) }}%">
                                            <div class="h-full" style="width: {{ round($part($row['issued']), 2) }}%; background: var(--ops-good)"></div>
                                            <div class="h-full" style="width: {{ round($part($row['open']), 2) }}%; background: var(--ops-series1)"></div>
                                            <div class="h-full opacity-60" style="width: {{ round($part($lost), 2) }}%; background: var(--ops-critical)"></div>
                                        </div>
                                    </div>
                                    <div class="ms-7 mt-1 flex flex-wrap gap-x-3 text-[11px] text-gray-500 dark:text-gray-400">
                                        <span>{{ $n($row['open']) }} en curso</span>
                                        @if ($compare && $row['rate_delta'] !== null && abs($row['rate_delta']) >= 0.05)
                                            <span style="color: {{ $row['rate_delta'] > 0 ? 'var(--ops-good-ink)' : 'var(--ops-bad-ink)' }}">
                                                {{ $row['rate_delta'] > 0 ? '+' : '−' }}{{ number_format(abs($row['rate_delta']), 1, ',', '.') }} pp vs ant.
                                            </span>
                                        @elseif ($compare && $row['previous_created'] === 0)
                                            <span>sin cotizaciones el período anterior</span>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            @endforeach

            {{-- Leyenda y reglas --}}
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-gray-200/80 pt-3 text-[11px] text-gray-500 dark:border-white/10 dark:text-gray-400">
                <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full" style="background: var(--ops-good)"></span>Convertidas</span>
                <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full" style="background: var(--ops-series1)"></span>En curso</span>
                <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full opacity-60" style="background: var(--ops-critical)"></span>Anuladas o eliminadas</span>
                <span>· El % en verde está sobre el promedio de la red y en rojo, por debajo.</span>
            </div>
            <details class="text-[11px] text-gray-500 dark:text-gray-400">
                <summary class="cursor-pointer font-medium text-gray-600 dark:text-gray-300">Cómo se calcula</summary>
                <ul class="mt-1.5 list-disc space-y-1 ps-5">
                    <li>Se atribuye a quien <strong>cotizó</strong>, no a quien quedó en la afiliación.</li>
                    <li><strong>Convertida</strong>: la cotización tiene una afiliación vigente creada desde ella, de cualquier plan.</li>
                    <li><strong>Mejor efectividad</strong> usa una tasa ajustada por volumen (límite inferior de Wilson al 95 %) para que una tasa alta con pocas cotizaciones no quede por encima de una sostenida con muchas.</li>
                    @if ($dimension === 'agent' && $withoutAgent > 0)
                        <li>{{ $n($withoutAgent) }} cotizaciones del período no tienen agente (las hizo la agencia directamente): cuentan solo en el ranking de agencias.</li>
                    @endif
                    <li>Las cotizaciones borradas cuentan desde el 09/11/2026; las anteriores no dejaron rastro.</li>
                </ul>
            </details>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
