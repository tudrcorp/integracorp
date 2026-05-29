@props([
    'label',
    'colSpan' => '',
    'select' => false,
])

<div @class(['group block', $colSpan !== '' ? $colSpan : null])>
    <span class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500 dark:text-slate-400">
        {{ $label }}
    </span>

    <div class="relative">
        {{ $slot }}

        @if ($select)
            <x-heroicon-m-chevron-down
                class="pointer-events-none absolute right-3.5 top-1/2 size-4 -translate-y-1/2 text-gray-400 transition group-focus-within:text-primary-600 dark:group-focus-within:text-indigo-300"
            />
        @endif
    </div>
</div>
