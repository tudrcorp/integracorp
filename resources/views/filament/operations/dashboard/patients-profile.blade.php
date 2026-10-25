<x-filament-widgets::widget class="ops-patients-profile">
    <x-filament::section heading="Pacientes" description="Pacientes distintos de los casos abiertos en el período, por sexo y edad.">
        @if ($total === 0)
            <div class="flex h-48 flex-col items-center justify-center gap-2 text-center">
                <x-filament::icon icon="heroicon-o-user-group" class="size-8 text-gray-300 dark:text-gray-600" />
                <p class="text-sm text-gray-500 dark:text-gray-400">No hay casos abiertos en este período.</p>
            </div>
        @else
            <div x-data="{ shown: false }" x-intersect.once="shown = true" wire:key="ops-patients-profile-{{ $periodKey }}">
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-semibold tracking-tight text-gray-950 dark:text-white">{{ number_format($total, 0, ',', '.') }}</span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">pacientes</span>
                </div>

                @if ($sexes !== [])
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
                                    unit: 'pacientes',
                                    swatch: getComputedStyle(segment).backgroundColor,
                                    lines: [segment.dataset.percent + ' de los pacientes del período'],
                                    footer: 'Clic para ver sus casos',
                                    x: Math.min(Math.max(box.left - host.left + box.width / 2, half), Math.max(half, host.width - half)),
                                    y: box.top - host.top - 8,
                                };
                            },
                        }"
                        x-on:mouseleave="tip = null"
                    >
                        <div class="flex h-3 w-full gap-[2px] overflow-hidden rounded-md" role="img" aria-label="Pacientes por sexo">
                            @foreach ($sexes as $index => $sex)
                                <button
                                    type="button"
                                    data-label="{{ $sex['label'] }}"
                                    data-value="{{ number_format($sex['value'], 0, ',', '.') }}"
                                    data-percent="{{ $sex['percent'] }}"
                                    x-on:mouseenter="show($el)"
                                    x-on:focus="show($el)"
                                    x-on:blur="tip = null"
                                    x-on:click="$wire.dispatch('operations-dashboard-detail', { dimension: 'sex', value: @js($sex['label']) })"
                                    aria-label="{{ $sex['label'] }}: {{ $sex['value'] }} pacientes ({{ $sex['percent'] }})"
                                    class="ops-bar-fill h-full w-0 first:rounded-s-md last:rounded-e-md hover:opacity-80 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                                    x-bind:style="'width: ' + (shown ? {{ $sex['share'] }} : 0) + '%; background: var(--ops-{{ $sex['token'] }}); transition-delay: {{ $index * 120 }}ms'"
                                ></button>
                            @endforeach
                        </div>

                        @include('filament.operations.dashboard.partials.tooltip')
                    </div>

                    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                        @foreach ($sexes as $sex)
                            <span class="inline-flex items-center gap-1.5">
                                <span class="size-2.5 rounded-sm" style="background: var(--ops-{{ $sex['token'] }})"></span>
                                {{ $sex['label'] }} · <span class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($sex['value'], 0, ',', '.') }}</span> ({{ $sex['percent'] }})
                            </span>
                        @endforeach
                    </div>
                @endif

                <p class="mt-5 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Por edad</p>
                <ol class="mt-1 space-y-0.5">
                    @foreach ($ages as $index => $age)
                        <li wire:key="ops-patients-age-{{ md5($age['label']) }}">
                            <button
                                type="button"
                                @if ($age['value'] > 0)
                                    x-on:click="$wire.dispatch('operations-dashboard-detail', { dimension: 'age_band', value: @js($age['label']) })"
                                @else
                                    aria-disabled="true"
                                @endif
                                class="group flex w-full items-center gap-3 rounded-lg px-2 py-1 text-start text-sm transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 {{ $age['value'] > 0 ? 'hover:bg-gray-50 dark:hover:bg-white/5' : 'cursor-default' }}"
                            >
                                <span class="w-12 shrink-0 tabular-nums text-gray-500 dark:text-gray-400">{{ $age['label'] }}</span>
                                <span class="h-1.5 flex-1 overflow-hidden rounded-full" style="background: var(--ops-track)">
                                    <span
                                        class="ops-bar-fill block h-full w-0 rounded-full group-hover:opacity-80"
                                        x-bind:style="'width: ' + (shown ? {{ $age['width'] }} : 0) + '%; transition-delay: {{ $index * 45 }}ms; background: var(--ops-series1)'"
                                    ></span>
                                </span>
                                <span class="w-8 shrink-0 text-end font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($age['value'], 0, ',', '.') }}</span>
                                <span class="w-12 shrink-0 text-end text-xs tabular-nums text-gray-500 dark:text-gray-400">{{ $age['percent'] }}</span>
                            </button>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
