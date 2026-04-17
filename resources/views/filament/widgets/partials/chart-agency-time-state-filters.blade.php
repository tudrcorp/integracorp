@if (method_exists($this, 'getChartYearSelectOptions') && filled($this->getChartYearSelectOptions()))
    <div class="flex flex-wrap items-center gap-2">
        @include('filament.widgets.partials.chart-agency-year-month-selects')

        @if (method_exists($this, 'getChartStateSelectOptions') && filled($this->getChartStateSelectOptions()))
            <x-filament::input.wrapper
                inline-prefix
                wire:target="chartStateId"
                class="fi-wi-chart-filter"
            >
                <x-filament::input.select
                    inline-prefix
                    wire:model.live="chartStateId"
                >
                    @foreach ($this->getChartStateSelectOptions() as $value => $label)
                        <option value="{{ $value }}">
                            {{ $label }}
                        </option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        @endif
    </div>
@endif
