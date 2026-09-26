{{--
    Pantalla de TV del monitor en vivo (Flux). Solo lectura: nada es clicable.
    Espera $board (LiveMonitorTvBoard::build()) y $refreshedAt.
    Las clases de color van escritas aquí a propósito: Tailwind solo compila lo que ve en resources/views.
--}}
@php
    $levelBorder = ['green' => 'border-l-lime-500', 'amber' => 'border-l-amber-500', 'red' => 'border-l-red-500'];
    $levelDot = ['green' => 'bg-lime-500 ring-lime-500/25', 'amber' => 'bg-amber-500 ring-amber-500/25', 'red' => 'bg-red-500 ring-red-500/30 animate-pulse'];
    $levelText = ['green' => 'text-lime-400', 'amber' => 'text-amber-400', 'red' => 'text-red-400'];
    $queueColors = ['unattended' => 'red', 'stuck' => 'red', 'zombie' => 'amber', 'busy' => 'sky', 'ok' => 'lime', 'unknown' => 'zinc'];
    $verdictColors = ['confirmed' => 'red', 'possible' => 'amber', 'benign' => 'zinc'];
    $severityColors = ['critical' => 'red', 'warning' => 'amber', 'info' => 'sky'];
    $severityLabels = ['critical' => 'Urgente', 'warning' => 'Atención', 'info' => 'Sugerencia'];
    $rttDot = ['good' => 'bg-lime-500', 'fair' => 'bg-amber-500', 'poor' => 'bg-red-500'];
    $metricLine = ['failed_logins' => 'text-red-500', 'server_errors' => 'text-rose-400', 'not_found' => 'text-amber-400', 'throttled' => 'text-orange-400', 'csrf' => 'text-yellow-300'];
    $metricLegend = ['failed_logins' => 'bg-red-500', 'server_errors' => 'bg-rose-400', 'not_found' => 'bg-amber-400', 'throttled' => 'bg-orange-400', 'csrf' => 'bg-yellow-300'];
    $timeFormat = ['hour' => '2-digit', 'minute' => '2-digit', 'hour12' => false];
@endphp

