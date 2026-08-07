@php
    use Filament\Widgets\View\Components\ChartWidgetComponent;
    use Illuminate\View\ComponentAttributeBag;

    $data = $this->getComparisonViewData();
    $currentLabel = $data['current_label'];
    $previousLabel = $data['previous_label'];
    $registeredPeriodLabel = $data['registered_period_label'] ?? 'Registrados este mes';
    $wireKeyPrefix = $data['wire_key_prefix'] ?? 'registration-mom';
    $eyebrow = $data['eyebrow'] ?? 'Captación mensual';
    $title = $data['title'] ?? 'Mes en curso vs mes pasado';
    $subtitlePrefix = $data['subtitle_prefix'] ?? 'Comparativo de altas registradas';
    $chartHint = $data['chart_hint'] ?? 'Altas por mes';
    $gridCols = (int) ($data['grid_cols'] ?? 2);
    $gridClass = $gridCols >= 3
        ? 'fi-metrics-registration-mom__grid fi-metrics-registration-mom__grid--3'
        : 'fi-metrics-registration-mom__grid';
    $cards = $data['cards'];
@endphp

<x-filament-widgets::widget class="fi-metrics-registration-mom">
    <div class="fi-metrics-registration-mom__shell">
        <div class="fi-metrics-registration-mom__header">
            <div>
                <p class="fi-metrics-registration-mom__eyebrow">{{ $eyebrow }}</p>
                <h3 class="fi-metrics-registration-mom__title">{{ $title }}</h3>
                <p class="fi-metrics-registration-mom__subtitle">
                    {{ $subtitlePrefix }} ·
                    <span class="tabular-nums">{{ $currentLabel }}</span>
                    vs
                    <span class="tabular-nums">{{ $previousLabel }}</span>
                </p>
            </div>
            <div class="fi-metrics-registration-mom__legend" aria-hidden="true">
                <span class="fi-metrics-registration-mom__legend-item fi-metrics-registration-mom__legend-item--up">↑ Subió</span>
                <span class="fi-metrics-registration-mom__legend-item fi-metrics-registration-mom__legend-item--down">↓ Bajó</span>
                <span class="fi-metrics-registration-mom__legend-item fi-metrics-registration-mom__legend-item--flat">= Se mantuvo igual</span>
            </div>
        </div>

        <div class="{{ $gridClass }}">
            @foreach ($cards as $card)
                @php
                    $trend = $card['trend'];
                    $decimals = (int) ($card['decimals'] ?? 0);
                    $valuePrefix = (string) ($card['value_prefix'] ?? '');
                    $delta = (float) $card['delta'];
                    $deltaLabel = ($delta > 0 ? '+' : '').$valuePrefix.number_format($delta, $decimals, ',', '.');
                    $chart = is_array($card['chart'] ?? null) ? $card['chart'] : null;
                    $chartData = is_array($chart['data'] ?? null) ? $chart['data'] : null;
                    $chartOptions = is_array($chart['options'] ?? null) ? $chart['options'] : [];
                    $chartYear = (int) ($chart['year'] ?? 0);
                    $hasChart = is_array($chartData)
                        && filled($chartData['labels'] ?? null)
                        && filled($chartData['datasets'] ?? null);
                    $chartColor = match ($card['accent'] ?? 'sky') {
                        'violet' => 'purple',
                        'emerald' => 'success',
                        'rose' => 'danger',
                        default => 'info',
                    };
                @endphp
                <article
                    class="fi-metrics-registration-mom__card fi-metrics-registration-mom__card--{{ $card['accent'] }} fi-metrics-registration-mom__card--trend-{{ $trend }}"
                    wire:key="{{ $wireKeyPrefix }}-{{ $card['key'] }}"
                >
                    <div class="fi-metrics-registration-mom__card-top">
                        <div>
                            <p class="fi-metrics-registration-mom__card-label">{{ $card['title'] }}</p>
                            <p class="fi-metrics-registration-mom__card-period">{{ $registeredPeriodLabel }}</p>
                        </div>
                        <div
                            class="fi-metrics-registration-mom__badge fi-metrics-registration-mom__badge--{{ $trend }}"
                            title="{{ $card['verdict_detail'] }}"
                        >
                            @if ($trend === 'up')
                                <x-filament::icon icon="heroicon-m-arrow-trending-up" class="fi-metrics-registration-mom__badge-icon" />
                            @elseif ($trend === 'down')
                                <x-filament::icon icon="heroicon-m-arrow-trending-down" class="fi-metrics-registration-mom__badge-icon" />
                            @else
                                <x-filament::icon icon="heroicon-m-minus" class="fi-metrics-registration-mom__badge-icon" />
                            @endif
                            <span class="tabular-nums">{{ $card['percent_label'] }}</span>
                        </div>
                    </div>

                    <div class="fi-metrics-registration-mom__metrics">
                        <div>
                            <p class="fi-metrics-registration-mom__current tabular-nums">
                                {{ $valuePrefix }}{{ number_format((float) $card['current'], $decimals, ',', '.') }}
                            </p>
                            <p class="fi-metrics-registration-mom__current-hint">{{ $currentLabel }}</p>
                        </div>
                        <div class="fi-metrics-registration-mom__vs">
                            <p class="fi-metrics-registration-mom__vs-label">frente al mes pasado</p>
                            <p class="fi-metrics-registration-mom__previous tabular-nums">
                                {{ $valuePrefix }}{{ number_format((float) $card['previous'], $decimals, ',', '.') }}
                            </p>
                            <p class="fi-metrics-registration-mom__delta tabular-nums fi-metrics-registration-mom__delta--{{ $trend }}">
                                Diferencia: {{ $deltaLabel }}
                            </p>
                        </div>
                    </div>

                    @if (filled($card['verdict'] ?? null))
                        <p class="fi-metrics-registration-mom__verdict" title="{{ $card['verdict_detail'] ?? '' }}">
                            {{ $card['verdict'] }}
                        </p>
                    @endif

                    @if ($hasChart)
                        <div class="fi-metrics-registration-mom__chart">
                            <div class="fi-metrics-registration-mom__chart-head">
                                <span class="fi-metrics-registration-mom__chart-title">Evolución {{ $chartYear > 0 ? $chartYear : 'del año' }}</span>
                                <span class="fi-metrics-registration-mom__chart-hint">{{ $chartHint }}</span>
                            </div>
                            <div class="fi-metrics-registration-mom__chart-canvas">
                                <div
                                    x-load
                                    x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
                                    wire:ignore
                                    wire:key="{{ $wireKeyPrefix }}-{{ $card['key'] }}-year-chart"
                                    data-chart-type="line"
                                    x-data="chart({
                                                cachedData: @js($chartData),
                                                maxHeight: @js('96px'),
                                                options: @js($chartOptions),
                                                type: @js('line'),
                                            })"
                                    style="height: 96px; width: 100%; box-sizing: border-box;"
                                    {{
                                        (new ComponentAttributeBag)
                                            ->color(ChartWidgetComponent::class, $chartColor)
                                            ->class([
                                                'fi-wi-chart-canvas-ctn',
                                                'fi-wi-chart-canvas-ctn-no-aspect-ratio',
                                                'fi-metrics-registration-mom__chart-ctn',
                                            ])
                                    }}
                                >
                                    <canvas
                                        x-ref="canvas"
                                        class="fi-metrics-registration-mom__chart-el"
                                    ></canvas>

                                    <span x-ref="backgroundColorElement" class="fi-wi-chart-bg-color"></span>
                                    <span x-ref="borderColorElement" class="fi-wi-chart-border-color"></span>
                                    <span x-ref="gridColorElement" class="fi-wi-chart-grid-color"></span>
                                    <span x-ref="textColorElement" class="fi-wi-chart-text-color"></span>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="fi-metrics-registration-mom__verdict fi-metrics-registration-mom__verdict--{{ $trend }}">
                        <span class="fi-metrics-registration-mom__verdict-label">{{ $card['verdict'] }}</span>
                        <span class="fi-metrics-registration-mom__verdict-detail">{{ $card['verdict_detail'] }}</span>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>
