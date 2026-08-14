@php
    use Filament\Widgets\View\Components\ChartWidgetComponent;
    use Illuminate\View\ComponentAttributeBag;

    $color = $this->getColor();
    $heading = $this->getHeading();
    $description = $this->getDescription();
    $isCollapsible = $this->isCollapsible();
    $type = $this->getType();
    $maxHeight = $this->getMaxHeight() ?? '360px';
@endphp

<x-filament-widgets::widget class="fi-wi-chart">
    <x-filament::section
        :description="$description"
        :heading="$heading"
        :collapsible="$isCollapsible"
    >
        <div
            @if ($pollingInterval = $this->getPollingInterval())
                wire:poll.{{ $pollingInterval }}="updateChartData"
            @endif
        >
            @if ($this->selectedMonth !== null && $this->selectedYear !== null)
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2 text-sm font-semibold text-gray-900 dark:text-white">
                    <span>
                        Detalle diario · {{ $this->selectedMonthLabel() }} {{ $this->selectedYear }}
                    </span>
                    <x-filament::button
                        wire:click="resetToMonths"
                        wire:loading.attr="disabled"
                        size="sm"
                        color="gray"
                        icon="heroicon-m-arrow-uturn-left"
                    >
                        Volver a meses
                    </x-filament::button>
                </div>
            @elseif ($this->selectedYear !== null)
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2 text-sm font-semibold text-gray-900 dark:text-white">
                    <span>
                        Detalle mensual · {{ $this->selectedYear }}
                    </span>
                    <x-filament::button
                        wire:click="resetToYears"
                        wire:loading.attr="disabled"
                        size="sm"
                        color="gray"
                        icon="heroicon-m-arrow-uturn-left"
                    >
                        Volver a años
                    </x-filament::button>
                </div>
            @endif

            <div
                x-load
                x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
                wire:ignore
                wire:key="affiliation-corporate-chart-{{ $this->getId() }}-{{ $this->chartKey }}-{{ $this->selectedYear ?? 'years' }}-{{ $this->selectedMonth ?? 'year' }}"
                data-chart-type="{{ $type }}"
                x-data="chart({
                            cachedData: @js($this->getCachedData()),
                            maxHeight: @js($maxHeight),
                            options: @js($this->getOptions()),
                            type: @js($type),
                        })"
                {{
                    (new ComponentAttributeBag)
                        ->color(ChartWidgetComponent::class, $color)
                        ->class([
                            'fi-wi-chart-canvas-ctn',
                            'fi-wi-chart-canvas-ctn-no-aspect-ratio',
                        ])
                }}
            >
                <canvas
                    x-ref="canvas"
                    @if ($maxHeight)
                        style="max-height: {{ $maxHeight }}"
                    @endif
                ></canvas>

                <span x-ref="backgroundColorElement" class="fi-wi-chart-bg-color"></span>
                <span x-ref="borderColorElement" class="fi-wi-chart-border-color"></span>
                <span x-ref="gridColorElement" class="fi-wi-chart-grid-color"></span>
                <span x-ref="textColorElement" class="fi-wi-chart-text-color"></span>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
