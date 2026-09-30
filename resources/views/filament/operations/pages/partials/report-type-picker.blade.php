@php
    /** @var list<\App\Enums\OperationReportType> $types */
    $selected = $getState();
    $statePathValue = $getStatePath();
@endphp

<div
    role="radiogroup"
    aria-label="Tipo de reporte"
    class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4"
>
    @foreach ($types as $type)
        @php($isSelected = $selected === $type->value)
        <button
            type="button"
            role="radio"
            aria-checked="{{ $isSelected ? 'true' : 'false' }}"
            wire:key="report-type-{{ $type->value }}"
            wire:click="$set('{{ $statePathValue }}', '{{ $type->value }}')"
            wire:loading.attr="disabled"
            @class([
                'group flex h-full flex-col gap-2 rounded-xl border p-4 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500',
                'border-primary-500 bg-primary-50 ring-1 ring-primary-500 dark:border-primary-400 dark:bg-primary-500/10 dark:ring-primary-400' => $isSelected,
                'border-gray-200 bg-white hover:border-primary-300 hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:hover:border-primary-400/60 dark:hover:bg-white/10' => ! $isSelected,
            ])
        >
            <span class="flex items-center justify-between gap-2">
                <span @class([
                    'flex size-9 items-center justify-center rounded-lg',
                    'bg-primary-500 text-white' => $isSelected,
                    'bg-gray-100 text-gray-600 group-hover:text-primary-600 dark:bg-white/10 dark:text-gray-300' => ! $isSelected,
                ])>
                    <x-filament::icon :icon="$type->icon()" class="size-5" />
                </span>
                @if ($isSelected)
                    <x-filament::icon icon="heroicon-s-check-circle" class="size-5 text-primary-600 dark:text-primary-400" />
                @endif
            </span>
            <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $type->label() }}</span>
            <span class="text-xs leading-snug text-gray-500 dark:text-gray-400">{{ $type->description() }}</span>
        </button>
    @endforeach
</div>
