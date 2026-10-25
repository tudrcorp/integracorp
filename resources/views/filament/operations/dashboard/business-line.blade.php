<x-filament-widgets::widget class="ops-business-line">
    <x-filament::section heading="Línea de negocio" description="Servicios de afiliados corporativos frente a individuales.">
        @if ($total === 0)
            <div class="flex h-48 flex-col items-center justify-center gap-2 text-center">
                <x-filament::icon icon="heroicon-o-building-office-2" class="size-8 text-gray-300 dark:text-gray-600" />
                <p class="text-sm text-gray-500 dark:text-gray-400">No hay servicios en este período.</p>
            </div>
        @else
            <div x-data="{ shown: false }" x-intersect.once="shown = true" wire:key="ops-business-line-{{ $periodKey }}">
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-semibold tracking-tight text-gray-950 dark:text-white">{{ number_format($total, 0, ',', '.') }}</span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">servicios</span>
                </div>

                {{-- Barra de composición: 2px de superficie entre segmentos. --}}
                <div
                    class="relative mt-4"
                    data-tip-host
                    x-data="{
                        tip: null,
                        show(segment) {
                            const box = segment.getBoundingClientRect();
                            const host = segment.closest('[data-tip-host]').getBoundingClientRect();
                            const half = 120;
                            this.tip = {
                                title: segment.dataset.label,
                                value: segment.dataset.value,
                                unit: 'servicios',
                                swatch: getComputedStyle(segment).backgroundColor,
                                lines: [segment.dataset.percent + ' de los servicios del período'],
                                footer: 'Clic para ver estos servicios',
                                x: Math.min(Math.max(box.left - host.left + box.width / 2, half), Math.max(half, host.width - half)),
                                y: box.top - host.top - 8,
                            };
                        },
                    }"
                    x-on:mouseleave="tip = null"
                >
                    <div class="flex h-4 w-full gap-[2px] overflow-hidden rounded-md" role="img" aria-label="Composición por línea de negocio">
                        @foreach ($segments as $index => $segment)
                            <button
                                type="button"
                                data-label="{{ $segment['label'] }}"
                                data-value="{{ number_format($segment['value'], 0, ',', '.') }}"
                                data-percent="{{ $segment['percent'] }}"
                                x-on:mouseenter="show($el)"
                                x-on:focus="show($el)"
                                x-on:blur="tip = null"
                                x-on:click="$wire.dispatch('operations-dashboard-detail', { dimension: 'business_line', value: @js($segment['label']) })"
                                aria-label="{{ $segment['label'] }}: {{ $segment['value'] }} servicios ({{ $segment['percent'] }})"
                                class="ops-bar-fill h-full w-0 first:rounded-s-md last:rounded-e-md hover:opacity-80 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                                x-bind:style="'width: ' + (shown ? {{ $segment['share'] }} : 0) + '%; background: var(--ops-{{ $segment['token'] }}); transition-delay: {{ $index * 120 }}ms'"
                            ></button>
                        @endforeach
                    </div>

                    @include('filament.operations.dashboard.partials.tooltip')
                </div>

                <ul class="mt-5 space-y-2">
                    @foreach ($segments as $segment)
                        <li wire:key="ops-business-line-row-{{ md5($segment['label']) }}">
                            <button
                                type="button"
                                x-on:click="$wire.dispatch('operations-dashboard-detail', { dimension: 'business_line', value: @js($segment['label']) })"
                                class="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-start transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:hover:bg-white/5"
                            >
                                <span class="size-3 shrink-0 rounded-sm" style="background: var(--ops-{{ $segment['token'] }})"></span>
                                <span class="min-w-0 flex-1 truncate text-sm text-gray-700 dark:text-gray-200">{{ $segment['label'] }}</span>
                                <span class="text-sm font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($segment['value'], 0, ',', '.') }}</span>
                                <span class="w-14 text-end text-xs tabular-nums text-gray-500 dark:text-gray-400">{{ $segment['percent'] }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>

                <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                    {{ number_format($patients, 0, ',', '.') }} pacientes distintos atendidos en el período.
                </p>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
