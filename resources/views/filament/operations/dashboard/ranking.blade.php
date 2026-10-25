<x-filament-widgets::widget class="ops-ranking">
    <x-filament::section :heading="$heading" :description="$description">
        @if ($rows === [])
            <div class="flex h-48 flex-col items-center justify-center gap-2 text-center">
                <x-filament::icon :icon="$icon" class="size-8 text-gray-300 dark:text-gray-600" />
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $emptyText }}</p>
            </div>
        @else
            <ol
                class="space-y-1"
                x-data="{ shown: false }"
                x-intersect.once="shown = true"
                wire:key="ops-ranking-{{ $dimension }}-{{ $periodKey }}"
            >
                @foreach ($rows as $index => $row)
                    <li wire:key="ops-ranking-{{ $dimension }}-{{ md5($row['key']) }}">
                        <button
                            type="button"
                            @if ($dimension !== '')
                                x-on:click="$wire.dispatch('operations-dashboard-detail', { dimension: @js($dimension), value: @js($row['key']) })"
                            @endif
                            class="{{ $dimension === '' ? 'cursor-default ' : '' }}group w-full rounded-lg px-2 py-1.5 text-start transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:hover:bg-white/5"
                        >
                            <div class="flex items-baseline gap-3 text-sm">
                                <span class="w-5 shrink-0 text-xs tabular-nums text-gray-400 dark:text-gray-500">{{ $index + 1 }}</span>
                                <span class="min-w-0 flex-1 truncate text-gray-700 dark:text-gray-200" title="{{ $row['label'] }}">{{ $row['label'] }}</span>
                                @if ($row['meta'])
                                    <span class="hidden shrink-0 text-xs text-gray-400 sm:inline dark:text-gray-500">{{ $row['meta'] }}</span>
                                @endif
                                <span class="shrink-0 font-semibold tabular-nums text-gray-950 dark:text-white">{{ ($money ?? false) ? 'US$ ' : '' }}{{ number_format($row['value'], 0, ',', '.') }}</span>
                                <span class="w-14 shrink-0 text-end text-xs tabular-nums text-gray-500 dark:text-gray-400">{{ $row['percent'] }}</span>
                            </div>
                            <div class="ms-8 mt-1 h-1.5 overflow-hidden rounded-full" style="background: var(--ops-track)">
                                <div
                                    class="ops-bar-fill h-full w-0 rounded-full group-hover:opacity-80"
                                    x-bind:style="'width: ' + (shown ? {{ $row['width'] }} : 0) + '%; transition-delay: {{ $index * 45 }}ms; background: var(--ops-series1)'"
                                ></div>
                            </div>
                        </button>
                    </li>
                @endforeach
            </ol>

            @if ($others > 0)
                <p class="mt-3 ps-10 text-xs text-gray-500 dark:text-gray-400">
                    Otros: {{ ($money ?? false) ? 'US$ '.number_format($others, 0, ',', '.') : number_format($others, 0, ',', '.').' '.$noun }}
                </p>
            @endif
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
