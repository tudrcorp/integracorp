@php
    use Filament\Widgets\View\Components\ChartWidgetComponent;
    use Illuminate\View\ComponentAttributeBag;

    $color = $this->getColor();
    $heading = $this->getHeading();
    $description = $this->getDescription();
    $isCollapsible = $this->isCollapsible();
    $type = $this->getType();
    $maxHeight = $this->getMaxHeight() ?: '360px';
    $availableMonths = $this->getAvailableComparisonMonths();
    $monthsByYear = collect($availableMonths)->groupBy('year');
    $selectedCount = $this->selectedComparisonMonthsCount();
    $monthlyExpanded = $this->monthlyChartExpanded;
    $yearChartData = $this->getYearToDateChartData();
@endphp

<x-filament-widgets::widget class="fi-wi-chart fi-admin-agency-sales-mom-line-chart">
    <x-filament::section
        :description="$description"
        :heading="$heading"
        :collapsible="$isCollapsible"
    >
        <div
            @if ($pollingInterval = $this->getPollingInterval())
                wire:poll.{{ $pollingInterval }}="updateChartData"
            @endif
            class="space-y-6"
        >
            <div
                class="fi-admin-agency-monthly-panel"
                wire:key="agency-sales-monthly-block"
                data-expanded="{{ $monthlyExpanded ? 'true' : 'false' }}"
            >
                <button
                    type="button"
                    class="fi-admin-agency-monthly-panel__trigger"
                    wire:click="toggleMonthlyChart"
                    aria-expanded="{{ $monthlyExpanded ? 'true' : 'false' }}"
                    aria-controls="agency-sales-monthly-panel-body"
                >
                    <span class="fi-admin-agency-monthly-panel__trigger-main">
                        <span
                            class="fi-admin-agency-monthly-panel__chevron"
                            aria-hidden="true"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                                <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                            </svg>
                        </span>
                        <span class="min-w-0 text-left">
                            <span class="fi-admin-agency-monthly-panel__title">
                                {{ $this->getMonthlyChartHeading() }}
                            </span>
                            <span class="fi-admin-agency-monthly-panel__subtitle">
                                {{ $monthlyExpanded ? $this->getMonthlyChartDescription() : 'Colapsado · ábrelo para comparar meses' }}
                            </span>
                        </span>
                    </span>

                    <span class="fi-admin-agency-monthly-panel__trigger-meta">
                        <span class="fi-admin-agency-monthly-panel__badge">
                            {{ $selectedCount }} {{ $selectedCount === 1 ? 'mes' : 'meses' }}
                        </span>
                        <span class="fi-admin-agency-monthly-panel__state">
                            {{ $monthlyExpanded ? 'Ocultar' : 'Mostrar' }}
                        </span>
                    </span>
                </button>

                @if ($monthlyExpanded)
                    <div
                        id="agency-sales-monthly-panel-body"
                        class="fi-admin-agency-monthly-panel__body space-y-3"
                        wire:key="agency-sales-monthly-body-open"
                    >
                        <div
                            class="fi-admin-agency-month-filter"
                            wire:key="agency-sales-month-chips"
                        >
                            <div class="fi-admin-agency-month-filter__header">
                                <div class="min-w-0">
                                    <p class="fi-admin-agency-month-filter__title">
                                        Comparar con meses anteriores
                                    </p>
                                    <p class="fi-admin-agency-month-filter__hint">
                                        Selecciona uno o varios meses para agregar barras al gráfico mensual
                                    </p>
                                </div>
                                <div class="fi-admin-agency-month-filter__meta">
                                    <span class="fi-admin-agency-month-filter__count">
                                        {{ $selectedCount }}
                                        {{ $selectedCount === 1 ? 'mes' : 'meses' }}
                                    </span>
                                </div>
                            </div>

                            <div class="fi-admin-agency-month-filter__years">
                                @foreach ($monthsByYear as $year => $yearMonths)
                                    <div
                                        class="fi-admin-agency-month-filter__year-row"
                                        wire:key="agency-sales-year-{{ $year }}"
                                    >
                                        <div class="fi-admin-agency-month-filter__year-badge" aria-hidden="true">
                                            {{ $year }}
                                        </div>
                                        <div
                                            class="fi-admin-agency-month-filter__months"
                                            role="group"
                                            aria-label="Meses de {{ $year }}"
                                        >
                                            @foreach ($yearMonths as $month)
                                                @php
                                                    $isActive = $this->isComparisonMonthActive($month['key']);
                                                @endphp
                                                <button
                                                    type="button"
                                                    wire:key="agency-sales-chip-{{ $month['key'] }}"
                                                    wire:click="toggleComparisonMonth('{{ $month['key'] }}')"
                                                    wire:loading.attr="disabled"
                                                    title="{{ $month['label'] }}"
                                                    aria-pressed="{{ $isActive ? 'true' : 'false' }}"
                                                    @class([
                                                        'fi-admin-agency-month-filter__chip',
                                                        'is-active' => $isActive,
                                                    ])
                                                >
                                                    <span class="fi-admin-agency-month-filter__chip-label">
                                                        {{ $month['short'] }}
                                                    </span>
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div
                            wire:key="{{ $this->chartWireKey() }}"
                            x-data="{ ready: false }"
                            x-init="$nextTick(() => { ready = true })"
                            x-bind:class="ready ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-1'"
                            class="transition-all duration-300 ease-out"
                        >
                            <div
                                x-load
                                x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
                                wire:ignore
                                data-chart-type="{{ $type }}"
                                data-chart-scope="monthly"
                                x-data="chart({
                                            cachedData: @js($this->getCachedData()),
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
                                            'fi-wi-chart-canvas-ctn-no-aspect-ratio',
                                        ])
                                }}
                            >
                                <canvas
                                    x-ref="canvas"
                                    style="height: 100% !important; width: 100% !important; max-height: none;"
                                ></canvas>

                                <span x-ref="backgroundColorElement" class="fi-wi-chart-bg-color"></span>
                                <span x-ref="borderColorElement" class="fi-wi-chart-border-color"></span>
                                <span x-ref="gridColorElement" class="fi-wi-chart-grid-color"></span>
                                <span x-ref="textColorElement" class="fi-wi-chart-text-color"></span>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="border-t border-gray-200/80 pt-5 dark:border-white/10">
                <div class="mb-3 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ $this->getYearToDateChartHeading() }}
                        </h3>
                        <p class="mt-0.5 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                            {{ $this->getYearToDateChartDescription() }}
                        </p>
                    </div>
                    <p class="text-[11px] font-medium text-indigo-600/90 dark:text-indigo-300/90">
                        Siempre visible
                    </p>
                </div>

                {{-- wire:ignore permanente: el filtro mensual no debe remount ni actualizar este canvas --}}
                <div wire:ignore wire:key="agency-sales-ytd-chart-static">
                    <div
                        x-load
                        x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
                        data-chart-type="bar"
                        data-chart-scope="yearly"
                        x-data="chart({
                                    cachedData: @js($yearChartData),
                                    maxHeight: @js($maxHeight),
                                    options: @js($this->getOptions()),
                                    type: 'bar',
                                })"
                        style="height: {{ $maxHeight }}; width: 100%; box-sizing: border-box;"
                        {{
                            (new ComponentAttributeBag)
                                ->color(ChartWidgetComponent::class, 'primary')
                                ->class([
                                    'fi-wi-chart-canvas-ctn',
                                    'fi-wi-chart-canvas-ctn-no-aspect-ratio',
                                ])
                        }}
                    >
                        <canvas
                            x-ref="canvas"
                            style="height: 100% !important; width: 100% !important; max-height: none;"
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
