<x-filament-widgets::widget class="corp-area-pulse">
    <div class="mb-3 flex items-baseline justify-between gap-3 px-1">
        <h2 class="text-base font-semibold text-gray-950 dark:text-white">Pulso por área</h2>
        <span class="text-xs text-gray-500 dark:text-gray-400">Abra un área para ver su detalle</span>
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5" x-data="{ shown: false }" x-intersect.once="shown = true" wire:key="corp-pulse-{{ $periodKey }}">
        @foreach ($areas as $index => $area)
            @php
                $delta = $area['delta'];
                $deltaColor = match ($area['improved']) {
                    true => 'var(--ops-good-ink)',
                    false => 'var(--ops-bad-ink)',
                    default => 'var(--ops-muted)',
                };
                $deltaIcon = match ($delta['direction']) {
                    'up' => 'heroicon-m-arrow-trending-up',
                    'down' => 'heroicon-m-arrow-trending-down',
                    default => 'heroicon-m-minus',
                };
            @endphp

            <{{ $area['url'] ? 'a' : 'div' }}
                @if ($area['url']) href="{{ $area['url'] }}" wire:navigate @endif
                wire:key="corp-pulse-{{ $area['key'] }}"
                x-bind:class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-2'"
                style="transition-delay: {{ $index * 70 }}ms"
                class="ops-reveal group flex flex-col rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10 {{ $area['url'] ? 'transition hover:-translate-y-0.5 hover:shadow-md hover:ring-gray-950/10 dark:hover:ring-white/20' : '' }}"
            >
                <div class="flex items-center justify-between gap-2">
                    <span class="inline-flex items-center gap-2 text-sm font-semibold text-gray-950 dark:text-white">
                        <span class="inline-flex size-7 items-center justify-center rounded-lg bg-gray-50 text-gray-500 dark:bg-white/5 dark:text-gray-400">
                            <x-filament::icon :icon="$area['icon']" class="size-4" />
                        </span>
                        {{ $area['label'] }}
                    </span>
                    @if ($area['url'])
                        <x-filament::icon icon="heroicon-m-arrow-up-right" class="size-4 text-gray-300 transition group-hover:text-gray-500 dark:text-gray-600" />
                    @endif
                </div>

                <span class="mt-3 text-xs text-gray-500 dark:text-gray-400">{{ $area['metric'] }}</span>
                <span class="text-2xl font-semibold tracking-tight text-gray-950 dark:text-white">{{ $area['display'] }}</span>

                @if ($compare)
                    <span class="mt-0.5 inline-flex items-center gap-1 text-xs font-semibold" style="color: {{ $deltaColor }}">
                        <x-filament::icon :icon="$deltaIcon" class="size-4" />
                        {{ $delta['text'] }}
                        <span class="font-normal text-gray-400 dark:text-gray-500">vs ant.</span>
                    </span>
                @endif

                <dl class="mt-3 space-y-1 border-t border-gray-950/5 pt-3 text-xs dark:border-white/10">
                    @foreach ($area['facts'] as [$factLabel, $factValue])
                        <div class="flex items-baseline justify-between gap-2">
                            <dt class="text-gray-500 dark:text-gray-400">{{ $factLabel }}</dt>
                            <dd class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ $factValue }}</dd>
                        </div>
                    @endforeach
                </dl>
            </{{ $area['url'] ? 'a' : 'div' }}>
        @endforeach
    </div>
</x-filament-widgets::widget>
