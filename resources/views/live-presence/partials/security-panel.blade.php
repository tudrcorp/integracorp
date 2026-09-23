{{--
    Resumen del monitor en vivo: semáforo, actividad, seguridad por minuto,
    amenazas, eventos y salud del sistema. Se usa en Negocios → Monitor en vivo
    y en la pantalla de TV ($tv = true: todo más grande y sin acciones).

    Espera: $security (SecuritySnapshot::build()), $kpis y $health (LiveActivitySnapshot),
    $tv (bool), $actions (bool: botones de desbloqueo).
--}}
@php
    $tv = $tv ?? false;
    $actions = $actions ?? false;
    $onlyConfirmed = $onlyConfirmed ?? false;
    $shownOffenders = $onlyConfirmed
        ? array_values(array_filter($security['offenders'], static fn (array $offender): bool => $offender['verdict'] === 'confirmed'))
        : $security['offenders'];

    $activity = [
        ['label' => 'Usuarios conectados', 'value' => $kpis['users'], 'hint' => $kpis['sessions'].' '.($kpis['sessions'] === 1 ? 'sesión activa' : 'sesiones activas').(($kpis['idle_sessions'] ?? 0) > 0 ? ' · '.$kpis['idle_sessions'].' '.($kpis['idle_sessions'] === 1 ? 'inactiva' : 'inactivas') : '')],
        ['label' => 'Pestañas activas', 'value' => $kpis['active_tabs'], 'hint' => ($kpis['sessions'] - $kpis['active_tabs']).' en segundo plano'],
        ['label' => 'En la PWA', 'value' => $kpis['pwa'], 'hint' => 'clientes en /app'],
        ['label' => 'Latencia media', 'value' => $kpis['avg_rtt'], 'unit' => 'ms', 'hint' => (($kpis['without_heartbeat'] ?? 0) > 0 ? $kpis['without_heartbeat'].' sin señal del navegador' : 'ida y vuelta del navegador'), 'level' => $kpis['avg_rtt'] === null ? null : ($kpis['avg_rtt'] < 200 ? 'good' : ($kpis['avg_rtt'] < 600 ? 'fair' : 'poor'))],
        ['label' => 'Respuesta p95', 'value' => $kpis['p95_ms'], 'unit' => 'ms', 'hint' => 'media '.($kpis['avg_ms'] ?? '—').' · máx '.($kpis['max_ms'] ?? '—').' ms (5 min)', 'level' => $kpis['p95_ms'] === null ? null : ($kpis['p95_ms'] < 800 ? 'good' : ($kpis['p95_ms'] < 2000 ? 'fair' : 'poor'))],
        ['label' => 'Peticiones / min', 'value' => $kpis['rpm'], 'hint' => 'de usuarios autenticados'],
    ];

    $securityMetrics = [
        'failed_logins' => ['Logins fallidos', '#ef4444'],
        'not_found' => ['Páginas inexistentes · 404', '#f59e0b'],
        'throttled' => ['Frenadas por límite · 429', '#f59e0b'],
        'csrf' => ['Formularios vencidos · 419', '#f59e0b'],
        'anonymous' => ['Peticiones sin sesión', '#38bdf8'],
        'server_errors' => ['Errores del servidor · 5xx', '#ef4444'],
    ];

    $tagColors = ['fuerza bruta' => 'red', 'relleno de credenciales' => 'red', 'herramienta de ataque' => 'red', 'inundación' => 'red', 'escáner' => 'amber', 'bot' => 'amber'];
    $verdictColors = ['confirmed' => 'red', 'possible' => 'amber', 'benign' => ''];
    $level = fn (bool $bad, bool $warn): string => $bad ? 'poor' : ($warn ? 'fair' : '');
    $queueReport = $health['queue_report'] ?? null;
    $stuckQueues = $queueReport['stuck'] ?? [];
    $failed = $queueReport['failed'] ?? [];
    try {
        $centerUrl = $tv ? null : \App\Filament\Business\Pages\LiveQueueCenter::getUrl();
    } catch (\Throwable) {
        $centerUrl = null;
    }
    $stuckRows = collect($queueReport['queues'] ?? [])->filter(static fn (array $row): bool => $row['stuck'] || ($row['unattended'] ?? false))->values();
    $healthChips = [
        ['label' => 'Colas', 'value' => $health['queue_pending'] ?? '—', 'hint' => $stuckQueues !== [] ? 'atascada: '.implode(', ', $stuckQueues) : 'pendientes', 'level' => $level($stuckQueues !== [] || ($health['queue_pending'] ?? 0) >= 200, ($health['queue_pending'] ?? 0) >= 50)],
        ['label' => 'Jobs fallidos', 'value' => $health['failed_jobs'] ?? '—', 'hint' => ($failed['last_24h'] ?? null) !== null ? $failed['last_24h'].' en 24 h' : null, 'level' => $level(($failed['last_24h'] ?? 0) >= 20, ($failed['last_24h'] ?? 0) > 0)],
        ['label' => 'Base de datos', 'value' => $health['db_ms'] !== null ? $health['db_ms'].' ms' : '—', 'level' => $level(($health['db_ms'] ?? 0) >= 200, ($health['db_ms'] ?? 0) >= 50)],
        ['label' => 'Carga CPU', 'value' => $health['load'] ? implode(' / ', $health['load']) : '—', 'hint' => '1 · 5 · 15 min'],
        ['label' => 'Disco libre', 'value' => $health['disk_free_pct'] !== null ? $health['disk_free_pct'].'%' : '—', 'level' => $health['disk_free_pct'] === null ? '' : $level($health['disk_free_pct'] < 15, $health['disk_free_pct'] < 25)],
    ];

    if ($health['redis']) {
        $healthChips[] = ['label' => 'Redis', 'value' => $health['redis']['memory'] ?? '—', 'hint' => ($health['redis']['clients'] ?? '—').' clientes · '.($health['redis']['ops_per_sec'] ?? '—').' ops/s'];
    }
