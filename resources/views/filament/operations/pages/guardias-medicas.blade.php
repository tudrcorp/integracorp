<x-filament-panels::page>
    @php
        $legend = [
            'gray' => ['#94a3b8', 'Programada'],
            'blue' => ['#0ea5e9', 'En curso'],
            'green' => ['#10b981', 'Con actividad (50 % o más)'],
            'amber' => ['#f59e0b', 'Actividad parcial'],
            'red' => ['#f43f5e', 'Sin actividad en el sistema'],
        ];
    @endphp

    <div
        class="w-full"
        x-data="{
            selected: $wire.entangle('selectedDates'),
            dragging: false,
            adding: true,
            canSelect: @js($canManage),
            start(date) {
                if (! this.canSelect) { return; }
                this.adding = ! this.selected.includes(date);
                this.dragging = true;
                this.apply(date);
            },
            over(date) { if (this.dragging) { this.apply(date); } },
            apply(date) {
                const has = this.selected.includes(date);
                if (this.adding && ! has) { this.selected = [...this.selected, date]; }
                if (! this.adding && has) { this.selected = this.selected.filter((item) => item !== date); }
            },
        }"
        x-on:pointerup.window="dragging = false"
    >
        <section class="relative overflow-hidden rounded-[2rem] border border-slate-200/70 bg-white/90 p-4 shadow-[0_8px_32px_rgba(15,23,42,0.08)] backdrop-blur-xl dark:border-white/10 dark:bg-slate-900/70 dark:shadow-[0_16px_48px_rgba(0,0,0,0.5)] sm:p-6">
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(56,189,248,0.14),transparent_45%)] dark:bg-[radial-gradient(circle_at_top,rgba(56,189,248,0.18),transparent_45%)]"></div>

            <header class="relative z-10 mb-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">
                        Guardias Médicas
                    </p>
                    <h2 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-100 sm:text-4xl">
                        {{ $monthLabel }}
                    </h2>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <a
                            href="{{ $pdfUrl }}"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-2 rounded-xl border border-cyan-200/80 bg-cyan-50 px-3 py-2 text-sm font-semibold text-cyan-800 transition hover:bg-cyan-100 dark:border-cyan-400/30 dark:bg-cyan-500/15 dark:text-cyan-100 dark:hover:bg-cyan-500/25"
                        >
                            <x-filament::icon icon="heroicon-o-arrow-down-tray" class="size-4" />
                            <span>Descargar PDF del mes</span>
                        </a>
                        @if ($canManage)
                            {{ $this->copyWeekAction }}
                        @endif
                    </div>
                </div>

                <div class="flex items-center gap-2 self-start rounded-2xl border border-slate-200/80 bg-white/90 p-1.5 shadow-sm dark:border-white/10 dark:bg-slate-900/80">
                    <button
                        type="button"
                        wire:click="changeMonth(-1)"
                        wire:loading.attr="disabled"
                        wire:target="changeMonth"
                        class="inline-flex size-10 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white"
                        aria-label="Mes anterior"
                    >
                        <x-filament::icon icon="heroicon-o-chevron-left" class="size-5" />
                    </button>

                    <button
                        type="button"
                        wire:click="goToCurrentMonth"
                        wire:loading.attr="disabled"
                        wire:target="goToCurrentMonth"
                        class="inline-flex min-w-24 items-center justify-center rounded-xl bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                    >
                        <x-filament::icon icon="heroicon-o-arrow-path" wire:loading wire:target="goToCurrentMonth" class="mr-1 size-4 animate-spin" />
                        <span>Hoy</span>
                    </button>

                    <button
                        type="button"
                        wire:click="changeMonth(1)"
                        wire:loading.attr="disabled"
                        wire:target="changeMonth"
                        class="inline-flex size-10 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white"
                        aria-label="Mes siguiente"
                    >
                        <x-filament::icon icon="heroicon-o-chevron-right" class="size-5" />
                    </button>
                </div>
            </header>

            <div class="relative z-10 mb-4 rounded-2xl border border-slate-200/80 bg-white/90 p-3 shadow-sm dark:border-white/10 dark:bg-slate-900/80">
                <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">
                    Sede
                </p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($sites as $site)
                        <button
                            type="button"
                            wire:key="site-{{ $site->id }}"
                            wire:click="selectSite({{ $site->id }})"
                            class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold transition {{ $site->id === $siteId
                                ? 'bg-cyan-500 text-white hover:bg-cyan-400'
                                : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700' }}"
                        >
                            {{ $site->name }}
                        </button>
                    @endforeach
                </div>
                <p class="mt-2 text-[11px] text-slate-500 dark:text-slate-400">
                    @if ($canManage)
                        Marca los días con un clic o arrastrando, y pulsa «Cargar guardias».
                    @else
                        Estás consultando las guardias. Para cargarlas necesitas el permiso «Guardias Médicas».
                    @endif
                </p>
            </div>

            @if ($canManage && ! $hasDoctors)
                <div class="relative z-10 mb-4 rounded-2xl border border-amber-300/80 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-900 dark:border-amber-400/30 dark:bg-amber-500/15 dark:text-amber-100">
                    No hay médicos de TDG activos. Revisa Médicos en Telemedicina.
                </div>
            @endif

            <div class="relative z-10 overflow-x-auto">
                <div class="min-w-[960px] rounded-[1.65rem] border border-slate-200/80 bg-slate-50/70 p-3 select-none dark:border-white/10 dark:bg-slate-950/40">
                    <div class="mb-3 grid grid-cols-7 gap-2">
                        @foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $weekday)
                            <div class="rounded-xl bg-slate-100/80 px-3 py-2 text-center text-sm font-semibold text-slate-600 dark:bg-slate-800/80 dark:text-slate-300">
                                {{ $weekday }}
                            </div>
                        @endforeach
                    </div>

                    <div class="grid grid-cols-7 gap-2">
                        @foreach ($days as $day)
                            @php
                                $guardCount = count($day['guards']);
                                $progressFillClass = match (true) {
                                    $guardCount >= 5 => 'bg-gradient-to-r from-[#191e2b] to-[#00c6e6] shadow-[0_4px_14px_rgba(0,198,230,0.35)]',
                                    $guardCount === 4 => 'bg-gradient-to-r from-[#052659] to-[#3fb2dc] shadow-[0_4px_12px_rgba(63,178,220,0.30)]',
                                    $guardCount === 3 => 'bg-gradient-to-r from-[#163a6b] to-[#7DA0CA] shadow-[0_3px_10px_rgba(84,134,179,0.28)]',
                                    $guardCount === 2 => 'bg-gradient-to-r from-[#5486B3] to-[#9FC1DE] shadow-[0_3px_8px_rgba(84,134,179,0.22)]',
                                    $guardCount === 1 => 'bg-gradient-to-r from-[#7DA0CA] to-[#C1E8FF] shadow-[0_3px_8px_rgba(125,160,202,0.20)]',
                                    default => 'bg-transparent',
                                };
                                $selectable = $canManage && $day['in_month'];
                            @endphp

                            <article
                                wire:key="day-{{ $day['date'] }}"
                                @if ($selectable)
                                    x-bind:class="{ 'ring-2 ring-cyan-400/45 border-cyan-400/55 dark:border-cyan-400/40 dark:ring-cyan-300/50': selected.includes('{{ $day['date'] }}') }"
                                    x-on:pointerdown="start('{{ $day['date'] }}')"
                                    x-on:pointerenter="over('{{ $day['date'] }}')"
                                @endif
                                class="group flex min-h-[130px] flex-col rounded-2xl border p-3 transition-all duration-200 ease-out
                                {{ $day['in_month']
                                    ? 'border-slate-200/80 bg-white/90 shadow-[0_2px_12px_rgba(15,23,42,0.05)] hover:-translate-y-0.5 hover:shadow-[0_10px_24px_rgba(15,23,42,0.1)] dark:border-white/10 dark:bg-slate-900/85 dark:shadow-[0_8px_20px_rgba(0,0,0,0.35)] dark:hover:shadow-[0_16px_30px_rgba(0,0,0,0.45)]'
                                    : 'border-transparent bg-slate-100/55 opacity-55 dark:bg-slate-900/40 dark:opacity-45' }}
                                {{ $selectable ? 'cursor-pointer' : 'cursor-default' }}
                                {{ $day['is_today'] ? 'ring-2 ring-cyan-400/60 dark:ring-cyan-300/70' : '' }}"
                            >
                                <div class="mb-2 flex items-center justify-between gap-2">
                                    <span class="text-2xl font-semibold leading-none tracking-tight {{ $day['in_month'] ? 'text-slate-900 dark:text-slate-100' : 'text-slate-400 dark:text-slate-600' }}">
                                        {{ $day['number'] }}
                                    </span>

                                    @if ($day['in_month'] && $guardCount > 0)
                                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-900 dark:bg-amber-500/20 dark:text-amber-100">
                                            {{ $guardCount }} guardia{{ $guardCount === 1 ? '' : 's' }}
                                        </span>
                                    @endif
                                </div>

                                @if ($day['in_month'])
                                    <div class="mt-auto space-y-2">
                                        @if ($guardCount > 0)
                                            <div class="space-y-1">
                                                @foreach ($day['guards'] as $guard)
                                                    <div
                                                        wire:key="guard-{{ $guard['id'] }}"
                                                        title="{{ $guard['status'] }}{{ $guard['notes'] !== '' ? ' · '.$guard['notes'] : '' }}"
                                                        x-on:pointerdown.stop
                                                        class="relative flex items-start gap-1.5 rounded-xl border border-slate-200/80 bg-white/90 px-2 py-1 dark:border-white/10 dark:bg-slate-900/80"
                                                    >
                                                        <span class="mt-1 inline-flex size-2 shrink-0 rounded-full" style="background: {{ $legend[$guard['color']][0] ?? '#94a3b8' }}"></span>
                                                        @if ($canManage)
                                                            <button type="button" class="min-w-0 flex-1 pr-3 text-left" wire:click="mountAction('editGuard', { guard: {{ $guard['id'] }} })">
                                                                <span class="block truncate text-xs font-semibold text-slate-700 dark:text-slate-200">{{ $guard['doctor'] }}</span>
                                                                <span class="block truncate text-[11px] text-slate-500 dark:text-slate-400">{{ $guard['schedule'] }}</span>
                                                            </button>
                                                            <button
                                                                type="button"
                                                                title="Anular guardia"
                                                                aria-label="Anular guardia de {{ $guard['doctor'] }}"
                                                                wire:click="mountAction('cancelGuard', { guard: {{ $guard['id'] }} })"
                                                                class="absolute right-1 top-1 text-slate-500 transition hover:text-slate-700 dark:text-slate-300 dark:hover:text-white"
                                                            >
                                                                <x-filament::icon icon="heroicon-o-x-mark" class="size-4" />
                                                            </button>
                                                        @else
                                                            <div class="min-w-0 flex-1">
                                                                <p class="truncate text-xs font-semibold text-slate-700 dark:text-slate-200">{{ $guard['doctor'] }}</p>
                                                                <p class="truncate text-[11px] text-slate-500 dark:text-slate-400">{{ $guard['schedule'] }}</p>
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif

                                        <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-slate-200/90 dark:bg-[#1a2233]">
                                            <div
                                                class="h-full rounded-full transition-all duration-300 {{ $progressFillClass }}"
                                                style="width: {{ min(100, $guardCount * 20) }}%;"
                                            ></div>
                                        </div>
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="relative z-10 mt-3 flex flex-wrap gap-3 text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                @foreach ($legend as [$color, $label])
                    <span class="inline-flex items-center gap-1.5">
                        <span class="inline-flex size-2 rounded-full" style="background: {{ $color }}"></span>
                        {{ $label }}
                    </span>
                @endforeach
            </div>

            <div class="relative z-10 mt-4 overflow-x-auto rounded-2xl border border-slate-200/80 bg-white/90 p-3 shadow-sm dark:border-white/10 dark:bg-slate-900/80">
                <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">
                    Resumen de {{ $monthLabel }} en {{ $siteName }}
                </p>

                @if ($summary === [])
                    <p class="text-sm text-slate-500 dark:text-slate-400">Todavía no hay guardias este mes en esta sede.</p>
                @else
                    <table class="w-full text-sm text-slate-700 dark:text-slate-200">
                        <thead>
                            <tr class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                                <th class="px-2 py-1 text-left">Médico</th>
                                <th class="px-2 py-1 text-right">Guardias</th>
                                <th class="px-2 py-1 text-right">Horas</th>
                                <th class="px-2 py-1 text-right">Con actividad</th>
                                <th class="px-2 py-1 text-right">Parcial</th>
                                <th class="px-2 py-1 text-right">Sin actividad</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($summary as $row)
                                <tr wire:key="summary-{{ $loop->index }}" class="border-t border-slate-200/80 dark:border-white/10">
                                    <td class="px-2 py-1 text-left font-semibold">{{ $row['doctor'] }}</td>
                                    <td class="px-2 py-1 text-right">{{ $row['guards'] }}</td>
                                    <td class="px-2 py-1 text-right">{{ $row['hours'] }}</td>
                                    <td class="px-2 py-1 text-right">{{ $row['active'] }}</td>
                                    <td class="px-2 py-1 text-right">{{ $row['partial'] }}</td>
                                    <td class="px-2 py-1 text-right">{{ $row['none'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="mt-2 text-[11px] text-slate-500 dark:text-slate-400">La actividad mide el uso del sistema durante la guardia, no la asistencia a la sede.</p>
                @endif
            </div>
        </section>

        <div
            x-cloak
            x-show="selected.length > 0"
            class="sticky bottom-3 z-20 mt-3 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-cyan-200/80 bg-white/90 p-3 shadow-[0_8px_32px_rgba(15,23,42,0.08)] backdrop-blur-xl dark:border-cyan-400/30 dark:bg-slate-900/80"
        >
            <span class="text-sm font-semibold text-slate-700 dark:text-slate-200" x-text="selected.length === 1 ? '1 día seleccionado' : selected.length + ' días seleccionados'"></span>
            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    x-on:click="selected = []"
                    class="inline-flex items-center justify-center rounded-xl bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                >
                    Limpiar
                </button>
                {{ $this->loadGuardsAction }}
            </div>
        </div>
    </div>
</x-filament-panels::page>
