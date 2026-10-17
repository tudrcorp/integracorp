@php
    use App\Support\UserActivity\UserActivityClock as Clock;
    use App\Support\UserActivity\UserActivityState as State;
    use App\Support\UserActivity\Workday\WorkMinuteKind as Kind;
    use App\Support\UserActivity\Workday\WorkdayConfig;

    $lunchLimit = WorkdayConfig::lunchMinutes();
    $meetingWarning = WorkdayConfig::meetingWarningMinutes();

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
    $usageClass = fn (?int $usage): string => $usage === null ? 'none' : ($usage >= 70 ? 'good' : ($usage >= 40 ? 'fair' : 'poor'));
    $clock = fn (int $minutes): string => sprintf('%d:%02d', intdiv(max(0, $minutes), 60), max(0, $minutes) % 60);
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
        'lunch' => '🍽️',
        'meeting' => '👥',
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
        .uam-kpis.four { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        @media (max-width: 1100px) { .uam-kpis.four { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 560px) { .uam-kpis.four { grid-template-columns: minmax(0, 1fr); } }
        .uam-kpi { padding: 14px 16px; border-radius: 16px; border: 1px solid var(--uam-border); background: var(--uam-bg); text-align: left; cursor: default; }
        button.uam-kpi { cursor: pointer; font: inherit; color: inherit; }
        button.uam-kpi:hover { border-color: var(--uam-accent); }
        .uam-kpi.on { box-shadow: 0 0 0 2px var(--uam-accent); }
        .uam-help-btn { position: relative; width: 18px; height: 18px; padding: 0; border-radius: 999px; border: 1px solid var(--uam-border); background: var(--uam-soft); color: var(--uam-muted); font-size: 11px; font-weight: 800; line-height: 1; display: inline-flex; align-items: center; justify-content: center; cursor: help; vertical-align: middle; margin-left: 4px; }
        .uam-help-btn::before { content: ''; position: absolute; inset: -13px; }
        .uam-help-btn:hover, .uam-help-btn:focus-visible { color: var(--uam-text); border-color: var(--uam-muted); outline: none; }
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
        .uam-hours { position: relative; display: block; min-width: 220px; height: 13px; margin-top: 6px; font-size: 10px; font-weight: 600; line-height: 13px; letter-spacing: 0; text-transform: none; color: var(--uam-muted); font-variant-numeric: tabular-nums; }
        .uam-hours.big { height: 14px; margin-top: 4px; font-size: 10.5px; }
        .uam-hours span { position: absolute; top: 0; transform: translateX(-50%); white-space: nowrap; }
        .uam-hours span.first { transform: none; }
        .uam-hours span.last { transform: translateX(-100%); }
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
        .uam-dayline { height: 16px; }
        .uam-usage.none { color: var(--uam-muted); font-weight: 600; }
        .uam-badges { display: flex; flex-wrap: wrap; gap: 4px; justify-content: flex-end; margin-top: 4px; }
        .uam-badge { display: inline-flex; align-items: center; gap: 4px; border-radius: 999px; padding: 1px 8px; font-size: 11px; font-weight: 700; white-space: nowrap; }
        .uam-badge.lunch { background: rgba(59, 130, 246, .14); color: #1d4ed8; }
        .uam-badge.over { background: rgba(249, 115, 22, .16); color: #c2410c; }
        .uam-badge.absent { background: rgba(239, 68, 68, .14); color: #b91c1c; }
        .uam-badge.extra { background: rgba(217, 70, 239, .14); color: #a21caf; }
        .uam-badge.guard { background: rgba(79, 70, 229, .14); color: #4338ca; }
        .uam-badge.kind { background: var(--uam-chip); color: var(--uam-muted); }
        .dark .uam-badge.lunch { color: #93c5fd; } .dark .uam-badge.over { color: #fdba74; } .dark .uam-badge.absent { color: #fca5a5; }
        .dark .uam-badge.extra { color: #f0abfc; } .dark .uam-badge.guard { color: #a5b4fc; }
        .uam-state.lunch { background: rgba(59, 130, 246, .15); color: #1d4ed8; }
        .uam-state.over { background: rgba(249, 115, 22, .17); color: #c2410c; }
        .uam-state.absent { background: rgba(239, 68, 68, .14); color: #b91c1c; }
        .uam-state.meeting { background: rgba(13, 148, 136, .15); color: #0f766e; }
        .dark .uam-state.meeting { color: #5eead4; }
        .uam-badge.meeting { background: rgba(13, 148, 136, .14); color: #0f766e; }
        .dark .uam-badge.meeting { color: #5eead4; }
        .uam-table tr.row.p-meeting > td:first-child { box-shadow: inset 4px 0 0 #0d9488; }
        .uam-table tr.row.p-meeting_long > td:first-child { box-shadow: inset 4px 0 0 #f97316; }
        .uam-meetings { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; font-size: 12.5px; }
        .uam-meetings li { display: grid; grid-template-columns: 130px 1fr auto; gap: 8px; align-items: center; padding: 6px 10px; border-radius: 10px; background: var(--uam-bg); border: 1px solid var(--uam-border); border-left: 4px solid #0d9488; }
        .dark .uam-state.lunch { color: #93c5fd; } .dark .uam-state.over { color: #fdba74; } .dark .uam-state.absent { color: #fca5a5; }
        .uam-table tr.row.p-lunch > td:first-child { box-shadow: inset 4px 0 0 #3b82f6; }
        .uam-table tr.row.p-lunch_over > td:first-child { box-shadow: inset 4px 0 0 #f97316; }
        .uam-table tr.row.p-absent > td:first-child { box-shadow: inset 4px 0 0 #ef4444; }
        .uam-table tr.row.p-absent > td { opacity: 1; }
        .uam-jornada { display: flex; flex-direction: column; gap: 10px; padding: 14px; border-radius: 14px; border: 1px solid var(--uam-border); background: var(--uam-soft); }
        .uam-jornada-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; }
        .uam-absences { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; font-size: 12.5px; }
        .uam-absences li { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 6px 10px; border-radius: 10px; background: var(--uam-bg); border: 1px solid var(--uam-border); }
        .uam-legend .band { width: 18px; height: 10px; border-radius: 3px; display: inline-block; }
        .uam-tip-target { cursor: help; transition: opacity .12s; }
        .uam-dayline:hover .uam-tip-target:not(:hover) { opacity: .82; }
        .uam-tip { position: fixed; left: -9999px; top: -9999px; z-index: 60; width: max-content; max-width: 300px; pointer-events: none; padding: 11px 13px 11px 15px; border-radius: 12px;
                   background: var(--uam-bg); color: var(--uam-text); border: 1px solid var(--uam-border); box-shadow: 0 18px 40px -12px rgba(15, 23, 42, .35), 0 2px 6px rgba(15, 23, 42, .08);
                   display: flex; flex-direction: column; gap: 6px; font-size: 12.5px; line-height: 1.35; overflow: hidden; }
        .uam-tip::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: var(--tip-color, #94a3b8); }
        .dark .uam-tip { box-shadow: 0 18px 40px -12px rgba(0, 0, 0, .7), 0 0 0 1px rgba(148, 163, 184, .08); }
        .uam-tip-head { display: flex; align-items: center; gap: 8px; }
        .uam-tip-head strong { font-size: 13.5px; font-weight: 800; }
        .uam-tip-dot { width: 10px; height: 10px; border-radius: 999px; flex-shrink: 0; background: var(--tip-color, #94a3b8); box-shadow: 0 0 0 3px color-mix(in srgb, var(--tip-color, #94a3b8) 25%, transparent); }
        .uam-tip-duration { margin-left: auto; padding: 2px 8px; border-radius: 999px; font-size: 11.5px; font-weight: 800; font-variant-numeric: tabular-nums;
                            background: color-mix(in srgb, var(--tip-color, #94a3b8) 16%, transparent); color: var(--uam-text); white-space: nowrap; }
        .uam-tip-range { display: flex; align-items: center; gap: 6px; font-size: 14px; font-weight: 700; font-variant-numeric: tabular-nums; }
        .uam-tip-range svg { width: 15px; height: 15px; color: var(--uam-muted); flex-shrink: 0; }
        .uam-tip-note { padding: 6px 8px; border-radius: 8px; background: var(--uam-soft); border: 1px solid var(--uam-border); font-weight: 600; }
        .uam-tip-impact { display: inline-flex; align-self: flex-start; align-items: center; gap: 5px; padding: 3px 9px; border-radius: 999px; font-size: 11.5px; font-weight: 700; }
        .uam-tip-impact::before { content: ''; width: 6px; height: 6px; border-radius: 999px; background: currentColor; }
        .uam-tip-impact.is-good { background: rgba(22, 163, 74, .13); color: #15803d; }
        .uam-tip-impact.is-bad { background: rgba(239, 68, 68, .13); color: #b91c1c; }
        .uam-tip-impact.is-extra { background: rgba(217, 70, 239, .13); color: #a21caf; }
        .uam-tip-impact.is-neutral { background: rgba(100, 116, 139, .13); color: var(--uam-muted); }
        .dark .uam-tip-impact.is-good { color: #86efac; } .dark .uam-tip-impact.is-bad { color: #fca5a5; } .dark .uam-tip-impact.is-extra { color: #f0abfc; }
        .uam-tip-desc { color: var(--uam-muted); font-size: 11.5px; }
        [x-cloak] { display: none !important; }
        @media (max-width: 768px) { .uam-search { margin-left: 0; width: 100%; min-width: 0; } }
    </style>

    @php
        /**
         * Barra del día. Con jornada: de fondo la franja laboral (verde tenue) y las
         * guardias (índigo tenue), y encima cada tramo con el color de su categoría.
         * Sin jornada (días anteriores, agentes): los estados de conexión de siempre.
         */
        /**
         * Datos del tooltip de un tramo: van en atributos `data-tip-*` y los dibuja
         * una sola tarjeta flotante (ver `.uam-tip`), no el `<title>` del navegador.
         */
        $tip = function (string $label, string $color, int $from, int $to, string $note = '', string $description = '', array $impact = ['', 'neutral']): string {
            $minutes = max(1, $to - $from);

            return ' data-tip-label="'.e($label).'" data-tip-color="'.e($color).'"'
                .' data-tip-range="'.e(Clock::formatMinute($from).' – '.Clock::formatMinute(min(1440, $to))).'"'
                .' data-tip-duration="'.e(Clock::formatDuration($minutes)).'"'
                .' data-tip-note="'.e($note).'" data-tip-desc="'.e($description).'"'
                .' data-tip-impact="'.e($impact[0]).'" data-tip-tone="'.e($impact[1]).'"';
        };

        /**
         * Barra del día. Con jornada: de fondo la franja laboral (verde tenue) y las
         * guardias (índigo tenue), y encima cada tramo con el color de su categoría.
         * Sin jornada (días anteriores, agentes): los estados de conexión de siempre.
         */
        $dayline = function (array $segments, bool $big = false, bool $showNow = false, array $bands = []) use ($nowMinute, $tip): string {
            $rects = '';
            foreach ($bands as $band) {
                [$fill, $edge, $bandLabel, $bandDescription] = match ($band['type']) {
                    'work' => ['rgba(22,163,74,.13)', 'rgba(22,163,74,.55)', 'Franja de jornada', 'Horario en que debe estar trabajando. Lo que aún no pasa se ve vacío.'],
                    'guard' => ['rgba(79,70,229,.15)', 'rgba(79,70,229,.55)', 'Franja de guardia', 'Guardia asignada: se mide la presencia, no se penaliza la inactividad.'],
                    default => ['rgba(217,70,239,.12)', 'rgba(217,70,239,.55)', 'Guardia en día libre', 'Guardia en fin de semana o feriado: su uso cuenta como horas extra.'],
                };
                $rects .= '<rect class="uam-tip-target" x="'.$band['from'].'" y="0" width="'.max(1, $band['to'] - $band['from']).'" height="10" fill="'.$fill.'"'
                    .$tip($bandLabel, $edge, $band['from'], $band['to'], $band['type'] === 'work' ? '' : $band['label'], $bandDescription).'></rect>'
                    .'<rect x="'.$band['from'].'" y="0" width="2" height="10" fill="'.$edge.'" pointer-events="none"></rect>'
                    .'<rect x="'.max(0, $band['to'] - 2).'" y="0" width="2" height="10" fill="'.$edge.'" pointer-events="none"></rect>';
            }
            foreach ($segments as $segment) {
                $kind = $segment['kind'] ?? null;
                $color = $kind instanceof Kind ? $kind->color() : $segment['state']->color();
                $label = $kind instanceof Kind ? $kind->label() : $segment['state']->label();
                $description = $kind instanceof Kind ? $kind->description() : $segment['state']->description();
                $note = trim((string) ($segment['note'] ?? ''));
                $impact = $kind instanceof Kind ? $kind->impact() : ['', 'neutral'];

                if ($kind === Kind::Meeting && str_contains($note, 'horas extra')) {
                    $impact = ['Suma a horas extra', 'extra'];
                    $note = trim(str_replace('· cuenta como horas extra', '', $note));
                }

                $rects .= '<rect class="uam-tip-target" x="'.$segment['from'].'" y="0" width="'.($segment['to'] - $segment['from'] + 1).'" height="10" fill="'.$color.'"'
                    .$tip($label, $color, $segment['from'], $segment['to'] + 1, $note, $description, $impact).'></rect>';
            }
            if ($showNow) {
                $rects .= '<rect class="uam-tip-target" x="'.max(0, $nowMinute - 2).'" y="0" width="5" height="10" fill="#0ea5e9"'
                    .' data-tip-label="Ahora" data-tip-color="#0ea5e9" data-tip-range="'.e(Clock::formatMinute($nowMinute)).'" data-tip-duration="" data-tip-note="" data-tip-desc="Lo que está a la derecha aún no ha pasado." data-tip-impact="" data-tip-tone="neutral"></rect>';
            }
            /** Guías cada 3 h, más marcadas a las 6, 12 y 18 h: coinciden con la escala de horas. */
            foreach ([180, 360, 540, 720, 900, 1080, 1260] as $guide) {
                $rects .= '<rect x="'.$guide.'" y="0" width="1.5" height="10" pointer-events="none" fill="'.($guide % 360 === 0 ? 'rgba(100,116,139,.35)' : 'rgba(100,116,139,.18)').'"></rect>';
            }

            return '<svg class="uam-dayline'.($big ? ' big' : '').'" viewBox="0 0 1440 10" preserveAspectRatio="none" role="img" aria-label="Actividad del día">'.$rects.'</svg>';
        };

        /** Escala de horas alineada con la barra del día: cada etiqueta cae en su hora exacta. */
        $hourScale = function (bool $big = false): string {
            $labels = '';
            foreach ([0, 3, 6, 9, 12, 15, 18, 21, 24] as $hour) {
                $label = match (true) {
                    $hour === 0 || $hour === 24 => '12a',
                    $hour < 12 => $hour.'a',
                    $hour === 12 => '12p',
                    default => ($hour - 12).'p',
                };
                $class = $hour === 0 ? 'first' : ($hour === 24 ? 'last' : '');
                $labels .= '<span class="'.$class.'" style="left: '.round($hour / 24 * 100, 4).'%;">'.$label.'</span>';
            }

            return '<span class="uam-hours'.($big ? ' big' : '').'" aria-hidden="true">'.$labels.'</span>';
        };
    @endphp

    <div
        class="uam"
        @if ($tab === 'live' && ! $paused) wire:poll.5s.visible @endif
        x-data="{
            tip: { show: false, label: '', range: '', duration: '', note: '', desc: '', impact: '', tone: 'neutral' },
            tipTarget: null,
            {{-- Una sola tarjeta para toda la página. El color y la posición se ponen
                 directo en el elemento: un x-bind:style reemplazaría el style entero
                 en cada movimiento y la tarjeta saltaría (se veía doble). --}}
            showTip(event) {
                const target = event.target.closest ? event.target.closest('.uam-tip-target') : null;

                if (! target) {
                    this.hideTip();
                    return;
                }

                if (target !== this.tipTarget) {
                    const data = target.dataset;
                    this.tipTarget = target;
                    this.tip = { show: true, label: data.tipLabel, range: data.tipRange, duration: data.tipDuration, note: data.tipNote, desc: data.tipDesc, impact: data.tipImpact, tone: data.tipTone };
                    this.$refs.tip.style.setProperty('--tip-color', data.tipColor || '#94a3b8');
                }

                this.$nextTick(() => this.placeTip(event));
            },
            hideTip() {
                if (! this.tip.show) return;

                this.tip.show = false;
                this.tipTarget = null;
                this.$refs.tip.style.left = '-9999px';
                this.$refs.tip.style.top = '-9999px';
            },
            placeTip(event) {
                const el = this.$refs.tip;

                if (! el || ! this.tip.show) return;

                const width = el.offsetWidth;
                const height = el.offsetHeight;
                let x = event.clientX + 16;
                let y = event.clientY + 18;

                if (x + width > window.innerWidth - 8) x = event.clientX - width - 16;
                if (y + height > window.innerHeight - 8) y = event.clientY - height - 14;

                el.style.left = Math.max(8, x) + 'px';
                el.style.top = Math.max(8, y) + 'px';
            },
        }"
        x-on:mousemove="showTip($event)"
        x-on:mouseleave="hideTip()"
        x-on:scroll.window.passive="hideTip()"
    >
        <div class="uam-tip" wire:ignore x-ref="tip" x-show="tip.show" x-cloak role="tooltip">
            <div class="uam-tip-head">
                <i class="uam-tip-dot"></i>
                <strong x-text="tip.label"></strong>
                <span class="uam-tip-duration" x-show="tip.duration" x-text="tip.duration"></span>
            </div>
            <div class="uam-tip-range">
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .2.08.39.22.53l3 3a.75.75 0 1 0 1.06-1.06l-2.78-2.78V5Z" clip-rule="evenodd"/></svg>
                <span x-text="tip.range"></span>
            </div>
            <div class="uam-tip-note" x-show="tip.note" x-text="tip.note"></div>
            <div class="uam-tip-impact" x-show="tip.impact" x-bind:class="'is-' + tip.tone" x-text="tip.impact"></div>
            <div class="uam-tip-desc" x-show="tip.desc" x-text="tip.desc"></div>
        </div>

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

                <div class="uam-kpis {{ $live['workday'] ? 'four' : '' }}">
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
                    @unless ($live['workday'])
                        <button type="button" class="uam-kpi {{ $liveState === 'background' ? 'on' : '' }}" wire:click="filterState('background')">
                            <div class="uam-kpi-label"><i style="width: 8px; height: 8px; border-radius: 999px; background: var(--uam-bgtab); display: inline-block;"></i> En otra pestaña</div>
                            <div class="uam-kpi-value">{{ $live['kpis']['background'] }}</div>
                            <div class="uam-kpi-hint">Sistema minimizado u oculto</div>
                        </button>
                    @endunless
                    <button type="button" class="uam-kpi {{ $liveState === 'all' ? 'on' : '' }}" wire:click="filterState('all')">
                        <div class="uam-kpi-label">👥 Trabajaron hoy</div>
                        <div class="uam-kpi-value">{{ $live['kpis']['today'] }}</div>
                        <div class="uam-kpi-hint">{{ $live['kpis']['offline'] }} ya no están conectados</div>
                    </button>
                    @if ($live['workday'])
                        <button type="button" class="uam-kpi {{ $liveState === 'lunch' ? 'on' : '' }}" wire:click="filterState('lunch')">
                            <div class="uam-kpi-label"><i style="width: 8px; height: 8px; border-radius: 999px; background: {{ Kind::Lunch->color() }}; display: inline-block;"></i> En almuerzo</div>
                            <div class="uam-kpi-value" style="color: {{ Kind::Lunch->color() }};">{{ $live['kpis']['lunch'] }}</div>
                            <div class="uam-kpi-hint" @if ($live['kpis']['lunch_over'] > 0) style="color: #c2410c; font-weight: 700;" @endif>
                                {{ $live['kpis']['lunch_over'] > 0 ? '⏰ '.$live['kpis']['lunch_over'].' ya pasaron de la hora' : 'Declararon su hora de almuerzo' }}
                            </div>
                        </button>
                        <button type="button" class="uam-kpi {{ $liveState === 'meeting' ? 'on' : '' }}" wire:click="filterState('meeting')">
                            <div class="uam-kpi-label"><i style="width: 8px; height: 8px; border-radius: 999px; background: {{ Kind::Meeting->color() }}; display: inline-block;"></i> En reunión</div>
                            <div class="uam-kpi-value" style="color: {{ Kind::Meeting->color() }};">{{ $live['kpis']['meeting'] }}</div>
                            <div class="uam-kpi-hint" @if ($live['kpis']['meeting_long'] > 0) style="color: #c2410c; font-weight: 700;" @endif>
                                {{ $live['kpis']['meeting_long'] > 0 ? '⏰ '.$live['kpis']['meeting_long'].' llevan más de '.$fmt($meetingWarning) : 'Declararon que están en reunión' }}
                            </div>
                        </button>
                        <button type="button" class="uam-kpi {{ $liveState === 'absent' ? 'on' : '' }}" wire:click="filterState('absent')">
                            <div class="uam-kpi-label"><i style="width: 8px; height: 8px; border-radius: 999px; background: {{ Kind::WorkAbsent->color() }}; display: inline-block;"></i> Ausentes en jornada</div>
                            <div class="uam-kpi-value" style="color: {{ $live['kpis']['absent'] > 0 ? Kind::WorkAbsent->color() : 'inherit' }};">{{ $live['kpis']['absent'] }}</div>
                            <div class="uam-kpi-hint">En su horario y sin el sistema abierto</div>
                        </button>
                        <div class="uam-kpi">
                            <div class="uam-kpi-label">💼 Uso de jornada hoy @include('filament.business.pages.partials.user-activity-usage-help')</div>
                            <div class="uam-kpi-value"><span class="uam-usage {{ $usageClass($live['kpis']['work_usage']) }}" style="font-size: 26px;">{{ $live['kpis']['work_usage'] !== null ? $live['kpis']['work_usage'].'%' : '—' }}</span></div>
                            <div class="uam-kpi-hint">{{ $fmt($live['kpis']['work_active']) }} trabajados (activo + reuniones) de {{ $fmt($live['kpis']['work_base']) }} de jornada transcurrida</div>
                        </div>
                        <div class="uam-kpi">
                            <div class="uam-kpi-label"><i style="width: 8px; height: 8px; border-radius: 999px; background: {{ Kind::Overtime->color() }}; display: inline-block;"></i> Horas extra hoy</div>
                            <div class="uam-kpi-value" style="color: {{ Kind::Overtime->color() }};">{{ $fmt($live['kpis']['overtime']) }}</div>
                            <div class="uam-kpi-hint">Uso fuera de horario, fin de semana o feriado</div>
                        </div>
                    @else
                        <div class="uam-kpi">
                            <div class="uam-kpi-label">⚡ Uso real hoy @include('filament.business.pages.partials.user-activity-usage-help')</div>
                            <div class="uam-kpi-value"><span class="uam-usage {{ $usageClass($live['kpis']['usage']) }}" style="font-size: 26px;">{{ $live['kpis']['usage'] }}%</span></div>
                            <div class="uam-kpi-hint">{{ $fmt($live['kpis']['active_minutes']) }} activos de {{ $fmt($live['kpis']['online_minutes']) }} conectados</div>
                        </div>
                    @endif
                </div>

                <div class="uam-filters">
                    @foreach (['connected' => 'Conectados', 'active' => 'Activos', 'idle' => 'Inactivos', 'background' => 'Otra pestaña', 'offline' => 'Desconectados', ...($live['workday'] ? ['lunch' => '🍽️ En almuerzo', 'meeting' => '👥 En reunión', 'absent' => 'Ausentes en jornada'] : []), 'all' => 'Todos los de hoy'] as $key => $label)
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
                            @if ($liveState === 'lunch' && $search === '' && $userType === 'all')
                                Nadie está en almuerzo en este momento.
                            @elseif ($liveState === 'meeting' && $search === '' && $userType === 'all')
                                Nadie está en reunión en este momento.
                            @elseif ($liveState === 'absent' && $search === '' && $userType === 'all')
                                ✅ Nadie está ausente en su jornada en este momento.
                            @else
                                {{ $search !== '' || $userType !== 'all' || $liveState !== 'connected' ? 'Nadie coincide con el filtro.' : 'No hay nadie conectado en este momento.' }}
                            @endif
                        </div>
                    @else
                        <table class="uam-table">
                            <thead>
                                <tr>
                                    <th>Persona</th>
                                    <th>Estado</th>
                                    <th>Dónde está</th>
                                    <th class="num">Activo hoy</th>
                                    <th class="num">{{ $live['workday'] ? 'Uso de jornada' : 'Uso real' }} @include('filament.business.pages.partials.user-activity-usage-help')</th>
                                    <th style="min-width: 300px;">Su día (0 h – 24 h){!! $hourScale() !!}</th>
                                    <th>Conexión</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($live['rows'] as $row)
                                    @php $key = $stateKey($row['state']); @endphp
                                    @php
                                        $work = $row['workday'];
                                        $presence = $row['presence'];
                                        $lunchMinutes = $row['lunch'] !== null && $row['lunch']['end'] === null ? max(0, $nowMinute - $row['lunch']['start']) : 0;
                                    @endphp
                                    <tr wire:key="live-{{ $row['user_id'] }}" class="row {{ $key === 'offline' && $presence === null ? 'offline' : '' }} {{ $presence ? 'p-'.$presence : '' }} {{ $selectedUserId === $row['user_id'] ? 'selected' : '' }}" wire:click="selectUser({{ $row['user_id'] }})">
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
                                            @if ($presence === 'lunch')
                                                <span class="uam-state lunch" title="Declaró su hora de almuerzo"><i style="background: {{ Kind::Lunch->color() }};"></i>🍽️ En almuerzo · {{ $clock($lunchMinutes) }}</span>
                                            @elseif ($presence === 'lunch_over')
                                                <span class="uam-state over" title="Pasó de los {{ $lunchLimit }} minutos de almuerzo y no ha marcado el regreso"><i style="background: {{ Kind::LunchExcess->color() }};"></i>⏰ Almuerzo excedido +{{ $clock($lunchMinutes - $lunchLimit) }}</span>
                                            @elseif (in_array($presence, ['meeting', 'meeting_long'], true))
                                                @php $meetingMinutes = max(0, $nowMinute - $row['meeting']['start']); @endphp
                                                <span class="uam-state {{ $presence === 'meeting_long' ? 'over' : 'meeting' }}" title="{{ $presence === 'meeting_long' ? 'Reunión abierta hace más de '.$fmt($meetingWarning).': puede que haya olvidado marcar el fin' : 'Declaró que está en reunión' }}"><i style="background: {{ $presence === 'meeting_long' ? Kind::LunchExcess->color() : Kind::Meeting->color() }};"></i>👥 {{ $presence === 'meeting_long' ? 'Reunión larga' : 'En reunión' }} · {{ $clock($meetingMinutes) }}</span>
                                            @elseif ($presence === 'absent')
                                                <span class="uam-state absent" title="Está en su horario y no tiene el sistema abierto"><i style="background: {{ Kind::WorkAbsent->color() }};"></i>Ausente en jornada</span>
                                            @else
                                                <span class="uam-state {{ $key }}" title="{{ $row['state']->description() }}"><i style="background: {{ $row['state']->color() }};"></i>{{ $row['state']->label() }}</span>
                                            @endif
                                            <div class="uam-sub" style="margin-top: 3px;">
                                                @if (in_array($presence, ['lunch', 'lunch_over'], true))
                                                    Salió a las {{ Clock::formatMinute($row['lunch']['start']) }}
                                                @elseif (in_array($presence, ['meeting', 'meeting_long'], true))
                                                    <span title="{{ $row['meeting']['reason'] }}">«{{ \Illuminate\Support\Str::limit($row['meeting']['reason'], 40) }}» · desde {{ Clock::formatMinute($row['meeting']['start']) }}</span>
                                                @elseif ($presence === 'absent' && $row['today']['online'] === 0)
                                                    No se ha conectado hoy
                                                @elseif ($key === 'idle' && $row['last_interaction_at'])
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
                                            @if ($work !== null)
                                                @include('filament.business.pages.partials.user-activity-workday-cell', ['work' => $work, 'presence' => $presence, 'nowMinute' => $nowMinute, 'isToday' => true])
                                            @else
                                                <span class="uam-usage {{ $usageClass($row['today']['usage']) }}">
                                                    <span class="track"><span class="fill" style="width: {{ $row['today']['usage'] }}%; display: block;"></span></span>{{ $row['today']['usage'] }}%
                                                </span>
                                            @endif
                                        </td>
                                        <td>{!! $dayline($row['segments'], false, true, $row['bands']) !!}</td>
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

                @include('filament.business.pages.partials.user-activity-day-legend', ['workday' => $live['workday'], 'showNow' => true])
            @endif

            {{-- ===================== REPORTE ===================== --}}
            @if ($tab === 'report' && $report !== null)
                <div class="uam-kpis">
                    <div class="uam-kpi"><div class="uam-kpi-label">👥 Personas con actividad</div><div class="uam-kpi-value">{{ $report['totals']['users'] }}</div><div class="uam-kpi-hint">{{ $rangeLabel }}</div></div>
                    @if ($workdayEnabled)
                        <div class="uam-kpi"><div class="uam-kpi-label">💼 Uso de jornada @include('filament.business.pages.partials.user-activity-usage-help')</div><div class="uam-kpi-value"><span class="uam-usage {{ $usageClass($report['totals']['work_usage']) }}" style="font-size: 26px;">{{ $report['totals']['work_usage'] !== null ? $report['totals']['work_usage'].'%' : '—' }}</span></div><div class="uam-kpi-hint">{{ $fmt($report['totals']['work_active']) }} activos de {{ $fmt($report['totals']['work_base']) }} de jornada</div></div>
                        <div class="uam-kpi"><div class="uam-kpi-label" style="color: {{ Kind::WorkAbsent->color() }};">Ausente en jornada</div><div class="uam-kpi-value">{{ $fmt($report['totals']['work_absent']) }}</div><div class="uam-kpi-hint">En su horario sin el sistema abierto</div></div>
                        <div class="uam-kpi"><div class="uam-kpi-label" style="color: {{ Kind::LunchExcess->color() }};">Exceso de almuerzo</div><div class="uam-kpi-value">{{ $fmt($report['totals']['lunch_excess']) }}</div><div class="uam-kpi-hint">Por encima de la hora de almuerzo</div></div>
                        <div class="uam-kpi"><div class="uam-kpi-label" style="color: {{ Kind::Overtime->color() }};">Horas extra</div><div class="uam-kpi-value">{{ $fmt($report['totals']['overtime']) }}</div><div class="uam-kpi-hint">Fuera de horario, fin de semana o feriado</div></div>
                    @endif
                    <div class="uam-kpi"><div class="uam-kpi-label" style="color: var(--uam-active);">Tiempo activo</div><div class="uam-kpi-value">{{ $fmt($report['totals']['active']) }}</div><div class="uam-kpi-hint">Usando el sistema</div></div>
                    <div class="uam-kpi"><div class="uam-kpi-label" style="color: #d97706;">Abierto sin usar</div><div class="uam-kpi-value">{{ $fmt($report['totals']['idle']) }}</div><div class="uam-kpi-hint">Inactivo con el sistema a la vista</div></div>
                    <div class="uam-kpi"><div class="uam-kpi-label">En otra pestaña</div><div class="uam-kpi-value">{{ $fmt($report['totals']['background']) }}</div><div class="uam-kpi-hint">Sistema minimizado u oculto</div></div>
                    <div class="uam-kpi"><div class="uam-kpi-label">⚡ Uso real @include('filament.business.pages.partials.user-activity-usage-help')</div><div class="uam-kpi-value"><span class="uam-usage {{ $usageClass($report['totals']['usage']) }}" style="font-size: 26px;">{{ $report['totals']['usage'] }}%</span></div><div class="uam-kpi-hint">{{ number_format($report['totals']['actions'], 0, ',', '.') }} acciones · {{ number_format($report['totals']['pages'], 0, ',', '.') }} pantallas</div></div>
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
                            $th = function (string $column, string $label, bool $num = true, string $afterHtml = '') use ($sort, $direction): string {
                                $arrow = $sort === $column ? ($direction === 'asc' ? ' ▲' : ' ▼') : '';

                                return '<th class="sortable'.($num ? ' num' : '').'" wire:click="sortBy(\''.$column.'\')">'.e($label).$arrow.$afterHtml.'</th>';
                            };
                        @endphp
                        <table class="uam-table">
                            <thead>
                                <tr>
                                    {!! $th('name', 'Persona', false) !!}
                                    {!! $th('days', 'Días') !!}
                                    @if ($workdayEnabled)
                                        {!! $th('work_base', 'Jornada') !!}
                                        {!! $th('work_active', 'Activo en jornada') !!}
                                        {!! $th('work_usage', 'Uso de jornada') !!}
                                        {!! $th('work_absent', 'Ausente') !!}
                                        {!! $th('meeting', 'En reuniones') !!}
                                        {!! $th('lunch_excess', 'Exceso almuerzo') !!}
                                        {!! $th('overtime', 'Horas extra') !!}
                                        {!! $th('guard', 'Guardia') !!}
                                    @endif
                                    {!! $th('online', 'Conectado') !!}
                                    {!! $th('active', 'Activo') !!}
                                    {!! $th('idle', 'Abierto sin usar') !!}
                                    {!! $th('background', 'Otra pestaña') !!}
                                    {!! $th('usage', 'Uso real', true, ' '.view('filament.business.pages.partials.user-activity-usage-help')->render()) !!}
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
                                        @if ($workdayEnabled)
                                            <td class="num">{{ $row['work_base'] > 0 ? $fmt($row['work_base']) : '—' }}</td>
                                            <td class="num"><strong style="color: var(--uam-active);">{{ $fmt($row['work_active']) }}</strong></td>
                                            <td class="num">
                                                @if ($row['work_usage'] !== null)
                                                    <span class="uam-usage {{ $usageClass($row['work_usage']) }}"><span class="track"><span class="fill" style="width: {{ $row['work_usage'] }}%; display: block;"></span></span>{{ $row['work_usage'] }}%</span>
                                                @else
                                                    <span class="uam-usage none" title="Sin días de jornada en el rango">—</span>
                                                @endif
                                            </td>
                                            <td class="num" style="color: {{ $row['work_absent'] > 0 ? '#dc2626' : 'inherit' }};">{{ $fmt($row['work_absent']) }}</td>
                                            <td class="num" style="color: {{ $row['meeting'] > 0 ? '#0f766e' : 'inherit' }};">{{ $fmt($row['meeting']) }}</td>
                                            <td class="num" style="color: {{ $row['lunch_excess'] > 0 ? '#c2410c' : 'inherit' }};">{{ $fmt($row['lunch_excess']) }}</td>
                                            <td class="num" style="color: {{ $row['overtime'] > 0 ? '#a21caf' : 'inherit' }};">{{ $fmt($row['overtime']) }}</td>
                                            <td class="num uam-sub">{{ $row['guard'] > 0 ? $fmt($row['guard']).' · '.$row['guard_presence'].'%' : '—' }}</td>
                                        @endif
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
                <p class="uam-sub">
                    @if ($workdayEnabled)
                        «Uso de jornada» = (activo + reuniones, en jornada) ÷ jornada (8 h con almuerzo declarado; sin declararlo, esa hora también es jornada). Las reuniones fuera de horario suman a horas extra. «Guardia» = horas de guardia y % con el sistema abierto.
                    @endif
                    «Uso real» = tiempo activo ÷ tiempo conectado. «Llega» y «Se va» son el promedio de la primera y la última conexión de cada día. El resumen de hoy se actualiza cada minuto.
                </p>
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

                @php $dayWork = $day['workday'] ?? null; @endphp
                @if ($dayWork !== null)
                    <div class="uam-jornada">
                        <div class="uam-jornada-head">
                            <strong>{{ $dayWork['kind']->emoji() }} {{ $dayWork['kind_label'] ?? $dayWork['kind']->label() }}</strong>
                            @if ($dayWork['work_start'] !== null)
                                <span class="uam-sub">Jornada {{ Clock::formatMinute($dayWork['work_start']) }} – {{ Clock::formatMinute($dayWork['work_end']) }} · almuerzo {{ $lunchLimit }} min</span>
                            @endif
                        </div>
                        <div>@include('filament.business.pages.partials.user-activity-workday-cell', ['work' => $dayWork, 'presence' => null, 'nowMinute' => $nowMinute, 'isToday' => $day['is_today']])</div>
                        <dl class="uam-grid">
                            <div class="uam-stat"><dt>Activo en jornada</dt><dd style="color: {{ Kind::WorkActive->color() }};">{{ $fmt((int) $dayWork['work_active']) }}</dd></div>
                            <div class="uam-stat"><dt>Inactivo en jornada</dt><dd style="color: #ca8a04;">{{ $fmt((int) $dayWork['work_idle']) }}</dd></div>
                            <div class="uam-stat"><dt>Ausente en jornada</dt><dd style="color: {{ Kind::WorkAbsent->color() }};">{{ $fmt((int) $dayWork['work_absent']) }}</dd></div>
                            <div class="uam-stat">
                                <dt>Almuerzo</dt>
                                <dd style="color: {{ $dayWork['lunch_excess'] > 0 ? Kind::LunchExcess->color() : Kind::Lunch->color() }}; font-size: 13.5px;">
                                    @if ($dayWork['lunch_declared'])
                                        {{ Clock::formatMinute($dayWork['lunch_start']) }} – {{ $dayWork['lunch_end'] !== null ? Clock::formatMinute($dayWork['lunch_end']) : 'en curso' }}
                                        <div class="uam-sub">{{ $fmt((int) $dayWork['lunch'] + (int) $dayWork['lunch_excess']) }}{{ $dayWork['lunch_excess'] > 0 ? ' · +'.$fmt((int) $dayWork['lunch_excess']).' de más' : '' }}{{ ($dayWork['lunch_reason'] ?? null) === 'auto' ? ' · no marcó el regreso' : '' }}</div>
                                    @else
                                        <span style="color: var(--uam-muted);">No declarado</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="uam-stat"><dt>En reuniones</dt><dd style="color: {{ Kind::Meeting->color() }};">{{ $fmt((int) $dayWork['meeting']) }} <span class="uam-sub">· {{ count($dayWork['meetings'] ?? []) }}</span></dd></div>
                            <div class="uam-stat"><dt>Horas extra</dt><dd style="color: {{ Kind::Overtime->color() }};">{{ $fmt((int) $dayWork['overtime']) }}</dd></div>
                            @if ($dayWork['guard'] > 0)
                                <div class="uam-stat"><dt>Guardia</dt><dd style="color: {{ Kind::GuardActive->color() }};">{{ $fmt((int) $dayWork['guard']) }} <span class="uam-sub">· {{ $dayWork['guard_presence'] }}% presente</span></dd></div>
                            @endif
                        </dl>
                        @if (($dayWork['meetings'] ?? []) !== [])
                            <div>
                                <div class="uam-section-title" style="margin-bottom: 6px;">Reuniones del día</div>
                                <ul class="uam-meetings">
                                    @foreach ($dayWork['meetings'] as $index => $dayMeeting)
                                        <li wire:key="meeting-{{ $day['date'] }}-{{ $index }}">
                                            <strong style="font-variant-numeric: tabular-nums;">{{ Clock::formatMinute($dayMeeting['start']) }} – {{ $dayMeeting['end'] !== null ? Clock::formatMinute($dayMeeting['end']) : 'en curso' }}</strong>
                                            <span style="overflow: hidden; text-overflow: ellipsis;" title="{{ $dayMeeting['reason'] }}">{{ $dayMeeting['reason'] }}</span>
                                            <span class="uam-sub" style="white-space: nowrap;">
                                                {{ $dayMeeting['end'] !== null ? $fmt(max(0, $dayMeeting['end'] - $dayMeeting['start'])) : '' }}
                                                @if (($dayMeeting['end_reason'] ?? null) === 'auto')<span class="uam-badge over">no marcó el fin</span>@endif
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                @endif

                <dl class="uam-grid">
                    <div class="uam-stat"><dt>Activo</dt><dd style="color: var(--uam-active);">{{ $fmt((int) $summary['active']) }}</dd></div>
                    <div class="uam-stat"><dt>Abierto sin usar</dt><dd style="color: #d97706;">{{ $fmt((int) $summary['idle']) }}</dd></div>
                    <div class="uam-stat"><dt>En otra pestaña</dt><dd>{{ $fmt((int) $summary['background']) }}</dd></div>
                    <div class="uam-stat"><dt>{{ $dayWork !== null ? 'Uso al estar conectado' : 'Uso real' }}</dt><dd><span class="uam-usage {{ $usageClass((int) $summary['usage']) }}" style="font-size: 16px;">{{ $summary['usage'] }}%</span></dd></div>
                    <div class="uam-stat"><dt>Primera / última conexión</dt><dd style="font-size: 13.5px;">{{ Clock::formatMinute($summary['first']) }} – {{ Clock::formatMinute($summary['last']) }}</dd></div>
                    <div class="uam-stat"><dt>Acciones · pantallas</dt><dd>{{ $summary['actions'] }} · {{ $summary['pages'] }}</dd></div>
                </dl>

                <div>
                    <div class="uam-section-title">Su día minuto a minuto</div>
                    @if ($day['segments'] === [] && ! $day['detail_available'])
                        <div class="uam-notice">El detalle minuto a minuto se guarda 90 días; para esta fecha quedan solo las cifras del resumen.</div>
                    @elseif ($day['segments'] === [] && ($day['bands'] ?? []) === [])
                        <div class="uam-sub">Sin actividad registrada este día.</div>
                    @else
                        {!! $dayline($day['segments'], true, $day['is_today'], $day['bands'] ?? []) !!}
                        {!! $hourScale(true) !!}
                        <div style="margin-top: 8px;">@include('filament.business.pages.partials.user-activity-day-legend', ['workday' => $dayWork !== null, 'showNow' => $day['is_today']])</div>
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
                                        <td>{!! $dayline($personDay['segments'], false, false, $personDay['bands'] ?? []) !!}</td>
                                        @if (($personDay['workday'] ?? null) !== null)
                                            <td class="num" style="width: 90px;"><strong style="color: var(--uam-active);">{{ $fmt((int) $personDay['workday']['work_active']) }}</strong></td>
                                            <td class="num" style="width: 90px;">
                                                @if ($personDay['workday']['usage'] !== null)
                                                    <span class="uam-usage {{ $usageClass($personDay['workday']['usage']) }}" title="Uso de jornada">{{ $personDay['workday']['usage'] }}%</span>
                                                @else
                                                    <span class="uam-sub" title="{{ $personDay['workday']['kind']->label() }}">{{ $personDay['workday']['kind']->emoji() }}</span>
                                                @endif
                                            </td>
                                        @else
                                            <td class="num" style="width: 90px;"><strong style="color: var(--uam-active);">{{ $fmt($personDay['active']) }}</strong></td>
                                            <td class="num" style="width: 90px;"><span class="uam-usage {{ $usageClass($personDay['usage']) }}" title="Uso real (activo ÷ conectado)">{{ $personDay['usage'] }}%</span></td>
                                        @endif
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

                @if ($detail['subject'])
                    <div>
                        <div class="uam-section-title">Jornada de esta persona</div>
                        <div class="uam-jornada">
                            <div class="uam-jornada-head">
                                <div>
                                    <strong>{{ $detail['schedule']->label() }}</strong>
                                    <div class="uam-sub">{{ $detail['schedule']->description() }}</div>
                                </div>
                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    {{ ($this->editWorkScheduleAction)(['userId' => $detail['user']->id]) }}
                                    {{ ($this->registerWorkAbsenceAction)(['userId' => $detail['user']->id]) }}
                                </div>
                            </div>
                            @if ($detail['absences']->isEmpty())
                                <div class="uam-sub">Sin vacaciones, reposos ni permisos registrados en los últimos 30 días ni a futuro.</div>
                            @else
                                <ul class="uam-absences">
                                    @foreach ($detail['absences'] as $absence)
                                        <li wire:key="absence-{{ $absence->id }}">
                                            <span>
                                                {{ $absence->type->emoji() }} <strong>{{ $absence->type->label() }}</strong>
                                                · {{ $absence->starts_on->format('d/m/Y') }}{{ $absence->starts_on->equalTo($absence->ends_on) ? '' : ' al '.$absence->ends_on->format('d/m/Y') }}
                                                @if ($absence->reason)<span class="uam-sub"> · {{ $absence->reason }}</span>@endif
                                            </span>
                                            {{ ($this->deleteWorkAbsenceAction)(['absenceId' => $absence->id]) }}
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            @unless ($detail['can_manage'])
                                <div class="uam-sub">Para cambiar la jornada o registrar ausencias hace falta el permiso «Gestionar jornadas laborales».</div>
                            @endunless
                        </div>
                    </div>
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
