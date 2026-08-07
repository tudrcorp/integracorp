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

<x-filament-widgets::widget class="fi-wi-chart fi-metrics-agents-by-state-chart fi-metrics-afiliaciones-plans-demand">
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
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
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
                <div class="fi-metrics-agents-by-state-chart__canvas">
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
