@props([
    'title',
    'count' => null,
    'open' => false,
])

<section
    x-data="{ open: {{ $open ? 'true' : 'false' }} }"
    {{ $attributes->class('overflow-hidden rounded-2xl border border-slate-200/80 bg-white dark:border-white/10 dark:bg-gray-900') }}
>
    <button
        type="button"
        @click="open = ! open"
        class="flex w-full items-center justify-between gap-3 px-4 py-2.5 text-left transition hover:bg-slate-50/90 dark:hover:bg-white/5"
        :aria-expanded="open.toString()"
    >
        <span class="flex min-w-0 items-center gap-2">
            <span class="truncate text-sm font-semibold text-slate-800 dark:text-white">{{ $title }}</span>
            @if ($count !== null)
                <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium tabular-nums text-slate-600 dark:bg-white/10 dark:text-slate-300">
                    {{ $count }}
                </span>
            @endif
        </span>
        <x-filament::icon
            icon="heroicon-m-chevron-down"
            class="size-4 shrink-0 text-slate-400 transition-transform duration-200"
            x-bind:class="open && 'rotate-180'"
        />
    </button>

    <div
        x-show="open"
        x-collapse
        @unless ($open) style="display: none;" @endunless
    >
        {{ $slot }}
    </div>
</section>
