@php
    /** @var array<string, mixed>|null $diagnosis */
    $severityLabels = ['danger' => 'Impide entrar', 'warning' => 'Revisar', 'ok' => 'Todo en orden'];
@endphp

<style>
    .lad { --lad-border: rgba(15, 23, 42, .1); --lad-muted: #64748b; --lad-soft: rgba(15, 23, 42, .03); font-size: 13px; }
    .dark .lad { --lad-border: rgba(255, 255, 255, .1); --lad-muted: #94a3b8; --lad-soft: rgba(255, 255, 255, .03); }
    .lad-empty { padding: 18px; border: 1px dashed var(--lad-border); border-radius: 12px; color: var(--lad-muted); text-align: center; }
    .lad-stack { display: flex; flex-direction: column; gap: 12px; }
    .lad-finding { display: flex; gap: 10px; padding: 10px 12px; border-radius: 12px; border: 1px solid var(--lad-border); background: var(--lad-soft); }
    .lad-finding.danger { border-color: rgba(220, 38, 38, .35); background: rgba(220, 38, 38, .06); }
    .lad-finding.warning { border-color: rgba(217, 119, 6, .35); background: rgba(217, 119, 6, .06); }
    .lad-finding.ok { border-color: rgba(22, 163, 74, .35); background: rgba(22, 163, 74, .06); }
    .lad-dot { flex-shrink: 0; width: 8px; height: 8px; margin-top: 6px; border-radius: 999px; background: #16a34a; }
    .lad-finding.danger .lad-dot { background: #dc2626; }
    .lad-finding.warning .lad-dot { background: #d97706; }
    .lad-tag { font-size: 10px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--lad-muted); }
    .lad-card { border: 1px solid var(--lad-border); border-radius: 12px; overflow: hidden; }
    .lad-card-head { padding: 8px 12px; font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--lad-muted); border-bottom: 1px solid var(--lad-border); }
    .lad-row { display: flex; justify-content: space-between; gap: 12px; padding: 8px 12px; border-top: 1px solid var(--lad-border); }
    .lad-row:first-of-type { border-top: 0; }
    .lad-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; white-space: nowrap; }
    .lad-muted { color: var(--lad-muted); }
    .lad-grid { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
    .lad-link { color: #0284c7; font-weight: 700; }
    .lad-bad { color: #dc2626; font-weight: 700; }
</style>

<div class="lad">
    @if ($diagnosis === null)
        <div class="lad-empty">Escriba un correo completo para ver el diagnóstico.</div>
    @else
        <div class="lad-stack">
            @foreach ($diagnosis['findings'] as $finding)
                <div class="lad-finding {{ $finding['severity'] }}">
                    <span class="lad-dot"></span>
                    <div style="min-width: 0;">
                        <div class="lad-tag">{{ $severityLabels[$finding['severity']] ?? '' }}</div>
                        <strong>{{ $finding['title'] }}</strong>
                        <div class="lad-muted" style="margin-top: 2px;">{{ $finding['detail'] }}</div>
                    </div>
                </div>
            @endforeach

            @if ($diagnosis['user'] === null && $diagnosis['suggestions'] !== [])
                <div class="lad-card">
                    <div class="lad-card-head">¿Quiso decir?</div>
                    @foreach ($diagnosis['suggestions'] as $suggestion)
                        <div class="lad-row">
                            <span>{{ $suggestion['name'] }}</span>
                            <span class="lad-mono">{{ $suggestion['email'] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($diagnosis['user'] !== null)
                <div class="lad-grid">
                    <div class="lad-card">
                        <div class="lad-card-head">Usuario</div>
                        <div class="lad-row"><span class="lad-muted">Nombre</span><span>{{ $diagnosis['user']['name'] }}</span></div>
                        <div class="lad-row"><span class="lad-muted">ID</span><span class="lad-mono">#{{ $diagnosis['user']['id'] }}</span></div>
                        <div class="lad-row"><span class="lad-muted">Estatus</span><span class="{{ $diagnosis['user']['status'] === 'ACTIVO' ? '' : 'lad-bad' }}">{{ $diagnosis['user']['status'] !== '' ? $diagnosis['user']['status'] : '—' }}</span></div>
                    </div>
                    <div class="lad-card">
                        <div class="lad-card-head">Entra por</div>
                        @forelse ($diagnosis['panels'] as $panel)
                            <div class="lad-row">
                                <span>{{ $panel['label'] }}</span>
                                <a class="lad-link" href="{{ $panel['url'] }}" target="_blank" rel="noopener">Enlace de login →</a>
                            </div>
                        @empty
                            <div class="lad-row"><span class="lad-bad">Ningún panel</span></div>
                        @endforelse
                    </div>
                </div>
            @endif

            @if ($diagnosis['ips'] !== [])
                <div class="lad-card">
                    <div class="lad-card-head">IPs de hoy</div>
                    @foreach ($diagnosis['ips'] as $ip)
                        <div class="lad-row">
                            <span class="lad-mono">{{ $ip['ip'] }}</span>
                            <span class="{{ $ip['blocked'] ? 'lad-bad' : 'lad-muted' }}">{{ $ip['blocked'] ? 'En lista negra' : 'Sin bloqueo' }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="lad-card">
                <div class="lad-card-head">Últimos intentos de hoy</div>
                @forelse ($diagnosis['events'] as $event)
                    <div class="lad-row">
                        <div style="min-width: 0;">
                            <strong>{{ $event['title'] }}</strong>
                            <div class="lad-muted" style="word-break: break-word;">{{ $event['detail'] }}</div>
                        </div>
                        <span class="lad-muted lad-mono">{{ $event['time'] }}</span>
                    </div>
                @empty
                    <div class="lad-row"><span class="lad-muted">Sin intentos registrados hoy.</span></div>
                @endforelse
            </div>
        </div>
    @endif
</div>