<div wire:poll.3s class="flex min-h-screen flex-col gap-5 p-6 2xl:p-8">
    {{-- Encabezado --}}
    <header class="flex items-center justify-between gap-6">
        <div class="flex items-center gap-5">
            <img src="{{ asset('image/logoTDG.png') }}" alt="Tu Dr. Group" class="h-11 w-auto">
            <div>
                <flux:heading size="xl" class="!text-3xl !font-bold">Monitor en vivo</flux:heading>
                <flux:text class="!text-base">IntegraCorp · estado del sistema en tiempo real</flux:text>
            </div>
            <flux:badge color="lime" size="lg" class="gap-2">
                <span class="size-2.5 animate-pulse rounded-full bg-lime-400"></span>
                En vivo · {{ $refreshedAt }}
            </flux:badge>
        </div>
        <div class="text-right">
            <div class="text-5xl font-bold tabular-nums tracking-tight" x-data="{ now: '' }" x-init="const tick = () => now = new Date().toLocaleTimeString('es-VE', { hour12: false }); tick(); setInterval(tick, 1000)" x-text="now" wire:ignore></div>
            <flux:text class="!text-base capitalize" x-data="{ today: '' }" x-init="today = new Date().toLocaleDateString('es-VE', { weekday: 'long', day: 'numeric', month: 'long' })" x-text="today" wire:ignore></flux:text>
        </div>
    </header>

    {{-- Semáforos y bloqueos --}}
    <section class="grid grid-cols-4 gap-4">
        @foreach ($board['lights'] as $key => $light)
            <flux:card class="!p-5 border-l-4 {{ $levelBorder[$light['level']] ?? 'border-l-zinc-500' }}" wire:key="tv-light-{{ $key }}">
                <div class="flex items-center gap-4">
                    <span class="size-5 shrink-0 rounded-full ring-8 {{ $levelDot[$light['level']] ?? 'bg-zinc-500 ring-zinc-500/25' }}"></span>
                    <div class="min-w-0">
                        <flux:text class="!text-xs font-semibold uppercase tracking-widest">{{ $light['title'] }}</flux:text>
                        <div class="text-2xl font-bold {{ $levelText[$light['level']] ?? '' }}">{{ $light['label'] }}</div>
                        <flux:text class="truncate !text-sm" title="{{ $light['detail'] }}">{{ $light['detail'] }}</flux:text>
                    </div>
                </div>
            </flux:card>
        @endforeach

        <flux:card class="!p-5">
            <flux:text class="!text-xs font-semibold uppercase tracking-widest">Bloqueos activos</flux:text>
            <div class="mt-2 grid grid-cols-3 gap-3">
                @foreach ([['Cuentas', $board['blocks']['locks']], ['Usuarios', $board['blocks']['users']], ['IPs', $board['blocks']['ips']]] as [$label, $count])
                    <div>
                        <div class="text-3xl font-bold tabular-nums {{ $count > 0 ? 'text-red-400' : '' }}">{{ $count }}</div>
                        <flux:text class="!text-sm">{{ $label }}</flux:text>
                    </div>
                @endforeach
            </div>
        </flux:card>
    </section>

    @if ($board['stuckQueues'] !== [])
        <flux:callout variant="danger" icon="exclamation-triangle" heading="{{ count($board['stuckQueues']) === 1 ? 'Cola sin atender' : 'Colas sin atender' }}">
            <flux:callout.text>
                @foreach ($board['stuckQueues'] as $queue)
                    <strong>{{ $queue['name'] }}</strong>: {{ $queue['advice'] }}@if (! $loop->last) · @endif
                @endforeach
            </flux:callout.text>
        </flux:callout>
    @endif

    {{-- Indicadores --}}
    <section class="grid grid-cols-6 gap-4">
        @foreach ($board['kpis'] as $kpi)
            <flux:card class="!p-5" wire:key="tv-kpi-{{ \Illuminate\Support\Str::slug($kpi['label']) }}">
                <flux:text class="!text-sm">{{ $kpi['label'] }}</flux:text>
                <div class="mt-1 flex items-baseline gap-2">
                    @if ($kpi['level'] !== null)
                        <span class="size-3 self-center rounded-full {{ ['green' => 'bg-lime-500', 'amber' => 'bg-amber-500', 'red' => 'bg-red-500'][$kpi['level']] }}"></span>
                    @endif
                    <span class="text-4xl font-bold tabular-nums">{{ $kpi['value'] }}</span>
                    @if ($kpi['unit'] !== '')
                        <span class="text-lg text-zinc-400">{{ $kpi['unit'] }}</span>
                    @endif
                </div>
                <flux:text class="mt-1 truncate !text-sm">{{ $kpi['hint'] }}</flux:text>
            </flux:card>
        @endforeach

        <flux:card class="relative overflow-hidden !p-5 !pb-14">
            <flux:text class="!text-sm">Peticiones / min</flux:text>
            <div class="mt-1 text-4xl font-bold tabular-nums">{{ $board['requestsSparkline'] !== [] ? $board['requestsSparkline'][array_key_last($board['requestsSparkline'])] : 0 }}</div>
            <flux:text class="!text-sm">{{ array_sum($board['requestsSparkline']) }} en 30 min</flux:text>
            <flux:chart class="pointer-events-none absolute inset-x-0 bottom-0 h-11 opacity-80" :value="$board['requestsSparkline']">
                <flux:chart.svg gutter="0">
                    <flux:chart.line class="text-sky-400" />
                    <flux:chart.area class="text-sky-400/20" />
                </flux:chart.svg>
            </flux:chart>
        </flux:card>
    </section>

    {{-- Gráficas de los últimos 30 minutos --}}
    <section class="grid grid-cols-3 gap-4">
        <flux:card class="col-span-2 !p-5">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">Tráfico · últimos 30 minutos</flux:heading>
                <div class="flex gap-4">
                    <flux:chart.legend label="Peticiones"><flux:chart.legend.indicator class="bg-sky-400" /></flux:chart.legend>
                    <flux:chart.legend label="Sin sesión"><flux:chart.legend.indicator class="bg-violet-400" /></flux:chart.legend>
                </div>
            </div>
            <flux:chart class="mt-3 h-40" :value="$board['chart']">
                <flux:chart.svg>
                    <flux:chart.area field="requests" class="text-sky-400/20" />
                    <flux:chart.line field="requests" class="text-sky-400" />
                    <flux:chart.line field="anonymous" class="text-violet-400" stroke-dasharray="4 4" />
                    <flux:chart.axis axis="x" field="at" :format="$timeFormat">
                        <flux:chart.axis.tick class="text-zinc-400" />
                        <flux:chart.axis.line class="text-zinc-700" />
                    </flux:chart.axis>
                    <flux:chart.axis axis="y" tick-start="0">
                        <flux:chart.axis.grid class="text-zinc-800" />
                        <flux:chart.axis.tick class="text-zinc-400" />
                    </flux:chart.axis>
                </flux:chart.svg>
            </flux:chart>

            <div class="mt-5 flex items-center justify-between">
                <flux:heading size="lg">Incidentes de seguridad por minuto</flux:heading>
                <div class="flex flex-wrap justify-end gap-4">
                    @foreach (\App\Support\LivePresence\LiveMonitorTvBoard::SECURITY_METRICS as $metric => $label)
                        <flux:chart.legend :label="$label"><flux:chart.legend.indicator class="{{ $metricLegend[$metric] }}" /></flux:chart.legend>
                    @endforeach
                </div>
            </div>
            <flux:chart class="mt-3 h-44" :value="$board['chart']">
                <flux:chart.svg>
                    @foreach (array_keys(\App\Support\LivePresence\LiveMonitorTvBoard::SECURITY_METRICS) as $metric)
                        <flux:chart.line :field="$metric" class="{{ $metricLine[$metric] }}" curve="none" />
                    @endforeach
                    <flux:chart.axis axis="x" field="at" :format="$timeFormat">
                        <flux:chart.axis.tick class="text-zinc-400" />
                        <flux:chart.axis.line class="text-zinc-700" />
                    </flux:chart.axis>
                    <flux:chart.axis axis="y" tick-start="0">
                        <flux:chart.axis.grid class="text-zinc-800" />
                        <flux:chart.axis.tick class="text-zinc-400" />
                    </flux:chart.axis>
                </flux:chart.svg>
            </flux:chart>
        </flux:card>

        <flux:card class="!p-5">
            <flux:heading size="lg">Seguridad · último minuto</flux:heading>
            <div class="mt-3 divide-y divide-zinc-800">
                @foreach ($board['lastMinute'] as $row)
                    <div class="flex items-center gap-4 py-2.5" wire:key="tv-minute-{{ $row['metric'] }}">
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold">{{ $row['label'] }}</div>
                            <flux:text class="!text-sm">{{ $row['five'] }} en 5 min · {{ $row['total'] }} en 30 min</flux:text>
                        </div>
                        <flux:chart class="h-8 w-24" :value="array_column($board['chart'], $row['metric'])">
                            <flux:chart.svg gutter="0">
                                <flux:chart.line class="{{ $metricLine[$row['metric']] }}" />
                            </flux:chart.svg>
                        </flux:chart>
                        <div class="w-16 text-right text-3xl font-bold tabular-nums {{ $levelText[$row['level']] }}">{{ $row['value'] }}</div>
                    </div>
                @endforeach
            </div>
        </flux:card>
    </section>

    {{-- Qué hacer, amenazas y eventos --}}
    <section class="grid grid-cols-3 gap-4">
        <flux:card class="!p-5">
            <flux:heading size="lg">Qué hacer ahora</flux:heading>
            <div class="mt-3 flex flex-col gap-3">
                @forelse ($board['actions'] as $action)
                    <div class="flex gap-3" wire:key="tv-action-{{ md5($action['title']) }}">
                        <flux:badge size="sm" :color="$severityColors[$action['severity']] ?? 'zinc'" class="h-fit shrink-0">{{ $severityLabels[$action['severity']] ?? '' }}</flux:badge>
                        <div class="min-w-0">
                            <div class="line-clamp-2 font-semibold leading-snug [overflow-wrap:anywhere]"><span class="text-zinc-400">{{ $action['area'] }} ·</span> {{ $action['title'] }}</div>
                            <flux:text class="line-clamp-2 !text-sm [overflow-wrap:anywhere]">{{ $action['detail'] }}</flux:text>
                        </div>
                    </div>
                @empty
                    <div class="flex items-center gap-2 text-lime-400"><flux:icon.check-circle /> Todo en orden. No hay tareas pendientes.</div>
                @endforelse
            </div>
        </flux:card>

        <flux:card class="!p-5">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">IPs sospechosas</flux:heading>
                <flux:badge size="sm" :color="$board['offendersTotal'] > 0 ? 'amber' : 'zinc'">{{ $board['offendersTotal'] }}</flux:badge>
            </div>
            <div class="mt-2 divide-y divide-zinc-800">
                @forelse ($board['offenders'] as $offender)
                    <div class="flex items-center gap-3 py-2" wire:key="tv-offender-{{ md5($offender['ip']) }}">
                        <div class="min-w-0 flex-1">
                            <div class="font-mono font-semibold">{{ $offender['flag'] }} {{ $offender['ip'] }}</div>
                            <flux:text class="truncate !text-sm">{{ $offender['location'] !== '' ? $offender['location'] : $offender['detail'] }}</flux:text>
                        </div>
                        @if ($offender['blocked'])
                            <flux:badge size="sm" color="zinc">Bloqueada</flux:badge>
                        @endif
                        <flux:badge size="sm" :color="$verdictColors[$offender['verdict']] ?? 'zinc'">{{ $offender['verdict_label'] }}</flux:badge>
                    </div>
                @empty
                    <flux:text class="py-2">Ninguna IP sospechosa.</flux:text>
                @endforelse
            </div>

            <flux:heading size="lg" class="mt-4">Cuentas bajo ataque</flux:heading>
            <div class="mt-2 divide-y divide-zinc-800">
                @forelse ($board['targets'] as $target)
                    <div class="flex items-center justify-between gap-3 py-2" wire:key="tv-target-{{ md5($target['account']) }}">
                        <span class="truncate font-semibold">{{ $target['account'] }}</span>
                        <flux:text class="shrink-0"><strong class="text-zinc-100">{{ $target['failures'] }}</strong> fallos</flux:text>
                    </div>
                @empty
                    <flux:text class="py-2">Ninguna cuenta con intentos fallidos.</flux:text>
                @endforelse
            </div>
        </flux:card>

        <flux:card class="!p-5">
            <flux:heading size="lg">Eventos de seguridad</flux:heading>
            <div class="mt-2 divide-y divide-zinc-800">
                @forelse ($board['events'] as $event)
                    <div class="flex gap-3 py-2" wire:key="tv-event-{{ $event['id'] ?? $loop->index }}-{{ $event['at'] ?? 0 }}">
                        <span class="mt-1.5 size-2.5 shrink-0 rounded-full {{ ['critical' => 'bg-red-500', 'warning' => 'bg-amber-500'][$event['severity'] ?? 'info'] ?? 'bg-sky-500' }}"></span>
                        <div class="min-w-0 flex-1">
                            <div class="flex justify-between gap-2">
                                <span class="truncate font-semibold">{{ $event['title'] ?? '' }}</span>
                                <span class="shrink-0 font-mono text-sm text-zinc-400">{{ $event['time'] ?? '' }}</span>
                            </div>
                            <flux:text class="line-clamp-1 !text-sm">{{ $event['detail'] ?? '' }}</flux:text>
                        </div>
                    </div>
                @empty
                    <flux:text class="py-2">Sin eventos todavía.</flux:text>
                @endforelse
            </div>
        </flux:card>
    </section>

    {{-- Colas y usuarios --}}
    <section class="grid grid-cols-3 gap-4">
        <flux:card class="!p-5">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">Colas</flux:heading>
                <flux:text class="!text-sm">
                    @if ($board['workers']['known'])
                        {{ $board['workers']['alive'] }} {{ $board['workers']['alive'] === 1 ? 'worker vivo' : 'workers vivos' }} · {{ $board['workers']['processed_30m'] }} en 30 min
                    @else
                        workers sin latido todavía
                    @endif
                </flux:text>
            </div>
            <flux:table class="mt-2">
                <flux:table.columns>
                    <flux:table.column>Cola</flux:table.column>
                    <flux:table.column align="end">Pend.</flux:table.column>
                    <flux:table.column align="end">Proceso</flux:table.column>
                    <flux:table.column align="end">Workers</flux:table.column>
                    <flux:table.column>Estado</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($board['queues'] as $queue)
                        <flux:table.row :key="'tv-queue-'.$queue['name']">
                            <flux:table.cell class="whitespace-nowrap font-mono font-semibold">{{ $queue['name'] }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ $queue['pending'] ?? '—' }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ $queue['reserved'] ?? '—' }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ $queue['listeners'] ?? '—' }}</flux:table.cell>
                            <flux:table.cell><flux:badge size="sm" :color="$queueColors[$queue['status']] ?? 'zinc'">{{ $queue['status_label'] }}</flux:badge></flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
            @if ($board['workers']['failed_24h'] !== null)
                <flux:text class="mt-2 !text-sm">{{ $board['workers']['failed_24h'] }} trabajos fallidos en 24 h</flux:text>
            @endif
        </flux:card>

        <flux:card class="col-span-2 !p-5">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">Usuarios conectados</flux:heading>
                <flux:text class="!text-sm">
                    {{ $board['totalSessions'] }} {{ $board['totalSessions'] === 1 ? 'sesión' : 'sesiones' }}@if ($board['totalSessions'] > count($board['sessions'])) · se muestran las {{ count($board['sessions']) }} más recientes @endif
                </flux:text>
            </div>
            @if ($board['sessions'] === [])
                <flux:text class="mt-3">No hay usuarios conectados en este momento.</flux:text>
            @else
                <flux:table class="mt-2">
                    <flux:table.columns>
                        <flux:table.column>Usuario</flux:table.column>
                        <flux:table.column>Dónde está</flux:table.column>
                        <flux:table.column>Ubicación</flux:table.column>
                        <flux:table.column>Latencia</flux:table.column>
                        <flux:table.column>Estado</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($board['sessions'] as $session)
                            <flux:table.row :key="'tv-session-'.$session['session_key']" class="{{ $session['idle'] ? 'opacity-50' : '' }}">
                                <flux:table.cell>
                                    <div class="font-semibold">{{ $session['user_name'] }}</div>
                                    <div class="text-sm text-zinc-400">{{ $session['user_email'] }}</div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex items-center gap-2">
                                        <flux:badge size="sm" :color="$session['is_pwa'] ? 'violet' : 'sky'">{{ $session['panel_label'] }}</flux:badge>
                                        <span class="max-w-72 truncate">{{ $session['page_label'] }}</span>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div>{{ $session['flag'] }} {{ $session['location'] }}</div>
                                    <div class="font-mono text-sm text-zinc-400">{{ $session['ip'] }}</div>
                                </flux:table.cell>
                                <flux:table.cell class="whitespace-nowrap">
                                    @if ($session['has_heartbeat'])
                                        <span class="mr-1.5 inline-block size-2.5 rounded-full {{ $rttDot[$session['rtt_level']] ?? 'bg-zinc-500' }}"></span>{{ $session['rtt_ms'] !== null ? $session['rtt_ms'].' ms' : 'midiendo…' }}
                                    @else
                                        <span class="text-zinc-500">sin señal</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="whitespace-nowrap">
                                    <div>{{ $session['idle'] ? 'Inactivo' : ($session['visible'] ? 'Activa' : 'Segundo plano') }}</div>
                                    <div class="text-sm text-zinc-400">{{ $session['last_seen_ago'] }}</div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
    </section>
</div>
