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

    {{ $this->table ?? null }}

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\Widgets\View\WidgetsRenderHook::TABLE_WIDGET_END, scopes: static::class) }}
</x-filament-widgets::widget>
