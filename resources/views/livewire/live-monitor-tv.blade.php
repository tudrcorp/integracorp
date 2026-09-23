<div class="tv" wire:poll.3s>
    <style>
        .tv { padding: 24px 32px; display: flex; flex-direction: column; gap: 16px; }
        .tv-top { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .tv-brand { display: flex; align-items: center; gap: 14px; }
        .tv-brand img { height: 42px; }
        .tv-brand h1 { margin: 0; font-size: 28px; font-weight: 800; }
        .tv-live { display: inline-flex; align-items: center; gap: 10px; font-size: 18px; color: #94a3b8; }
        .tv-dot { width: 12px; height: 12px; border-radius: 999px; background: #16a34a; animation: tv-pulse 1.8s infinite; }
        @keyframes tv-pulse { 70% { box-shadow: 0 0 0 10px rgba(22, 163, 74, 0); } 0% { box-shadow: 0 0 0 0 rgba(22, 163, 74, .45); } }
        .tv-clock { font-size: 34px; font-weight: 800; font-variant-numeric: tabular-nums; }
        .tv-card { background: #0b1220; border: 1px solid #1f2a44; border-radius: 16px; padding: 14px 16px; }
        .tv-title { font-size: 13px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; margin-bottom: 8px; }
        .tv-users { width: 100%; border-collapse: collapse; font-size: 16px; }
        .tv-users td { padding: 8px 6px; border-bottom: 1px solid #1f2a44; vertical-align: middle; }
        .tv-badge { display: inline-block; border-radius: 999px; padding: 2px 10px; font-size: 13px; font-weight: 700; background: rgba(14, 165, 233, .15); color: #38bdf8; }
        .tv-badge.pwa { background: rgba(124, 58, 237, .18); color: #a78bfa; }
        .tv-muted { color: #94a3b8; font-size: 14px; }
        .tv-dot-sm { display: inline-block; width: 10px; height: 10px; border-radius: 999px; background: #64748b; margin-right: 6px; }
        .tv-dot-sm.good { background: #16a34a; } .tv-dot-sm.fair { background: #d97706; } .tv-dot-sm.poor { background: #dc2626; }
    </style>

    <div class="tv-top">
        <div class="tv-brand">
            <img src="{{ asset('image/logoTDG.png') }}" alt="Tu Dr. Group">
            <h1>Monitor en vivo</h1>
            <span class="tv-live"><span class="tv-dot"></span>En vivo · {{ $refreshedAt }}</span>
        </div>
        <div class="tv-clock" x-data="{ now: '' }" x-init="const tick = () => now = new Date().toLocaleTimeString('es-VE', { hour12: false }); tick(); setInterval(tick, 1000)" x-text="now" wire:ignore></div>
    </div>

    @include('live-presence.partials.security-panel', ['security' => $security, 'kpis' => $kpis, 'health' => $health, 'tv' => true, 'actions' => false])

    <div class="tv-card">
        <div class="tv-title">Usuarios conectados · {{ $totalSessions }} {{ $totalSessions === 1 ? 'sesión' : 'sesiones' }}{{ $totalSessions > count($sessions) ? ' (se muestran las '.count($sessions).' más recientes)' : '' }}</div>
        @if ($sessions === [])
            <div class="tv-muted">No hay usuarios conectados en este momento.</div>
        @else
            <table class="tv-users">
                @foreach ($sessions as $session)
                    <tr wire:key="tv-session-{{ $session['session_key'] }}">
                        <td><strong>{{ $session['user_name'] }}</strong><div class="tv-muted">{{ $session['user_email'] }}</div></td>
                        <td><span class="tv-badge {{ $session['is_pwa'] ? 'pwa' : '' }}">{{ $session['panel_label'] }}</span> {{ $session['page_label'] }}
                            @if ($session['last_action'] !== '')<div class="tv-muted">⚡ {{ \Illuminate\Support\Str::limit($session['last_action'], 70) }}</div>@endif
                        </td>
                        <td>{{ $session['flag'] }} {{ $session['location'] }}<div class="tv-muted">{{ $session['ip'] }}</div></td>
                        <td>{{ $session['browser'] }}<div class="tv-muted">{{ $session['os'] }}</div></td>
                        <td><span class="tv-dot-sm {{ $session['rtt_level'] }}"></span>{{ $session['rtt_ms'] !== null ? $session['rtt_ms'].' ms' : '—' }}</td>
                        <td class="tv-muted">{{ $session['last_seen_ago'] }}<br>{{ $session['visible'] ? 'activa' : 'segundo plano' }}</td>
                    </tr>
                @endforeach
            </table>
        @endif
    </div>
</div>
