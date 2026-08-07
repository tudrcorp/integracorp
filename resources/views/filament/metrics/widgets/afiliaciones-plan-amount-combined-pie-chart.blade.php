@php
    use Filament\Widgets\View\Components\ChartWidgetComponent;
    use Illuminate\View\ComponentAttributeBag;

    $color = $this->getColor();
    $heading = $this->getHeading();
    $description = $this->getDescription();
    $isCollapsible = $this->isCollapsible();
    $type = $this->getType();
    $hasData = $this->iosBarChartHasData();
    $cachedData = $hasData ? $this->getCachedData() : null;
    $maxHeight = $this->getMaxHeight();
@endphp

<x-filament-widgets::widget class="fi-wi-chart fi-metrics-agents-by-state-chart fi-metrics-afiliaciones-plan-amount-pie fi-metrics-afiliaciones-plan-amount-combined-pie">
    <x-filament::section
        :description="$description"
        :heading="$heading"
        :collapsible="$isCollapsible"
    >
        <div>
            @if (! $hasData)
                <div class="fi-metrics-agents-by-state-chart__empty" role="status">
                    <div class="fi-metrics-agents-by-state-chart__empty-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-9">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 1 0 7.5 7.5h-7.5V6Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0 0 13.5 3v7.5Z" />
                        </svg>
                    </div>
                    <p class="fi-metrics-agents-by-state-chart__empty-title">
                        {{ $this->getIosBarChartEmptyTitle() }}
                    </p>
                    <p class="fi-metrics-agents-by-state-chart__empty-body">
                        {{ $this->getIosBarChartEmptyBody() }}
                    </p>
                </div>
            @else
                <div class="fi-metrics-agents-by-state-chart__canvas fi-metrics-afiliaciones-plan-amount-pie__canvas">
                    <div
                        x-load
                        x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
                        wire:ignore
                        wire:key="{{ $this->getIosBarChartWireKey() }}"
                        data-chart-type="{{ $type }}"
                        x-data="chart({
                                    cachedData: @js($cachedData),
                                    maxHeight: @js($maxHeight),
                                    options: @js($this->getOptions()),
                                    type: @js($type),
                                })"
                        style="height: {{ $maxHeight }}; width: 100%; box-sizing: border-box;"
                        {{
                            (new ComponentAttributeBag)
                                ->color(ChartWidgetComponent::class, $color)
                                ->class([
                                    'fi-wi-chart-canvas-ctn',
                                    'fi-wi-chart-canvas-ctn-no-aspect-ratio' => filled($maxHeight),
                                ])
                        }}
                    >
                        <canvas
                            x-ref="canvas"
                            class="fi-metrics-agents-by-state-chart__canvas-el"
                        ></canvas>

                        <span x-ref="backgroundColorElement" class="fi-wi-chart-bg-color"></span>
                        <span x-ref="borderColorElement" class="fi-wi-chart-border-color"></span>
                        <span x-ref="gridColorElement" class="fi-wi-chart-grid-color"></span>
                        <span x-ref="textColorElement" class="fi-wi-chart-text-color"></span>
                    </div>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
