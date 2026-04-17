@if (method_exists($this, 'getChartYearSelectOptions') && filled($this->getChartYearSelectOptions()))
    <div class="flex flex-wrap items-center gap-2">
        @include('filament.widgets.partials.chart-agency-year-month-selects')
    </div>
@endif
