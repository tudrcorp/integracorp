@php
    use Filament\Widgets\View\Components\ChartWidgetComponent;
    use Illuminate\View\ComponentAttributeBag;

    /**
     * Lienzo de Chart.js de Filament (misma estructura que la vista base del widget).
     * Con `maintainAspectRatio: false` el lienzo toma la altura del contenedor: sin
     * altura explícita Chart.js cae a 150 px y salta etiquetas.
     */
    $maxHeight = $this->getMaxHeight();
    $chartHeight = method_exists($this, 'getChartHeight') ? $this->getChartHeight() : $maxHeight;
@endphp

<div
    @if ($pollingInterval = $this->getPollingInterval())
        wire:poll.{{ $pollingInterval }}="updateChartData"
    @endif
>
    <div
        x-load
        x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
        wire:ignore
        data-chart-type="{{ $this->getType() }}"
        x-data="chart({
                    cachedData: @js($this->getCachedData()),
                    maxHeight: @js($maxHeight),
                    options: @js($this->getOptions()),
                    type: @js($this->getType()),
                })"
        {{
            (new ComponentAttributeBag)
                ->color(ChartWidgetComponent::class, $this->getColor())
                ->class([
                    'fi-wi-chart-canvas-ctn',
                    'fi-wi-chart-canvas-ctn-no-aspect-ratio' => filled($maxHeight),
                ])
        }}
        @if ($chartHeight)
            style="height: {{ $chartHeight }}"
        @endif
    >
        <canvas x-ref="canvas"></canvas>

        <span x-ref="backgroundColorElement" class="fi-wi-chart-bg-color"></span>
        <span x-ref="borderColorElement" class="fi-wi-chart-border-color"></span>
        <span x-ref="gridColorElement" class="fi-wi-chart-grid-color"></span>
        <span x-ref="textColorElement" class="fi-wi-chart-text-color"></span>
    </div>
</div>
