@php
    use App\Support\UserActivity\UserActivityClock as Clock;
    use App\Support\UserActivity\UserActivityState as State;

    $fmt = fn (int $minutes): string => Clock::formatDuration($minutes);
    $ago = function (?int $timestamp): string {
        if ($timestamp === null) {
            return '';
        }
        $seconds = max(0, now()->getTimestamp() - $timestamp);

        return match (true) {
            $seconds < 60 => 'hace un momento',
            $seconds < 3600 => 'hace '.intdiv($seconds, 60).' min',
            default => 'hace '.Clock::formatDuration(intdiv($seconds, 60)),
        };
    };
    $usageClass = fn (int $usage): string => $usage >= 70 ? 'good' : ($usage >= 40 ? 'fair' : 'poor');
    $initials = function (string $name): string {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1).mb_substr($parts[1] ?? '', 0, 1));
    };
    $nowMinute = Clock::minuteOfDay(Clock::now());
    $stateKey = fn (State $state): string => match ($state) {
        State::Active => 'active',
        State::Idle => 'idle',
        State::Background => 'background',
        State::Offline => 'offline',
    };
    $eventIcon = fn (string $type): string => match ($type) {
        'page' => '📄',
        'action' => '👆',
        'download' => '⬇️',
        'visibility' => '🗂️',
        'idle' => '⏸️',
        'active' => '▶️',
        default => '•',
    };
@endphp

