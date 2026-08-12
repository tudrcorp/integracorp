@php
    $variant = $this->getRankingTableVariant();
    $widgetClass = \App\Support\Filament\CorporateQuotesRankingTableUi::widgetClass($variant);
    $showPeriodFilters = $variant === 'agency';
@endphp

<x-filament-widgets::widget
    @class([
        'fi-wi-table',
        $widgetClass,
    ])
>
    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\Widgets\View\WidgetsRenderHook::TABLE_WIDGET_START, scopes: static::class) }}

    <div
        @class([
            'iq-ranking-table-shell',
            'iq-ranking-table-shell--agent' => $variant === 'agent',
            'iq-ranking-table-shell--agency' => $variant === 'agency',
        ])
        @if ($variant === 'agent')
            x-data="{ filtering: false }"
            x-on:corporate-quotes-agent-filter-start.window="filtering = true"
            x-on:corporate-quotes-agent-filter-end.window="filtering = false"
        @endif
    >
        @if ($showPeriodFilters)
            <div class="ac-ranking-agency-header">
                <h3 class="ac-ranking-agency-header__title">
                    {{ \App\Support\Filament\CorporateQuotesRankingTableUi::heading('agency') }}
                </h3>

                <div class="ac-ranking-period-filters">
                    <x-filament::input.wrapper
                        inline-prefix
                        wire:target="filterYear"
                        class="fi-wi-chart-filter"
                    >
                        <x-filament::input.select
                            inline-prefix
                            wire:model.live="filterYear"
                            aria-label="Año"
                        >
                            @foreach ($this->getRankingYearFilterOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>

                    <x-filament::input.wrapper
                        inline-prefix
                        wire:target="filterMonth"
                        class="fi-wi-chart-filter"
                    >
                        <x-filament::input.select
                            inline-prefix
                            wire:model.live="filterMonth"
                            aria-label="Mes"
                        >
                            @foreach ($this->getRankingMonthFilterOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
            </div>
        @endif

        @if ($variant === 'agent')
            <div
                class="iq-ranking-filter-overlay"
                wire:loading.delay.short.class="iq-ranking-filter-overlay--visible"
                wire:target="filterAgentsByAgency, clearAgencyFilter, selectAgency, applyPeriodFilter, filterYear, filterMonth"
                x-show="filtering"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                x-cloak
            >
                <div class="iq-ranking-filter-overlay__panel">
                    <x-filament::loading-indicator class="iq-ranking-filter-overlay__spinner" />
                    <p class="iq-ranking-filter-overlay__label">Preparando filtrado…</p>
                </div>
            </div>
        @endif

        {{ $this->table ?? null }}
    </div>

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\Widgets\View\WidgetsRenderHook::TABLE_WIDGET_END, scopes: static::class) }}
</x-filament-widgets::widget>
