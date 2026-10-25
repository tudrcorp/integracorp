@php
    use App\Filament\Operations\Widgets\Dashboard\OperationsWorkloadHeatmap;

    $hourLabel = static fn (int $hour): string => str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':00';
    $fullDays = ['lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];
@endphp

<x-filament-widgets::widget class="ops-heatmap">
    <x-filament::section :heading="$heading" :description="$description">
        @if ($total === 0)
            <div class="flex h-56 flex-col items-center justify-center gap-2 text-center">
                <x-filament::icon icon="heroicon-o-clock" class="size-8 text-gray-300 dark:text-gray-600" />
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $emptyText }}</p>
            </div>
        @else
            <div
                x-data="{ shown: false }"
                x-intersect.once="shown = true"
                x-bind:class="shown ? 'opacity-100' : 'opacity-0'"
                class="ops-reveal"
                wire:key="ops-heatmap-{{ $periodKey }}"
            >
                @if ($busiest)
                    <div class="mb-4 grid grid-cols-1 gap-2 sm:grid-cols-3">
                        <div class="rounded-xl bg-gray-50 px-3 py-2 dark:bg-white/5">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Tramo más cargado</p>
                            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ ucfirst($fullDays[$busiest['weekday']]) }}, {{ $hourLabel($busiest['hour']) }} · {{ number_format($busiest['value'], 0, ',', '.') }}</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 px-3 py-2 dark:bg-white/5">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Día con más {{ $noun }}</p>
                            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ ucfirst($fullDays[$busiestDay]) }} · {{ number_format($byWeekday[$busiestDay], 0, ',', '.') }}</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 px-3 py-2 dark:bg-white/5">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Hora pico</p>
                            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $hourLabel($busiestHour) }} – {{ $hourLabel(($busiestHour + 1) % 24) }}</p>
                        </div>
                    </div>
                @endif

                <div
                    class="relative"
                    data-tip-host
                    x-data="{
                        tip: null,
                        total: @js($total),
                        average: @js($average),
                        fmt: new Intl.NumberFormat('es-VE', { maximumFractionDigits: 1 }),
                        show(cell) {
                            const value = Number(cell.dataset.value);
                            const box = cell.getBoundingClientRect();
                            const host = cell.closest('[data-tip-host]').getBoundingClientRect();
                            const half = 120;
                            const share = this.total > 0 ? value * 100 / this.total : 0;
                            const ratio = this.average > 0 ? value / this.average : 0;
                            let comparison = this.fmt.format(share) + ' % del período';
                            if (ratio >= 1.05) { comparison += ' · ' + this.fmt.format(ratio) + '× un tramo promedio'; }
                            else if (ratio <= 0.95) { comparison += ' · por debajo de un tramo promedio'; }
                            else { comparison += ' · en el promedio'; }
                            this.tip = {
                                title: cell.dataset.day + ' · ' + cell.dataset.range,
                                value: this.fmt.format(value),
                                unit: value === 1 ? @js($singular) : @js($noun),
                                swatch: cell.style.background,
                                lines: value > 0 ? [comparison] : [],
                                footer: value > 0 ? @js($clickText) : @js('Sin '.$noun.' en este tramo'),
                                x: Math.min(Math.max(box.left - host.left + box.width / 2, half), host.width - half),
                                y: box.top - host.top - 8,
                            };
                        },
                    }"
                    x-on:mouseleave="tip = null"
                >
                    <div class="overflow-x-auto pb-1">
                        <div class="grid min-w-[640px] gap-[3px]" style="grid-template-columns: 2.5rem repeat(24, minmax(0, 1fr));" role="grid" aria-label="{{ ucfirst($noun) }} por día de la semana y hora">
                            <span></span>
                            @for ($hour = 0; $hour < 24; $hour++)
                                <span class="text-center text-[10px] tabular-nums text-gray-400 dark:text-gray-500">{{ $hour % 3 === 0 ? str_pad((string) $hour, 2, '0', STR_PAD_LEFT) : '' }}</span>
                            @endfor

                            @foreach ($matrix as $weekday => $hours)
                                <span class="flex items-center text-xs text-gray-500 dark:text-gray-400">{{ $weekdays[$weekday] }}</span>
                                @foreach ($hours as $hour => $value)
                                    @php($step = OperationsWorkloadHeatmap::step($value, $max))
                                    <button
                                        type="button"
                                        role="gridcell"
                                        data-day="{{ ucfirst($fullDays[$weekday]) }}"
                                        data-range="{{ $hourLabel($hour) }} – {{ $hourLabel(($hour + 1) % 24) }}"
                                        data-value="{{ $value }}"
                                        x-on:mouseenter="show($el)"
                                        x-on:focus="show($el)"
                                        x-on:blur="tip = null"
                                        @if ($value > 0)
                                            x-on:click="$wire.dispatch('operations-dashboard-detail', { dimension: 'slot', value: '{{ $weekday }}-{{ $hour }}' })"
                                        @else
                                            aria-disabled="true"
                                        @endif
                                        aria-label="{{ ucfirst($fullDays[$weekday]) }} {{ $hourLabel($hour) }}: {{ $value }} {{ $noun }}"
                                        class="ops-heat-cell h-7 w-full rounded-[4px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 {{ $value === 0 ? 'cursor-default ring-1 ring-inset ring-gray-950/5 dark:ring-white/10' : 'cursor-pointer' }}"
                                        style="background: var(--ops-heat-{{ $step }})"
                                    ></button>
                                @endforeach
                            @endforeach
                        </div>
                    </div>

                    {{-- Tooltip único: sigue a la celda bajo el cursor o con foco. --}}
                    @include('filament.operations.dashboard.partials.tooltip')
                </div>

                <div class="mt-3 flex items-center justify-end gap-2 text-[11px] text-gray-500 dark:text-gray-400">
                    <span>Menos</span>
                    @for ($step = 1; $step <= OperationsWorkloadHeatmap::STEPS; $step++)
                        <span class="size-3 rounded-[3px]" style="background: var(--ops-heat-{{ $step }})"></span>
                    @endfor
                    <span>Más ({{ number_format($max, 0, ',', '.') }} en el tramo pico)</span>
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
