@php
    $chartTabs = $this->getChartTabs();
    $activeChartTab = $this->activeChartTab;
    $activeChartWidgetClass = $this->getActiveChartWidgetClass();
@endphp

<x-filament-widgets::widget class="fi-indicadores-de-desempeno-charts-tabs">
    <div class="fi-supplier-convenio-tabs-ios fi-supplier-status-tabs-ios fi-sc-tabs mb-4 w-full">
        <nav class="fi-tabs" role="tablist" aria-label="Gráficos de indicadores de desempeño">
            @foreach ($chartTabs as $tabKey => $tab)
                <button
                    type="button"
                    role="tab"
                    wire:click="$set('activeChartTab', @js($tabKey))"
                    wire:loading.attr="disabled"
                    wire:target="activeChartTab"
                    aria-selected="{{ $activeChartTab === $tabKey ? 'true' : 'false' }}"
                    @class([
                        'fi-tabs-item fi-supplier-status-tab-pill',
                        'fi-active' => $activeChartTab === $tabKey,
                    ])
                >
                    {{ $tab['label'] }}
                </button>
            @endforeach
        </nav>
    </div>

    <div
        wire:key="indicadores-chart-panel-{{ $activeChartTab }}"
        wire:loading.class="opacity-60"
        wire:target="activeChartTab"
        class="transition-opacity duration-200"
    >
        @livewire($activeChartWidgetClass, key('indicadores-chart-' . $activeChartTab))
    </div>
</x-filament-widgets::widget>
