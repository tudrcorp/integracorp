@php
    $variant = $this->getRankingTableVariant();
    $widgetClass = \App\Support\Filament\IndividualQuotesRankingTableUi::widgetClass($variant);
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
        ])
        @if ($variant === 'agent')
            x-data="{ filtering: false }"
            x-on:individual-quotes-agent-filter-start.window="filtering = true"
            x-on:individual-quotes-agent-filter-end.window="filtering = false"
        @endif
    >
        @if ($variant === 'agent')
            <div
                class="iq-ranking-filter-overlay"
                wire:loading.delay.short.class="iq-ranking-filter-overlay--visible"
                wire:target="filterAgentsByAgency, clearAgencyFilter, selectAgency"
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
