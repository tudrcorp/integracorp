@php
    $stats = $this->getTrafficLightStats();
@endphp

<x-filament-widgets::widget class="fi-wi-traffic-light-activity fi-wi-chart">
    <x-filament::section
        heading="Control de Actividad e Interacción"
        description="Monitoreo de agentes basado en Cotizaciones y Ventas"
        :collapsible="false"
    >
        <x-slot name="afterHeader">
            <div class="flex flex-wrap items-center gap-2">
                <x-filament::input.wrapper
                    inline-prefix
                    wire:target="filterYear"
                    class="fi-wi-chart-filter"
                >
                    <x-filament::input.select
                        inline-prefix
                        wire:model.live="filterYear"
                        aria-label="Año"
                    >
                        @foreach ($this->getChartYearOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>

                <x-filament::input.wrapper
                    inline-prefix
                    wire:target="filterMonth"
                    class="fi-wi-chart-filter"
                >
                    <x-filament::input.select
                        inline-prefix
                        wire:model.live="filterMonth"
                        aria-label="Mes"
                    >
                        @foreach ($this->getChartMonthOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </x-slot>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-emerald-200/70 bg-emerald-50/70 p-5 shadow-[0px_0px_0px_1px_rgba(0,0,0,0.06),0px_1px_1px_-0.5px_rgba(0,0,0,0.06),0px_3px_3px_-1.5px_rgba(0,0,0,0.06),_0px_6px_6px_-3px_rgba(0,0,0,0.06),0px_12px_12px_-6px_rgba(0,0,0,0.06),0px_24px_24px_-12px_rgba(0,0,0,0.06)] dark:border-emerald-800/40 dark:bg-emerald-950/20">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="inline-block size-2.5 rounded-full bg-emerald-500"></span>
                            <p class="text-sm font-semibold tracking-wide text-emerald-700 dark:text-emerald-300">
                                ACTIVOS
                            </p>
                        </div>
                        <p class="mt-1 text-sm text-emerald-700/80 dark:text-emerald-200/70">
                            Interacción &lt; 30 días
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-bold text-emerald-700 dark:text-emerald-300">
                            {{ $stats['activoPct'] }}%
                        </p>
                        <p class="text-sm text-emerald-700/70 dark:text-emerald-200/70">
                            {{ $stats['activo'] }} Agentes
                        </p>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-amber-200/70 bg-amber-50/70 p-5 shadow-[0px_0px_0px_1px_rgba(0,0,0,0.06),0px_1px_1px_-0.5px_rgba(0,0,0,0.06),0px_3px_3px_-1.5px_rgba(0,0,0,0.06),_0px_6px_6px_-3px_rgba(0,0,0,0.06),0px_12px_12px_-6px_rgba(0,0,0,0.06),0px_24px_24px_-12px_rgba(0,0,0,0.06)] dark:border-amber-800/40 dark:bg-amber-950/20">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="inline-block size-2.5 rounded-full bg-amber-500"></span>
                            <p class="text-sm font-semibold tracking-wide text-amber-700 dark:text-amber-300">
                                EN RIESGO
                            </p>
                        </div>
                        <p class="mt-1 text-sm text-amber-700/80 dark:text-amber-200/70">
                            Sin interacción 31–90 días
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-bold text-amber-700 dark:text-amber-300">
                            {{ $stats['enRiesgoPct'] }}%
                        </p>
                        <p class="text-sm text-amber-700/70 dark:text-amber-200/70">
                            {{ $stats['enRiesgo'] }} Agentes
                        </p>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-rose-200/70 bg-rose-50/70 p-5 shadow-[0px_0px_0px_1px_rgba(0,0,0,0.06),0px_1px_1px_-0.5px_rgba(0,0,0,0.06),0px_3px_3px_-1.5px_rgba(0,0,0,0.06),_0px_6px_6px_-3px_rgba(0,0,0,0.06),0px_12px_12px_-6px_rgba(0,0,0,0.06),0px_24px_24px_-12px_rgba(0,0,0,0.06)] dark:border-rose-800/40 dark:bg-rose-950/20">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="inline-block size-2.5 rounded-full bg-rose-500"></span>
                            <p class="text-sm font-semibold tracking-wide text-rose-700 dark:text-rose-300">
                                INACTIVOS
                            </p>
                        </div>
                        <p class="mt-1 text-sm text-rose-700/80 dark:text-rose-200/70">
                            Alerta Gerencia (&gt; 91 días)
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-bold text-rose-700 dark:text-rose-300">
                            {{ $stats['inactivoPct'] }}%
                        </p>
                        <p class="text-sm text-rose-700/70 dark:text-rose-200/70">
                            {{ $stats['inactivo'] }} Agentes
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

