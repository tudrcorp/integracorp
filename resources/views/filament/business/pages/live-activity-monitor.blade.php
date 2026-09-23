<x-filament-panels::page>
    <style>
        .lam { --lam-bg: #ffffff; --lam-soft: #f8fafc; --lam-border: #e5e7eb; --lam-text: #0f172a; --lam-muted: #64748b; --lam-accent: #0ea5e9;
               --lam-good: #16a34a; --lam-fair: #d97706; --lam-poor: #dc2626; --lam-chip: #f1f5f9; color: var(--lam-text); display: flex; flex-direction: column; gap: 16px; }
        .dark .lam { --lam-bg: #0b1220; --lam-soft: #111a2e; --lam-border: #1f2a44; --lam-text: #e2e8f0; --lam-muted: #94a3b8; --lam-chip: #16213a; }
        .lam-card { background: var(--lam-bg); border: 1px solid var(--lam-border); border-radius: 16px; }
        .lam-top { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; }
        .lam-live { display: inline-flex; align-items: center; gap: 8px; font-size: 13px; color: var(--lam-muted); }
        .lam-dot { width: 9px; height: 9px; border-radius: 999px; background: var(--lam-good); box-shadow: 0 0 0 0 rgba(22, 163, 74, .5); animation: lam-pulse 1.8s infinite; }
        .lam-dot.paused { background: var(--lam-muted); animation: none; }
        @keyframes lam-pulse { 0% { box-shadow: 0 0 0 0 rgba(22, 163, 74, .45); } 70% { box-shadow: 0 0 0 8px rgba(22, 163, 74, 0); } 100% { box-shadow: 0 0 0 0 rgba(22, 163, 74, 0); } }
        .lam-btn { border: 1px solid var(--lam-border); background: var(--lam-bg); color: var(--lam-text); border-radius: 999px; padding: 6px 14px; font-size: 13px; font-weight: 600; cursor: pointer; }
        .lam-btn:hover { border-color: var(--lam-accent); }
        .lam-badge { display: inline-flex; align-items: center; gap: 4px; border-radius: 999px; padding: 2px 9px; font-size: 11px; font-weight: 600; background: var(--lam-chip); color: var(--lam-muted); white-space: nowrap; }
        .lam-badge.accent { background: rgba(14, 165, 233, .12); color: #0284c7; }
        .lam-badge.pwa { background: rgba(124, 58, 237, .12); color: #7c3aed; }
        .lam-badge.warn { background: rgba(217, 119, 6, .14); color: var(--lam-fair); }
        .lam-filters { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .lam-chip { border: 1px solid var(--lam-border); background: var(--lam-bg); color: var(--lam-text); border-radius: 999px; padding: 5px 12px; font-size: 12.5px; font-weight: 600; cursor: pointer; }
        .lam-chip.on { background: var(--lam-accent); border-color: var(--lam-accent); color: #fff; }
        .lam-chip span { opacity: .7; margin-left: 4px; }
        .lam-search { margin-left: auto; min-width: 240px; border: 1px solid var(--lam-border); background: var(--lam-bg); color: var(--lam-text); border-radius: 999px; padding: 7px 14px; font-size: 13px; }
        .lam-table-wrap { overflow-x: auto; }
        .lam-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .lam-table th { text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--lam-muted); padding: 10px 14px; border-bottom: 1px solid var(--lam-border); background: var(--lam-soft); }
        .lam-table td { padding: 11px 14px; border-bottom: 1px solid var(--lam-border); vertical-align: middle; }
        .lam-table tr.lam-row { cursor: pointer; transition: background .15s; }
        .lam-table tr.lam-row:hover, .lam-table tr.lam-row.selected { background: var(--lam-soft); }
        .lam-table tr.lam-row.idle > td { opacity: .5; }
        .lam-table tr.lam-row.idle:hover > td { opacity: .85; }
        .lam-nosignal { display: inline-flex; align-items: center; gap: 5px; border-radius: 999px; padding: 2px 9px; font-size: 11px; font-weight: 600; background: rgba(100, 116, 139, .16); color: var(--lam-muted); white-space: nowrap; cursor: help; }
        .lam-user { display: flex; align-items: center; gap: 10px; min-width: 190px; }
        .lam-avatar { width: 34px; height: 34px; border-radius: 999px; background: linear-gradient(135deg, #0ea5e9, #6366f1); color: #fff; display: grid; place-items: center; font-weight: 800; font-size: 12px; flex-shrink: 0; position: relative; }
        .lam-presence { position: absolute; right: -1px; bottom: -1px; width: 11px; height: 11px; border-radius: 999px; border: 2px solid var(--lam-bg); background: var(--lam-good); }
        .lam-presence.bg { background: var(--lam-fair); }
        .lam-name { font-weight: 700; }
        .lam-sub { font-size: 12px; color: var(--lam-muted); }
        .lam-action { font-size: 12px; color: var(--lam-accent); margin-top: 3px; max-width: 360px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .lam-metric { display: inline-flex; align-items: center; gap: 6px; font-weight: 700; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .lam-level { width: 8px; height: 8px; border-radius: 999px; background: var(--lam-muted); }
        .lam-level.good { background: var(--lam-good); } .lam-level.fair { background: var(--lam-fair); } .lam-level.poor { background: var(--lam-poor); }
        .lam-empty { padding: 48px 16px; text-align: center; color: var(--lam-muted); }
        .lam-drawer-backdrop { position: fixed; inset: 0; background: rgba(15, 23, 42, .35); z-index: 40; }
        .lam-drawer { position: fixed; top: 0; right: 0; bottom: 0; width: min(520px, 100vw); background: var(--lam-bg); border-left: 1px solid var(--lam-border); z-index: 41; overflow-y: auto; padding: 22px; display: flex; flex-direction: column; gap: 18px; box-shadow: -20px 0 40px rgba(15, 23, 42, .18); }
        .lam-section-title { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--lam-muted); margin-bottom: 8px; }
        .lam-grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 16px; font-size: 13px; }
        .lam-grid2 dt { font-size: 11.5px; color: var(--lam-muted); }
        .lam-grid2 dd { margin: 1px 0 0; font-weight: 600; word-break: break-word; }
        .lam-timeline { list-style: none; margin: 0; padding: 0; border-left: 2px solid var(--lam-border); }
        .lam-timeline li { position: relative; padding: 0 0 12px 14px; font-size: 12.5px; }
        .lam-timeline li::before { content: ''; position: absolute; left: -6px; top: 4px; width: 10px; height: 10px; border-radius: 999px; background: var(--lam-accent); border: 2px solid var(--lam-bg); }
        .lam-timeline li.action::before { background: #7c3aed; } .lam-timeline li.download::before { background: var(--lam-good); } .lam-timeline li.visibility::before { background: var(--lam-muted); }
        .lam-ua { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px; color: var(--lam-muted); word-break: break-all; }
        @media (max-width: 768px) { .lam-search { margin-left: 0; width: 100%; } }
    </style>

    <div class="lam" @unless ($paused) wire:poll.3s.visible @endunless>
        <div class="lam-top">
            <div class="lam-live">
                <span class="lam-dot {{ $paused ? 'paused' : '' }}"></span>
                {{ $paused ? 'En pausa' : 'En vivo' }} · actualizado {{ $refreshedAt }}
                <span class="lam-badge accent">{{ $health['store'] }}</span>
                @unless ($health['geoip'])
                    <span class="lam-badge warn" title="Instálela con: php artisan live-presence:geoip-update. Mientras tanto, las IPs públicas se ven sin ciudad.">Sin base de ubicación</span>
                @endunless
            </div>
            <button type="button" class="lam-btn" wire:click="togglePause">
                {{ $paused ? '▶ Reanudar' : '⏸ Pausar' }}
            </button>
        </div>

        @include('live-presence.partials.security-panel', ['security' => $security, 'kpis' => $kpis, 'health' => $health, 'advice' => $advice, 'tv' => false, 'actions' => true])

        <div class="lam-filters">
            <button type="button" class="lam-chip {{ $panelFilter === 'all' ? 'on' : '' }}" wire:click="filterPanel('all')">Todos<span>{{ $kpis['listed'] }}</span></button>
            @foreach ($kpis['panels'] as $panel => $count)
                <button type="button" wire:key="chip-{{ $panel }}" class="lam-chip {{ $panelFilter === $panel ? 'on' : '' }}" wire:click="filterPanel('{{ $panel }}')">
                    {{ $panelLabels[$panel] ?? $panel }}<span>{{ $count }}</span>
                </button>
            @endforeach
            <input type="search" class="lam-search" placeholder="Buscar usuario, IP, ciudad, página o acción…" wire:model.live.debounce.300ms="search">
        </div>

        <div class="lam-card lam-table-wrap">
            @if ($sessions === [])
                <div class="lam-empty">
                    <x-filament::icon icon="heroicon-o-signal-slash" style="width: 32px; height: 32px; margin: 0 auto 8px;" />
                    {{ $search !== '' || $panelFilter !== 'all' ? 'Nadie coincide con el filtro.' : 'No hay usuarios conectados en este momento.' }}
                </div>
            @else
                <table class="lam-table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Dónde y qué hace</th>
                            <th>Conexión</th>
                            <th>Dispositivo</th>
                            <th>Latencia</th>
                            <th>Servidor</th>
                            <th>Actividad</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sessions as $session)
                            <tr wire:key="session-{{ $session['session_key'] }}"
                                class="lam-row {{ $selectedSession === $session['session_key'] ? 'selected' : '' }} {{ $session['idle'] ? 'idle' : '' }}"
                                wire:click="selectSession('{{ $session['session_key'] }}')">
                                <td>
                                    <div class="lam-user">
                                        <div class="lam-avatar">{{ $session['initials'] }}<span class="lam-presence {{ $session['visible'] ? '' : 'bg' }}"></span></div>
                                        <div>
                                            <div class="lam-name">{{ $session['user_name'] }}
                                                @if (in_array($session['user_id'], $blockedIds, true))
                                                    <span class="lam-badge" style="background: rgba(220, 38, 38, .14); color: #dc2626;">Bloqueado</span>
                                                @endif
                                            </div>
                                            <div class="lam-sub">{{ $session['user_email'] }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="lam-badge {{ $session['is_pwa'] ? 'pwa' : 'accent' }}">{{ $session['panel_label'] }}</span>
                                    <div style="margin-top: 4px; font-weight: 600;">{{ $session['page_label'] ?: '—' }}</div>
                                    @if ($session['last_action'] !== '')
                                        <div class="lam-action" title="{{ $session['last_action'] }}">⚡ {{ $session['last_action'] }} · {{ $session['last_action_ago'] }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div>{{ $session['flag'] }} {{ $session['location'] }}</div>
                                    <div class="lam-sub">{{ $session['ip'] }}</div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <x-filament::icon :icon="match ($session['device']) { 'mobile' => 'heroicon-o-device-phone-mobile', 'tablet' => 'heroicon-o-device-tablet', default => 'heroicon-o-computer-desktop' }" style="width: 16px; height: 16px;" />
                                        {{ $session['browser'] }}
                                    </div>
                                    <div class="lam-sub">{{ $session['os'] }}
                                        @if ($session['pwa_installed'])
                                            <span class="lam-badge pwa">PWA instalada</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @if ($session['has_heartbeat'])
                                        <span class="lam-metric"><span class="lam-level {{ $session['rtt_level'] }}"></span>{{ $session['rtt_ms'] !== null ? $session['rtt_ms'].' ms' : 'midiendo…' }}</span>
                                    @else
                                        <span class="lam-nosignal" title="El navegador no está enviando su latido: suele ser una pestaña abierta antes de la última actualización (se corrige recargando), un bloqueador de publicidad o antivirus, o una conexión inestable. Solo se le ve cuando hace clic o navega.">Sin señal del navegador</span>
                                    @endif
                                    <div class="lam-sub">{{ $session['conn_type'] !== '' ? strtoupper($session['conn_type']) : '' }}</div>
                                </td>
                                <td>
                                    <span class="lam-metric"><span class="lam-level {{ $session['server_level'] }}"></span>{{ $session['server_ms'] !== null ? $session['server_ms'].' ms' : '—' }}</span>
                                    <div class="lam-sub">{{ $session['queries'] !== null ? $session['queries'].' consultas' : '' }}</div>
                                </td>
                                <td>
                                    @if ($session['idle'])
                                        <span class="lam-badge warn">Inactivo · {{ $session['last_seen_ago'] }}</span>
                                        <div class="lam-sub" style="margin-top: 3px;">sin señales desde hace un rato · {{ $session['session_duration'] }}</div>
                                    @else
                                        <div style="font-weight: 600;">{{ $session['last_seen_ago'] }}</div>
                                        <div class="lam-sub">{{ $session['visible'] ? 'Pestaña activa' : 'En segundo plano' }} · {{ $session['session_duration'] }}</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="lam-card" style="padding: 14px 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <strong>Lista negra</strong>
                <span class="lam-sub">{{ count($blocks) }} {{ count($blocks) === 1 ? 'usuario bloqueado' : 'usuarios bloqueados' }}</span>
            </div>
            @if ($blocks === [])
                <div class="lam-sub">Nadie bloqueado. Para bloquear, abra el detalle de un usuario conectado.</div>
            @else
                <table class="lam-table">
                    <thead><tr><th>Usuario</th><th>Motivo</th><th>Vence</th><th>Bloqueado por</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($blocks as $block)
                            <tr wire:key="block-{{ $block->id }}">
                                <td><div class="lam-name">{{ $block->user_name }}</div><div class="lam-sub">{{ $block->user_email }} · ID {{ $block->user_id }}</div></td>
                                <td style="max-width: 380px;">{{ $block->reason }}</td>
                                <td>{{ $block->expires_at ? $block->expires_at->format('d/m/Y H:i') : 'Hasta levantarlo' }}</td>
                                <td><div>{{ $block->blocked_by_name }}</div><div class="lam-sub">{{ $block->created_at?->format('d/m/Y H:i') }}</div></td>
                                <td style="text-align: right;">{{ ($this->liftBlockAction)(['blockId' => $block->id]) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="lam-card" style="padding: 14px 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <strong>IPs en lista negra</strong>
                <span class="lam-sub">{{ count($ipBlocks) }} {{ count($ipBlocks) === 1 ? 'IP bloqueada' : 'IPs bloqueadas' }} · {{ array_sum($security['series']['blocked'] ?? []) }} peticiones rechazadas en 30 min</span>
            </div>
            @if ($ipBlocks === [])
                <div class="lam-sub">Ninguna IP bloqueada. Para bloquear, use «Lista negra» en la tabla de IPs sospechosas.</div>
            @else
                <table class="lam-table">
                    <thead><tr><th>IP</th><th>Motivo</th><th>Vence</th><th>Bloqueada por</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($ipBlocks as $ipBlock)
                            <tr wire:key="ip-block-{{ $ipBlock->id }}">
                                <td>
                                    <div class="lam-name" style="font-family: ui-monospace, SFMono-Regular, Menlo, monospace;">{{ $ipBlock->ip }}</div>
                                    <div class="lam-sub">{{ \App\Support\LivePresence\IpThreatAssessment::LABELS[$ipBlock->verdict] ?? 'Sin veredicto' }}@if (! empty($ipBlock->evidence['location'])) · {{ $ipBlock->evidence['location'] }}@endif</div>
                                </td>
                                <td style="max-width: 380px;">{{ $ipBlock->reason }}</td>
                                <td>{{ $ipBlock->expires_at ? $ipBlock->expires_at->format('d/m/Y H:i') : 'Hasta levantarla' }}</td>
                                <td><div>{{ $ipBlock->blocked_by_name }}</div><div class="lam-sub">{{ $ipBlock->created_at?->format('d/m/Y H:i') }}</div></td>
                                <td style="text-align: right;">{{ ($this->liftIpBlockAction)(['blockId' => $ipBlock->id]) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if ($dismissedIps !== [])
                <div style="margin-top: 12px; padding-top: 10px; border-top: 1px solid var(--lam-border);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <strong style="font-size: 13px;">Marcadas como legítimas</strong>
                        <span class="lam-sub">no se listan como sospechosas mientras dure la marca</span>
                    </div>
                    <table class="lam-table">
                        <thead><tr><th>IP</th><th>Nota</th><th>Hasta</th><th>Marcada por</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($dismissedIps as $dismissedIp => $dismissal)
                                <tr wire:key="dismissed-{{ md5($dismissedIp) }}">
                                    <td class="lam-name" style="font-family: ui-monospace, SFMono-Regular, Menlo, monospace;">{{ $dismissedIp }}</td>
                                    <td>{{ $dismissal['note'] !== '' ? $dismissal['note'] : '—' }}</td>
                                    <td>{{ date('d/m/Y H:i', (int) $dismissal['until']) }}</td>
                                    <td><div>{{ $dismissal['by'] }}</div><div class="lam-sub">{{ date('d/m/Y H:i', (int) $dismissal['at']) }}</div></td>
                                    <td style="text-align: right;">{{ ($this->undismissIpAction)(['ip' => $dismissedIp]) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if ($selected)
            @php
                $levelColor = fn (?string $level): string => ['good' => 'var(--lam-good)', 'fair' => 'var(--lam-fair)', 'poor' => 'var(--lam-poor)'][$level] ?? 'var(--lam-muted)';
                $msLevel = fn (?int $ms, int $good, int $fair): string => $ms === null ? 'unknown' : ($ms < $good ? 'good' : ($ms < $fair ? 'fair' : 'poor'));
                $deviceIcon = match ($selected['device']) { 'mobile' => 'heroicon-o-device-phone-mobile', 'tablet' => 'heroicon-o-device-tablet', default => 'heroicon-o-computer-desktop' };
                $eventIcon = ['page' => 'heroicon-m-document-text', 'action' => 'heroicon-m-bolt', 'download' => 'heroicon-m-arrow-down-tray', 'visibility' => 'heroicon-m-eye-slash'];
                $isBlocked = in_array($selected['user_id'], $blockedIds, true);
                $tiles = [
                    ['label' => 'Latencia', 'value' => $selected['has_heartbeat'] ? $selected['rtt_ms'] : null, 'suffix' => 'ms', 'level' => $selected['has_heartbeat'] ? $selected['rtt_level'] : 'unknown', 'hint' => $selected['has_heartbeat'] ? 'ida y vuelta' : 'sin señal del navegador'],
                    ['label' => 'Servidor', 'value' => $selected['server_ms'], 'suffix' => 'ms', 'level' => $selected['server_level'], 'hint' => 'última respuesta'],
                    ['label' => 'Carga de página', 'value' => $selected['load_ms'], 'suffix' => 'ms', 'level' => $msLevel($selected['load_ms'], 2500, 5000), 'hint' => 'en el navegador'],
                    ['label' => 'Primer byte', 'value' => $selected['ttfb_ms'], 'suffix' => 'ms', 'level' => $msLevel($selected['ttfb_ms'], 800, 1800), 'hint' => 'TTFB'],
                    ['label' => 'Consultas', 'value' => $selected['queries'], 'suffix' => '', 'level' => $msLevel($selected['queries'], 40, 120), 'hint' => ($selected['memory_mb'] !== null ? $selected['memory_mb'].' MB de memoria' : 'última petición')],
                    ['label' => 'Peticiones', 'value' => $selected['requests'], 'suffix' => '', 'level' => 'unknown', 'hint' => 'en '.$selected['session_duration']],
                ];
            @endphp

            <style>
                .lam-drawer { padding: 0; gap: 0; width: min(560px, 100vw); }
                .lam-dh { position: sticky; top: 0; z-index: 2; background: var(--lam-bg); border-bottom: 1px solid var(--lam-border); padding: 18px 20px 14px; }
                .lam-dh-row { display: flex; align-items: flex-start; gap: 14px; }
                .lam-dh-info { min-width: 0; flex: 1; }
                .lam-dh-name { font-size: 18px; font-weight: 800; line-height: 1.2; }
                .lam-dh-mail { font-size: 12.5px; color: var(--lam-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
                .lam-dh-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
                .lam-icon-btn { width: 34px; height: 34px; border-radius: 999px; border: 1px solid var(--lam-border); background: var(--lam-bg); color: var(--lam-muted); display: grid; place-items: center; cursor: pointer; flex-shrink: 0; }
                .lam-icon-btn:hover { color: var(--lam-text); border-color: var(--lam-accent); }
                .lam-dh-actions { display: flex; gap: 8px; margin-top: 12px; }
                .lam-db { padding: 16px 20px 28px; display: flex; flex-direction: column; gap: 16px; }
                .lam-now { border: 1px solid var(--lam-border); border-left: 4px solid var(--lam-accent); border-radius: 14px; padding: 14px 16px; background: var(--lam-soft); }
                .lam-now-page { font-size: 16px; font-weight: 800; margin-top: 2px; }
                .lam-now-title { font-size: 12.5px; color: var(--lam-muted); margin-top: 2px; }
                .lam-now-action { display: flex; gap: 8px; align-items: flex-start; margin-top: 10px; padding-top: 10px; border-top: 1px dashed var(--lam-border); font-size: 13px; }
                .lam-tiles { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
                .lam-tile { border: 1px solid var(--lam-border); border-radius: 12px; padding: 10px 12px; background: var(--lam-bg); }
                .lam-tile-label { font-size: 11px; font-weight: 700; color: var(--lam-muted); text-transform: uppercase; letter-spacing: .04em; display: flex; align-items: center; gap: 6px; }
                .lam-tile-value { font-size: 20px; font-weight: 800; font-variant-numeric: tabular-nums; margin-top: 4px; }
                .lam-tile-value small { font-size: 12px; font-weight: 600; color: var(--lam-muted); margin-left: 2px; }
                .lam-tile-hint { font-size: 11px; color: var(--lam-muted); }
                .lam-block { border: 1px solid var(--lam-border); border-radius: 14px; overflow: hidden; }
                .lam-block-head { display: flex; align-items: center; gap: 8px; padding: 10px 14px; background: var(--lam-soft); font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: var(--lam-muted); }
                .lam-kv { display: flex; justify-content: space-between; gap: 16px; padding: 9px 14px; border-top: 1px solid var(--lam-border); font-size: 13px; }
                .lam-kv:first-of-type { border-top: 0; }
                .lam-kv span:first-child { color: var(--lam-muted); flex-shrink: 0; }
                .lam-kv span:last-child { font-weight: 600; text-align: right; word-break: break-word; }
                .lam-ua-box summary { cursor: pointer; padding: 9px 14px; border-top: 1px solid var(--lam-border); font-size: 12.5px; color: var(--lam-muted); list-style: none; }
                .lam-ua-box summary::-webkit-details-marker { display: none; }
                .lam-ua-box .lam-ua { padding: 0 14px 12px; }
                .lam-sibling { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-top: 1px solid var(--lam-border); cursor: pointer; font-size: 13px; }
                .lam-sibling:hover { background: var(--lam-soft); }
                .lam-tl { list-style: none; margin: 0; padding: 8px 14px 4px; }
                .lam-tl li { display: flex; gap: 12px; padding: 8px 0; position: relative; }
                .lam-tl li:not(:last-child)::after { content: ''; position: absolute; left: 13px; top: 36px; bottom: -6px; width: 2px; background: var(--lam-border); }
                .lam-tl-icon { width: 28px; height: 28px; border-radius: 999px; display: grid; place-items: center; flex-shrink: 0; background: rgba(14, 165, 233, .14); color: #0ea5e9; }
                .lam-tl-icon.action { background: rgba(124, 58, 237, .16); color: #8b5cf6; }
                .lam-tl-icon.download { background: rgba(22, 163, 74, .15); color: var(--lam-good); }
                .lam-tl-icon.visibility { background: rgba(100, 116, 139, .18); color: var(--lam-muted); }
                .lam-tl-body { flex: 1; min-width: 0; }
                .lam-tl-label { font-size: 13px; font-weight: 600; word-break: break-word; }
                .lam-tl-meta { font-size: 11.5px; color: var(--lam-muted); margin-top: 2px; }
                .lam-tl-time { font-size: 11.5px; color: var(--lam-muted); white-space: nowrap; font-variant-numeric: tabular-nums; }
                @media (max-width: 480px) { .lam-tiles { grid-template-columns: repeat(2, 1fr); } }
            </style>

            <div class="lam-drawer-backdrop" wire:click="closeDetail"></div>
            <aside class="lam-drawer" wire:key="drawer-{{ $selected['session_key'] }}" x-data x-on:keydown.escape.window="$wire.closeDetail()">
                <header class="lam-dh">
                    <div class="lam-dh-row">
                        <div class="lam-avatar" style="width: 48px; height: 48px; font-size: 16px;">{{ $selected['initials'] }}<span class="lam-presence {{ $selected['visible'] ? '' : 'bg' }}"></span></div>
                        <div class="lam-dh-info">
                            <div class="lam-dh-name">{{ $selected['user_name'] }}</div>
                            <div class="lam-dh-mail" title="{{ $selected['user_email'] }}">{{ $selected['user_email'] }} · ID {{ $selected['user_id'] }}</div>
                        </div>
                        <button type="button" class="lam-icon-btn" wire:click="closeDetail" title="Cerrar (Esc)" aria-label="Cerrar">
                            <x-filament::icon icon="heroicon-m-x-mark" style="width: 18px; height: 18px;" />
                        </button>
                    </div>
                    <div class="lam-dh-chips">
                        <span class="lam-badge {{ $selected['is_pwa'] ? 'pwa' : 'accent' }}">{{ $selected['panel_label'] }}</span>
                        <span class="lam-badge" style="{{ $selected['visible'] ? 'background: rgba(22, 163, 74, .14); color: var(--lam-good);' : 'background: rgba(217, 119, 6, .14); color: var(--lam-fair);' }}">
                            {{ $selected['idle'] ? 'Inactivo' : ($selected['visible'] ? 'Pestaña activa' : 'En segundo plano') }} · {{ $selected['last_seen_ago'] }}
                        </span>
                        <span class="lam-badge">{{ $selected['flag'] }} {{ $selected['location'] }}</span>
                        @if ($selected['pwa_installed'])
                            <span class="lam-badge pwa">PWA instalada</span>
                        @endif
                        @unless ($selected['has_heartbeat'])
                            <span class="lam-nosignal" title="El navegador no está enviando su latido: pestaña abierta antes de la última actualización, bloqueador o conexión inestable.">Sin señal del navegador</span>
                        @endunless
                        @if ($isBlocked)
                            <span class="lam-badge" style="background: rgba(220, 38, 38, .14); color: var(--lam-poor);">Bloqueado</span>
                        @endif
                    </div>
                    @if (! $isBlocked && $selectedCanBeBlocked)
                        <div class="lam-dh-actions">
                            {{ ($this->blockUserAction)(['userId' => $selected['user_id']]) }}
                        </div>
                    @endif
                </header>

                <div class="lam-db">
                    <section class="lam-now">
                        <div class="lam-section-title" style="margin: 0;">Ahora</div>
                        <div class="lam-now-page">{{ $selected['page_label'] ?: 'Página desconocida' }}</div>
                        @if ($selected['page_title'] !== '')
                            <div class="lam-now-title">{{ $selected['page_title'] }}</div>
                        @endif
                        @if ($selected['last_action'] !== '')
                            <div class="lam-now-action">
                                <x-filament::icon icon="heroicon-m-bolt" style="width: 16px; height: 16px; color: #8b5cf6; flex-shrink: 0; margin-top: 1px;" />
                                <div><strong>{{ $selected['last_action'] }}</strong><div class="lam-sub">{{ $selected['last_action_ago'] }}</div></div>
                            </div>
                        @endif
                    </section>

                    <section class="lam-tiles">
                        @foreach ($tiles as $tile)
                            <div class="lam-tile">
                                <div class="lam-tile-label"><span class="lam-level {{ $tile['level'] }}" style="background: {{ $levelColor($tile['level']) }};"></span>{{ $tile['label'] }}</div>
                                <div class="lam-tile-value">{{ $tile['value'] ?? '—' }}@if ($tile['value'] !== null && $tile['suffix'] !== '')<small>{{ $tile['suffix'] }}</small>@endif</div>
                                <div class="lam-tile-hint">{{ $tile['hint'] }}</div>
                            </div>
                        @endforeach
                    </section>

                    <section class="lam-block">
                        <div class="lam-block-head"><x-filament::icon icon="heroicon-m-globe-americas" style="width: 16px; height: 16px;" />Conexión</div>
                        <div class="lam-kv"><span>IP</span><span class="lam-mono" style="font-family: ui-monospace, Menlo, monospace;">{{ $selected['ip'] }}</span></div>
                        <div class="lam-kv"><span>Ubicación</span><span>{{ $selected['flag'] }} {{ $selected['location'] }}</span></div>
                        <div class="lam-kv"><span>Red</span><span>{{ $selected['conn_type'] !== '' ? strtoupper($selected['conn_type']) : '—' }}{{ $selected['downlink'] !== null ? ' · '.$selected['downlink'].' Mbps' : '' }}</span></div>
                        <div class="lam-kv"><span>Zona horaria · idioma</span><span>{{ $selected['timezone'] ?: '—' }} · {{ $selected['lang'] ?: '—' }}</span></div>
                    </section>

                    <section class="lam-block">
                        <div class="lam-block-head"><x-filament::icon :icon="$deviceIcon" style="width: 16px; height: 16px;" />Dispositivo</div>
                        <div class="lam-kv"><span>Navegador</span><span>{{ $selected['browser'] }}</span></div>
                        <div class="lam-kv"><span>Sistema</span><span>{{ $selected['os'] }}</span></div>
                        <div class="lam-kv"><span>Tipo</span><span>{{ ['mobile' => 'Teléfono', 'tablet' => 'Tableta', 'desktop' => 'Computadora'][$selected['device']] ?? $selected['device'] }}{{ $selected['screen'] !== '' ? ' · '.$selected['screen'] : '' }}</span></div>
                        <div class="lam-kv"><span>Memoria · núcleos</span><span>{{ $selected['device_memory'] !== null ? $selected['device_memory'].' GB' : '—' }} · {{ $selected['cores'] ?? '—' }}</span></div>
                        <details class="lam-ua-box">
                            <summary>Ver User-Agent completo ▸</summary>
                            <div class="lam-ua">{{ $selected['user_agent'] ?: '—' }}</div>
                        </details>
                    </section>

                    @if ($selectedSiblings !== [])
                        <section class="lam-block">
                            <div class="lam-block-head"><x-filament::icon icon="heroicon-m-square-2-stack" style="width: 16px; height: 16px;" />Otras sesiones abiertas · {{ count($selectedSiblings) }}</div>
                            @foreach ($selectedSiblings as $sibling)
                                <div class="lam-sibling" wire:key="sibling-{{ $sibling['session_key'] }}" wire:click="selectSession('{{ $sibling['session_key'] }}')">
                                    <span class="lam-badge {{ $sibling['is_pwa'] ? 'pwa' : 'accent' }}">{{ $sibling['panel_label'] }}</span>
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="font-weight: 600;">{{ $sibling['page_label'] }}</div>
                                        <div class="lam-sub">{{ $sibling['browser'] }} en {{ $sibling['os'] }} · {{ $sibling['ip'] }}</div>
                                    </div>
                                    <span class="lam-sub">{{ $sibling['last_seen_ago'] }}</span>
                                </div>
                            @endforeach
                        </section>
                    @endif

                    <section class="lam-block">
                        <div class="lam-block-head"><x-filament::icon icon="heroicon-m-clock" style="width: 16px; height: 16px;" />Línea de tiempo · {{ count($selectedTimeline) }}</div>
                        @if ($selectedTimeline === [])
                            <div class="lam-sub" style="padding: 12px 14px;">Aún no hay acciones registradas.</div>
                        @else
                            <ul class="lam-tl">
                                @foreach ($selectedTimeline as $index => $event)
                                    @php($type = $event['type'] ?? 'page')
                                    <li wire:key="event-{{ $index }}-{{ $event['at'] ?? 0 }}">
                                        <span class="lam-tl-icon {{ $type }}"><x-filament::icon :icon="$eventIcon[$type] ?? 'heroicon-m-document-text'" style="width: 15px; height: 15px;" /></span>
                                        <div class="lam-tl-body">
                                            <div class="lam-tl-label">{{ $event['label'] ?? '—' }}</div>
                                            @php($eventMs = isset($event['ms']) ? (int) $event['ms'] : null)
                                            <div class="lam-tl-meta">
                                                {{ $event['panel'] ?? '' }}{{ ! empty($event['page']) ? ' · '.$event['page'] : '' }}
                                                @if ($eventMs !== null)
                                                    · <span style="{{ $eventMs >= 5000 ? 'color: var(--lam-poor); font-weight: 700;' : ($eventMs >= 2000 ? 'color: var(--lam-fair); font-weight: 700;' : '') }}">{{ $eventMs >= 1000 ? number_format($eventMs / 1000, 1, ',', '.').' s' : $eventMs.' ms' }}{{ $eventMs >= 5000 ? ' · muy lento' : ($eventMs >= 2000 ? ' · lento' : '') }}</span>
                                                @endif
                                                {{ isset($event['status']) && (int) $event['status'] >= 400 ? ' · error HTTP '.$event['status'] : '' }}
                                            </div>
                                        </div>
                                        <div class="lam-tl-time" title="{{ $event['ago'] }}">{{ $event['time'] }}</div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                </div>
            </aside>
        @endif
    </div>
</x-filament-panels::page>
