@props([
    'icon' => 'heroicon-o-envelope',
    'eyebrow' => '',
    'title' => '',
    'description' => '',
    'chips' => [],
    'meta' => null,
])

@php
    $tones = [
        'primary' => 'bg-primary-50 text-primary-700 ring-primary-600/20 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-400/30',
        'info' => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-400/30',
        'success' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/30',
        'warning' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/30',
        'danger' => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-400/30',
        'gray' => 'bg-gray-50 text-gray-600 ring-gray-500/20 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10',
    ];
@endphp

<div class="mt-2 flex w-full flex-col gap-4 rounded-3xl border border-gray-200/80 bg-white/80 p-4 shadow-sm backdrop-blur-md sm:flex-row sm:items-start sm:p-5 dark:border-white/10 dark:bg-gray-900/70">
    <span class="inline-flex size-11 shrink-0 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 ring-1 ring-primary-600/15 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-400/20">
        <x-dynamic-component :component="$icon" class="size-6" />
    </span>

    <div class="min-w-0 flex-1 space-y-2">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ $eyebrow }}</p>
            <p class="truncate text-base font-semibold text-gray-900 dark:text-white">{{ $title }}</p>
            <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-400">{{ $description }}</p>
        </div>

        @if ($chips !== [])
            <div class="flex flex-wrap gap-2">
                @foreach ($chips as $chip)
                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $tones[$chip['tone']] ?? $tones['gray'] }}">
                        {{ $chip['label'] }}
                    </span>
                @endforeach
            </div>
        @endif

        @if (filled($meta))
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $meta }}</p>
        @endif
    </div>
</div>
