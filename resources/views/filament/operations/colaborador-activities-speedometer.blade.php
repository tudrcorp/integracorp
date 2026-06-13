@php
    $breakdown = $this->getActivityBreakdown();
    $performance = $this->getPerformanceMeta();
    $needleRotation = $this->getNeedleRotation();
    $gaugeMax = $this->getGaugeMax();
    $collaboratorOptions = $this->getCollaboratorOptions();
    $gradientId = 'gauge-gradient-' . $this->getId();
    $shadowId = 'needle-shadow-' . $this->getId();
    $breakdownItems = [
        ['label' => 'Tickets creados', 'value' => $breakdown['tickets'], 'tone' => '#0a84ff'],
        ['label' => 'Observaciones', 'value' => $breakdown['observaciones'], 'tone' => '#ff9f0a'],
        ['label' => 'Actualizaciones en sistema', 'value' => $breakdown['actualizaciones'], 'tone' => '#5856d6'],
        ['label' => 'Nuevos proveedores', 'value' => $breakdown['nuevos_proveedores'], 'tone' => '#34c759'],
        ['label' => 'Cartas de aceptación', 'value' => $breakdown['cartas_aceptacion'], 'tone' => '#ffcc00'],
    ];
    $totalForBars = max(1, $breakdown['total']);
@endphp

