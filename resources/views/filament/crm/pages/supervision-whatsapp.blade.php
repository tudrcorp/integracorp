<x-filament-panels::page>
    <style>
        .crm-sup {
            --s-line: #d3deeb; --s-panel: #ffffff; --s-soft: #eef3f8; --s-text: #122033; --s-muted: #5a6e84;
            --s-accent: #052f60; --s-accent-2: #2d89ca; --s-ok: #0d7a48; --s-ok-bg: #e2f3e9; --s-warn: #9a5b00; --s-warn-bg: #fcefd8;
            --s-bad: #b42318; --s-bad-bg: #fde7e4; --s-info: #1d4ed8; --s-info-bg: #e8efff; --s-gap: #e3e9f1;
            display: grid; gap: 16px; color: var(--s-text); font-size: 13px;
        }
        .dark .crm-sup {
            --s-line: rgb(51 65 85); --s-panel: rgb(15 23 42); --s-soft: rgb(23 34 56); --s-text: rgb(238 242 247); --s-muted: rgb(154 171 191);
            --s-accent: rgb(140 200 245); --s-accent-2: rgb(94 164 214); --s-ok: #86efac; --s-ok-bg: rgb(15 44 28); --s-warn: #fcd34d; --s-warn-bg: rgb(53 39 8);
            --s-bad: #fda4af; --s-bad-bg: rgb(58 19 22); --s-info: #93c5fd; --s-info-bg: rgb(23 37 84); --s-gap: rgb(30 41 59);
        }
        .crm-sup-bar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; }
        .crm-sup-seg { display: inline-flex; flex-wrap: wrap; gap: 4px; padding: 3px; border: 1px solid var(--s-line); border-radius: 999px; background: var(--s-panel); }
        .crm-sup-seg button { border: 0; background: none; color: var(--s-muted); border-radius: 999px; padding: 5px 12px; font-weight: 600; cursor: pointer; font-size: 12.5px; }
        .crm-sup-seg button[aria-pressed=true] { background: var(--s-accent); color: #fff; }
        .dark .crm-sup-seg button[aria-pressed=true] { color: rgb(15 23 42); }
        .crm-sup-seg button:focus-visible, .crm-sup-btn:focus-visible { outline: 2px solid var(--s-accent-2); outline-offset: 2px; }
        .crm-sup-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .crm-sup-btn { display: inline-flex; align-items: center; gap: 6px; border: 1px solid var(--s-line); background: var(--s-panel); color: var(--s-text); border-radius: 10px; padding: 6px 11px; font-weight: 600; font-size: 12.5px; cursor: pointer; text-decoration: none; }
        .crm-sup-btn:hover { background: var(--s-soft); }
        .crm-sup-btn[disabled] { opacity: .6; cursor: progress; }
        .crm-sup-meta { color: var(--s-muted); font-size: 12px; }

        .crm-sup-health { display: grid; grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr)); gap: 10px; }
        .crm-sup-hcard { border: 1px solid var(--s-line); background: var(--s-panel); border-radius: 14px; padding: 11px 13px; display: grid; gap: 4px; min-width: 0; }
        .crm-sup-hcard header { display: flex; align-items: center; gap: 8px; font-weight: 650; }
        .crm-sup-hcard p { margin: 0; color: var(--s-muted); font-size: 12px; line-height: 1.4; }
        .crm-sup-hcard small { color: var(--s-muted); font-size: 11.5px; }
        .crm-sup-hcard.is-down { border-color: var(--s-bad); background: var(--s-bad-bg); }
        .crm-sup-hcard.is-warn { border-color: var(--s-warn); background: var(--s-warn-bg); }
        .crm-sup-dot { width: 10px; height: 10px; border-radius: 50%; background: var(--s-muted); flex: none; }
        .crm-sup-dot.is-ok { background: var(--s-ok); box-shadow: 0 0 0 0 var(--s-ok); animation: crm-sup-pulse 2s infinite; }
        .crm-sup-dot.is-warn { background: var(--s-warn); }
        .crm-sup-dot.is-down { background: var(--s-bad); animation: crm-sup-blink 1s infinite; }
        @keyframes crm-sup-pulse { 0% { box-shadow: 0 0 0 0 color-mix(in srgb, var(--s-ok) 55%, transparent); } 70% { box-shadow: 0 0 0 7px transparent; } 100% { box-shadow: 0 0 0 0 transparent; } }
        @keyframes crm-sup-blink { 50% { opacity: .35; } }
        @media (prefers-reduced-motion: reduce) { .crm-sup-dot { animation: none !important; } }

        .crm-sup-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(10.5rem, 1fr)); gap: 10px; }
        .crm-sup-kpis.is-six { grid-template-columns: repeat(6, minmax(0, 1fr)); }
        @media (max-width: 1100px) { .crm-sup-kpis.is-six { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        @media (max-width: 560px) { .crm-sup-kpis.is-six { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        .crm-sup-kpi { border: 1px solid var(--s-line); background: var(--s-panel); border-radius: 14px; padding: 12px 14px; display: grid; gap: 2px; }
        .crm-sup-kpi b { font-size: 22px; font-weight: 650; letter-spacing: -0.02em; font-variant-numeric: tabular-nums; }
        .crm-sup-kpi span { color: var(--s-muted); font-size: 12px; }
        .crm-sup-kpi.is-bad { border-color: var(--s-bad); background: var(--s-bad-bg); }
        .crm-sup-kpi.is-bad b { color: var(--s-bad); }
        .crm-sup-kpi.is-ok b { color: var(--s-ok); }

        .crm-sup-grid { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); gap: 16px; align-items: start; }
        .crm-sup-grid.is-even { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        @media (max-width: 1024px) { .crm-sup-grid, .crm-sup-grid.is-even { grid-template-columns: 1fr; } }
        .crm-sup-stack { display: grid; gap: 16px; min-width: 0; }
        .crm-sup-card { border: 1px solid var(--s-line); background: var(--s-panel); border-radius: 14px; padding: 14px; display: grid; gap: 10px; min-width: 0; }
        .crm-sup-card h3 { margin: 0; font-size: 14px; font-weight: 650; }
        .crm-sup-card p.hint { margin: 0; color: var(--s-muted); font-size: 12px; }
        .crm-sup-empty { color: var(--s-muted); font-size: 12.5px; margin: 0; }
        .crm-sup-table-wrap { overflow-x: auto; }
        .crm-sup-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
        .crm-sup-table th { text-align: left; color: var(--s-muted); font-weight: 600; padding: 6px 8px; border-bottom: 1px solid var(--s-line); white-space: nowrap; }
        .crm-sup-table td { padding: 7px 8px; border-bottom: 1px solid var(--s-line); font-variant-numeric: tabular-nums; }
        .crm-sup-table td.num, .crm-sup-table th.num { text-align: right; white-space: nowrap; }
        .crm-sup-state { display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
        .crm-sup-state i { width: 8px; height: 8px; border-radius: 50%; background: var(--s-muted); }
        .crm-sup-state.is-available i { background: var(--s-ok); }
        .crm-sup-state.is-busy i { background: var(--s-warn); }
        .crm-sup-state.is-away i { background: var(--s-bad); }
        .crm-sup-pill { display: inline-flex; border-radius: 6px; padding: 1px 7px; font-size: 11px; font-weight: 650; white-space: nowrap; }
        .crm-sup-pill.is-bad { background: var(--s-bad-bg); color: var(--s-bad); }
        .crm-sup-pill.is-warn { background: var(--s-warn-bg); color: var(--s-warn); }
        .crm-sup-pill.is-ok { background: var(--s-ok-bg); color: var(--s-ok); }
        .crm-sup-pill.is-info { background: var(--s-info-bg); color: var(--s-info); }
        .crm-sup-risk { list-style: none; margin: 0; padding: 0; display: grid; gap: 6px; }
        .crm-sup-risk a { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 2px 8px; padding: 8px 10px; border: 1px solid var(--s-line); border-radius: 10px; color: inherit; text-decoration: none; }
        .crm-sup-risk a:hover { background: var(--s-soft); }
        .crm-sup-risk small { color: var(--s-muted); }

        .crm-sup-hbars { display: grid; gap: 6px; }
        .crm-sup-hbar { display: grid; grid-template-columns: minmax(0, 11rem) minmax(0, 1fr) 3rem; gap: 8px; align-items: center; font-size: 12px; }
        .crm-sup-hbar i { display: block; height: 9px; border-radius: 999px; background: var(--s-accent); min-width: 2px; }
        .crm-sup-hbar .num { text-align: right; font-variant-numeric: tabular-nums; }
        .crm-sup-hbar span:first-child { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        .crm-sup-cols { display: flex; align-items: flex-end; gap: 3px; height: 150px; padding-top: 6px; border-bottom: 1px solid var(--s-line); }
        .crm-sup-col { flex: 1 1 0; min-width: 0; height: 100%; display: flex; align-items: flex-end; justify-content: center; gap: 1px; }
        .crm-sup-col i { display: block; flex: 1 1 0; max-width: 14px; border-radius: 3px 3px 0 0; min-height: 1px; background: var(--s-accent); }
        .crm-sup-col i.is-b { background: var(--s-accent-2); opacity: .75; }
        .crm-sup-col i.is-c { background: var(--s-ok); opacity: .8; }
        .crm-sup-axis { display: flex; gap: 3px; font-size: 10.5px; color: var(--s-muted); }
        .crm-sup-axis span { flex: 1 1 0; min-width: 0; text-align: center; overflow: hidden; white-space: nowrap; }
        .crm-sup-legend { display: flex; flex-wrap: wrap; gap: 12px; font-size: 11.5px; color: var(--s-muted); }
        .crm-sup-legend i { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 4px; vertical-align: -1px; background: var(--s-accent); }
        .crm-sup-legend i.is-b { background: var(--s-accent-2); opacity: .75; }
        .crm-sup-legend i.is-c { background: var(--s-ok); opacity: .8; }

        .crm-sup-heat { display: grid; grid-template-columns: repeat(24, minmax(0, 1fr)); gap: 3px; }
        .crm-sup-heat div { aspect-ratio: 1 / 1.3; border-radius: 4px; background: var(--s-gap); display: grid; place-items: end center; font-size: 9.5px; color: var(--s-muted); padding-bottom: 2px; }
        .crm-sup-heat-axis { display: grid; grid-template-columns: repeat(24, minmax(0, 1fr)); gap: 3px; font-size: 10px; color: var(--s-muted); text-align: center; }

        .crm-sup-funnel { display: grid; gap: 6px; }
        .crm-sup-funnel div { display: grid; grid-template-columns: minmax(0, 12rem) minmax(0, 1fr) 4.5rem; gap: 8px; align-items: center; font-size: 12.5px; }
        .crm-sup-funnel i { display: block; height: 22px; border-radius: 6px; background: linear-gradient(90deg, var(--s-accent), var(--s-accent-2)); min-width: 3px; }
        .crm-sup-funnel .num { text-align: right; font-variant-numeric: tabular-nums; font-weight: 650; }

        .crm-sup-insights { display: grid; grid-template-columns: repeat(auto-fit, minmax(18rem, 1fr)); gap: 10px; }
        .crm-sup-insight { border: 1px solid var(--s-line); border-left: 4px solid var(--s-info); background: var(--s-panel); border-radius: 12px; padding: 11px 13px; display: grid; gap: 4px; }
        .crm-sup-insight b { font-size: 13.5px; }
        .crm-sup-insight p { margin: 0; color: var(--s-muted); font-size: 12.5px; line-height: 1.45; }
        .crm-sup-insight.is-bad { border-left-color: var(--s-bad); }
        .crm-sup-insight.is-warn { border-left-color: var(--s-warn); }
        .crm-sup-insight.is-ok { border-left-color: var(--s-ok); }

        .crm-sup-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 6px; }
        .crm-sup-list li { border: 1px solid var(--s-line); border-radius: 10px; padding: 8px 10px; display: grid; gap: 2px; font-size: 12.5px; }
        .crm-sup-list small { color: var(--s-muted); }

        .crm-sup-timeline { display: grid; grid-template-columns: minmax(0, 10rem) minmax(0, 1fr) 4.5rem; gap: 10px; align-items: center; font-size: 12px; }
        .crm-sup-ticks { display: grid; grid-template-columns: repeat(60, minmax(0, 1fr)); gap: 2px; }
        .crm-sup-ticks i { display: block; height: 18px; border-radius: 2px; background: var(--s-gap); }
        .crm-sup-ticks i.is-ok { background: var(--s-ok); opacity: .85; }
        .crm-sup-ticks i.is-warn { background: var(--s-warn); }
        .crm-sup-ticks i.is-down { background: var(--s-bad); }
        .crm-sup-ticks i.is-unknown { background: var(--s-muted); opacity: .45; }
        .crm-sup-tech { display: grid; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); gap: 8px; }
        .crm-sup-tech div { border: 1px solid var(--s-line); border-radius: 10px; padding: 8px 10px; display: grid; gap: 2px; }
        .crm-sup-tech span { color: var(--s-muted); font-size: 11.5px; }
        .crm-sup-tech b { font-variant-numeric: tabular-nums; }
    </style>

    @php
        $kpis = $data['kpis'];
        $trends = $data['trends'] ?? [];
        $tz = 'America/Caracas';
        $clock = fn (?int $ts, string $format = 'd/m H:i'): string => $ts ? \Illuminate\Support\Carbon::createFromTimestamp($ts)->timezone($tz)->format($format) : '—';
        $nowTs = now()->getTimestamp();
        $ago = function (?int $ts) use ($nowTs): string {
            if (! $ts) {
                return '—';
            }
            $seconds = max(0, $nowTs - $ts);

            return $seconds < 60 ? 'hace '.$seconds.' s' : ($seconds < 3600 ? 'hace '.intdiv($seconds, 60).' min' : 'hace '.intdiv($seconds, 3600).' h '.intdiv($seconds % 3600, 60).' min');
        };
        $pct = fn (int $part, int $total): string => $total > 0 ? (int) round($part / $total * 100).' %' : '—';
        $period = $bot['period'] ?? null;
        $openSince = [];
        foreach ($incidents as $incident) {
            if ($incident['to'] === null) {
                $openSince[$incident['key']] = $incident['from'];
            }
        }
        $statusLabel = ['ok' => 'En línea', 'warn' => 'Atención', 'down' => 'Caído', 'unknown' => 'Sin datos'];
    @endphp

    <div class="crm-sup" @if ($canSeeHealth) wire:poll.10s @else wire:poll.30s @endif>
        {{-- Barra superior: periodo, vistas y exportar --}}
        <div class="crm-sup-bar">
            <div class="crm-sup-seg" role="tablist" aria-label="Vistas">
                @foreach ($tabs as $key => $label)
                    <button type="button" role="tab" wire:click="setTab('{{ $key }}')" aria-pressed="{{ $tab === $key ? 'true' : 'false' }}" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" wire:key="tab-{{ $key }}">{{ $label }}</button>
                @endforeach
            </div>
            <div class="crm-sup-actions">
                <div class="crm-sup-seg" role="group" aria-label="Periodo">
                    @foreach ($ranges as $key => $label)
                        <button type="button" wire:click="setRange('{{ $key }}')" aria-pressed="{{ $range === $key ? 'true' : 'false' }}" wire:key="range-{{ $key }}">{{ $label }}</button>
                    @endforeach
                </div>
                <button type="button" class="crm-sup-btn" wire:click="exportCsv" wire:loading.attr="disabled" wire:target="exportCsv" title="Todas las cifras del periodo en una hoja de cálculo">
                    <x-filament::icon icon="heroicon-o-table-cells" class="h-4 w-4" /> Excel
                </button>
                <button type="button" class="crm-sup-btn" wire:click="exportPdf" wire:loading.attr="disabled" wire:target="exportPdf" title="Resumen del periodo para informes">
                    <x-filament::icon icon="heroicon-o-document-arrow-down" class="h-4 w-4" /> PDF
                </button>
            </div>
        </div>
        <div class="crm-sup-bar">
            <span class="crm-sup-meta">Actualizado {{ $clock($data['generated_at'], 'H:i:s') }} · se refresca solo cada {{ $canSeeHealth ? '10' : '30' }} s</span>
            <span class="crm-sup-actions">
                <a class="crm-sup-btn" href="{{ $inboxUrl }}" wire:navigate>Ir a la bandeja</a>
                <a class="crm-sup-btn" href="{{ $libraryUrl }}" wire:navigate>Respuestas y documentos</a>
            </span>
        </div>

        {{-- Salud en vivo (solo SUPERADMIN), visible en todas las vistas --}}
        @if ($canSeeHealth && $health !== null)
            <div class="crm-sup-health" aria-live="polite">
                @foreach ($health['checks'] as $check)
                    <article class="crm-sup-hcard is-{{ $check['status'] }}" wire:key="health-{{ $check['key'] }}">
                        <header>
                            <span class="crm-sup-dot is-{{ $check['status'] }}" aria-hidden="true"></span>
                            <span>{{ $check['label'] }}</span>
                            <span class="crm-sup-pill is-{{ ['ok' => 'ok', 'warn' => 'warn', 'down' => 'bad', 'unknown' => 'info'][$check['status']] }}" style="margin-left: auto;">{{ $statusLabel[$check['status']] }}</span>
                        </header>
                        <p>{{ $check['detail'] }}</p>
                        <small>
                            @if ($check['latency_ms'] !== null) {{ $check['latency_ms'] }} ms · @endif
                            @if (isset($openSince[$check['key']])) así desde las {{ $clock($openSince[$check['key']], 'H:i') }} @else medido {{ $ago($health['at']) }} @endif
                        </small>
                    </article>
                @endforeach
            </div>
        @endif

        {{-- ======================= RESUMEN ======================= --}}
        @if ($tab === 'resumen')
            <div class="crm-sup-kpis is-six">
                <div class="crm-sup-kpi"><b>{{ $kpis['incoming'] }}</b><span>Casos que entraron</span></div>
                <div class="crm-sup-kpi"><b>{{ $kpis['attended'] }}</b><span>Casos tomados</span></div>
                <div class="crm-sup-kpi {{ ($trends['sla'] ?? 100) < 70 ? 'is-bad' : (($trends['sla'] ?? 0) >= 90 ? 'is-ok' : '') }}"><b>{{ ($trends['sla'] ?? null) !== null ? $trends['sla'].' %' : '—' }}</b><span>Tomados a tiempo (meta {{ $trends['sla_minutes'] ?? 15 }} min)</span></div>
                <div class="crm-sup-kpi"><b>{{ \App\Support\CrmInbox\CrmInboxDashboard::duration($trends['wait_median'] ?? null) }}</b><span>Espera para ser tomado (mediana)</span></div>
                <div class="crm-sup-kpi"><b>{{ \App\Support\CrmInbox\CrmInboxDashboard::duration($kpis['first_reply_median']) }}</b><span>Primera respuesta (mediana)</span></div>
                <div class="crm-sup-kpi"><b>{{ $kpis['waiting_now'] }}</b><span>Sin tomar ahora</span></div>
                <div class="crm-sup-kpi {{ $kpis['late_now'] > 0 ? 'is-bad' : '' }}"><b>{{ $kpis['late_now'] }}</b><span>Sin tomar en rojo</span></div>
                <div class="crm-sup-kpi {{ $kpis['unanswered_now'] > 0 ? 'is-bad' : '' }}"><b>{{ $kpis['unanswered_now'] }}</b><span>En atención sin respuesta</span></div>
                <div class="crm-sup-kpi {{ ($trends['abandoned'] ?? 0) > 0 ? 'is-bad' : '' }}"><b>{{ $trends['abandoned'] ?? 0 }}</b><span>Esperaron más de {{ $trends['abandon_minutes'] ?? 30 }} min o no se tomaron</span></div>
                <div class="crm-sup-kpi"><b>{{ $kpis['finished'] }}</b><span>Finalizadas por analistas</span></div>
                <div class="crm-sup-kpi"><b>{{ \App\Support\CrmInbox\CrmInboxDashboard::duration($kpis['handle_median']) }}</b><span>Tiempo de atención (mediana)</span></div>
                <div class="crm-sup-kpi"><b>{{ $kpis['conversion'] !== null ? $kpis['conversion'].' %' : '—' }}</b><span>Ventas sobre cerrados ({{ $kpis['won'] }} de {{ $kpis['closed'] }})</span></div>
            </div>

            <div class="crm-sup-grid">
                <section class="crm-sup-card">
                    <h3>Casos por día</h3>
                    @php
                        $days = $trends['days'] ?? [];
                        $maxDay = max(1, ...array_map(fn ($d) => max($d['incoming'], $d['taken'], $d['finished']), $days ?: [['incoming' => 0, 'taken' => 0, 'finished' => 0]]));
                        $step = max(1, (int) ceil(count($days) / 10));
                    @endphp
                    @if (array_sum(array_column($days, 'incoming')) === 0 && array_sum(array_column($days, 'finished')) === 0)
                        <p class="crm-sup-empty">Sin casos en este periodo.</p>
                    @else
                        <div class="crm-sup-legend"><span><i></i>Entraron</span><span><i class="is-b"></i>Tomados</span><span><i class="is-c"></i>Finalizados</span></div>
                        <div class="crm-sup-cols" role="img" aria-label="Casos por día">
                            @foreach ($days as $day)
                                <div class="crm-sup-col" title="{{ \Illuminate\Support\Carbon::parse($day['day'])->format('d/m') }}: {{ $day['incoming'] }} entraron · {{ $day['taken'] }} tomados · {{ $day['finished'] }} finalizados" wire:key="day-{{ $day['day'] }}">
                                    <i style="height: {{ round($day['incoming'] / $maxDay * 100) }}%"></i>
                                    <i class="is-b" style="height: {{ round($day['taken'] / $maxDay * 100) }}%"></i>
                                    <i class="is-c" style="height: {{ round($day['finished'] / $maxDay * 100) }}%"></i>
                                </div>
                            @endforeach
                        </div>
                        <div class="crm-sup-axis">
                            @foreach ($days as $i => $day)
                                <span>{{ $i % $step === 0 ? \Illuminate\Support\Carbon::parse($day['day'])->format('d/m') : '' }}</span>
                            @endforeach
                        </div>
                    @endif
                </section>

                <div class="crm-sup-stack">
                    <section class="crm-sup-card">
                        <h3>En riesgo ahora</h3>
                        <p class="hint">Ábrelo en la bandeja: allí puedes pasarlo a otra persona.</p>
                        @if ($data['risk'] === [])
                            <p class="crm-sup-empty">Nada en rojo. Todo al día.</p>
                        @else
                            <ul class="crm-sup-risk">
                                @foreach ($data['risk'] as $item)
                                    <li wire:key="risk-{{ $item['handoff_id'] }}">
                                        <a href="{{ $inboxUrl }}?caso={{ $item['handoff_id'] }}" wire:navigate>
                                            <b>{{ $item['name'] }}</b>
                                            <span class="crm-sup-pill {{ $item['kind'] === 'untaken' ? 'is-warn' : 'is-bad' }}">{{ $item['kind'] === 'untaken' ? 'Sin tomar' : 'Sin respuesta' }}</span>
                                            <small>{{ $item['area'] }} · espera desde {{ $clock($item['since']) }}</small>
                                            <small>{{ $item['since'] ? intdiv(max(0, $nowTs - $item['since']), 60).' min' : '' }}</small>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>

                    <section class="crm-sup-card">
                        <h3>Cómo terminaron</h3>
                        @php $maxOutcome = max(1, ...(array_column($data['outcomes'], 'total') ?: [1])); @endphp
                        @if ($data['outcomes'] === [])
                            <p class="crm-sup-empty">Aún no hay casos cerrados con motivo en este periodo.</p>
                        @else
                            <div class="crm-sup-hbars">
                                @foreach ($data['outcomes'] as $outcome)
                                    <div class="crm-sup-hbar" wire:key="outcome-{{ $outcome['key'] }}">
                                        <span>{{ $outcome['label'] }}</span>
                                        <i style="width: {{ round($outcome['total'] / $maxOutcome * 100) }}%"></i>
                                        <span class="num">{{ $outcome['total'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </section>
                </div>
            </div>

            @if ($period !== null)
                <section class="crm-sup-card">
                    <h3>Bot Sol en este periodo</h3>
                    <div class="crm-sup-kpis">
                        <div class="crm-sup-kpi"><b>{{ $period['clientes'] }}</b><span>Clientes atendidos por el bot</span></div>
                        <div class="crm-sup-kpi is-ok"><b>{{ $pct($period['resueltos_sin_humano'], $period['clientes']) }}</b><span>Resueltos sin una persona</span></div>
                        <div class="crm-sup-kpi"><b>{{ $period['clientes_derivados'] }}</b><span>Derivados a analistas</span></div>
                        <div class="crm-sup-kpi"><b>{{ $period['propuestas_pdf'] }}</b><span>Propuestas en PDF</span></div>
                        <div class="crm-sup-kpi {{ ($period['errores'] + $period['errores_escalados']) > 0 ? 'is-bad' : '' }}"><b>{{ $period['errores'] + $period['errores_escalados'] }}</b><span>Errores del asistente</span></div>
                    </div>
                </section>
            @endif
        @endif

        {{-- ======================= BOT ======================= --}}
        @if ($tab === 'bot')
            @if ($bot === null)
                <section class="crm-sup-card">
                    <h3>Sin datos del bot</h3>
                    <p class="hint">No se pudieron leer las métricas del bot desde n8n. Si eres SUPERADMIN, revisa «Salud en vivo». Los datos de la bandeja siguen disponibles en las otras vistas.</p>
                </section>
            @else
                <div class="crm-sup-kpis">
                    <div class="crm-sup-kpi"><b>{{ $period['clientes'] }}</b><span>Clientes atendidos</span></div>
                    <div class="crm-sup-kpi"><b>{{ $period['clientes_nuevos'] }}</b><span>Clientes nuevos</span></div>
                    <div class="crm-sup-kpi"><b>{{ $period['mensajes_in'] }}</b><span>Mensajes recibidos</span></div>
                    <div class="crm-sup-kpi"><b>{{ $period['mensajes_out'] }}</b><span>Mensajes enviados</span></div>
                    <div class="crm-sup-kpi"><b>{{ $period['audios_in'] }}</b><span>Notas de voz recibidas</span></div>
                    <div class="crm-sup-kpi is-ok"><b>{{ $pct($period['resueltos_sin_humano'], $period['clientes']) }}</b><span>Resueltos sin una persona ({{ $period['resueltos_sin_humano'] }})</span></div>
                    <div class="crm-sup-kpi"><b>{{ $period['derivaciones'] }}</b><span>Derivaciones ({{ $period['clientes_derivados'] }} clientes)</span></div>
                    <div class="crm-sup-kpi"><b>{{ $period['derivaciones_repetidas'] }}</b><span>Derivaciones repetidas evitadas</span></div>
                    <div class="crm-sup-kpi"><b>{{ $pct($period['seguimientos_respondidos'], $period['seguimientos_enviados']) }}</b><span>Responden al seguimiento ({{ $period['seguimientos_respondidos'] }} de {{ $period['seguimientos_enviados'] }})</span></div>
                    <div class="crm-sup-kpi {{ ($period['errores'] + $period['errores_escalados']) > 0 ? 'is-bad' : '' }}"><b>{{ $period['errores'] + $period['errores_escalados'] }}</b><span>Errores del asistente ({{ $period['errores_escalados'] }} escalados)</span></div>
                </div>

                <div class="crm-sup-grid is-even">
                    <section class="crm-sup-card">
                        <h3>Embudo del bot</h3>
                        <p class="hint">De todos los clientes que escribieron, cuántos avanzaron en cada paso. Las ventas las cierran los analistas.</p>
                        @php
                            $funnel = [
                                ['Escribieron', $period['clientes']],
                                ['Recibieron cotización', $period['clientes_cotizados']],
                                ['Recibieron propuesta en PDF', $period['propuestas_pdf']],
                                ['Pasaron a un analista', $period['clientes_derivados']],
                                ['Ventas cerradas', $kpis['won']],
                            ];
                            $top = max(1, $period['clientes']);
                        @endphp
                        <div class="crm-sup-funnel">
                            @foreach ($funnel as [$label, $value])
                                <div><span>{{ $label }}</span><i style="width: {{ min(100, round($value / $top * 100)) }}%"></i><span class="num">{{ $value }}</span></div>
                            @endforeach
                        </div>
                    </section>

                    <section class="crm-sup-card">
                        <h3>Clientes y derivaciones por día</h3>
                        @php
                            $bdays = $bot['days'];
                            $maxB = max(1, ...array_map(fn ($d) => max($d['clients'], $d['handoffs']), $bdays ?: [['clients' => 0, 'handoffs' => 0]]));
                            $bstep = max(1, (int) ceil(count($bdays) / 10));
                        @endphp
                        @if ($bdays === [] || array_sum(array_column($bdays, 'clients')) === 0)
                            <p class="crm-sup-empty">Sin conversaciones en este periodo.</p>
                        @else
                            <div class="crm-sup-legend"><span><i></i>Clientes</span><span><i class="is-b"></i>Derivaciones</span></div>
                            <div class="crm-sup-cols" role="img" aria-label="Clientes y derivaciones por día">
                                @foreach ($bdays as $day)
                                    <div class="crm-sup-col" title="{{ \Illuminate\Support\Carbon::parse($day['day'])->format('d/m') }}: {{ $day['clients'] }} clientes · {{ $day['messages'] }} mensajes · {{ $day['handoffs'] }} derivaciones" wire:key="bday-{{ $day['day'] }}">
                                        <i style="height: {{ round($day['clients'] / $maxB * 100) }}%"></i>
                                        <i class="is-b" style="height: {{ round($day['handoffs'] / $maxB * 100) }}%"></i>
                                    </div>
                                @endforeach
                            </div>
                            <div class="crm-sup-axis">
                                @foreach ($bdays as $i => $day)
                                    <span>{{ $i % $bstep === 0 ? \Illuminate\Support\Carbon::parse($day['day'])->format('d/m') : '' }}</span>
                                @endforeach
                            </div>
                        @endif
                    </section>
                </div>

                <section class="crm-sup-card">
                    <h3>¿A qué hora escriben los clientes?</h3>
                    <p class="hint">Mensajes recibidos por hora del día (hora de Caracas). Más oscuro = más mensajes.</p>
                    @php $maxH = max(1, ...$bot['hours']); @endphp
                    <div class="crm-sup-heat">
                        @foreach ($bot['hours'] as $hour => $count)
                            <div style="background: color-mix(in srgb, var(--s-accent) {{ $count > 0 ? max(8, round($count / $maxH * 100)) : 0 }}%, var(--s-gap));" title="{{ sprintf('%02d:00', $hour) }}: {{ $count }} mensajes"></div>
                        @endforeach
                    </div>
                    <div class="crm-sup-heat-axis">
                        @foreach (range(0, 23) as $hour)
                            <span>{{ $hour % 3 === 0 ? $hour : '' }}</span>
                        @endforeach
                    </div>
                </section>

                <div class="crm-sup-grid is-even">
                    <section class="crm-sup-card">
                        <h3>Derivaciones por área</h3>
                        @php $maxA = max(1, ...(array_values($bot['areas']) ?: [1])); arsort($bot['areas']); @endphp
                        @if ($bot['areas'] === [])
                            <p class="crm-sup-empty">El bot no derivó casos en este periodo.</p>
                        @else
                            <div class="crm-sup-hbars">
                                @foreach ($bot['areas'] as $area => $count)
                                    <div class="crm-sup-hbar"><span>{{ $area === 'sin_area' ? 'Sin área' : \App\Support\CrmInbox\CrmInboxAreas::label($area) }}</span><i style="width: {{ round($count / $maxA * 100) }}%"></i><span class="num">{{ $count }}</span></div>
                                @endforeach
                            </div>
                        @endif
                    </section>

                    <section class="crm-sup-card">
                        <h3>En qué etapa están los clientes</h3>
                        @php
                            $stageLabels = ['greeting' => 'Saludo', 'discovery' => 'Descubriendo la necesidad', 'quote' => 'Cotizando', 'objection' => 'Con dudas u objeciones', 'close' => 'Cerrando', 'won' => 'Ganado', 'lost' => 'Perdido', 'support' => 'Soporte', 'handoff' => 'Con un analista'];
                            $stages = $bot['stages']; arsort($stages);
                            $maxS = max(1, ...(array_values($stages) ?: [1]));
                        @endphp
                        @if ($stages === [])
                            <p class="crm-sup-empty">Sin clientes activos en este periodo.</p>
                        @else
                            <div class="crm-sup-hbars">
                                @foreach ($stages as $stage => $count)
                                    <div class="crm-sup-hbar"><span>{{ $stageLabels[$stage] ?? $stage }}</span><i style="width: {{ round($count / $maxS * 100) }}%"></i><span class="num">{{ $count }}</span></div>
                                @endforeach
                            </div>
                        @endif
                    </section>
                </div>
            @endif
        @endif

        {{-- ======================= ANALISTAS ======================= --}}
        @if ($tab === 'analistas')
            <section class="crm-sup-card">
                <h3>Analistas</h3>
                <p class="hint">Primero quien tiene clientes esperando respuesta. La primera respuesta se mide desde que el analista tomó el caso.</p>
                @if ($data['analysts'] === [])
                    <p class="crm-sup-empty">No hay analistas activos en este departamento.</p>
                @else
                    <div class="crm-sup-table-wrap">
                        <table class="crm-sup-table">
                            <thead>
                                <tr>
                                    <th>Analista</th>
                                    <th>Estado</th>
                                    <th class="num">En atención</th>
                                    <th class="num">Sin responder</th>
                                    <th class="num">Tomados</th>
                                    <th class="num">1.ª respuesta</th>
                                    <th class="num">Finalizadas</th>
                                    <th class="num">Atención</th>
                                    <th class="num">Al bot</th>
                                    <th class="num">Cerrados</th>
                                    <th class="num">Ventas</th>
                                    <th class="num">Efectividad</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($data['analysts'] as $analyst)
                                    <tr wire:key="analyst-{{ $analyst['id'] }}">
                                        <td>{{ $analyst['name'] }}</td>
                                        <td><span class="crm-sup-state is-{{ $analyst['state'] }}"><i aria-hidden="true"></i>{{ $analyst['state_label'] }}</span></td>
                                        <td class="num">{{ $analyst['active'] }}</td>
                                        <td class="num">@if ($analyst['waiting'] > 0)<span class="crm-sup-pill is-bad">{{ $analyst['waiting'] }}</span>@else 0 @endif</td>
                                        <td class="num">{{ $analyst['attended'] }}</td>
                                        <td class="num">{{ \App\Support\CrmInbox\CrmInboxDashboard::duration($analyst['reply_median']) }}</td>
                                        <td class="num">{{ $analyst['finished'] }}</td>
                                        <td class="num">{{ \App\Support\CrmInbox\CrmInboxDashboard::duration($analyst['handle_median']) }}</td>
                                        <td class="num">{{ $analyst['released'] }}</td>
                                        <td class="num">{{ $analyst['closed'] }}</td>
                                        <td class="num">{{ $analyst['won'] }}</td>
                                        <td class="num">{{ $pct($analyst['won'], $analyst['closed']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endif

        {{-- ======================= HALLAZGOS ======================= --}}
        @if ($tab === 'hallazgos')
            <div class="crm-sup-insights">
                @foreach ($insights as $i => $insight)
                    <article class="crm-sup-insight is-{{ $insight['tone'] }}" wire:key="insight-{{ $i }}">
                        <b>{{ $insight['title'] }}</b>
                        <p>{{ $insight['text'] }}</p>
                    </article>
                @endforeach
            </div>

            <div class="crm-sup-grid is-even">
                <section class="crm-sup-card">
                    <h3>Casos y espera por hora del día</h3>
                    <p class="hint">Barras: casos que entraron. Número: espera mediana para ser tomado. Una espera alta con muchos casos indica que falta gente en esa hora.</p>
                    @php
                        $hrs = $trends['hours'] ?? [];
                        $maxHc = max(1, ...(array_column($hrs, 'incoming') ?: [1]));
                        $slaS = ($trends['sla_minutes'] ?? 15) * 60;
                    @endphp
                    @if (array_sum(array_column($hrs, 'incoming')) === 0)
                        <p class="crm-sup-empty">Sin casos en este periodo.</p>
                    @else
                        <div class="crm-sup-cols" style="height: 120px;" role="img" aria-label="Casos por hora">
                            @foreach ($hrs as $row)
                                <div class="crm-sup-col" title="{{ sprintf('%02d:00', $row['hour']) }}: {{ $row['incoming'] }} casos · espera {{ \App\Support\CrmInbox\CrmInboxDashboard::duration($row['wait_median']) }}">
                                    <i style="height: {{ round($row['incoming'] / $maxHc * 100) }}%; {{ ($row['wait_median'] ?? 0) > $slaS ? 'background: var(--s-bad);' : '' }}"></i>
                                </div>
                            @endforeach
                        </div>
                        <div class="crm-sup-axis">
                            @foreach ($hrs as $row)
                                <span>{{ $row['hour'] % 3 === 0 ? $row['hour'] : '' }}</span>
                            @endforeach
                        </div>
                        <div class="crm-sup-legend"><span><i></i>Casos</span><span><i style="background: var(--s-bad);"></i>Espera mayor a la meta ({{ intdiv($slaS, 60) }} min)</span></div>
                    @endif
                </section>

                <section class="crm-sup-card">
                    <h3>Espera por área</h3>
                    @if (($trends['areas'] ?? []) === [])
                        <p class="crm-sup-empty">Sin casos en este periodo.</p>
                    @else
                        <div class="crm-sup-table-wrap">
                            <table class="crm-sup-table">
                                <thead><tr><th>Área</th><th class="num">Casos</th><th class="num">Espera mediana</th></tr></thead>
                                <tbody>
                                    @foreach ($trends['areas'] as $area)
                                        <tr wire:key="area-{{ $area['key'] }}">
                                            <td>{{ $area['label'] }}</td>
                                            <td class="num">{{ $area['incoming'] }}</td>
                                            <td class="num">@if (($area['wait_median'] ?? 0) > $slaS)<span class="crm-sup-pill is-bad">{{ \App\Support\CrmInbox\CrmInboxDashboard::duration($area['wait_median']) }}</span>@else {{ \App\Support\CrmInbox\CrmInboxDashboard::duration($area['wait_median']) }} @endif</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            </div>

            <div class="crm-sup-grid is-even">
                <section class="crm-sup-card">
                    <h3>Motivos recientes de derivación</h3>
                    <p class="hint">Lo que el bot no resolvió solo. Si un motivo se repite, conviene enseñárselo al bot (FAQ o instrucciones).</p>
                    @if (($bot['reasons'] ?? []) === [])
                        <p class="crm-sup-empty">{{ $bot === null ? 'Sin datos del bot.' : 'Sin derivaciones en este periodo.' }}</p>
                    @else
                        <ul class="crm-sup-list">
                            @foreach ($bot['reasons'] as $i => $reason)
                                <li wire:key="reason-{{ $i }}">
                                    <span>{{ $reason['motivo'] !== '' ? $reason['motivo'] : 'Sin motivo' }}</span>
                                    <small>{{ \App\Support\CrmInbox\CrmInboxAreas::label($reason['area'] !== '' ? $reason['area'] : null) }} · {{ $clock($reason['at']) }}</small>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                <section class="crm-sup-card">
                    <h3>Errores recientes del bot</h3>
                    <p class="hint">Fallos técnicos del asistente (modelo o red). Escalado = el cliente pasó solo a un analista para no quedar sin respuesta.</p>
                    @if (($bot['errors'] ?? []) === [])
                        <p class="crm-sup-empty">{{ $bot === null ? 'Sin datos del bot.' : 'Sin errores en este periodo.' }}</p>
                    @else
                        <ul class="crm-sup-list">
                            @foreach ($bot['errors'] as $i => $error)
                                <li wire:key="error-{{ $i }}">
                                    <span>{{ $error['error'] !== '' ? $error['error'] : 'Error sin detalle' }}</span>
                                    <small>{{ $clock($error['at']) }} @if ($error['escalated']) · <span class="crm-sup-pill is-bad">Escalado</span> @endif</small>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>
        @endif

        {{-- ======================= SALUD (solo SUPERADMIN) ======================= --}}
        @if ($tab === 'salud' && $canSeeHealth && $health !== null)
            <section class="crm-sup-card">
                <h3>Última hora, minuto a minuto</h3>
                <p class="hint">Cada cuadro es un minuto medido por el vigilante del CRM. Gris = sin medición. A la derecha, el porcentaje en línea de las últimas 24 horas.</p>
                @foreach ($health['checks'] as $check)
                    <div class="crm-sup-timeline" wire:key="tl-{{ $check['key'] }}">
                        <span>{{ $check['label'] }}</span>
                        <div class="crm-sup-ticks" role="img" aria-label="Historial de {{ $check['label'] }}">
                            @foreach ($history as $beat)
                                @php $s = $beat['s'][$check['key']] ?? null; @endphp
                                <i class="{{ $s ? 'is-'.$s : '' }}" title="{{ $clock($beat['t'], 'H:i') }} · {{ $s ? $statusLabel[$s] : 'sin medición' }}"></i>
                            @endforeach
                        </div>
                        <b class="num" style="text-align: right;">{{ ($uptime[$check['key']] ?? null) !== null ? $uptime[$check['key']].' %' : '—' }}</b>
                    </div>
                @endforeach
                @if (collect($history)->whereNotNull('s')->isEmpty())
                    <p class="crm-sup-empty">Aún no hay mediciones guardadas: el vigilante del CRM (crm-inbox:watch) las registra cada minuto.</p>
                @endif
            </section>

            <div class="crm-sup-grid is-even">
                <section class="crm-sup-card">
                    <h3>Detalle técnico</h3>
                    @php
                        $wa = $health['checks']['whatsapp']['meta'] ?? [];
                        $bl = $health['checks']['bot']['meta'] ?? [];
                        $ib = $health['checks']['inbox']['meta'] ?? [];
                    @endphp
                    <div class="crm-sup-tech">
                        <div><span>Instancia de Evolution</span><b>{{ $wa['instance'] ?? '—' }}</b></div>
                        <div><span>Número vinculado</span><b>{{ isset($wa['number']) && $wa['number'] ? '+'.$wa['number'] : '—' }}</b></div>
                        <div><span>Estado de la conexión</span><b>{{ $wa['state'] ?? '—' }}</b></div>
                        <div><span>Último mensaje recibido</span><b>{{ $ago($bl['last_in_at'] ?? null) }}</b></div>
                        <div><span>Última respuesta enviada</span><b>{{ $ago($bl['last_out_at'] ?? null) }}</b></div>
                        <div><span>Mensajes últimos 5 min</span><b>{{ ($bl['in_5m'] ?? '—') }} in · {{ ($bl['out_5m'] ?? '—') }} out</b></div>
                        <div><span>Sin procesar (&gt; 2 min)</span><b>{{ $bl['stuck'] ?? '—' }}</b></div>
                        <div><span>Errores última hora</span><b>{{ $bl['errors_60m'] ?? '—' }}</b></div>
                        <div><span>Derivaciones abiertas en el bot</span><b>{{ $bl['open_handoffs'] ?? '—' }}</b></div>
                        <div><span>Clientes con analista ahora</span><b>{{ $bl['takeover_now'] ?? '—' }}</b></div>
                        <div><span>Casos por entregar a la bandeja</span><b>{{ $bl['crm_pending'] ?? '—' }}</b></div>
                        <div><span>Trabajos en cola «inbox»</span><b>{{ $ib['backlog'] ?? '—' }}</b></div>
                    </div>
                </section>

                <section class="crm-sup-card">
                    <h3>Incidentes</h3>
                    <p class="hint">Cuándo empezó y terminó cada problema. Los SUPERADMIN reciben aviso por la campanita y por correo si WhatsApp, n8n o el bot siguen caídos más de {{ config('crm-inbox.health_alert_minutes', 2) }} min.</p>
                    @if ($incidents === [])
                        <p class="crm-sup-empty">Sin incidentes registrados. Todo en orden.</p>
                    @else
                        <ul class="crm-sup-list">
                            @foreach ($incidents as $i => $incident)
                                <li wire:key="incident-{{ $i }}-{{ $incident['from'] }}">
                                    <span>
                                        <span class="crm-sup-pill {{ $incident['status'] === 'down' ? 'is-bad' : 'is-warn' }}">{{ $statusLabel[$incident['status']] ?? $incident['status'] }}</span>
                                        <b>{{ $incident['label'] }}</b>
                                        @if ($incident['to'] === null) <span class="crm-sup-pill is-bad">En curso</span> @endif
                                    </span>
                                    <small>
                                        {{ $clock($incident['from']) }} → {{ $incident['to'] ? $clock($incident['to'], 'H:i') : 'ahora' }}
                                        ({{ \App\Support\CrmInbox\CrmInboxDashboard::duration(($incident['to'] ?? $nowTs) - $incident['from']) }})
                                        @if ($incident['detail'] !== '') · {{ $incident['detail'] }} @endif
                                    </small>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>
        @endif
    </div>
</x-filament-panels::page>