<x-filament-panels::page>
    <style>
        .uam { --uam-bg: #ffffff; --uam-soft: #f8fafc; --uam-border: #e5e7eb; --uam-text: #0f172a; --uam-muted: #64748b; --uam-accent: #0ea5e9; --uam-chip: #f1f5f9;
               --uam-active: #16a34a; --uam-idle: #f59e0b; --uam-bgtab: #94a3b8; --uam-off: #cbd5e1; --uam-track: #eef2f7;
               color: var(--uam-text); display: flex; flex-direction: column; gap: 16px; }
        .dark .uam { --uam-bg: #0b1220; --uam-soft: #111a2e; --uam-border: #1f2a44; --uam-text: #e2e8f0; --uam-muted: #94a3b8; --uam-chip: #16213a; --uam-off: #334155; --uam-track: #16213a; }
        .uam-card { background: var(--uam-bg); border: 1px solid var(--uam-border); border-radius: 16px; }
        .uam-bar-top { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; }
        .uam-seg { display: inline-flex; padding: 4px; border-radius: 999px; background: var(--uam-chip); gap: 4px; }
        .uam-seg button { border: 0; background: transparent; color: var(--uam-muted); border-radius: 999px; padding: 7px 16px; font-size: 13.5px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 7px; }
        .uam-seg button.on { background: var(--uam-bg); color: var(--uam-text); box-shadow: 0 1px 3px rgba(15, 23, 42, .12); }
        .uam-live { display: inline-flex; align-items: center; gap: 8px; font-size: 13px; color: var(--uam-muted); }
        .uam-dot { width: 9px; height: 9px; border-radius: 999px; background: var(--uam-active); animation: uam-pulse 1.8s infinite; display: inline-block; }
        .uam-dot.paused { background: var(--uam-muted); animation: none; }
        @keyframes uam-pulse { 0% { box-shadow: 0 0 0 0 rgba(22, 163, 74, .45); } 70% { box-shadow: 0 0 0 8px rgba(22, 163, 74, 0); } 100% { box-shadow: 0 0 0 0 rgba(22, 163, 74, 0); } }
        .uam-btn { border: 1px solid var(--uam-border); background: var(--uam-bg); color: var(--uam-text); border-radius: 999px; padding: 6px 14px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .uam-btn:hover { border-color: var(--uam-accent); }
        .uam-btn.primary { background: var(--uam-accent); border-color: var(--uam-accent); color: #fff; }
        .uam-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; }
        .uam-kpi { padding: 14px 16px; border-radius: 16px; border: 1px solid var(--uam-border); background: var(--uam-bg); text-align: left; cursor: default; }
        button.uam-kpi { cursor: pointer; font: inherit; color: inherit; }
        button.uam-kpi:hover { border-color: var(--uam-accent); }
        .uam-kpi.on { box-shadow: 0 0 0 2px var(--uam-accent); }
        .uam-kpi-label { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; display: flex; align-items: center; gap: 6px; color: var(--uam-muted); }
        .uam-kpi-value { font-size: 26px; font-weight: 800; font-variant-numeric: tabular-nums; margin-top: 4px; line-height: 1.1; }
        .uam-kpi-hint { font-size: 12px; color: var(--uam-muted); margin-top: 2px; }
        .uam-filters { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .uam-chip { border: 1px solid var(--uam-border); background: var(--uam-bg); color: var(--uam-text); border-radius: 999px; padding: 5px 12px; font-size: 12.5px; font-weight: 600; cursor: pointer; }
        .uam-chip.on { background: var(--uam-accent); border-color: var(--uam-accent); color: #fff; }
        .uam-search, .uam-date { border: 1px solid var(--uam-border); background: var(--uam-bg); color: var(--uam-text); border-radius: 999px; padding: 7px 14px; font-size: 13px; }
        .uam-search { margin-left: auto; min-width: 260px; }
        .uam-dates { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .uam-table-wrap { overflow-x: auto; }
        .uam-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .uam-table th { text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--uam-muted); padding: 10px 12px; border-bottom: 1px solid var(--uam-border); background: var(--uam-soft); white-space: nowrap; }
        .uam-table th.sortable { cursor: pointer; user-select: none; }
        .uam-table th.sortable:hover { color: var(--uam-text); }
        .uam-table th.num, .uam-table td.num { text-align: right; font-variant-numeric: tabular-nums; }
        .uam-table td { padding: 11px 12px; border-bottom: 1px solid var(--uam-border); vertical-align: middle; }
        .uam-table tr.row { cursor: pointer; transition: background .15s; }
        .uam-table tr.row:hover, .uam-table tr.row.selected { background: var(--uam-soft); }
        .uam-table tr.row.offline > td { opacity: .6; }
        .uam-user { display: flex; align-items: center; gap: 10px; min-width: 210px; }
        .uam-avatar { width: 36px; height: 36px; border-radius: 999px; background: linear-gradient(135deg, #0ea5e9, #6366f1); color: #fff; display: grid; place-items: center; font-weight: 800; font-size: 12.5px; flex-shrink: 0; position: relative; }
        .uam-presence { position: absolute; right: -1px; bottom: -1px; width: 12px; height: 12px; border-radius: 999px; border: 2px solid var(--uam-bg); }
        .uam-name { font-weight: 700; }
        .uam-sub { font-size: 12px; color: var(--uam-muted); }
        .uam-type { display: inline-flex; border-radius: 999px; padding: 1px 8px; font-size: 10.5px; font-weight: 700; background: var(--uam-chip); color: var(--uam-muted); margin-left: 6px; vertical-align: 1px; }
        .uam-state { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; padding: 3px 10px; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .uam-state.active { background: rgba(22, 163, 74, .13); color: var(--uam-active); }
        .uam-state.idle { background: rgba(245, 158, 11, .16); color: #b45309; }
        .dark .uam-state.idle { color: #fbbf24; }
        .uam-state.background { background: rgba(148, 163, 184, .2); color: var(--uam-muted); }
        .uam-state.offline { background: rgba(100, 116, 139, .12); color: var(--uam-muted); }
        .uam-state i { width: 8px; height: 8px; border-radius: 999px; display: inline-block; }
        .uam-where { font-size: 12px; color: var(--uam-muted); max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .uam-dayline { width: 100%; min-width: 220px; height: 12px; border-radius: 6px; background: var(--uam-track); display: block; overflow: hidden; }
        .uam-dayline.big { height: 26px; border-radius: 8px; }
        .uam-ticks { display: flex; justify-content: space-between; font-size: 10.5px; color: var(--uam-muted); margin-top: 4px; font-variant-numeric: tabular-nums; }
        .uam-legend { display: flex; flex-wrap: wrap; gap: 12px; font-size: 12px; color: var(--uam-muted); }
        .uam-legend span { display: inline-flex; align-items: center; gap: 6px; }
        .uam-legend i { width: 10px; height: 10px; border-radius: 3px; display: inline-block; }
        .uam-usage { display: inline-flex; align-items: center; gap: 8px; justify-content: flex-end; font-weight: 800; font-variant-numeric: tabular-nums; }
        .uam-usage .track { width: 64px; height: 6px; border-radius: 999px; background: var(--uam-track); overflow: hidden; }
        .uam-usage .fill { height: 100%; border-radius: 999px; }
        .uam-usage.good { color: var(--uam-active); } .uam-usage.good .fill { background: var(--uam-active); }
        .uam-usage.fair { color: #d97706; } .uam-usage.fair .fill { background: #f59e0b; }
        .uam-usage.poor { color: #dc2626; } .uam-usage.poor .fill { background: #dc2626; }
        .uam-empty { padding: 48px 16px; text-align: center; color: var(--uam-muted); }
        .uam-notice { padding: 10px 14px; border-radius: 12px; font-size: 12.5px; border: 1px solid rgba(245, 158, 11, .4); background: rgba(245, 158, 11, .08); }
        .uam-drawer-backdrop { position: fixed; inset: 0; background: rgba(15, 23, 42, .35); z-index: 40; }
        .uam-drawer { position: fixed; top: 0; right: 0; bottom: 0; width: min(680px, 100vw); background: var(--uam-bg); border-left: 1px solid var(--uam-border); z-index: 41; overflow-y: auto; padding: 22px; display: flex; flex-direction: column; gap: 18px; box-shadow: -20px 0 40px rgba(15, 23, 42, .18); }
        .uam-section-title { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--uam-muted); margin-bottom: 8px; }
        .uam-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; }
        .uam-stat { padding: 10px 12px; border-radius: 12px; background: var(--uam-soft); border: 1px solid var(--uam-border); }
        .uam-stat dt { font-size: 11px; color: var(--uam-muted); }
        .uam-stat dd { margin: 2px 0 0; font-weight: 800; font-size: 16px; font-variant-numeric: tabular-nums; }
        .uam-daynav { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .uam-daynav strong { font-size: 15px; }
        .uam-timeline { list-style: none; margin: 0; padding: 0; }
        .uam-timeline li { display: grid; grid-template-columns: 78px 22px 1fr; gap: 6px; padding: 7px 0; border-bottom: 1px dashed var(--uam-border); font-size: 12.5px; align-items: start; }
        .uam-timeline time { color: var(--uam-muted); font-variant-numeric: tabular-nums; font-size: 12px; padding-top: 1px; }
        .uam-timeline li.idle { background: rgba(245, 158, 11, .07); }
        .uam-timeline li.active { background: rgba(22, 163, 74, .06); }
        .uam-heat { display: grid; grid-template-columns: 34px repeat(24, 1fr); gap: 2px; font-size: 10px; color: var(--uam-muted); }
        .uam-heat div.c { height: 16px; border-radius: 3px; }
        .uam-rank { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; font-size: 12.5px; }
        .uam-rank li { display: grid; grid-template-columns: 1fr auto; gap: 8px; align-items: center; }
        .uam-rank .bar { grid-column: 1 / -1; height: 5px; border-radius: 999px; background: var(--uam-track); overflow: hidden; }
        .uam-rank .bar span { display: block; height: 100%; background: var(--uam-accent); border-radius: 999px; }
        .uam-loading { opacity: .55; transition: opacity .15s; }
        @media (max-width: 768px) { .uam-search { margin-left: 0; width: 100%; min-width: 0; } }
    </style>

    @php
        $dayline = function (array $segments, bool $big = false, bool $showNow = false) use ($nowMinute): string {
            $rects = '';
            foreach ($segments as $segment) {
                $rects .= '<rect x="'.$segment['from'].'" y="0" width="'.($segment['to'] - $segment['from'] + 1).'" height="10" fill="'.$segment['state']->color().'"><title>'
                    .e($segment['state']->label().' · '.Clock::formatMinute($segment['from']).' – '.Clock::formatMinute($segment['to'] + 1)).'</title></rect>';
            }
            if ($showNow) {
                $rects .= '<rect x="'.$nowMinute.'" y="0" width="3" height="10" fill="#0ea5e9"><title>Ahora</title></rect>';
            }
            /** Guías de las 6, 12 y 18 h. */
            foreach ([360, 720, 1080] as $guide) {
                $rects .= '<rect x="'.$guide.'" y="0" width="1.5" height="10" fill="rgba(100,116,139,.35)"></rect>';
            }

            return '<svg class="uam-dayline'.($big ? ' big' : '').'" viewBox="0 0 1440 10" preserveAspectRatio="none" role="img" aria-label="Actividad del día">'.$rects.'</svg>';
        };
    @endphp

    <div class="uam" @if ($tab === 'live' && ! $paused) wire:poll.5s.visible @endif>
        <div class="uam-bar-top">
            <div class="uam-seg" role="tablist">
                <button type="button" role="tab" class="{{ $tab === 'live' ? 'on' : '' }}" wire:click="setTab('live')" aria-selected="{{ $tab === 'live' ? 'true' : 'false' }}">
                    <span class="uam-dot {{ $tab === 'live' && ! $paused ? '' : 'paused' }}"></span> En vivo
                </button>
                <button type="button" role="tab" class="{{ $tab === 'report' ? 'on' : '' }}" wire:click="setTab('report')" aria-selected="{{ $tab === 'report' ? 'true' : 'false' }}">
                    📊 Reporte por fechas
                </button>
            </div>

            @if ($tab === 'live')
                <div class="uam-live">
                    {{ $paused ? 'En pausa' : 'Se actualiza cada 5 s' }} · {{ $refreshedAt }}
                    <button type="button" class="uam-btn" wire:click="togglePause">{{ $paused ? '▶ Reanudar' : '⏸ Pausar' }}</button>
                </div>
            @else
                <div class="uam-dates">
                    @foreach (['today' => 'Hoy', 'yesterday' => 'Ayer', 'week' => 'Esta semana', 'last_week' => 'Semana pasada', 'month' => 'Este mes', 'last_month' => 'Mes pasado', 'last30' => 'Últimos 30 días'] as $key => $label)
                        <button type="button" class="uam-chip" wire:click="preset('{{ $key }}')">{{ $label }}</button>
                    @endforeach
                    <input type="date" class="uam-date" wire:model.live="from" max="{{ now()->toDateString() }}" aria-label="Desde">
                    <span class="uam-sub">a</span>
                    <input type="date" class="uam-date" wire:model.live="to" max="{{ now()->toDateString() }}" aria-label="Hasta">
                    <a href="{{ $this->exportUrl() }}" class="uam-btn primary" title="Descarga el reporte con los filtros actuales (abre en Excel)">⬇ Excel</a>
                </div>
            @endif
        </div>

        <div wire:loading.class="uam-loading" wire:target="setTab,preset,from,to,sortBy,filterType,filterState,search" style="display: flex; flex-direction: column; gap: 16px;">
            {{-- ===================== EN VIVO ===================== --}}
            @if ($tab === 'live' && $live !== null)
                @unless ($live['available'])
                    <div class="uam-notice">No se pudo leer la actividad en vivo (Redis o caché no responde). La navegación de los usuarios no se ve afectada; reintente en unos segundos.</div>
                @endunless

                <div class="uam-kpis">
                    <button type="button" class="uam-kpi {{ $liveState === 'active' ? 'on' : '' }}" wire:click="filterState('active')">
                        <div class="uam-kpi-label"><span class="uam-state active" style="padding: 0;"><i style="background: var(--uam-active);"></i></span> Activos ahora</div>
                        <div class="uam-kpi-value" style="color: var(--uam-active);">{{ $live['kpis']['active'] }}</div>
                        <div class="uam-kpi-hint">Usando el sistema</div>
                    </button>
                    <button type="button" class="uam-kpi {{ $liveState === 'idle' ? 'on' : '' }}" wire:click="filterState('idle')">
                        <div class="uam-kpi-label"><i style="width: 8px; height: 8px; border-radius: 999px; background: var(--uam-idle); display: inline-block;"></i> Inactivos</div>
                        <div class="uam-kpi-value" style="color: #d97706;">{{ $live['kpis']['idle'] }}</div>
                        <div class="uam-kpi-hint">Sistema abierto sin tocarlo (+{{ $idleMinutes }} min)</div>
                    </button>
                    <button type="button" class="uam-kpi {{ $liveState === 'background' ? 'on' : '' }}" wire:click="filterState('background')">
                        <div class="uam-kpi-label"><i style="width: 8px; height: 8px; border-radius: 999px; background: var(--uam-bgtab); display: inline-block;"></i> En otra pestaña</div>
                        <div class="uam-kpi-value">{{ $live['kpis']['background'] }}</div>
                        <div class="uam-kpi-hint">Sistema minimizado u oculto</div>
                    </button>
                    <button type="button" class="uam-kpi {{ $liveState === 'all' ? 'on' : '' }}" wire:click="filterState('all')">
                        <div class="uam-kpi-label">👥 Trabajaron hoy</div>
                        <div class="uam-kpi-value">{{ $live['kpis']['today'] }}</div>
                        <div class="uam-kpi-hint">{{ $live['kpis']['offline'] }} ya no están conectados</div>
                    </button>
                    <div class="uam-kpi">
                        <div class="uam-kpi-label">⚡ Uso real hoy</div>
                        <div class="uam-kpi-value"><span class="uam-usage {{ $usageClass($live['kpis']['usage']) }}" style="font-size: 26px;">{{ $live['kpis']['usage'] }}%</span></div>
                        <div class="uam-kpi-hint">{{ $fmt($live['kpis']['active_minutes']) }} activos de {{ $fmt($live['kpis']['online_minutes']) }} conectados</div>
                    </div>
                </div>

                <div class="uam-filters">
                    @foreach (['connected' => 'Conectados', 'active' => 'Activos', 'idle' => 'Inactivos', 'background' => 'Otra pestaña', 'offline' => 'Desconectados', 'all' => 'Todos los de hoy'] as $key => $label)
                        <button type="button" wire:key="state-{{ $key }}" class="uam-chip {{ $liveState === $key ? 'on' : '' }}" wire:click="filterState('{{ $key }}')">{{ $label }}</button>
                    @endforeach
                    <span class="uam-sub" style="margin: 0 4px;">|</span>
                    <button type="button" class="uam-chip {{ $userType === 'all' ? 'on' : '' }}" wire:click="filterType('all')">Todos</button>
                    @foreach ($types as $key => $label)
                        @continue($key === 'other')
                        <button type="button" wire:key="type-{{ $key }}" class="uam-chip {{ $userType === $key ? 'on' : '' }}" wire:click="filterType('{{ $key }}')">{{ $label }}</button>
                    @endforeach
                    <input type="search" class="uam-search" placeholder="Buscar persona, correo, departamento o pantalla…" wire:model.live.debounce.300ms="search">
                </div>

                <div class="uam-card uam-table-wrap">
                    @if ($live['rows'] === [])
                        <div class="uam-empty">
                            <x-filament::icon icon="heroicon-o-user-group" style="width: 32px; height: 32px; margin: 0 auto 8px;" />
                            {{ $search !== '' || $userType !== 'all' || $liveState !== 'connected' ? 'Nadie coincide con el filtro.' : 'No hay nadie conectado en este momento.' }}
                        </div>
                    @else
                        <table class="uam-table">
                            <thead>
                                <tr>
                                    <th>Persona</th>
                                    <th>Estado</th>
                                    <th>Dónde está</th>
                                    <th class="num">Activo hoy</th>
                                    <th class="num">Uso real</th>
                                    <th style="min-width: 260px;">Su día (0 h – 24 h)</th>
                                    <th>Conexión</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($live['rows'] as $row)
                                    @php $key = $stateKey($row['state']); @endphp
                                    <tr wire:key="live-{{ $row['user_id'] }}" class="row {{ $key === 'offline' ? 'offline' : '' }} {{ $selectedUserId === $row['user_id'] ? 'selected' : '' }}" wire:click="selectUser({{ $row['user_id'] }})">
                                        <td>
                                            <div class="uam-user">
                                                <div class="uam-avatar">{{ $initials($row['name']) }}<span class="uam-presence" style="background: {{ $row['state']->color() }};"></span></div>
                                                <div>
                                                    <div class="uam-name">{{ $row['name'] }}<span class="uam-type">{{ $row['type_label'] }}</span></div>
                                                    <div class="uam-sub">{{ $row['type_detail'] !== '' ? $row['type_detail'] : $row['email'] }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="uam-state {{ $key }}" title="{{ $row['state']->description() }}"><i style="background: {{ $row['state']->color() }};"></i>{{ $row['state']->label() }}</span>
                                            <div class="uam-sub" style="margin-top: 3px;">
                                                @if ($key === 'idle' && $row['last_interaction_at'])
                                                    Sin tocarlo {{ str_replace('hace ', 'desde hace ', $ago($row['last_interaction_at'])) }}
                                                @elseif ($key === 'offline')
                                                    Salió {{ $row['today']['last'] !== null ? 'a las '.Clock::formatMinute($row['today']['last']) : '' }}
                                                @elseif ($row['since'])
                                                    {{ ucfirst($ago($row['since'])) }}
                                                @endif
                                                @if ($row['tabs'] > 1) · {{ $row['tabs'] }} pestañas @endif
                                            </div>
                                        </td>
                                        <td>
                                            @if ($key !== 'offline')
                                                <div class="uam-where" title="{{ $row['page'] }}">{{ $row['page'] ?: '—' }}</div>
                                                <div class="uam-sub">{{ $row['panel'] }}</div>
                                            @else
                                                <span class="uam-sub">—</span>
                                            @endif
                                        </td>
                                        <td class="num"><strong>{{ $fmt($row['today']['active']) }}</strong><div class="uam-sub">de {{ $fmt($row['today']['online']) }}</div></td>
                                        <td class="num">
                                            <span class="uam-usage {{ $usageClass($row['today']['usage']) }}">
                                                <span class="track"><span class="fill" style="width: {{ $row['today']['usage'] }}%; display: block;"></span></span>{{ $row['today']['usage'] }}%
                                            </span>
                                        </td>
                                        <td>{!! $dayline($row['segments'], false, true) !!}</td>
                                        <td class="uam-sub" style="white-space: nowrap;">
                                            {{ Clock::formatMinute($row['today']['first']) }} → {{ $key === 'offline' ? Clock::formatMinute($row['today']['last']) : 'ahora' }}
                                            @if ($row['device'] !== '')<div>{{ $row['device'] }}{{ $row['city'] !== '' ? ' · '.$row['city'] : '' }}</div>@endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>

                <div class="uam-legend">
                    @foreach ([State::Active, State::Idle, State::Background] as $state)
                        <span title="{{ $state->description() }}"><i style="background: {{ $state->color() }};"></i>{{ $state->label() }}</span>
                    @endforeach
                    <span><i style="background: var(--uam-track); border: 1px solid var(--uam-border);"></i>Desconectado</span>
                    <span><i style="background: #0ea5e9;"></i>Ahora</span>
                </div>
            @endif

            {{-- ===================== REPORTE ===================== --}}
            @if ($tab === 'report' && $report !== null)
                <div class="uam-kpis">
                    <div class="uam-kpi"><div class="uam-kpi-label">👥 Personas con actividad</div><div class="uam-kpi-value">{{ $report['totals']['users'] }}</div><div class="uam-kpi-hint">{{ $rangeLabel }}</div></div>
                    <div class="uam-kpi"><div class="uam-kpi-label" style="color: var(--uam-active);">Tiempo activo</div><div class="uam-kpi-value">{{ $fmt($report['totals']['active']) }}</div><div class="uam-kpi-hint">Usando el sistema</div></div>
                    <div class="uam-kpi"><div class="uam-kpi-label" style="color: #d97706;">Abierto sin usar</div><div class="uam-kpi-value">{{ $fmt($report['totals']['idle']) }}</div><div class="uam-kpi-hint">Inactivo con el sistema a la vista</div></div>
                    <div class="uam-kpi"><div class="uam-kpi-label">En otra pestaña</div><div class="uam-kpi-value">{{ $fmt($report['totals']['background']) }}</div><div class="uam-kpi-hint">Sistema minimizado u oculto</div></div>
                    <div class="uam-kpi"><div class="uam-kpi-label">⚡ Uso real</div><div class="uam-kpi-value"><span class="uam-usage {{ $usageClass($report['totals']['usage']) }}" style="font-size: 26px;">{{ $report['totals']['usage'] }}%</span></div><div class="uam-kpi-hint">{{ number_format($report['totals']['actions'], 0, ',', '.') }} acciones · {{ number_format($report['totals']['pages'], 0, ',', '.') }} pantallas</div></div>
                </div>

                <div class="uam-filters">
                    <button type="button" class="uam-chip {{ $userType === 'all' ? 'on' : '' }}" wire:click="filterType('all')">Todos</button>
                    @foreach ($types as $key => $label)
                        @continue($key === 'other')
                        <button type="button" wire:key="rtype-{{ $key }}" class="uam-chip {{ $userType === $key ? 'on' : '' }}" wire:click="filterType('{{ $key }}')">{{ $label }}</button>
                    @endforeach
                    <input type="search" class="uam-search" placeholder="Buscar persona, correo o departamento…" wire:model.live.debounce.300ms="search">
                </div>

                <div class="uam-card uam-table-wrap">
                    @if ($report['rows'] === [])
                        <div class="uam-empty">
                            <x-filament::icon icon="heroicon-o-chart-bar" style="width: 32px; height: 32px; margin: 0 auto 8px;" />
                            {{ $search !== '' || $userType !== 'all' ? 'Nadie coincide con el filtro en este rango.' : 'No hay actividad registrada en este rango de fechas.' }}
                        </div>
                    @else
                        @php
                            $th = function (string $column, string $label, bool $num = true) use ($sort, $direction): string {
                                $arrow = $sort === $column ? ($direction === 'asc' ? ' ▲' : ' ▼') : '';

                                return '<th class="sortable'.($num ? ' num' : '').'" wire:click="sortBy(\''.$column.'\')">'.e($label).$arrow.'</th>';
                            };
                        @endphp
                        <table class="uam-table">
                            <thead>
                                <tr>
                                    {!! $th('name', 'Persona', false) !!}
                                    {!! $th('days', 'Días') !!}
                                    {!! $th('online', 'Conectado') !!}
                                    {!! $th('active', 'Activo') !!}
                                    {!! $th('idle', 'Abierto sin usar') !!}
                                    {!! $th('background', 'Otra pestaña') !!}
                                    {!! $th('usage', 'Uso real') !!}
                                    {!! $th('avg_active', 'Activo por día') !!}
                                    {!! $th('actions', 'Acciones') !!}
                                    {!! $th('avg_first', 'Llega') !!}
                                    {!! $th('avg_last', 'Se va') !!}
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($report['rows'] as $row)
                                    <tr wire:key="rep-{{ $row['user_id'] }}" class="row {{ $selectedUserId === $row['user_id'] ? 'selected' : '' }}" wire:click="selectUser({{ $row['user_id'] }}, '{{ $row['last_day'] }}')">
                                        <td>
                                            <div class="uam-user">
                                                <div class="uam-avatar">{{ $initials($row['name']) }}</div>
                                                <div>
                                                    <div class="uam-name">{{ $row['name'] }}<span class="uam-type">{{ $row['type_label'] }}</span></div>
                                                    <div class="uam-sub">{{ $row['type_detail'] !== '' ? $row['type_detail'] : $row['email'] }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="num">{{ $row['days'] }}</td>
                                        <td class="num">{{ $fmt($row['online']) }}</td>
                                        <td class="num"><strong style="color: var(--uam-active);">{{ $fmt($row['active']) }}</strong></td>
                                        <td class="num" style="color: #d97706;">{{ $fmt($row['idle']) }}</td>
                                        <td class="num uam-sub">{{ $fmt($row['background']) }}</td>
                                        <td class="num">
                                            <span class="uam-usage {{ $usageClass($row['usage']) }}">
                                                <span class="track"><span class="fill" style="width: {{ $row['usage'] }}%; display: block;"></span></span>{{ $row['usage'] }}%
                                            </span>
                                        </td>
                                        <td class="num">{{ $fmt($row['avg_active']) }}</td>
                                        <td class="num">{{ number_format($row['actions'], 0, ',', '.') }}</td>
                                        <td class="num uam-sub">{{ Clock::formatMinute($row['avg_first']) }}</td>
                                        <td class="num uam-sub">{{ Clock::formatMinute($row['avg_last']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
                <p class="uam-sub">«Uso real» = tiempo activo ÷ tiempo conectado. «Llega» y «Se va» son el promedio de la primera y la última conexión de cada día. El resumen de hoy se actualiza cada minuto.</p>
            @endif
        </div>

        {{-- ===================== DETALLE DE UNA PERSONA ===================== --}}
        @if ($detail !== null)
            @php
                $day = $detail['day'];
                $summary = $day['summary'];
                $dateObj = \Carbon\CarbonImmutable::parse($day['date']);
                $dateLabel = $day['is_today'] ? 'Hoy' : ($dateObj->isSameDay(now()->subDay()) ? 'Ayer' : ucfirst($dateObj->translatedFormat('l d \d\e F Y')));
                $liveRow = $live !== null ? collect($live['rows'])->firstWhere('user_id', $detail['user']->id) : null;
            @endphp
            <div class="uam-drawer-backdrop" wire:click="closeDetail"></div>
            <aside class="uam-drawer" role="dialog" aria-label="Actividad de {{ $detail['user']->name }}" x-data x-on:keydown.escape.window="$wire.closeDetail()">
                <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;">
                    <div class="uam-user">
                        <div class="uam-avatar" style="width: 46px; height: 46px; font-size: 15px;">{{ $initials((string) $detail['user']->name) }}</div>
                        <div>
                            <div class="uam-name" style="font-size: 17px;">{{ $detail['user']->name }}<span class="uam-type">{{ $detail['profile']['label'] }}</span></div>
                            <div class="uam-sub">{{ $detail['profile']['detail'] !== '' ? $detail['profile']['detail'].' · ' : '' }}{{ $detail['user']->email }}</div>
                            @if ($liveRow)
                                @php $liveKey = $stateKey($liveRow['state']); @endphp
                                <span class="uam-state {{ $liveKey }}" style="margin-top: 6px;"><i style="background: {{ $liveRow['state']->color() }};"></i>{{ $liveRow['state']->label() }} {{ $liveKey !== 'offline' && $liveRow['page'] ? '· '.$liveRow['page'] : '' }}</span>
                            @endif
                        </div>
                    </div>
                    <button type="button" class="uam-btn" wire:click="closeDetail" aria-label="Cerrar">✕</button>
                </div>

                <div class="uam-daynav">
                    <button type="button" class="uam-btn" wire:click="shiftDay(-1)">‹ Día anterior</button>
                    <strong>{{ $dateLabel }}</strong>
                    <button type="button" class="uam-btn" wire:click="shiftDay(1)" @disabled($detail['is_latest_day'])>Día siguiente ›</button>
                </div>

                <dl class="uam-grid">
                    <div class="uam-stat"><dt>Activo</dt><dd style="color: var(--uam-active);">{{ $fmt((int) $summary['active']) }}</dd></div>
                    <div class="uam-stat"><dt>Abierto sin usar</dt><dd style="color: #d97706;">{{ $fmt((int) $summary['idle']) }}</dd></div>
                    <div class="uam-stat"><dt>En otra pestaña</dt><dd>{{ $fmt((int) $summary['background']) }}</dd></div>
                    <div class="uam-stat"><dt>Uso real</dt><dd><span class="uam-usage {{ $usageClass((int) $summary['usage']) }}" style="font-size: 16px;">{{ $summary['usage'] }}%</span></dd></div>
                    <div class="uam-stat"><dt>Primera / última conexión</dt><dd style="font-size: 13.5px;">{{ Clock::formatMinute($summary['first']) }} – {{ Clock::formatMinute($summary['last']) }}</dd></div>
                    <div class="uam-stat"><dt>Acciones · pantallas</dt><dd>{{ $summary['actions'] }} · {{ $summary['pages'] }}</dd></div>
                </dl>

                <div>
                    <div class="uam-section-title">Su día minuto a minuto</div>
                    @if ($day['segments'] === [] && ! $day['detail_available'])
                        <div class="uam-notice">El detalle minuto a minuto se guarda 90 días; para esta fecha quedan solo las cifras del resumen.</div>
                    @elseif ($day['segments'] === [])
                        <div class="uam-sub">Sin actividad registrada este día.</div>
                    @else
                        {!! $dayline($day['segments'], true, $day['is_today']) !!}
                        <div class="uam-ticks">@foreach ([0, 3, 6, 9, 12, 15, 18, 21, 24] as $hour)<span>{{ $hour === 0 || $hour === 24 ? '12a' : ($hour < 12 ? $hour.'a' : ($hour === 12 ? '12p' : ($hour - 12).'p')) }}</span>@endforeach</div>
                        <div class="uam-legend" style="margin-top: 8px;">
                            @foreach ([State::Active, State::Idle, State::Background] as $state)
                                <span><i style="background: {{ $state->color() }};"></i>{{ $state->label() }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>

                @if ($detail['person'] !== null)
                    @php $person = $detail['person']; @endphp
                    <div>
                        <div class="uam-section-title">Días del rango · {{ $rangeLabel }}</div>
                        <table class="uam-table">
                            <tbody>
                                @foreach ($person['days'] as $personDay)
                                    <tr wire:key="pday-{{ $personDay['date'] }}" class="row {{ $personDay['date'] === $day['date'] ? 'selected' : '' }}" wire:click="selectUser({{ $detail['user']->id }}, '{{ $personDay['date'] }}')">
                                        <td style="white-space: nowrap; width: 110px;"><strong>{{ ucfirst(\Carbon\CarbonImmutable::parse($personDay['date'])->translatedFormat('D d M')) }}</strong></td>
                                        <td>{!! $dayline($personDay['segments']) !!}</td>
                                        <td class="num" style="width: 90px;"><strong style="color: var(--uam-active);">{{ $fmt($personDay['active']) }}</strong></td>
                                        <td class="num" style="width: 70px;"><span class="uam-usage {{ $usageClass($personDay['usage']) }}">{{ $personDay['usage'] }}%</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div>
                        <div class="uam-section-title">¿A qué horas usa el sistema? (minutos activos)</div>
                        <div class="uam-heat">
                            <span></span>
                            @for ($hour = 0; $hour < 24; $hour++)<span style="text-align: center;">{{ $hour % 3 === 0 ? $hour : '' }}</span>@endfor
                            @foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $weekday => $weekdayLabel)
                                <span>{{ $weekdayLabel }}</span>
                                @foreach ($person['heatmap'][$weekday] as $hour => $minutes)
                                    <div class="c" title="{{ $weekdayLabel }} {{ $hour }}:00 · {{ $fmt($minutes) }} activo" style="background: {{ $minutes > 0 ? 'rgba(22,163,74,'.round(0.15 + 0.85 * $minutes / $person['heat_max'], 2).')' : 'var(--uam-track)' }};"></div>
                                @endforeach
                            @endforeach
                        </div>
                    </div>

                    @if ($person['top_pages'] !== [] || $person['top_panels'] !== [])
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 18px;">
                            @foreach (['Pantallas que más abre' => $person['top_pages'], 'Paneles donde trabaja' => $person['top_panels']] as $title => $ranking)
                                @continue($ranking === [])
                                @php $rankMax = max(array_column($ranking, 'total')); @endphp
                                <div>
                                    <div class="uam-section-title">{{ $title }}</div>
                                    <ul class="uam-rank">
                                        @foreach ($ranking as $item)
                                            <li><span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $item['label'] }}">{{ $item['label'] }}</span><strong>{{ $item['total'] }}</strong><span class="bar"><span style="width: {{ round($item['total'] * 100 / $rankMax) }}%;"></span></span></li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif

                <div>
                    <div class="uam-section-title">Recorrido paso a paso · {{ count($day['events']) }} {{ count($day['events']) === 1 ? 'evento' : 'eventos' }}</div>
                    @if ($day['events'] === [])
                        <div class="uam-sub">{{ $day['detail_available'] ? 'Sin pasos registrados este día.' : 'El recorrido se guarda 90 días.' }}</div>
                    @else
                        <ul class="uam-timeline">
                            @foreach ($day['events'] as $event)
                                <li wire:key="ev-{{ $event['key'] }}" class="{{ $event['type'] }}">
                                    <time>{{ \Carbon\CarbonImmutable::parse($event['at'])->format('h:i:s a') }}</time>
                                    <span aria-hidden="true">{{ $eventIcon($event['type']) }}</span>
                                    <div>
                                        <div style="font-weight: 600;">{{ $event['label'] }}</div>
                                        @if ($event['page'] || $event['panel'])
                                            <div class="uam-sub">{{ collect([$event['panel'], $event['page']])->filter()->implode(' · ') }}</div>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </aside>
        @endif
    </div>
</x-filament-panels::page>
