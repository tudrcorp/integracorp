{{--
    Almuerzo y reuniones. Las acciones están en el menú de usuario
    (work-break-menu-items); aquí viven el estado, las modales, la pregunta al
    retomar el trabajo y un indicador en la barra solo mientras corre el almuerzo
    o la reunión. Los contadores corren en el navegador desde la hora que guarda
    el servidor; cada 60 s se resincroniza por si algo se cerró en otra pestaña.
--}}
@php
    $state = $status['state'] ?? 'hidden';
    $inMeeting = (bool) ($meeting['open'] ?? false);
@endphp
<div
    class="wbt"
    wire:poll.60s.visible="refreshStatus"
    x-data="{
        now: Date.now(),
        asked: false,
        grace: {{ (int) $graceSeconds }},
        since(startedAt, endedAt = 0) {
            const start = Number(startedAt || 0);
            const end = Number(endedAt || 0) || Math.floor(this.now / 1000);
            return start > 0 ? Math.max(0, end - start) : 0;
        },
        elapsed() {
            return this.since($wire.status.started_at, $wire.status.ended_at);
        },
        meetingElapsed() {
            return this.since($wire.meeting.started_at);
        },
        clock(seconds) {
            const h = Math.floor(seconds / 3600);
            const m = Math.floor((seconds % 3600) / 60);
            const s = seconds % 60;
            return (h > 0 ? h + ':' + String(m).padStart(2, '0') : String(m).padStart(2, '0')) + ':' + String(s).padStart(2, '0');
        },
        over() {
            return Math.max(0, this.elapsed() - Number($wire.status.limit || 60) * 60);
        },
        interacted(event) {
            if (this.asked || this.$root.contains(event.target) || event.target.closest?.('.fi-modal')) return;
            const now = Date.now();
            if ($wire.meeting.open && this.meetingElapsed() >= this.grace && now >= Number($wire.meetingSnoozeUntil || 0) * 1000) {
                this.asked = true;
                $wire.mountAction('meetingPrompt');
                return;
            }
            if ($wire.status.state === 'on' && this.elapsed() >= this.grace && now >= Number($wire.snoozeUntil || 0) * 1000) {
                this.asked = true;
                $wire.mountAction('returnPrompt');
            }
        },
        duration(seconds) {
            const minutes = Math.floor(seconds / 60);
            const h = Math.floor(minutes / 60);
            const m = minutes % 60;
            return h === 0 ? m + ' min' : (m === 0 ? h + ' h' : h + ' h ' + m + ' min');
        },
        lunchText() {
            const status = $wire.status || {};
            return {
                on: this.over() > 0 ? 'Almuerzo excedido +' + this.clock(this.over()) : 'En almuerzo ' + this.clock(this.elapsed()),
                early: 'Almuerzo desde ' + (status.opens_at || ''),
                done: 'Almuerzo ' + this.duration(this.elapsed()) + (Number(status.excess || 0) > 0 ? ' · +' + status.excess + ' min' : ' ✓'),
                missed: 'Almuerzo no declarado hoy',
            }[status.state] || '';
        },
        syncStore() {
            const meeting = $wire.meeting || {};
            Alpine.store('workBreak', {
                ready: true,
                status: $wire.status || {},
                meeting: meeting,
                lunchText: this.lunchText(),
                meetingText: meeting.open ? 'En reunión ' + this.clock(this.meetingElapsed()) + (meeting.reason ? ' · ' + meeting.reason : '') : '',
                meetingTodayText: this.duration(Number(meeting.today_minutes || 0) * 60),
            });
        },
        timer: null,
        init() {
            this.syncStore();
            this.timer = setInterval(() => { this.now = Date.now(); this.syncStore(); }, 1000);
            $wire.$watch('snoozeUntil', () => { this.asked = false; });
            $wire.$watch('meetingSnoozeUntil', () => { this.asked = false; });
        },
        destroy() {
            clearInterval(this.timer);
        },
    }"
    x-on:keydown.window.capture="interacted($event)"
    x-on:pointerdown.window.capture="interacted($event)"
    x-on:wbt-action.window="$wire.mountAction($event.detail)"
