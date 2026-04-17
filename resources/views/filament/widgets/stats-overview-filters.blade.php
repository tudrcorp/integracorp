@php
    $year = (int) ($year ?? now()->year);
@endphp

<div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
    <x-filament::input.wrapper
        inline-prefix
        class="fi-wi-chart-filter w-full sm:w-auto"
        wire:target="statsFilters.year"
    >
        <x-filament::input.select
            inline-prefix
            wire:model.live="statsFilters.year"
        >
            @foreach ($yearOptions as $value => $label)
                <option value="{{ $value }}">
                    {{ $label }}
                </option>
            @endforeach
        </x-filament::input.select>
    </x-filament::input.wrapper>

    <x-filament::input.wrapper
        inline-prefix
        class="fi-wi-chart-filter w-full sm:w-auto"
        wire:target="statsFilters.month"
    >
        <x-filament::input.select
            inline-prefix
            wire:model.live="statsFilters.month"
        >
            @foreach ($monthOptions as $value => $label)
                <option value="{{ $value }}">
                    {{ $label }}
                </option>
            @endforeach
        </x-filament::input.select>
    </x-filament::input.wrapper>
</div>

