@php
    use Filament\Widgets\View\Components\ChartWidgetComponent;
    use Illuminate\View\ComponentAttributeBag;

    $color = $this->getColor();
    $heading = $this->getHeading();
    $description = $this->getDescription();
    $isCollapsible = $this->isCollapsible();
    $type = $this->getType();
@endphp

<x-filament-widgets::widget class="fi-wi-chart fi-operations-dashboard-patients-chart">
    <x-filament::section
        :description="$description"
        :heading="$heading"
        :collapsible="$isCollapsible"
    >
        <div
            x-data="{
                showDetail: @entangle('selectedPatientId').live,
                patientName: @entangle('selectedPatientName').live,
            }"
        >
            <div
                class="mb-3 flex flex-wrap items-center justify-between gap-2"
                x-show="showDetail"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 -translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 -translate-y-2"
                x-cloak
            >
                <div class="text-sm font-semibold text-gray-900 dark:text-white">
                    <span class="text-primary-600 dark:text-primary-400">Detalle de casos</span>
                    ·
                    <span x-text="patientName"></span>
                </div>

                <x-filament::button
                    wire:click="resetToPatientsOverview"
                    wire:loading.attr="disabled"
                    size="sm"
                    color="gray"
                    icon="heroicon-m-arrow-uturn-left"
                >
                    Volver al Top 20
                </x-filament::button>
            </div>

            <div
                @if ($pollingInterval = $this->getPollingInterval())
                    wire:poll.{{ $pollingInterval }}="updateChartData"
                @endif
            >
                <div
                    x-show="true"
                    x-transition:enter="transition ease-out duration-500"
                    x-transition:enter-start="opacity-0 scale-[0.98] blur-[1px]"
                    x-transition:enter-end="opacity-100 scale-100 blur-0"
                >
                    <div
                        x-load
                        x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
                        wire:ignore
                        wire:key="top-patients-md-{{ $this->selectedPatientId ?? 'overview' }}"
                        data-chart-type="{{ $type }}"
                        x-data="chart({
                                    cachedData: @js($this->getCachedData()),
                                    maxHeight: @js($maxHeight = $this->getMaxHeight()),
                                    options: @js($this->getOptions()),
                                    type: @js($type),
                                })"
                        style="height: {{ $this->getMaxHeight() ?? '460px' }}; width: 100%; box-sizing: border-box;"
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
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