@endphp

<style>
    .lsec { --s-bg: #ffffff; --s-soft: #f8fafc; --s-border: #e5e7eb; --s-text: #0f172a; --s-muted: #64748b; --s-red: #dc2626; --s-amber: #d97706; --s-green: #16a34a;
            display: flex; flex-direction: column; gap: 12px; color: var(--s-text); }
    .dark .lsec, .lsec.tv { --s-bg: #0b1220; --s-soft: #0f172a; --s-border: #1e293b; --s-text: #e2e8f0; --s-muted: #94a3b8; }
    .lsec-card { background: var(--s-bg); border: 1px solid var(--s-border); border-radius: 14px; }
    .lsec-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--s-muted); }
    .lsec-muted { color: var(--s-muted); font-size: 12px; }
    .lsec-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }

    /* Semáforo */
    .lsec-status { display: flex; align-items: center; gap: 14px; padding: 12px 18px; border-left-width: 4px; }
    .lsec-status.green { border-left-color: var(--s-green); } .lsec-status.amber { border-left-color: var(--s-amber); }
    .lsec-status.red { border-left-color: var(--s-red); animation: lsec-alarm 1.2s infinite; }
    @keyframes lsec-alarm { 50% { box-shadow: 0 0 0 5px rgba(220, 38, 38, .2); } }
    .lsec-light { width: 14px; height: 14px; border-radius: 999px; flex-shrink: 0; }
    .lsec-light.green { background: var(--s-green); box-shadow: 0 0 0 4px rgba(22, 163, 74, .18); }
    .lsec-light.amber { background: var(--s-amber); box-shadow: 0 0 0 4px rgba(217, 119, 6, .2); }
    .lsec-light.red { background: var(--s-red); box-shadow: 0 0 0 4px rgba(220, 38, 38, .25); }
    .lsec-status-title { font-size: 16px; font-weight: 800; }
    .lsec-status-reason { font-size: 12.5px; color: var(--s-muted); }
    .lsec-status-side { margin-left: auto; display: flex; gap: 18px; font-size: 12.5px; color: var(--s-muted); white-space: nowrap; }
    .lsec-status-side strong { color: var(--s-text); font-size: 15px; margin-right: 4px; }

    /* Franjas de métricas: una sola tarjeta dividida en columnas */
    .lsec-strip { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); }
    .lsec-strip > div { padding: 12px 16px; border-left: 1px solid var(--s-border); min-width: 0; }
    .lsec-strip > div:first-child { border-left: 0; }
    .lsec-strip-head { display: flex; justify-content: space-between; align-items: center; padding: 10px 16px 0; }
    .lsec-value { font-size: 22px; font-weight: 800; font-variant-numeric: tabular-nums; line-height: 1.2; margin-top: 4px; display: flex; align-items: center; gap: 6px; }
    .lsec-value small { font-size: 12px; font-weight: 600; color: var(--s-muted); }
    .lsec-hint { font-size: 11.5px; color: var(--s-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .lsec-dot { width: 8px; height: 8px; border-radius: 999px; flex-shrink: 0; }
    .lsec-dot.good { background: var(--s-green); } .lsec-dot.fair { background: var(--s-amber); } .lsec-dot.poor { background: var(--s-red); }
    .lsec-spark { display: block; width: 100%; height: 26px; margin-top: 6px; }

    /* Amenazas y eventos */
    .lsec-grid { display: grid; grid-template-columns: minmax(0, 3fr) minmax(0, 2fr); gap: 12px; }
    .lsec-panel-head { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border-bottom: 1px solid var(--s-border); }
    .lsec-sub { padding: 10px 16px 12px; border-top: 1px solid var(--s-border); }
    .lsec-sub:first-of-type { border-top: 0; }
    .lsec-subtitle { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
    .lsec-count { font-size: 11px; font-weight: 800; border-radius: 999px; padding: 1px 8px; background: rgba(100, 116, 139, .15); color: var(--s-muted); }
    .lsec-count.alert { background: rgba(220, 38, 38, .15); color: var(--s-red); }
    .lsec-ok { display: flex; align-items: center; gap: 6px; font-size: 12.5px; color: var(--s-muted); }
    .lsec-ok::before { content: '✓'; color: var(--s-green); font-weight: 800; }
    .lsec-row { display: grid; gap: 10px; align-items: center; padding: 7px 0; border-top: 1px solid var(--s-border); font-size: 13px; }
    .lsec-row:first-child { border-top: 0; }
    .lsec-row.ip { grid-template-columns: minmax(0, 1.2fr) minmax(0, 1.6fr) minmax(0, 1fr) auto; }
    .lsec-row.ip.with-actions { grid-template-columns: minmax(0, 1.2fr) minmax(0, 1.6fr) minmax(0, 1fr) auto auto; }
    .lsec-row.ip.benign { opacity: .62; }
    .lsec-verdict { display: inline-block; border-radius: 6px; padding: 1px 7px; font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; margin-top: 3px; background: rgba(100, 116, 139, .15); color: var(--s-muted); }
    .lsec-verdict.red { background: rgba(220, 38, 38, .18); color: var(--s-red); } .lsec-verdict.amber { background: rgba(217, 119, 6, .18); color: var(--s-amber); }
    .lsec-verdict.blocked { background: rgba(15, 23, 42, .85); color: #fff; }
    .lsec-why { font-size: 12px; color: var(--s-muted); margin-top: 2px; }
    .lsec-why.warn { color: var(--s-amber); }
    .lsec-ip-actions { display: flex; flex-direction: column; gap: 4px; align-items: flex-end; }
    .lsec-filter { font-size: 11px; font-weight: 700; border: 1px solid var(--s-border); border-radius: 999px; padding: 2px 10px; background: transparent; color: var(--s-muted); cursor: pointer; }
    .lsec-filter.on { background: rgba(220, 38, 38, .15); border-color: rgba(220, 38, 38, .4); color: var(--s-red); }
    .lsec-row.pair { grid-template-columns: minmax(0, 1fr) auto; }
    .lsec-tag { display: inline-block; border-radius: 999px; padding: 1px 8px; font-size: 11px; font-weight: 700; margin: 1px 3px 1px 0; background: rgba(100, 116, 139, .15); color: var(--s-muted); }
    .lsec-tag.red { background: rgba(220, 38, 38, .15); color: var(--s-red); } .lsec-tag.amber { background: rgba(217, 119, 6, .16); color: var(--s-amber); }
    .lsec-two { display: grid; grid-template-columns: 1fr 1fr; }
    .lsec-two > .lsec-sub:last-child { border-left: 1px solid var(--s-border); }
    .lsec-events { list-style: none; margin: 0; padding: 4px 16px 10px; max-height: 360px; overflow-y: auto; }
    .lsec-events li { display: flex; gap: 10px; padding: 8px 0; border-top: 1px solid var(--s-border); font-size: 12.5px; }
    .lsec-events li:first-child { border-top: 0; }
    .lsec-sev { width: 4px; border-radius: 4px; flex-shrink: 0; background: #38bdf8; }
    .lsec-sev.critical { background: var(--s-red); } .lsec-sev.warning { background: var(--s-amber); }

    /* Secciones plegables */
    .lsec-fold { display: flex; flex-direction: column; gap: 8px; }
    .lsec-fold-head { display: flex; align-items: center; gap: 10px; width: 100%; background: transparent; border: 0; padding: 2px 4px; color: inherit; cursor: pointer; text-align: left; }
    .lsec-fold-head:hover .lsec-fold-toggle { color: var(--s-text); border-color: var(--s-muted); }
    .lsec-fold-summary { font-size: 12.5px; color: var(--s-muted); min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .lsec-fold-summary strong { color: var(--s-text); }
    .lsec-fold-summary.alert strong { color: var(--s-red); }
    .lsec-fold-toggle { margin-left: auto; flex-shrink: 0; display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: var(--s-muted); border: 1px solid var(--s-border); border-radius: 999px; padding: 3px 10px; }
    .lsec-fold-chevron { display: inline-block; transition: transform .2s; }
    .lsec-fold-chevron.closed { transform: rotate(-90deg); }

    /* Salud del sistema */
    .lsec-health { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .lsec-chip { display: inline-flex; align-items: baseline; gap: 6px; border: 1px solid var(--s-border); background: var(--s-bg); border-radius: 999px; padding: 5px 12px; font-size: 12px; color: var(--s-muted); }
    .lsec-chip strong { color: var(--s-text); font-size: 13px; font-variant-numeric: tabular-nums; }
    .lsec-chip.fair { border-color: rgba(217, 119, 6, .55); } .lsec-chip.fair strong { color: var(--s-amber); }
    .lsec-chip.poor { border-color: rgba(220, 38, 38, .6); } .lsec-chip.poor strong { color: var(--s-red); }

    /* Colas y trabajos */
    .lsec-banner { display: flex; gap: 12px; align-items: flex-start; padding: 12px 18px; border-left: 4px solid var(--s-red); background: rgba(220, 38, 38, .07); }
    .lsec-banner-title { font-weight: 800; color: var(--s-red); }
    .lsec-cmd { display: flex; align-items: center; gap: 8px; margin-top: 6px; }
    .lsec-cmd code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; background: var(--s-soft); border: 1px solid var(--s-border); border-radius: 8px; padding: 4px 8px; overflow-x: auto; white-space: nowrap; max-width: 100%; }
    .lsec-copy { flex-shrink: 0; font-size: 11.5px; font-weight: 700; border: 1px solid var(--s-border); border-radius: 999px; padding: 3px 10px; background: var(--s-bg); color: var(--s-muted); cursor: pointer; }
    .lsec-copy:hover { color: var(--s-text); }
    .lsec-queues { width: 100%; border-collapse: collapse; font-size: 13px; }
    .lsec-queues th { text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--s-muted); padding: 8px 16px; border-bottom: 1px solid var(--s-border); }
    .lsec-queues td { padding: 7px 16px; border-top: 1px solid var(--s-border); vertical-align: middle; font-variant-numeric: tabular-nums; }
    .lsec-queues tr:first-child td { border-top: 0; }
    .lsec-queues .num { text-align: right; }
    .lsec-queues tr.stuck td { background: rgba(220, 38, 38, .06); }
    .lsec-state { display: inline-block; border-radius: 999px; padding: 1px 8px; font-size: 11px; font-weight: 700; }
    .lsec-state.ok { background: rgba(22, 163, 74, .14); color: var(--s-green); }
    .lsec-state.busy { background: rgba(217, 119, 6, .16); color: var(--s-amber); }
    .lsec-state.stuck { background: rgba(220, 38, 38, .15); color: var(--s-red); }
    .lsec-state.idle { background: rgba(100, 116, 139, .15); color: var(--s-muted); }
    .lsec-failed-kpis { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); border-bottom: 1px solid var(--s-border); }
    .lsec-failed-kpis > div { padding: 10px 16px; border-left: 1px solid var(--s-border); }
    .lsec-failed-kpis > div:first-child { border-left: 0; }
    .lsec.tv .lsec-queues { font-size: 15px; }

    /* TV: todo más grande para leer de lejos */
    .lsec.tv .lsec-status-title { font-size: 28px; } .lsec.tv .lsec-status-reason { font-size: 16px; } .lsec.tv .lsec-light { width: 22px; height: 22px; }
    .lsec.tv .lsec-value { font-size: 34px; } .lsec.tv .lsec-label { font-size: 13px; } .lsec.tv .lsec-hint { font-size: 13px; }
    .lsec.tv .lsec-row, .lsec.tv .lsec-events li { font-size: 15px; } .lsec.tv .lsec-chip { font-size: 14px; } .lsec.tv .lsec-chip strong { font-size: 16px; }
    .lsec.tv .lsec-events { max-height: 440px; }

    @media (max-width: 1200px) {
        .lsec-strip { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .lsec-strip > div:nth-child(4) { border-left: 0; }
        .lsec-strip > div:nth-child(n+4) { border-top: 1px solid var(--s-border); }
        .lsec-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 640px) { .lsec-two { grid-template-columns: 1fr; } .lsec-two > .lsec-sub:last-child { border-left: 0; } .lsec-status { flex-wrap: wrap; } .lsec-status-side { margin-left: 0; } }
</style>

<div class="lsec {{ $tv ? 'tv' : '' }}">
    @isset($advice)
        @include('live-presence.partials.advisor', ['advice' => $advice, 'actions' => ! $tv])
    @endisset

    {{-- Semáforo --}}
    <div class="lsec-card lsec-status {{ $security['level'] }}">
        <span class="lsec-light {{ $security['level'] }}"></span>
        <div style="min-width: 0;">
            <div class="lsec-status-title">{{ $security['level_label'] }}</div>
            @foreach ($security['reasons'] as $reason)
                <div class="lsec-status-reason">{{ $reason }}</div>
            @endforeach
        </div>
        <div class="lsec-status-side">
            <span><strong>{{ count($security['locks']) }}</strong>{{ count($security['locks']) === 1 ? 'cuenta bloqueada' : 'cuentas bloqueadas' }}</span>
            <span><strong>{{ $security['blocked_users'] }}</strong>{{ $security['blocked_users'] === 1 ? 'usuario' : 'usuarios' }} en la lista negra</span>
            <span><strong>{{ $security['blocked_ips'] ?? 0 }}</strong>{{ ($security['blocked_ips'] ?? 0) === 1 ? 'IP' : 'IPs' }} en la lista negra</span>
        </div>
    </div>

    {{-- Cola atascada: ningún worker la atiende --}}
    @if ($stuckRows->isNotEmpty())
        <div class="lsec-card lsec-banner" role="alert">
            <span class="lsec-light red" style="margin-top: 3px;"></span>
            <div style="min-width: 0;">
                <div class="lsec-banner-title">{{ $stuckRows->count() === 1 ? 'Cola sin atender' : 'Colas sin atender' }}</div>
                @foreach ($stuckRows as $row)
                    <div class="lsec-status-reason"><strong style="color: var(--s-text);">{{ $row['name'] }}</strong>: {{ $row['advice'] ?? ($row['pending'].' trabajos esperan.') }}</div>
                @endforeach
                <div class="lsec-status-reason">
                    Reinicie el worker con todas las colas
                    @if ($centerUrl)
                        o <a href="{{ $centerUrl }}?tab=colas" wire:navigate style="color: #0284c7; font-weight: 700;">libere la cola →</a> sacando lo que la traba
                    @endif
                </div>
                <div class="lsec-cmd"><code>{{ $queueReport['worker_command'] }}</code></div>
            </div>
        </div>
    @endif

    {{-- Actividad --}}
    <section class="lsec-fold" @unless ($tv) x-data="{ open: $persist(true).as('lam-fold-activity') }" @endunless>
        @unless ($tv)
            <button type="button" class="lsec-fold-head" x-on:click="open = ! open" x-bind:aria-expanded="open" title="Ocultar o mostrar esta sección">
                <span class="lsec-label">Actividad</span>
                <span class="lsec-fold-summary" x-show="! open" wire:ignore.self><strong>{{ $kpis['users'] }}</strong> usuarios · <strong>{{ $kpis['active_tabs'] }}</strong> pestañas activas · <strong>{{ $kpis['pwa'] }}</strong> en la PWA · latencia <strong>{{ $kpis['avg_rtt'] ?? '—' }}</strong> ms · p95 <strong>{{ $kpis['p95_ms'] ?? '—' }}</strong> ms · <strong>{{ $kpis['rpm'] }}</strong> pet/min</span>
                <span class="lsec-fold-toggle">
                    <span class="lsec-fold-chevron" x-bind:class="open ? '' : 'closed'" wire:ignore.self>▾</span>
                    <span x-text="open ? 'Ocultar' : 'Mostrar'" wire:ignore>Ocultar</span>
                </span>
            </button>
        @endunless
        <div @unless ($tv) x-show="open" x-collapse wire:ignore.self @endunless>
        <div class="lsec-card lsec-strip">
            @foreach ($activity as $item)
                <div>
                    <div class="lsec-label">{{ $item['label'] }}</div>
                    <div class="lsec-value">
                        @if (! empty($item['level']))<span class="lsec-dot {{ $item['level'] }}"></span>@endif
                        {{ $item['value'] ?? '—' }}@if ($item['value'] !== null && ! empty($item['unit']))<small>{{ $item['unit'] }}</small>@endif
                    </div>
                    <div class="lsec-hint" title="{{ $item['hint'] }}">{{ $item['hint'] }}</div>
                </div>
            @endforeach
        </div>
        </div>
    </section>

    {{-- Seguridad por minuto --}}
    <section class="lsec-fold" @unless ($tv) x-data="{ open: $persist(true).as('lam-fold-security') }" @endunless>
        @unless ($tv)
            <button type="button" class="lsec-fold-head" x-on:click="open = ! open" x-bind:aria-expanded="open" title="Ocultar o mostrar esta sección">
                <span class="lsec-label">Seguridad</span>
                <span class="lsec-fold-summary {{ (($security['last_minute']['failed_logins'] ?? 0) + ($security['last_minute']['server_errors'] ?? 0)) > 0 ? 'alert' : '' }}" x-show="! open" wire:ignore.self>Último minuto: <strong>{{ $security['last_minute']['failed_logins'] ?? 0 }}</strong> logins fallidos · <strong>{{ $security['last_minute']['not_found'] ?? 0 }}</strong> 404 · <strong>{{ $security['last_minute']['throttled'] ?? 0 }}</strong> 429 · <strong>{{ $security['last_minute']['csrf'] ?? 0 }}</strong> 419 · <strong>{{ $security['last_minute']['anonymous'] ?? 0 }}</strong> sin sesión · <strong>{{ $security['last_minute']['server_errors'] ?? 0 }}</strong> errores 5xx</span>
                <span class="lsec-fold-toggle">
                    <span class="lsec-fold-chevron" x-bind:class="open ? '' : 'closed'" wire:ignore.self>▾</span>
                    <span x-text="open ? 'Ocultar' : 'Mostrar'" wire:ignore>Ocultar</span>
                </span>
            </button>
        @endunless
        <div @unless ($tv) x-show="open" x-collapse wire:ignore.self @endunless>
        <div class="lsec-card">
            <div class="lsec-strip-head">
                <span class="lsec-muted">Por minuto, con gráficas de los últimos 30 min</span>
            </div>
            <div class="lsec-strip">
                @foreach ($securityMetrics as $metric => [$label, $color])
                    @php $values = $security['series'][$metric] ?? []; @endphp
                    <div wire:key="metric-{{ $metric }}">
                        <div class="lsec-label">{{ $label }}</div>
                        <div class="lsec-value">{{ $security['last_minute'][$metric] ?? 0 }}<small>/min</small></div>
                        <div class="lsec-hint">{{ $security['last_five'][$metric] ?? 0 }} en 5 min · {{ array_sum($values) }} en 30 min</div>
                        <svg class="lsec-spark" viewBox="0 0 160 26" preserveAspectRatio="none" aria-hidden="true">
                            <polyline fill="none" stroke="{{ $color }}" stroke-width="1.8" stroke-linejoin="round" points="{{ \App\Support\LivePresence\SecuritySnapshot::sparkline($values, 160, 26) }}" />
                        </svg>
                    </div>
                @endforeach
            </div>
        </div>
        </div>
    </section>

    {{-- Amenazas y eventos --}}
    <section id="lsec-threats" class="lsec-fold" @unless ($tv) x-data="{ open: $persist(true).as('lam-fold-threats') }" @endunless>
        @unless ($tv)
            <button type="button" class="lsec-fold-head" x-on:click="open = ! open" x-bind:aria-expanded="open" title="Ocultar o mostrar esta sección">
                <span class="lsec-label">Amenazas y eventos</span>
                <span class="lsec-fold-summary {{ ($security['offenders'] !== [] || $security['targets'] !== [] || $security['locks'] !== []) ? 'alert' : '' }}" x-show="! open" wire:ignore.self><strong>{{ count($security['offenders']) }}</strong> IPs sospechosas · <strong>{{ count($security['targets']) }}</strong> cuentas atacadas · <strong>{{ count($security['locks']) }}</strong> bloqueadas · <strong>{{ count($security['events']) }}</strong> eventos</span>
                <span class="lsec-fold-toggle">
                    <span class="lsec-fold-chevron" x-bind:class="open ? '' : 'closed'" wire:ignore.self>▾</span>
                    <span x-text="open ? 'Ocultar' : 'Mostrar'" wire:ignore>Ocultar</span>
                </span>
            </button>
        @endunless
        <div @unless ($tv) x-show="open" x-collapse wire:ignore.self @endunless>
        <div class="lsec-grid">
            <div class="lsec-card">
                <div class="lsec-panel-head">
                    <span class="lsec-label">Amenazas · últimas 24 h</span>
                </div>

                <div class="lsec-sub">
                    <div class="lsec-subtitle">
                        <span class="lsec-label">IPs sospechosas</span>
                        <span style="display: inline-flex; align-items: center; gap: 8px;">
                            @if ($actions && $security['offenders'] !== [])
                                <button type="button" class="lsec-filter {{ $onlyConfirmed ? 'on' : '' }}" wire:click="toggleOnlyConfirmedThreats" title="Solo cambia la vista: no borra nada">
                                    {{ $onlyConfirmed ? '✓ ' : '' }}Solo confirmadas
                                </button>
                                {{ $this->clearFalsePositivesAction }}
                            @endif
                            <span class="lsec-count {{ $security['offenders'] !== [] ? 'alert' : '' }}">{{ count($shownOffenders) }}@if ($onlyConfirmed) / {{ count($security['offenders']) }} @endif</span>
                        </span>
                    </div>
                    @forelse ($shownOffenders as $offender)
                        <div class="lsec-row ip {{ $actions ? 'with-actions' : '' }} {{ $offender['verdict'] === 'benign' && ! $offender['blocked'] ? 'benign' : '' }}" wire:key="offender-{{ $offender['ip'] }}">
                            <div>
                                <div class="lsec-mono" style="font-weight: 700;">{{ $offender['ip'] }}</div>
                                <div class="lsec-muted">{{ $offender['flag'] }} {{ $offender['location'] ?: 'Ubicación desconocida' }}</div>
                                @if ($offender['blocked'])
                                    <span class="lsec-verdict blocked">En lista negra</span>
                                @else
                                    <span class="lsec-verdict {{ $verdictColors[$offender['verdict']] ?? '' }}">{{ $offender['verdict_label'] }}</span>
                                @endif
                            </div>
                            <div style="min-width: 0;" title="{{ $offender['user_agent'] }}">
                                @foreach ($offender['tags'] as $tag)
                                    <span class="lsec-tag {{ $tagColors[$tag] ?? '' }}">{{ $tag }}</span>
                                @endforeach
                                @foreach ($offender['reasons'] as $reason)
                                    <div class="lsec-why">{{ $reason }}</div>
                                @endforeach
                                @foreach ($offender['mitigations'] as $mitigation)
                                    <div class="lsec-why warn">⚠ {{ $mitigation }}</div>
                                @endforeach
                            </div>
                            <div>
                                <div>{{ $offender['failed_logins'] }} logins · {{ $offender['accounts_tried'] }} cuentas</div>
                                <div class="lsec-muted" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $offender['last_account'] ?: $offender['last_path'] }}</div>
                            </div>
                            <div class="lsec-muted" style="text-align: right;">{{ $offender['last_seen_ago'] }}<br>puntaje {{ $offender['score'] }}</div>
                            @if ($actions)
                                <div class="lsec-ip-actions">
                                    @unless ($offender['blocked'])
                                        {{ ($this->blacklistIpAction)(['ip' => $offender['ip']]) }}
                                        {{ ($this->dismissIpAction)(['ip' => $offender['ip']]) }}
                                    @endunless
                                    {{ ($this->resetIpAction)(['ip' => $offender['ip']]) }}
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="lsec-ok">{{ $onlyConfirmed && $security['offenders'] !== [] ? 'Ninguna amenaza confirmada. Hay '.count($security['offenders']).' IPs sospechosas ocultas por el filtro.' : 'Ninguna IP con comportamiento sospechoso.' }}</div>
                    @endforelse
                </div>

                <div class="lsec-two" style="border-top: 1px solid var(--s-border);">
                    <div class="lsec-sub">
                        <div class="lsec-subtitle">
                            <span class="lsec-label">Cuentas bajo ataque</span>
                            <span class="lsec-count {{ $security['targets'] !== [] ? 'alert' : '' }}">{{ count($security['targets']) }}</span>
                        </div>
                        @forelse ($security['targets'] as $account => $failures)
                            <div class="lsec-row pair" wire:key="target-{{ md5($account) }}">
                                <span style="font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $account }}">{{ $account }}</span>
                                <span class="lsec-muted"><strong style="color: var(--s-text);">{{ (int) $failures }}</strong> fallos</span>
                            </div>
                        @empty
                            <div class="lsec-ok">Ninguna cuenta con intentos fallidos.</div>
                        @endforelse
                    </div>

                    <div class="lsec-sub">
                        <div class="lsec-subtitle">
                            <span class="lsec-label">Bloqueadas temporalmente</span>
                            <span class="lsec-count {{ $security['locks'] !== [] ? 'alert' : '' }}">{{ count($security['locks']) }}</span>
                        </div>
                        @forelse ($security['locks'] as $lock)
                            <div class="lsec-row pair" wire:key="lock-{{ md5($lock['account']) }}">
                                <div style="min-width: 0;">
                                    <div style="font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $lock['account'] }}</div>
                                    <div class="lsec-muted">{{ $lock['failures'] }} fallos · {{ $lock['ips'] }} {{ (int) $lock['ips'] === 1 ? 'IP' : 'IPs' }} · libera {{ date('H:i', (int) $lock['until']) }}</div>
                                </div>
                                @if ($actions)
                                    <div>{{ ($this->unlockAccountAction)(['account' => $lock['account']]) }}</div>
                                @endif
                            </div>
                        @empty
                            <div class="lsec-ok">Ninguna cuenta bloqueada.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="lsec-card">
                <div class="lsec-panel-head">
                    <span class="lsec-label">Eventos de seguridad</span>
                    <span class="lsec-muted">en vivo</span>
                </div>
                @if ($security['events'] === [])
                    <div style="padding: 12px 16px;"><div class="lsec-ok">Sin eventos todavía.</div></div>
                @else
                    <ul class="lsec-events">
                        @foreach ($security['events'] as $event)
                            <li wire:key="sec-event-{{ $event['id'] ?? $loop->index }}-{{ $event['at'] ?? 0 }}">
                                <span class="lsec-sev {{ $event['severity'] ?? 'info' }}"></span>
                                <div style="min-width: 0; flex: 1;">
                                    <div style="display: flex; justify-content: space-between; gap: 8px;">
                                        <strong>{{ $event['title'] ?? '' }}</strong>
                                        <span class="lsec-muted" title="{{ $event['ago'] }}">{{ $event['time'] }}</span>
                                    </div>
                                    <div style="word-break: break-word;">{{ $event['detail'] ?? '' }}</div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
        </div>
    </section>

    {{-- Colas y trabajos --}}
    @if ($queueReport)
        @php
            $queueWorkers = $queueReport['workers'] ?? ['known' => false, 'alive' => []];
            $unattendedQueues = $queueReport['unattended'] ?? [];
            $queueStatusClasses = ['unattended' => 'stuck', 'stuck' => 'stuck', 'zombie' => 'busy', 'busy' => 'busy', 'ok' => 'ok', 'unknown' => 'idle'];
            $categoryTags = ['provider' => 'amber', 'network' => 'amber', 'code' => 'red', 'resources' => 'red', 'config' => '', 'data' => '', 'unknown' => ''];
        @endphp
        <section class="lsec-fold" @unless ($tv) x-data="{ open: $persist(true).as('lam-fold-queues') }" @endunless>
            @unless ($tv)
                <button type="button" class="lsec-fold-head" x-on:click="open = ! open" x-bind:aria-expanded="open" title="Ocultar o mostrar esta sección">
                    <span class="lsec-label">Colas y trabajos</span>
                    <span class="lsec-fold-summary {{ $stuckQueues !== [] || $unattendedQueues !== [] || ($failed['last_24h'] ?? 0) > 0 ? 'alert' : '' }}" x-show="! open" wire:ignore.self><strong>{{ $queueReport['pending_total'] }}</strong> pendientes · <strong>{{ count($unattendedQueues) + count($stuckQueues) }}</strong> sin atender · <strong>{{ $queueWorkers['known'] ? count($queueWorkers['alive']) : '—' }}</strong> workers · <strong>{{ $failed['last_24h'] ?? '—' }}</strong> fallidos en 24 h</span>
                    <span class="lsec-fold-toggle">
                        <span class="lsec-fold-chevron" x-bind:class="open ? '' : 'closed'" wire:ignore.self>▾</span>
                        <span x-text="open ? 'Ocultar' : 'Mostrar'" wire:ignore>Ocultar</span>
                    </span>
                </button>
            @endunless
            <div @unless ($tv) x-show="open" x-collapse wire:ignore.self @endunless>
            <div class="lsec-grid">
                <div class="lsec-card">
                    <div class="lsec-panel-head">
                        <span class="lsec-label">Colas · driver {{ $health['queue_driver'] }}</span>
                        <span class="lsec-muted">
                            @if (! $queueWorkers['known'])
                                workers sin latido todavía
                            @else
                                {{ count($queueWorkers['alive']) }} {{ count($queueWorkers['alive']) === 1 ? 'worker vivo' : 'workers vivos' }} · {{ $queueReport['throughput']['processed_30m'] ?? 0 }} procesados en 30 min
                            @endif
                            @if ($centerUrl) · <a href="{{ $centerUrl }}?tab=colas" wire:navigate style="color: #0284c7; font-weight: 700;">Ver detalle →</a>@endif
                        </span>
                    </div>
                    <table class="lsec-queues">
                        <thead>
                            <tr>
                                <th>Cola</th>
                                <th class="num">Pendientes</th>
                                <th class="num">En proceso</th>
                                <th class="num">Programados</th>
                                <th class="num">Más viejo</th>
                                <th class="num">Workers</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($queueReport['queues'] as $row)
                                <tr class="{{ in_array($row['status'] ?? '', ['unattended', 'stuck'], true) ? 'stuck' : '' }}" wire:key="queue-{{ $row['name'] }}" title="{{ $row['advice'] ?? '' }}">
                                    <td class="lsec-mono" style="font-weight: 700;">{{ $row['name'] }}</td>
                                    <td class="num">{{ $row['pending'] ?? '—' }}</td>
                                    <td class="num">{{ $row['reserved'] ?? '—' }}@if (($row['zombies'] ?? 0) > 0) <span style="color: var(--s-amber);">({{ $row['zombies'] }} colgados)</span>@endif</td>
                                    <td class="num">{{ $row['delayed'] ?? '—' }}</td>
                                    <td class="num">{{ \App\Support\LivePresence\QueueHealth::ageLabel($row['oldest_seconds']) }}</td>
                                    <td class="num">{{ $queueWorkers['known'] ? ($row['listeners'] ?? 0) : '—' }}</td>
                                    <td><span class="lsec-state {{ $queueStatusClasses[$row['status'] ?? 'unknown'] ?? 'idle' }}">{{ $row['status_label'] ?? '—' }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if ($unattendedQueues !== [] || $stuckQueues !== [])
                        <div class="lsec-sub" style="border-top: 1px solid var(--s-border);">
                            <div class="lsec-status-reason">Arranque el worker con todas las colas:</div>
                            <div class="lsec-cmd"><code>{{ $queueReport['worker_command'] }}</code>@unless ($tv) @include('live-presence.partials.copy-button', ['text' => $queueReport['worker_command'], 'label' => 'Copiar']) @endunless</div>
                        </div>
                    @endif
                </div>

                <div class="lsec-card">
                    <div class="lsec-panel-head">
                        <span class="lsec-label">Trabajos fallidos</span>
                        <span class="lsec-muted">por causa · 7 días @if ($centerUrl) · <a href="{{ $centerUrl }}?tab=causas" wire:navigate style="color: #0284c7; font-weight: 700;">Gestionar →</a>@endif</span>
                    </div>
                    <div class="lsec-failed-kpis">
                        <div><div class="lsec-label">1 h</div><div class="lsec-value">@if (($failed['last_hour'] ?? 0) > 0)<span class="lsec-dot poor"></span>@endif{{ $failed['last_hour'] ?? '—' }}</div></div>
                        <div><div class="lsec-label">24 h</div><div class="lsec-value">@if (($failed['last_24h'] ?? 0) > 0)<span class="lsec-dot fair"></span>@endif{{ $failed['last_24h'] ?? '—' }}</div></div>
                        <div><div class="lsec-label">Total</div><div class="lsec-value">{{ $failed['total'] ?? '—' }}</div></div>
                    </div>
                    <div class="lsec-sub">
                        @forelse ($failed['groups'] ?? [] as $cause)
                            <div class="lsec-row pair" wire:key="failed-{{ $cause['fingerprint'] }}">
                                <div style="min-width: 0;">
                                    <div><strong>{{ $cause['job'] }}</strong> <span class="lsec-tag {{ $categoryTags[$cause['diagnosis']['category']] ?? '' }}">{{ $cause['diagnosis']['category_label'] }}</span></div>
                                    <div style="font-size: 12.5px;">{{ $cause['diagnosis']['title'] }}</div>
                                    <div class="lsec-muted">→ {{ $cause['diagnosis']['action_label'] }} · último {{ $cause['last_ago'] }}</div>
                                </div>
                                <span class="lsec-count alert" title="último: {{ $cause['last_at'] }}">{{ $cause['count'] }}</span>
                            </div>
                        @empty
                            <div class="lsec-ok">Ningún trabajo falló en los últimos 7 días.</div>
                        @endforelse
                    </div>
                </div>
            </div>
            </div>
        </section>
    @endif

    {{-- Salud del sistema --}}
    <div class="lsec-health">
        @foreach ($healthChips as $chip)
            <span class="lsec-chip {{ $chip['level'] ?? '' }}" @if (! empty($chip['hint'])) title="{{ $chip['hint'] }}" @endif>
                {{ $chip['label'] }} <strong>{{ $chip['value'] }}</strong>
                @if (! empty($chip['hint']))<span>{{ $chip['hint'] }}</span>@endif
            </span>
        @endforeach
        <span class="lsec-chip">PHP <strong>{{ $health['php'] }}</strong> {{ $health['environment'] }} · cola {{ $health['queue_driver'] }} · {{ $health['store'] }}</span>
        <span class="lsec-muted">medido {{ $health['measured_at'] }}</span>
        <span class="lsec-muted" style="margin-left: auto;">
            Ubicación por IP: <a href="{{ config('live-presence.geoip.provider_url', 'https://db-ip.com') }}" target="_blank" rel="noopener" style="color: inherit; text-decoration: underline;">{{ config('live-presence.geoip.provider', 'DB-IP') }}</a> · CC BY 4.0
        </span>
    </div>
</div>
