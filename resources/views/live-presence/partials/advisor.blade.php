{{--
    Tres semáforos (Seguridad · Colas · Errores) y «Qué hacer ahora».
    Espera: $advice (OperationsAdvisor::advise()), $actions (bool: botones), $lights (list de claves a mostrar, opcional).
--}}
@php
    $actions = $actions ?? true;
    $shownLights = $lights ?? ['security', 'queues', 'errors'];
    $lightTitles = ['security' => 'Seguridad', 'queues' => 'Colas', 'errors' => 'Errores'];
    $severityLabels = ['critical' => 'Urgente', 'warning' => 'Atención', 'info' => 'Sugerencia'];
@endphp

<style>
    .ladv { --a-bg: #ffffff; --a-soft: #f8fafc; --a-border: #e5e7eb; --a-text: #0f172a; --a-muted: #64748b; --a-red: #dc2626; --a-amber: #d97706; --a-green: #16a34a; --a-blue: #0284c7;
            display: flex; flex-direction: column; gap: 12px; color: var(--a-text); }
    .dark .ladv { --a-bg: #0b1220; --a-soft: #0f172a; --a-border: #1e293b; --a-text: #e2e8f0; --a-muted: #94a3b8; --a-blue: #38bdf8; }
    .ladv-lights { display: grid; grid-template-columns: repeat(var(--ladv-cols, 3), minmax(0, 1fr)); gap: 12px; }
    .ladv-light { display: flex; align-items: center; gap: 12px; background: var(--a-bg); border: 1px solid var(--a-border); border-left: 5px solid var(--a-green); border-radius: 14px; padding: 12px 16px; min-width: 0; }
    .ladv-light.amber { border-left-color: var(--a-amber); } .ladv-light.red { border-left-color: var(--a-red); animation: ladv-alarm 1.2s infinite; }
    @keyframes ladv-alarm { 50% { box-shadow: 0 0 0 4px rgba(220, 38, 38, .18); } }
    .ladv-bulb { width: 14px; height: 14px; border-radius: 999px; flex-shrink: 0; background: var(--a-green); box-shadow: 0 0 0 4px rgba(22, 163, 74, .18); }
    .ladv-bulb.amber { background: var(--a-amber); box-shadow: 0 0 0 4px rgba(217, 119, 6, .2); } .ladv-bulb.red { background: var(--a-red); box-shadow: 0 0 0 4px rgba(220, 38, 38, .25); }
    .ladv-kicker { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--a-muted); }
    .ladv-state { font-size: 16px; font-weight: 800; }
    .ladv-detail { font-size: 12.5px; color: var(--a-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ladv-todo { background: var(--a-bg); border: 1px solid var(--a-border); border-radius: 14px; }
    .ladv-todo-head { display: flex; justify-content: space-between; align-items: center; padding: 11px 16px; border-bottom: 1px solid var(--a-border); }
    .ladv-item { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: 12px; align-items: center; padding: 10px 16px; border-top: 1px solid var(--a-border); }
    .ladv-item:first-of-type { border-top: 0; }
    .ladv-sev { font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; border-radius: 6px; padding: 2px 8px; white-space: nowrap; }
    .ladv-sev.critical { background: rgba(220, 38, 38, .15); color: var(--a-red); } .ladv-sev.warning { background: rgba(217, 119, 6, .16); color: var(--a-amber); } .ladv-sev.info { background: rgba(2, 132, 199, .12); color: var(--a-blue); }
    .ladv-title { font-weight: 700; font-size: 13.5px; }
    .ladv-area { font-size: 11px; font-weight: 700; color: var(--a-muted); margin-right: 6px; }
    .ladv-text { font-size: 12.5px; color: var(--a-muted); word-break: break-word; }
    .ladv-cta { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 700; border: 1px solid var(--a-border); border-radius: 999px; padding: 5px 12px; background: var(--a-soft); color: var(--a-text); text-decoration: none; white-space: nowrap; cursor: pointer; }
    .ladv-cta:hover { border-color: var(--a-blue); color: var(--a-blue); }
    .ladv-ok { padding: 12px 16px; font-size: 13px; color: var(--a-muted); }
    .ladv-ok::before { content: '✓ '; color: var(--a-green); font-weight: 800; }
    .lcopy { position: relative; display: inline-flex; }
    .lcopy-btn { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 700; border: 1px solid var(--a-border, #e5e7eb); border-radius: 999px; padding: 5px 12px; background: var(--a-soft, #f8fafc); color: var(--a-text, #0f172a); cursor: pointer; white-space: nowrap; }
    .dark .lcopy-btn { border-color: #1e293b; background: #0f172a; color: #e2e8f0; }
    .lcopy-btn:hover { border-color: #0284c7; }
    @media (max-width: 900px) { .ladv-lights { grid-template-columns: 1fr; } .ladv-item { grid-template-columns: minmax(0, 1fr); } }
</style>

<div class="ladv">
    <div class="ladv-lights" style="--ladv-cols: {{ count($shownLights) }};">
        @foreach ($shownLights as $key)
            @php $light = $advice['lights'][$key]; @endphp
            <div class="ladv-light {{ $light['level'] }}" wire:key="ladv-light-{{ $key }}">
                <span class="ladv-bulb {{ $light['level'] }}"></span>
                <div style="min-width: 0;">
                    <div class="ladv-kicker">{{ $lightTitles[$key] }}</div>
                    <div class="ladv-state">{{ $light['label'] }}</div>
                    <div class="ladv-detail" title="{{ $light['detail'] }}">{{ $light['detail'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="ladv-todo">
        <div class="ladv-todo-head">
            <span class="ladv-kicker">Qué hacer ahora</span>
            <span class="ladv-detail">{{ count($advice['actions']) }} {{ count($advice['actions']) === 1 ? 'tarea' : 'tareas' }}, la más urgente primero</span>
        </div>
        @forelse ($advice['actions'] as $item)
            <div class="ladv-item" wire:key="ladv-item-{{ md5($item['title']) }}">
                <span class="ladv-sev {{ $item['severity'] }}">{{ $severityLabels[$item['severity']] }}</span>
                <div style="min-width: 0;">
                    <div class="ladv-title"><span class="ladv-area">{{ $item['area'] }}</span>{{ $item['title'] }}</div>
                    <div class="ladv-text">{{ $item['detail'] }}</div>
                </div>
                <div>
                    @if ($actions && $item['cta'] !== null)
                        @if ($item['cta']['type'] === 'copy')
                            @include('live-presence.partials.copy-button', ['text' => $item['cta']['value'], 'label' => $item['cta']['label']])
                        @elseif ($item['cta']['type'] === 'link')
                            <a class="ladv-cta" href="{{ $item['cta']['value'] }}" wire:navigate>{{ $item['cta']['label'] }} →</a>
                        @else
                            <a class="ladv-cta" href="{{ $item['cta']['value'] }}">{{ $item['cta']['label'] }} ↓</a>
                        @endif
                    @endif
                </div>
            </div>
        @empty
            <div class="ladv-ok">Todo en orden: no hay nada que atender.</div>
        @endforelse
    </div>
</div>
