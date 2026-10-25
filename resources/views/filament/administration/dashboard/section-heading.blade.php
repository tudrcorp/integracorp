<x-filament-widgets::widget class="admin-section-heading">
    <div id="{{ $anchor }}" class="scroll-mt-32 flex items-end justify-between gap-3 border-b border-gray-950/5 pb-2 pt-4 dark:border-white/10">
        <div class="flex items-center gap-2.5">
            <span class="inline-flex size-8 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                <x-filament::icon :icon="$icon" class="size-5" />
            </span>
            <div>
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ $title }}</h2>
                @if (filled($description))
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $description }}</p>
                @endif
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