<x-filament-widgets::widget class="fi-wi-chart fi-indicadores-speedometer-widget">
    <x-filament::section
        heading="Medidor de actividades diarias"
        description="Seleccione colaborador y fecha para evaluar el desempeño del día."
    >
        <x-slot name="afterHeader">
            <div class="flex flex-wrap items-center gap-2">
                <x-filament::input.wrapper class="fi-wi-chart-filter min-w-[14rem]">
                    <x-filament::input.select wire:model.live="selectedCollaborator">
                        @foreach ($collaboratorOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>

                <x-filament::input.wrapper class="fi-wi-chart-filter">
                    <x-filament::input type="date" wire:model.live="activityDate" />
                </x-filament::input.wrapper>
            </div>
        </x-slot>

        @if ($collaboratorOptions === [])
            <div class="rounded-2xl border border-dashed border-gray-300 px-6 py-12 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">
                No hay colaboradores con actividades registradas.
            </div>
        @else
            <div
                wire:key="speedometer-shell-{{ $selectedCollaborator }}-{{ $activityDate }}"
                class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)] xl:gap-8"
            >
                {{-- Velocímetro --}}
                <div
                    wire:loading.class="opacity-60"
                    wire:target="selectedCollaborator, activityDate"
                    class="relative overflow-hidden rounded-3xl border border-white/70 bg-gradient-to-br from-slate-50 via-white to-slate-100 p-6 shadow-[0_20px_60px_rgba(15,23,42,0.08)] transition-opacity duration-300 dark:border-white/10 dark:from-slate-900 dark:via-slate-900 dark:to-slate-800 sm:p-8"
                >
                    <div class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-[#34c759]/10 blur-3xl"></div>
                    <div class="pointer-events-none absolute -bottom-12 -left-8 h-44 w-44 rounded-full bg-[#0a84ff]/10 blur-3xl"></div>

                    <div
                        x-data="{
                            color: @js($performance['color']),
                            label: @js($performance['label']),
                            displayValue: 0,
                            displayRotation: -90,
                            frame: null,
                            init() {
                                this.animateTo(
                                    @js($breakdown['total']),
                                    @js($needleRotation),
                                    @js($performance['color']),
                                    @js($performance['label']),
                                );
                            },
                            animateTo(value, rotation, color, label) {
                                if (this.frame) {
                                    cancelAnimationFrame(this.frame);
                                }

                                const startValue = this.displayValue;
                                const startRotation = this.displayRotation;
                                const deltaValue = value - startValue;
                                const deltaRotation = rotation - startRotation;
                                const startTime = performance.now();
                                const duration = 900;

                                this.color = color;
                                this.label = label;

                                const step = (now) => {
                                    const progress = Math.min(1, (now - startTime) / duration);
                                    const eased = 1 - Math.pow(1 - progress, 3);

                                    this.displayValue = startValue + (deltaValue * eased);
                                    this.displayRotation = startRotation + (deltaRotation * eased);

                                    if (progress < 1) {
                                        this.frame = requestAnimationFrame(step);
                                    } else {
                                        this.displayValue = value;
                                        this.displayRotation = rotation;
                                        this.frame = null;
                                    }
                                };

                                this.frame = requestAnimationFrame(step);
                            },
                        }"
                        class="relative mx-auto flex w-full max-w-2xl flex-col items-center"
                    >
                        <div class="relative w-full" style="padding-bottom: 52%;">
                            <svg
                                viewBox="0 0 400 220"
                                class="absolute inset-0 h-full w-full"
                                role="img"
                                aria-label="Medidor de actividades diarias"
                            >
                                <defs>
                                    <linearGradient id="{{ $gradientId }}" x1="0%" y1="0%" x2="100%" y2="0%">
                                        <stop offset="0%" stop-color="#ff3b30" />
                                        <stop offset="16%" stop-color="#ff3b30" />
                                        <stop offset="22%" stop-color="#ff6b4a" />
                                        <stop offset="35%" stop-color="#ffcc00" />
                                        <stop offset="65%" stop-color="#ffcc00" />
                                        <stop offset="75%" stop-color="#9ed63a" />
                                        <stop offset="80%" stop-color="#34c759" />
                                        <stop offset="100%" stop-color="#30d158" />
                                    </linearGradient>

                                    <filter id="{{ $shadowId }}" x="-50%" y="-50%" width="200%" height="200%">
                                        <feDropShadow dx="0" dy="2" stdDeviation="3" flood-color="rgba(0,0,0,0.28)" />
                                    </filter>

                                    <radialGradient id="hub-{{ $this->getId() }}" cx="50%" cy="50%" r="50%">
                                        <stop offset="0%" stop-color="#ffffff" />
                                        <stop offset="100%" stop-color="#e5e7eb" />
                                    </radialGradient>
                                </defs>

                                {{-- Pista base --}}
                                <path
                                    d="M 56 178 A 144 144 0 0 1 344 178"
                                    fill="none"
                                    stroke="rgba(148,163,184,0.22)"
                                    stroke-width="28"
                                    stroke-linecap="round"
                                />

                                {{-- Arco de zonas con gradiente fluido --}}
                                <path
                                    d="M 56 178 A 144 144 0 0 1 344 178"
                                    fill="none"
                                    stroke="url(#{{ $gradientId }})"
                                    stroke-width="24"
                                    stroke-linecap="round"
                                    opacity="0.95"
                                />

                                {{-- Marcas --}}
                                @foreach ([0, 10, 20, $gaugeMax] as $mark)
                                    @php
                                        $angle = -180 + (($mark / $gaugeMax) * 180);
                                        $radians = deg2rad($angle);
                                        $cx = 200 + cos($radians) * 126;
                                        $cy = 178 + sin($radians) * 126;
                                    @endphp
                                    <circle cx="{{ round($cx, 1) }}" cy="{{ round($cy, 1) }}" r="3" fill="rgba(100,116,139,0.55)" />
                                    <text
                                        x="{{ round($cx, 1) }}"
                                        y="{{ round($cy + ($mark === 0 || $mark === $gaugeMax ? 18 : 14), 1) }}"
                                        text-anchor="middle"
                                        fill="rgba(71,85,105,0.9)"
                                        font-size="13"
                                        font-weight="600"
                                    >{{ $mark }}</text>
                                @endforeach

                                {{-- Aguja: rotate() nativo SVG --}}
                                <g transform="translate(200 178)" filter="url(#{{ $shadowId }})">
                                    <g :transform="`rotate(${displayRotation})`">
                                        <path
                                            d="M -7 4 L 0 -112 L 7 4 Z"
                                            :fill="color"
                                            opacity="0.95"
                                        />
                                        <path
                                            d="M -2.5 0 L 0 -108 L 2.5 0 Z"
                                            fill="rgba(255,255,255,0.45)"
                                        />
                                    </g>
                                    <circle cx="0" cy="0" r="16" fill="url(#hub-{{ $this->getId() }})" stroke="rgba(15,23,42,0.12)" stroke-width="2" />
                                    <circle cx="0" cy="0" r="7" :fill="color" />
                                </g>
                            </svg>
                        </div>

                        {{-- Valor central: fuera del arco, sin superposición --}}
                        <div class="-mt-10 flex flex-col items-center text-center sm:-mt-12">
                            <div
                                class="tabular-nums text-6xl font-bold leading-none tracking-tight text-slate-900 transition-colors duration-500 dark:text-white sm:text-7xl"
                                x-text="Math.round(displayValue)"
                            ></div>
                            <p class="mt-2 text-sm font-medium text-slate-500 dark:text-slate-400">
                                actividades del día
                            </p>
                        </div>

                        <div
                            class="mt-5 inline-flex items-center gap-2.5 rounded-full px-5 py-2.5 text-sm font-semibold shadow-sm transition-all duration-500"
                            :style="`background: color-mix(in srgb, ${color} 16%, white); color: ${color}; border: 1px solid color-mix(in srgb, ${color} 28%, transparent);`"
                        >
                            <span
                                class="inline-block h-2.5 w-2.5 rounded-full shadow-[0_0_10px_currentColor]"
                                :style="`background-color: ${color};`"
                            ></span>
                            <span x-text="label"></span>
                        </div>

                        <p class="mt-3 max-w-md text-center text-sm text-slate-500 dark:text-slate-400">
                            {{ $performance['description'] }}
                        </p>
                    </div>
                </div>

                {{-- Panel lateral --}}
                <div class="flex flex-col gap-4">
                    <div class="rounded-2xl border border-gray-200/80 bg-white/80 p-5 backdrop-blur-sm dark:border-gray-700 dark:bg-gray-900/50">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                                    {{ $selectedCollaborator }}
                                </h3>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ \Illuminate\Support\Carbon::parse($activityDate)->translatedFormat('l, d \d\e F \d\e Y') }}
                                </p>
                            </div>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                máx. {{ $gaugeMax }}
                            </span>
                        </div>

                        <dl class="mt-5 space-y-4">
                            @foreach ($breakdownItems as $item)
                                @php
                                    $pct = min(100, round(($item['value'] / $totalForBars) * 100));
                                @endphp
                                <div wire:key="breakdown-{{ $item['label'] }}-{{ $breakdown['total'] }}">
                                    <div class="mb-1.5 flex items-center justify-between gap-3 text-sm">
                                        <dt class="text-gray-600 dark:text-gray-300">{{ $item['label'] }}</dt>
                                        <dd class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ $item['value'] }}</dd>
                                    </div>
                                    <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                        <div
                                            class="h-full rounded-full transition-all duration-700 ease-out"
                                            style="width: {{ $pct }}%; background: linear-gradient(90deg, color-mix(in srgb, {{ $item['tone'] }} 70%, white), {{ $item['tone'] }});"
                                        ></div>
                                    </div>
                                </div>
                            @endforeach
                        </dl>
                    </div>

                    <div class="grid gap-2.5 text-xs">
                        <div class="flex items-center gap-3 rounded-xl border border-[#ff3b30]/20 bg-[#ff3b30]/8 px-3.5 py-3 text-gray-700 dark:text-gray-200">
                            <span class="h-3 w-3 shrink-0 rounded-full bg-[#ff3b30] shadow-[0_0_12px_rgba(255,59,48,0.45)]"></span>
                            <span><strong>Bajo:</strong> menos de 10 actividades diarias.</span>
                        </div>
                        <div class="flex items-center gap-3 rounded-xl border border-[#ffcc00]/25 bg-[#ffcc00]/12 px-3.5 py-3 text-gray-700 dark:text-gray-200">
                            <span class="h-3 w-3 shrink-0 rounded-full bg-[#ffcc00] shadow-[0_0_12px_rgba(255,204,0,0.4)]"></span>
                            <span><strong>Medio:</strong> entre 10 y 20 actividades.</span>
                        </div>
                        <div class="flex items-center gap-3 rounded-xl border border-[#34c759]/25 bg-[#34c759]/10 px-3.5 py-3 text-gray-700 dark:text-gray-200">
                            <span class="h-3 w-3 shrink-0 rounded-full bg-[#34c759] shadow-[0_0_12px_rgba(52,199,89,0.45)]"></span>
                            <span><strong>Alto:</strong> más de 20 actividades diarias.</span>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
