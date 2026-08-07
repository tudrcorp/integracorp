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

<x-filament-widgets::widget class="fi-wi-chart fi-metrics-agents-by-state-chart fi-metrics-agents-by-affiliation-amount-chart">
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
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
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
