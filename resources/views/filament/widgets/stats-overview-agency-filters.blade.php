{{-- Alineación a la derecha del bloque de filtros (misma línea que el título de la sección). --}}
<div class="ms-auto flex w-full max-w-full flex-wrap items-center justify-end gap-2">
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