>
    <style>
        .wbt { display: inline-flex; align-items: center; gap: 6px; }
        .wbt-pill { display: inline-flex; align-items: center; gap: 7px; border-radius: 999px; padding: 6px 12px; font-size: 13px; font-weight: 700; line-height: 1; border: 1px solid transparent; white-space: nowrap; cursor: pointer; transition: transform .12s, box-shadow .12s; font-variant-numeric: tabular-nums; }
        .wbt-pill:hover:not([disabled]):not(span):not(div) { transform: translateY(-1px); box-shadow: 0 4px 12px -4px rgba(15, 23, 42, .25); }
        .wbt-pill[disabled], span.wbt-pill, div.wbt-pill { cursor: default; }
        .wbt-pill svg { width: 16px; height: 16px; flex-shrink: 0; }
        .wbt-ready { background: #2563eb; color: #fff; }
        .wbt-meet { background: #0d9488; color: #fff; }
        .wbt-on { background: #dbeafe; color: #1d4ed8; border-color: #93c5fd; }
        .wbt-on.over { background: #ffedd5; color: #c2410c; border-color: #fdba74; animation: wbt-blink 1.6s infinite; }
        .wbt-meeting { background: #ccfbf1; color: #0f766e; border-color: #5eead4; max-width: 340px; }
        .wbt-meeting .wbt-reason { overflow: hidden; text-overflow: ellipsis; font-weight: 600; max-width: 140px; }
        .wbt-back { background: #16a34a; color: #fff; border: 0; border-radius: 999px; padding: 4px 10px; font-size: 12px; font-weight: 800; margin-left: 2px; cursor: pointer; }
        .wbt-muted { background: #f1f5f9; color: #64748b; border-color: #e2e8f0; font-weight: 600; }
        .wbt-done { background: #dcfce7; color: #15803d; border-color: #86efac; font-weight: 600; }
        .wbt-done.over { background: #ffedd5; color: #c2410c; border-color: #fdba74; }
        .dark .wbt-on { background: rgba(37, 99, 235, .18); color: #93c5fd; border-color: rgba(147, 197, 253, .35); }
        .dark .wbt-on.over, .dark .wbt-done.over { background: rgba(249, 115, 22, .18); color: #fdba74; border-color: rgba(253, 186, 116, .35); }
        .dark .wbt-meeting { background: rgba(13, 148, 136, .2); color: #5eead4; border-color: rgba(94, 234, 212, .35); }
        .dark .wbt-muted { background: rgba(148, 163, 184, .12); color: #94a3b8; border-color: rgba(148, 163, 184, .25); }
        .dark .wbt-done { background: rgba(22, 163, 74, .16); color: #86efac; border-color: rgba(134, 239, 172, .3); }
        .wbt-label { display: inline; }
        @media (max-width: 640px) { .wbt-label, .wbt-meeting .wbt-reason { display: none; } }
        @keyframes wbt-blink { 0%, 100% { box-shadow: 0 0 0 0 rgba(249, 115, 22, .45); } 50% { box-shadow: 0 0 0 5px rgba(249, 115, 22, 0); } }
    </style>

    {{-- Solo mientras corre: recuerda marcar el regreso. Las acciones están en el menú de usuario. --}}
    @if ($inMeeting)
        <div class="wbt-pill wbt-meeting" role="status" title="En reunión: {{ $meeting['reason'] }}. Para marcar el fin, abre el menú de tu perfil → «Terminé la reunión».">
            <x-filament::icon icon="heroicon-o-user-group" />
            <span x-text="clock(meetingElapsed())">{{ sprintf('%02d:00', (int) $meeting['minutes']) }}</span>
            <span class="wbt-label">En reunión</span>
        </div>
    @endif

    @if ($state === 'on')
        <div class="wbt-pill wbt-on" x-bind:class="{ 'over': over() > 0 }" role="status" title="Para marcar el regreso, abre el menú de tu perfil → «Volver del almuerzo».">
            <x-filament::icon icon="heroicon-o-clock" />
            <span x-text="over() > 0 ? '+' + clock(over()) : clock(elapsed())">{{ sprintf('%02d:00', (int) $status['minutes']) }}</span>
            <span class="wbt-label" x-text="over() > 0 ? 'Almuerzo excedido' : 'En almuerzo'">En almuerzo</span>
        </div>
    @endif

    <x-filament-actions::modals />
</div>
