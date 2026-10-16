{{--
    Botón de almuerzo (barra superior). El contador corre en el navegador desde
    la hora de inicio que guarda el servidor; cada 60 s se resincroniza por si
    el almuerzo se cerró desde otra pestaña.
--}}
@php
    $state = $status['state'] ?? 'hidden';
@endphp
<div
    class="wbt"
    wire:poll.60s.visible="refreshStatus"
    x-data="{
        now: Date.now(),
        asked: false,
        grace: {{ (int) $graceSeconds }},
        elapsed() {
            const start = Number($wire.status.started_at || 0);
            const end = Number($wire.status.ended_at || 0) || Math.floor(this.now / 1000);
            return start > 0 ? Math.max(0, end - start) : 0;
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
            if ($wire.status.state !== 'on' || this.asked) return;
            if (this.$root.contains(event.target) || event.target.closest?.('.fi-modal')) return;
            if (this.elapsed() < this.grace) return;
            if (Date.now() < Number($wire.snoozeUntil || 0) * 1000) return;
            this.asked = true;
            $wire.mountAction('returnPrompt');
        },
        timer: null,
        init() {
            this.timer = setInterval(() => this.now = Date.now(), 1000);
            $wire.$watch('snoozeUntil', () => { this.asked = false; });
        },
        destroy() {
            clearInterval(this.timer);
        },
    }"
    x-on:keydown.window.capture="interacted($event)"
    x-on:pointerdown.window.capture="interacted($event)"
>
    <style>
        .wbt { display: inline-flex; align-items: center; }
        .wbt-pill { display: inline-flex; align-items: center; gap: 7px; border-radius: 999px; padding: 6px 12px; font-size: 13px; font-weight: 700; line-height: 1; border: 1px solid transparent; white-space: nowrap; cursor: pointer; transition: transform .12s, box-shadow .12s; font-variant-numeric: tabular-nums; }
        .wbt-pill:hover:not([disabled]) { transform: translateY(-1px); box-shadow: 0 4px 12px -4px rgba(15, 23, 42, .25); }
        .wbt-pill[disabled] { cursor: default; }
        .wbt-pill svg { width: 16px; height: 16px; flex-shrink: 0; }
        .wbt-ready { background: #2563eb; color: #fff; }
        .wbt-on { background: #dbeafe; color: #1d4ed8; border-color: #93c5fd; }
        .wbt-on.over { background: #ffedd5; color: #c2410c; border-color: #fdba74; animation: wbt-blink 1.6s infinite; }
        .wbt-back { background: #16a34a; color: #fff; border-radius: 999px; padding: 4px 10px; font-size: 12px; font-weight: 800; margin-left: 2px; }
        .wbt-muted { background: #f1f5f9; color: #64748b; border-color: #e2e8f0; font-weight: 600; }
        .wbt-done { background: #dcfce7; color: #15803d; border-color: #86efac; font-weight: 600; }
        .wbt-done.over { background: #ffedd5; color: #c2410c; border-color: #fdba74; }
        .dark .wbt-on { background: rgba(37, 99, 235, .18); color: #93c5fd; border-color: rgba(147, 197, 253, .35); }
        .dark .wbt-on.over, .dark .wbt-done.over { background: rgba(249, 115, 22, .18); color: #fdba74; border-color: rgba(253, 186, 116, .35); }
        .dark .wbt-muted { background: rgba(148, 163, 184, .12); color: #94a3b8; border-color: rgba(148, 163, 184, .25); }
        .dark .wbt-done { background: rgba(22, 163, 74, .16); color: #86efac; border-color: rgba(134, 239, 172, .3); }
        .wbt-label { display: inline; }
        @media (max-width: 640px) { .wbt-label { display: none; } }
        @keyframes wbt-blink { 0%, 100% { box-shadow: 0 0 0 0 rgba(249, 115, 22, .45); } 50% { box-shadow: 0 0 0 5px rgba(249, 115, 22, 0); } }
    </style>

    @if ($state === 'ready')
        <button type="button" class="wbt-pill wbt-ready" wire:click="mountAction('startLunch')" wire:loading.attr="disabled" title="{{ $status['message'] }}">
            <x-filament::icon icon="heroicon-o-clock" />
            <span class="wbt-label">Salir a almorzar</span>
        </button>
    @elseif ($state === 'on')
        <div class="wbt-pill wbt-on" x-bind:class="{ 'over': over() > 0 }" role="status" title="{{ $status['message'] }}">
            <x-filament::icon icon="heroicon-o-clock" />
            <span class="wbt-label" x-text="over() > 0 ? 'Almuerzo excedido' : 'En almuerzo'">En almuerzo</span>
            <span x-text="over() > 0 ? '+' + clock(over()) : clock(elapsed())">{{ sprintf('%02d:00', (int) $status['minutes']) }}</span>
            <button type="button" class="wbt-back" wire:click="mountAction('endLunch')" wire:loading.attr="disabled" wire:target="mountAction">Volver</button>
        </div>
    @elseif ($state === 'early')
        <button type="button" class="wbt-pill wbt-muted" disabled title="{{ $status['message'] }}">
            <x-filament::icon icon="heroicon-o-clock" />
            <span class="wbt-label">Almuerzo desde {{ $status['opens_at'] }}</span>
        </button>
    @elseif ($state === 'done')
        <span class="wbt-pill wbt-done {{ ($status['excess'] ?? 0) > 0 ? 'over' : '' }}" title="{{ $status['message'] }}">
            <x-filament::icon icon="heroicon-o-check-circle" />
            <span class="wbt-label">Almuerzo {{ \App\Support\UserActivity\UserActivityClock::formatDuration((int) $status['minutes']) }}</span>
        </span>
    @endif

    <x-filament-actions::modals />
</div>
