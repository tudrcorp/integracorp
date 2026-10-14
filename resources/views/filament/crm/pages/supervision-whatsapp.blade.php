<x-filament-panels::page>
    <style>
        .crm-sup {
            --s-line: #d3deeb; --s-panel: #ffffff; --s-soft: #eef3f8; --s-text: #122033; --s-muted: #5a6e84;
            --s-accent: #052f60; --s-ok: #0d7a48; --s-ok-bg: #e2f3e9; --s-warn: #9a5b00; --s-warn-bg: #fcefd8; --s-bad: #b42318; --s-bad-bg: #fde7e4;
            display: grid; gap: 16px; color: var(--s-text); font-size: 13px;
        }
        .dark .crm-sup {
            --s-line: rgb(51 65 85); --s-panel: rgb(15 23 42); --s-soft: rgb(23 34 56); --s-text: rgb(238 242 247); --s-muted: rgb(154 171 191);
            --s-accent: rgb(140 200 245); --s-ok: #86efac; --s-ok-bg: rgb(15 44 28); --s-warn: #fcd34d; --s-warn-bg: rgb(53 39 8); --s-bad: #fda4af; --s-bad-bg: rgb(58 19 22);
        }
        .crm-sup-bar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: space-between; }
        .crm-sup-ranges { display: inline-flex; gap: 4px; padding: 3px; border: 1px solid var(--s-line); border-radius: 999px; background: var(--s-panel); }
        .crm-sup-ranges button { border: 0; background: none; color: var(--s-muted); border-radius: 999px; padding: 5px 12px; font-weight: 600; cursor: pointer; font-size: 12.5px; }
        .crm-sup-ranges button[aria-pressed=true] { background: var(--s-accent); color: #fff; }
        .dark .crm-sup-ranges button[aria-pressed=true] { color: rgb(15 23 42); }
        .crm-sup-links { display: flex; gap: 14px; flex-wrap: wrap; }
        .crm-sup-links a { color: var(--s-accent); font-weight: 600; }
        .crm-sup-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); gap: 10px; }
        .crm-sup-kpi { border: 1px solid var(--s-line); background: var(--s-panel); border-radius: 14px; padding: 12px 14px; display: grid; gap: 2px; }
        .crm-sup-kpi b { font-size: 22px; font-weight: 650; letter-spacing: -0.02em; font-variant-numeric: tabular-nums; }
        .crm-sup-kpi span { color: var(--s-muted); font-size: 12px; }
        .crm-sup-kpi.is-bad { border-color: var(--s-bad); background: var(--s-bad-bg); }
        .crm-sup-kpi.is-bad b { color: var(--s-bad); }
        .crm-sup-grid { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); gap: 16px; align-items: start; }
        @media (max-width: 1024px) { .crm-sup-grid { grid-template-columns: 1fr; } }
        .crm-sup-card { border: 1px solid var(--s-line); background: var(--s-panel); border-radius: 14px; padding: 14px; display: grid; gap: 10px; min-width: 0; }
        .crm-sup-card h3 { margin: 0; font-size: 14px; font-weight: 650; }
        .crm-sup-card p.hint { margin: 0; color: var(--s-muted); font-size: 12px; }
        .crm-sup-table-wrap { overflow-x: auto; }
        .crm-sup-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
        .crm-sup-table th { text-align: left; color: var(--s-muted); font-weight: 600; padding: 6px 8px; border-bottom: 1px solid var(--s-line); white-space: nowrap; }
        .crm-sup-table td { padding: 7px 8px; border-bottom: 1px solid var(--s-line); font-variant-numeric: tabular-nums; white-space: nowrap; }
        .crm-sup-table td.num, .crm-sup-table th.num { text-align: right; }
        .crm-sup-state { display: inline-flex; align-items: center; gap: 6px; }
        .crm-sup-state i { width: 8px; height: 8px; border-radius: 50%; background: var(--s-muted); }
        .crm-sup-state.is-available i { background: var(--s-ok); }
        .crm-sup-state.is-busy i { background: var(--s-warn); }
        .crm-sup-state.is-away i { background: var(--s-bad); }
        .crm-sup-pill { display: inline-flex; border-radius: 6px; padding: 1px 7px; font-size: 11px; font-weight: 650; }
        .crm-sup-pill.is-bad { background: var(--s-bad-bg); color: var(--s-bad); }
        .crm-sup-pill.is-warn { background: var(--s-warn-bg); color: var(--s-warn); }
        .crm-sup-risk { list-style: none; margin: 0; padding: 0; display: grid; gap: 6px; }
        .crm-sup-risk a { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 2px 8px; padding: 8px 10px; border: 1px solid var(--s-line); border-radius: 10px; color: inherit; text-decoration: none; }
        .crm-sup-risk a:hover { background: var(--s-soft); }
        .crm-sup-risk small { color: var(--s-muted); }
        .crm-sup-bars { display: grid; gap: 6px; }
        .crm-sup-bar-row { display: grid; grid-template-columns: minmax(0, 10rem) minmax(0, 1fr) 2.5rem; gap: 8px; align-items: center; font-size: 12px; }
        .crm-sup-bar-row i { display: block; height: 8px; border-radius: 999px; background: var(--s-accent); min-width: 2px; }
        .crm-sup-bar-row .num { text-align: right; font-variant-numeric: tabular-nums; }
        .crm-sup-empty { color: var(--s-muted); font-size: 12.5px; margin: 0; }
    </style>

    @php
        $kpis = $data['kpis'];
        $outcomeTotals = array_column($data['outcomes'], 'total');
        $maxOutcome = $outcomeTotals === [] ? 1 : max(1, ...$outcomeTotals);
        $clock = fn (?int $ts): string => $ts ? \Illuminate\Support\Carbon::createFromTimestamp($ts)->timezone('America/Caracas')->format('d/m H:i') : '';
        $nowTs = now()->getTimestamp();
    @endphp

    <div class="crm-sup" wire:poll.30s>
        <div class="crm-sup-bar">
            <div class="crm-sup-ranges" role="group" aria-label="Periodo">
                @foreach ($ranges as $key => $label)
                    <button type="button" wire:click="setRange('{{ $key }}')" aria-pressed="{{ $range === $key ? 'true' : 'false' }}" wire:key="range-{{ $key }}">{{ $label }}</button>
                @endforeach
            </div>
            <div class="crm-sup-links">
                <a href="{{ $inboxUrl }}" wire:navigate>Ir a la bandeja</a>
                <a href="{{ $libraryUrl }}" wire:navigate>Respuestas y documentos</a>
                <span class="crm-sup-empty">Actualizado {{ $clock($data['generated_at']) }} · se refresca solo</span>
            </div>
        </div>

        <div class="crm-sup-kpis">
            <div class="crm-sup-kpi"><b>{{ $kpis['incoming'] }}</b><span>Casos que entraron</span></div>
            <div class="crm-sup-kpi"><b>{{ $kpis['attended'] }}</b><span>Casos tomados</span></div>
            <div class="crm-sup-kpi"><b>{{ \App\Support\CrmInbox\CrmInboxDashboard::duration($kpis['first_reply_median']) }}</b><span>Primera respuesta (mediana, desde que entró)</span></div>
            <div class="crm-sup-kpi"><b>{{ $kpis['waiting_now'] }}</b><span>Sin tomar ahora</span></div>
            <div class="crm-sup-kpi {{ $kpis['late_now'] > 0 ? 'is-bad' : '' }}"><b>{{ $kpis['late_now'] }}</b><span>Sin tomar en rojo</span></div>
            <div class="crm-sup-kpi {{ $kpis['unanswered_now'] > 0 ? 'is-bad' : '' }}"><b>{{ $kpis['unanswered_now'] }}</b><span>En atención sin respuesta</span></div>
            <div class="crm-sup-kpi"><b>{{ $kpis['quotes'] }}</b><span>Propuestas enviadas</span></div>
            <div class="crm-sup-kpi"><b>{{ $kpis['conversion'] !== null ? $kpis['conversion'].' %' : '—' }}</b><span>Ventas sobre casos cerrados ({{ $kpis['won'] }} de {{ $kpis['closed'] }})</span></div>
        </div>

        <div class="crm-sup-grid">
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
                                    <th class="num">Cerrados</th>
                                    <th class="num">Ventas</th>
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
                                        <td class="num">{{ $analyst['closed'] }}</td>
                                        <td class="num">{{ $analyst['won'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <div style="display: grid; gap: 16px;">
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
                    @if ($data['outcomes'] === [])
                        <p class="crm-sup-empty">Aún no hay casos cerrados con motivo en este periodo.</p>
                    @else
                        <div class="crm-sup-bars">
                            @foreach ($data['outcomes'] as $outcome)
                                <div class="crm-sup-bar-row" wire:key="outcome-{{ $outcome['key'] }}">
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
    </div>
</x-filament-panels::page>
