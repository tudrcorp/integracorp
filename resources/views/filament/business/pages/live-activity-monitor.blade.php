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
        .lam-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; }
        .lam-kpi { padding: 14px 16px; }
        .lam-kpi-label { font-size: 12px; color: var(--lam-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
        .lam-kpi-value { font-size: 28px; font-weight: 800; line-height: 1.15; margin-top: 4px; font-variant-numeric: tabular-nums; }
        .lam-kpi-hint { font-size: 12px; color: var(--lam-muted); margin-top: 2px; }
        .lam-health { display: flex; flex-wrap: wrap; gap: 8px 18px; padding: 12px 16px; font-size: 12.5px; color: var(--lam-muted); }
        .lam-health strong { color: var(--lam-text); font-variant-numeric: tabular-nums; }
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
                    <span class="lam-badge warn" title="Sin la base GeoLite2 la ciudad solo se obtiene de Cloudflare.">Sin base GeoLite2</span>
                @endunless
            </div>
            <button type="button" class="lam-btn" wire:click="togglePause">
                {{ $paused ? '▶ Reanudar' : '⏸ Pausar' }}
            </button>
        </div>

        <div class="lam-kpis">
            <div class="lam-card lam-kpi">
                <div class="lam-kpi-label">Usuarios conectados</div>
                <div class="lam-kpi-value">{{ $kpis['users'] }}</div>
                <div class="lam-kpi-hint">{{ $kpis['sessions'] }} {{ $kpis['sessions'] === 1 ? 'sesión' : 'sesiones' }} abiertas</div>
            </div>
            <div class="lam-card lam-kpi">
                <div class="lam-kpi-label">Pestañas activas</div>
                <div class="lam-kpi-value">{{ $kpis['active_tabs'] }}</div>
                <div class="lam-kpi-hint">{{ $kpis['sessions'] - $kpis['active_tabs'] }} en segundo plano</div>
            </div>
            <div class="lam-card lam-kpi">
                <div class="lam-kpi-label">En la PWA</div>
                <div class="lam-kpi-value">{{ $kpis['pwa'] }}</div>
                <div class="lam-kpi-hint">clientes en /app</div>
            </div>
            <div class="lam-card lam-kpi">
                <div class="lam-kpi-label">Latencia media</div>
                <div class="lam-kpi-value">{{ $kpis['avg_rtt'] !== null ? $kpis['avg_rtt'].' ms' : '—' }}</div>
                <div class="lam-kpi-hint">ida y vuelta medida en el navegador</div>
            </div>
            <div class="lam-card lam-kpi">
                <div class="lam-kpi-label">Respuesta del servidor</div>
                <div class="lam-kpi-value">{{ $kpis['p95_ms'] !== null ? $kpis['p95_ms'].' ms' : '—' }}</div>
                <div class="lam-kpi-hint">p95 · media {{ $kpis['avg_ms'] ?? '—' }} ms · máx {{ $kpis['max_ms'] ?? '—' }} ms (5 min)</div>
            </div>
            <div class="lam-card lam-kpi">
                <div class="lam-kpi-label">Peticiones / min</div>
                <div class="lam-kpi-value">{{ $kpis['rpm'] }}</div>
                <div class="lam-kpi-hint">de usuarios autenticados</div>
            </div>
        </div>

        <div class="lam-card lam-health">
            <span>Colas pendientes <strong>{{ $health['queue_pending'] ?? '—' }}</strong>
                @foreach ($health['queues'] as $queue => $size)
                    <span class="lam-sub">· {{ $queue }} {{ $size ?? '—' }}</span>
                @endforeach
            </span>
            <span>Jobs fallidos <strong>{{ $health['failed_jobs'] ?? '—' }}</strong></span>
            <span>Base de datos <strong>{{ $health['db_ms'] !== null ? $health['db_ms'].' ms' : '—' }}</strong></span>
            @if ($health['redis'])
                <span>Redis <strong>{{ $health['redis']['memory'] ?? '—' }}</strong> · {{ $health['redis']['clients'] ?? '—' }} clientes · {{ $health['redis']['ops_per_sec'] ?? '—' }} ops/s</span>
            @endif
            @if ($health['load'])
                <span>Carga CPU <strong>{{ implode(' / ', $health['load']) }}</strong></span>
            @endif
            @if ($health['disk_free_pct'] !== null)
                <span>Disco libre <strong>{{ $health['disk_free_pct'] }}%</strong></span>
            @endif
            <span>PHP <strong>{{ $health['php'] }}</strong> · {{ $health['environment'] }} · cola {{ $health['queue_driver'] }}</span>
            <span class="lam-sub">medido {{ $health['measured_at'] }}</span>
        </div>

        <div class="lam-filters">
            <button type="button" class="lam-chip {{ $panelFilter === 'all' ? 'on' : '' }}" wire:click="filterPanel('all')">Todos<span>{{ $kpis['sessions'] }}</span></button>
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
                                class="lam-row {{ $selectedSession === $session['session_key'] ? 'selected' : '' }}"
                                wire:click="selectSession('{{ $session['session_key'] }}')">
                                <td>
                                    <div class="lam-user">
                                        <div class="lam-avatar">{{ $session['initials'] }}<span class="lam-presence {{ $session['visible'] ? '' : 'bg' }}"></span></div>
                                        <div>
                                            <div class="lam-name">{{ $session['user_name'] }}</div>
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
                                    <span class="lam-metric"><span class="lam-level {{ $session['rtt_level'] }}"></span>{{ $session['rtt_ms'] !== null ? $session['rtt_ms'].' ms' : '—' }}</span>
                                    <div class="lam-sub">{{ $session['conn_type'] !== '' ? strtoupper($session['conn_type']) : '' }}</div>
                                </td>
                                <td>
                                    <span class="lam-metric"><span class="lam-level {{ $session['server_level'] }}"></span>{{ $session['server_ms'] !== null ? $session['server_ms'].' ms' : '—' }}</span>
                                    <div class="lam-sub">{{ $session['queries'] !== null ? $session['queries'].' consultas' : '' }}</div>
                                </td>
                                <td>
                                    <div style="font-weight: 600;">{{ $session['last_seen_ago'] }}</div>
                                    <div class="lam-sub">{{ $session['visible'] ? 'Pestaña activa' : 'En segundo plano' }} · {{ $session['session_duration'] }}</div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        @if ($selected)
            <div class="lam-drawer-backdrop" wire:click="closeDetail"></div>
            <aside class="lam-drawer" wire:key="drawer-{{ $selected['session_key'] }}">
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px;">
                    <div class="lam-user">
                        <div class="lam-avatar" style="width: 44px; height: 44px; font-size: 15px;">{{ $selected['initials'] }}<span class="lam-presence {{ $selected['visible'] ? '' : 'bg' }}"></span></div>
                        <div>
                            <div class="lam-name" style="font-size: 16px;">{{ $selected['user_name'] }}</div>
                            <div class="lam-sub">{{ $selected['user_email'] }} · ID {{ $selected['user_id'] }}</div>
                        </div>
                    </div>
                    <button type="button" class="lam-btn" wire:click="closeDetail">Cerrar</button>
                </div>

                <div>
                    <div class="lam-section-title">Ahora</div>
                    <dl class="lam-grid2">
                        <div><dt>Panel</dt><dd>{{ $selected['panel_label'] }}</dd></div>
                        <div><dt>Página</dt><dd>{{ $selected['page_label'] ?: '—' }}</dd></div>
                        <div style="grid-column: span 2;"><dt>Título de la pestaña</dt><dd>{{ $selected['page_title'] ?: '—' }}</dd></div>
                        <div style="grid-column: span 2;"><dt>Última acción</dt><dd>{{ $selected['last_action'] ?: '—' }} {{ $selected['last_action_ago'] ? '· '.$selected['last_action_ago'] : '' }}</dd></div>
                        <div><dt>Estado</dt><dd>{{ $selected['visible'] ? 'Pestaña activa' : 'En segundo plano' }}</dd></div>
                        <div><dt>Última señal</dt><dd>{{ $selected['last_seen_ago'] }}</dd></div>
                    </dl>
                </div>

                <div>
                    <div class="lam-section-title">Conexión</div>
                    <dl class="lam-grid2">
                        <div><dt>IP</dt><dd>{{ $selected['ip'] }}</dd></div>
                        <div><dt>Ubicación</dt><dd>{{ $selected['flag'] }} {{ $selected['location'] }}</dd></div>
                        <div><dt>Latencia (ida y vuelta)</dt><dd>{{ $selected['rtt_ms'] !== null ? $selected['rtt_ms'].' ms' : '—' }}</dd></div>
                        <div><dt>Red</dt><dd>{{ $selected['conn_type'] !== '' ? strtoupper($selected['conn_type']) : '—' }}{{ $selected['downlink'] !== null ? ' · '.$selected['downlink'].' Mbps' : '' }}</dd></div>
                        <div><dt>Zona horaria</dt><dd>{{ $selected['timezone'] ?: '—' }}</dd></div>
                        <div><dt>Idioma</dt><dd>{{ $selected['lang'] ?: '—' }}</dd></div>
                    </dl>
                </div>

                <div>
                    <div class="lam-section-title">Dispositivo</div>
                    <dl class="lam-grid2">
                        <div><dt>Navegador</dt><dd>{{ $selected['browser'] }}</dd></div>
                        <div><dt>Sistema</dt><dd>{{ $selected['os'] }}</dd></div>
                        <div><dt>Tipo</dt><dd>{{ ['mobile' => 'Teléfono', 'tablet' => 'Tableta', 'desktop' => 'Computadora'][$selected['device']] ?? $selected['device'] }}</dd></div>
                        <div><dt>PWA</dt><dd>{{ $selected['pwa_installed'] ? 'Instalada' : ($selected['is_pwa'] ? 'En el navegador' : 'No') }}</dd></div>
                        <div><dt>Pantalla</dt><dd>{{ $selected['screen'] ?: '—' }}</dd></div>
                        <div><dt>Memoria · núcleos</dt><dd>{{ $selected['device_memory'] !== null ? $selected['device_memory'].' GB' : '—' }} · {{ $selected['cores'] ?? '—' }}</dd></div>
                        <div style="grid-column: span 2;"><dt>User-Agent</dt><dd class="lam-ua">{{ $selected['user_agent'] ?: '—' }}</dd></div>
                    </dl>
                </div>

                <div>
                    <div class="lam-section-title">Rendimiento</div>
                    <dl class="lam-grid2">
                        <div><dt>Última respuesta del servidor</dt><dd>{{ $selected['server_ms'] !== null ? $selected['server_ms'].' ms' : '—' }}</dd></div>
                        <div><dt>Consultas · memoria</dt><dd>{{ $selected['queries'] ?? '—' }} · {{ $selected['memory_mb'] !== null ? $selected['memory_mb'].' MB' : '—' }}</dd></div>
                        <div><dt>Carga de la página</dt><dd>{{ $selected['load_ms'] !== null ? $selected['load_ms'].' ms' : '—' }}</dd></div>
                        <div><dt>Primer byte (TTFB)</dt><dd>{{ $selected['ttfb_ms'] !== null ? $selected['ttfb_ms'].' ms' : '—' }}</dd></div>
                        <div><dt>Peticiones en la sesión</dt><dd>{{ $selected['requests'] }}</dd></div>
                        <div><dt>Tiempo conectado</dt><dd>{{ $selected['session_duration'] }}</dd></div>
                    </dl>
                </div>

                @if ($selectedSiblings !== [])
                    <div>
                        <div class="lam-section-title">Otras sesiones abiertas ({{ count($selectedSiblings) }})</div>
                        @foreach ($selectedSiblings as $sibling)
                            <div wire:key="sibling-{{ $sibling['session_key'] }}" class="lam-sub" style="padding: 6px 0; border-bottom: 1px solid var(--lam-border); cursor: pointer;" wire:click="selectSession('{{ $sibling['session_key'] }}')">
                                <strong style="color: var(--lam-text);">{{ $sibling['panel_label'] }}</strong> · {{ $sibling['page_label'] }} · {{ $sibling['browser'] }} en {{ $sibling['os'] }} · {{ $sibling['ip'] }} · {{ $sibling['last_seen_ago'] }}
                            </div>
                        @endforeach
                    </div>
                @endif

                <div>
                    <div class="lam-section-title">Línea de tiempo (últimas {{ count($selectedTimeline) }} acciones)</div>
                    @if ($selectedTimeline === [])
                        <div class="lam-sub">Aún no hay acciones registradas.</div>
                    @else
                        <ul class="lam-timeline">
                            @foreach ($selectedTimeline as $index => $event)
                                <li wire:key="event-{{ $index }}-{{ $event['at'] ?? 0 }}" class="{{ $event['type'] ?? 'page' }}">
                                    <div style="font-weight: 600;">{{ $event['label'] ?? '—' }}</div>
                                    <div class="lam-sub">{{ $event['time'] }} · {{ $event['ago'] }} · {{ $event['panel'] ?? '' }}{{ isset($event['ms']) ? ' · '.$event['ms'].' ms' : '' }}{{ isset($event['status']) && (int) $event['status'] >= 400 ? ' · HTTP '.$event['status'] : '' }}</div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </aside>
        @endif
    </div>
</x-filament-panels::page>
