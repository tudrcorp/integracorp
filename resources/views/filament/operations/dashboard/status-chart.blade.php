@php
    $rows = $this->getLegendRows();
    $total = $this->getTotal();
    $dimension = $this->detailDimension();
@endphp

<x-filament-widgets::widget class="fi-wi-chart ops-status-chart">
    <x-filament::section :heading="$this->getHeading()" :description="$this->getDescription()">
        @if ($total == 0)
            <div class="flex h-56 flex-col items-center justify-center gap-2 text-center">
                <x-filament::icon icon="heroicon-o-chart-pie" class="size-8 text-gray-300 dark:text-gray-600" />
                <p class="text-sm text-gray-500 dark:text-gray-400">No hay {{ $this->noun() }} en este período.</p>
            </div>
        @else
            <div class="relative">
                @include('filament.operations.dashboard.partials.chart-canvas')

                <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                    <span class="{{ $this->isMoney() ? 'text-2xl' : 'text-3xl' }} font-semibold tracking-tight text-gray-950 dark:text-white">{{ $this->formatValue($total) }}</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $this->noun() }}</span>
                </div>
            </div>

            <ul class="mt-4 space-y-1">
                @foreach ($rows as $row)
                    <li wire:key="ops-status-{{ md5($row['label']) }}">
                        <button
                            type="button"
                            @if ($dimension !== '')
                                x-on:click="$wire.dispatch('operations-dashboard-detail', { dimension: @js($dimension), value: @js($row['label']) })"
                            @endif
                            class="{{ $dimension === '' ? 'cursor-default ' : '' }}flex w-full items-center gap-3 rounded-lg px-2 py-1.5 text-start text-sm transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:hover:bg-white/5"
                        >
                            <span class="size-2.5 shrink-0 rounded-full" style="background: var(--ops-{{ in_array($row['role'], ['series1', 'series2', 'good', 'warning', 'violet', 'critical', 'muted'], true) ? $row['role'] : 'muted' }})"></span>
                            <span class="min-w-0 flex-1 truncate text-gray-700 dark:text-gray-200">{{ $row['label'] }}</span>
                            <span class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ $this->formatValue($row['value']) }}</span>
                            <span class="w-14 text-end text-xs tabular-nums text-gray-500 dark:text-gray-400">{{ $row['percent'] }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
