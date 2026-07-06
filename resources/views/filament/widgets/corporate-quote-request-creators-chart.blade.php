@php
    use Filament\Widgets\View\Components\ChartWidgetComponent;
    use Illuminate\View\ComponentAttributeBag;

    $color = $this->getColor();
    $heading = $this->getHeading();
    $description = $this->getDescription();
    $filters = $this->getFilters();
    $isCollapsible = $this->isCollapsible();
    $type = $this->getType();
    $monthOptions = $this->getMonthSelectOptions();
    $isFullYear = $this->isFullYearPeriod();
    $selectedYear = (int) ($this->filter ?? now()->year);
@endphp

<x-filament-widgets::widget class="fi-wi-chart fi-agency-registrations-chart-like-suppliers">
    <x-filament::section
        :description="$description"
        :heading="$heading"
        :collapsible="$isCollapsible"
    >
        @if ($filters)
            <x-slot name="afterHeader">
                <div class="flex flex-wrap items-center gap-2">
                    <x-filament::input.wrapper
                        inline-prefix
                        wire:target="filter"
                        class="fi-wi-chart-filter w-full sm:w-auto"
                    >
                        <x-filament::input.select
                            inline-prefix
                            wire:model.live="filter"
                        >
                            @foreach ($filters as $value => $label)
                                <option value="{{ $value }}">
                                    {{ $label }}
                                </option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>

                    <x-filament::input.wrapper
                        inline-prefix
                        wire:target="month"
                        class="fi-wi-chart-filter w-full sm:w-auto"
                    >
                        <x-filament::input.select
                            inline-prefix
                            wire:model.live="month"
                        >
                            @foreach ($monthOptions as $value => $label)
                                <option value="{{ $value }}">
                                    {{ $label }}
                                </option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
            </x-slot>
        @endif

        <div
            @if ($pollingInterval = $this->getPollingInterval())
                wire:poll.{{ $pollingInterval }}="updateChartData"
            @endif
        >
            <div
                x-load
                x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
                wire:ignore
                wire:key="cqr-creators-top-{{ $selectedYear }}-{{ $this->month ?? now()->month }}"
                data-chart-type="{{ $type }}"
                x-data="chart({
                            cachedData: @js($this->getCachedData()),
                            maxHeight: @js($maxHeight = $isFullYear ? '320px' : ($this->getMaxHeight() ?? '420px')),
                            options: @js($this->getOptions()),
                            type: @js($type),
                        })"
                style="height: {{ $isFullYear ? '320px' : ($this->getMaxHeight() ?? '420px') }}; width: 100%; box-sizing: border-box;"
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
                    class="block max-w-full"
                    style="max-height: {{ $isFullYear ? '320px' : ($this->getMaxHeight() ?? '420px') }}"
                ></canvas>

                <span x-ref="backgroundColorElement" class="fi-wi-chart-bg-color"></span>
                <span x-ref="borderColorElement" class="fi-wi-chart-border-color"></span>
                <span x-ref="gridColorElement" class="fi-wi-chart-grid-color"></span>
                <span x-ref="textColorElement" class="fi-wi-chart-text-color"></span>
            </div>

            @if ($isFullYear)
                <div class="mt-6 border-t border-gray-200 pt-5 dark:border-white/10">
                    <p class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-900 dark:text-white">
                        Desglose mensual · {{ $selectedYear }}
                    </p>

                    <div
                        x-load
                        x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
                        wire:ignore
                        wire:key="cqr-creators-monthly-{{ $selectedYear }}"
                        data-chart-type="bar"
                        x-data="chart({
                                    cachedData: @js($this->getMonthlyBreakdownChartData()),
                                    maxHeight: @js('280px'),
                                    options: @js($this->getMonthlyBreakdownChartOptions()),
                                    type: 'bar',
                                })"
                        style="height: 280px; width: 100%; box-sizing: border-box;"
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
                            class="block max-w-full"
                            style="max-height: 280px"
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
