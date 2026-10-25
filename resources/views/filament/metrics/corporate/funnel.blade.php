<x-filament-widgets::widget class="corp-funnel">
    <x-filament::section heading="Embudo comercial" description="Cotizaciones individuales del período. Clic en una etapa para verlas.">
        @if (($steps[0]['value'] ?? 0) === 0)
            <div class="flex h-48 flex-col items-center justify-center gap-2 text-center">
                <x-filament::icon icon="heroicon-o-funnel" class="size-8 text-gray-300 dark:text-gray-600" />
                <p class="text-sm text-gray-500 dark:text-gray-400">No hay cotizaciones en este período.</p>
            </div>
        @else
            <ol class="space-y-4" x-data="{ shown: false }" x-intersect.once="shown = true" wire:key="corp-funnel-{{ $periodKey }}">
                @foreach ($steps as $index => $step)
                    <li wire:key="corp-funnel-{{ $step['key'] }}">
                        <button
                            type="button"
                            x-on:click="$wire.dispatch('operations-dashboard-detail', { dimension: 'quote_funnel', value: @js($step['key']) })"
                            class="group w-full rounded-lg p-1 text-start focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                            x-tooltip="{ content: @js($step['hint']), theme: $store.theme }"
                        >
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <span class="text-gray-700 dark:text-gray-200">{{ $step['label'] }}</span>
                                <span class="text-lg font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($step['value'], 0, ',', '.') }}</span>
                            </div>
                            <div class="mt-1.5 h-3 overflow-hidden rounded-full" style="background: var(--ops-track)">
                                <div
                                    class="ops-bar-fill h-full rounded-full group-hover:opacity-80"
                                    x-bind:style="'width: ' + (shown ? {{ $step['width'] }} : 0) + '%; transition-delay: {{ $index * 120 }}ms; background: var(--ops-series1)'"
                                ></div>
                            </div>
                            @if ($step['from_previous'])
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $step['from_previous'] }}</p>
                            @endif
                        </button>
                    </li>
                @endforeach
            </ol>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
