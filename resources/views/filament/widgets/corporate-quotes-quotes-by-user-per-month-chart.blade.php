@php
    use Filament\Widgets\View\Components\ChartWidgetComponent;
    use Illuminate\View\ComponentAttributeBag;

    $color = $this->getColor();
    $heading = $this->getHeading();
    $description = $this->getDescription();
    $filters = $this->getFilters();
    $isCollapsible = $this->isCollapsible();
    $type = $this->getType();
    $legendItems = $this->getFilterLegendItems();
@endphp

<x-filament-widgets::widget class="fi-wi-chart">
    <x-filament::section
        :description="$description"
        :heading="$heading"
        :collapsible="$isCollapsible"
    >
        @if ($filters || method_exists($this, 'getFiltersSchema'))
            <x-slot name="afterHeader">
                @if ($filters)
                    <x-filament::input.wrapper
                        inline-prefix
                        wire:target="filter"
                        class="fi-wi-chart-filter"
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
                @endif

                @if (method_exists($this, 'getFiltersSchema'))
                    <x-filament::dropdown
                        placement="bottom-end"
                        shift
                        width="xs"
                        class="fi-wi-chart-filter"
                    >
                        <x-slot name="trigger">
                            {{ $this->getFiltersTriggerAction() }}
                        </x-slot>

                        <div class="fi-wi-chart-filter-content">
                            {{ $this->getFiltersSchema() }}

                            @if (method_exists($this, 'hasDeferredFilters') && $this->hasDeferredFilters())
                                <div class="fi-wi-chart-filter-content-actions-ctn">
                                    {{ $this->getFiltersApplyAction() }}
                                    {{ $this->getFiltersResetAction() }}
                                </div>
                            @endif
                        </div>
                    </x-filament::dropdown>
                @endif
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
                wire:key="cq-quotes-by-user-per-month-{{ $this->filter ?? now()->year }}"
                data-chart-type="{{ $type }}"
                x-data="chart({
                            cachedData: @js($this->getCachedData()),
                            maxHeight: @js($maxHeight = $this->getMaxHeight()),
                            options: @js($this->getOptions()),
                            type: @js($type),
                        })"
                style="height: {{ $this->getMaxHeight() ?? '440px' }}; width: 100%; box-sizing: border-box;"
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
                    @if ($maxHeight)
                        style="max-height: {{ $maxHeight }}"
                    @endif
                ></canvas>

                <span x-ref="backgroundColorElement" class="fi-wi-chart-bg-color"></span>
                <span x-ref="borderColorElement" class="fi-wi-chart-border-color"></span>
                <span x-ref="gridColorElement" class="fi-wi-chart-grid-color"></span>
                <span x-ref="textColorElement" class="fi-wi-chart-text-color"></span>
            </div>

            <div
                class="mt-4 w-full border-t border-gray-200 pt-3 text-center dark:border-white/10"
                wire:key="cq-quotes-by-user-legend-{{ $this->filter ?? now()->year }}"
            >
                @if (count($legendItems) > 0)
                    <ul class="m-0 flex w-full list-none flex-wrap items-center justify-center gap-x-3 gap-y-2 p-0 text-xs sm:text-sm text-gray-600 dark:text-gray-400">
                        @foreach ($legendItems as $item)
                            <li class="inline-flex max-w-full items-center gap-1.5">
                                <span
                                    class="inline-block size-2.5 shrink-0 rounded-sm ring-1 ring-gray-950/10 dark:ring-white/10"
                                    style="background-color: {{ $item['color'] }}"
                                    aria-hidden="true"
                                ></span>
                                <span class="min-w-0 truncate font-medium text-gray-950 dark:text-white">{{ $item['label'] }}</span>
                                <span class="shrink-0 tabular-nums text-gray-500 dark:text-gray-400">({{ $item['total'] }})</span>
                            </li>
                        @endforeach
                    </ul>
                @elseif ($filters)
                    <p class="m-0 text-xs text-gray-500 dark:text-gray-400">
                        Ningún usuario con más de 1 cotización en este año.
                    </p>
                @endif
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
