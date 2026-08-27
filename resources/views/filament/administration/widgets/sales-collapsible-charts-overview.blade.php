@php
    $expanded = $this->sectionExpanded;
    $heading = $this->getHeading();
    $description = $this->getDescription();
    $variant = $this->salesChartsPanelVariant();
    $yearChartWidget = $this->yearChartWidget();
    $planChartWidget = $this->planChartWidget();
@endphp

<x-filament-widgets::widget
    :attributes="
        (new \Illuminate\View\ComponentAttributeBag)
            ->class([
                'fi-admin-sales-stats-widget',
                'fi-admin-sales-stats-widget--' . $variant,
            ])
    "
>
    <div
        class="fi-admin-sales-stats-panel"
        data-expanded="{{ $expanded ? 'true' : 'false' }}"
        data-variant="{{ $variant }}"
        wire:key="sales-charts-panel-{{ class_basename($this) }}"
    >
        <button
            type="button"
            class="fi-admin-sales-stats-panel__trigger"
            wire:click="toggleSection"
            wire:loading.attr="disabled"
            wire:target="toggleSection"
            aria-expanded="{{ $expanded ? 'true' : 'false' }}"
            aria-controls="sales-charts-panel-body-{{ class_basename($this) }}"
        >
            <span class="fi-admin-sales-stats-panel__trigger-main">
                <span class="fi-admin-sales-stats-panel__icon" aria-hidden="true">
                    <x-filament::icon
                        :icon="$this->salesChartsPanelIcon()"
                        class="fi-admin-sales-stats-panel__icon-svg"
                    />
                </span>

                <span class="min-w-0 text-left">
                    <span class="fi-admin-sales-stats-panel__title">
                        {{ $heading }}
                    </span>
                    @if (filled($description))
                        <span class="fi-admin-sales-stats-panel__subtitle">
                            {{ $expanded ? $description : 'Colapsado · haz clic para ver los gráficos' }}
                        </span>
                    @endif
                </span>
            </span>

            <span class="fi-admin-sales-stats-panel__trigger-meta">
                <span class="fi-admin-sales-stats-panel__state">
                    {{ $expanded ? 'Ocultar' : 'Mostrar' }}
                </span>
                <span class="fi-admin-sales-stats-panel__chevron" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                        <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 0 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1 -1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                    </svg>
                </span>
            </span>
        </button>

        @if ($expanded)
            <div
                id="sales-charts-panel-body-{{ class_basename($this) }}"
                class="fi-admin-sales-stats-panel__body"
                wire:key="sales-charts-body-{{ class_basename($this) }}-open"
            >
                <div class="fi-admin-sales-charts-grid grid grid-cols-1 gap-4 lg:grid-cols-2">
                    @livewire($yearChartWidget, key('sales-year-chart-'.$this->getId()))
                    @livewire($planChartWidget, key('sales-plan-chart-'.$this->getId()))
                </div>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
