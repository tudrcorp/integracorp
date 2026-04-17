<x-filament::input.wrapper
    inline-prefix
    wire:target="chartYear"
    class="fi-wi-chart-filter"
>
    <x-filament::input.select
        inline-prefix
        wire:model.live="chartYear"
    >
        @foreach ($this->getChartYearSelectOptions() as $value => $label)
            <option value="{{ $value }}">
                {{ $label }}
            </option>
        @endforeach
    </x-filament::input.select>
</x-filament::input.wrapper>

<x-filament::input.wrapper
    inline-prefix
    wire:target="chartMonth"
    class="fi-wi-chart-filter"
>
    <x-filament::input.select
        inline-prefix
        wire:model.live="chartMonth"
    >
        @foreach ($this->getChartMonthSelectOptions() as $value => $label)
            <option value="{{ $value }}">
                {{ $label }}
            </option>
        @endforeach
    </x-filament::input.select>
</x-filament::input.wrapper>
