<div
    x-data="{
        now: Math.floor(Date.now() / 1000),
        held: false,
        heldSince: 0,
        lastTyping: 0,
        audio: null,
        ticker: null,
        ringer: null,
        titleTimer: null,
        baseTitle: document.title,
        init() {
            const unlock = () => this.ensureAudio();
            document.addEventListener('pointerdown', unlock, { once: true, capture: true });
            document.addEventListener('keydown', unlock, { once: true, capture: true });
            document.addEventListener('keydown', (event) => {
                if (this.editable(event.target)) this.lastTyping = Date.now();
            }, true);
            this.ticker = setInterval(() => {
                this.now = Math.floor(Date.now() / 1000);
                if (this.held && (! this.typing() || Date.now() - this.heldSince > 25000)) {
                    this.held = false;
                    this.$nextTick(() => this.$root.querySelector('[data-crm-ia-primary]')?.focus({ preventScroll: true }));
                }
            }, 1000);
            this.ringer = setInterval(() => { if (this.visible()) this.chime(); }, 15000);
            document.addEventListener('visibilitychange', () => this.blinkTitle());
        },
        destroy() {
            clearInterval(this.ticker);
            clearInterval(this.ringer);
            this.stopBlink();
        },
        visible() {
            return this.$root.querySelector('.crm-ia-dialog') !== null && ! this.held;
        },
        editable(el) {
            if (! el) return false;
            if (el.isContentEditable) return true;
            return el.tagName === 'TEXTAREA' || (el.tagName === 'INPUT' && ['text', 'search', 'email', 'tel', 'url', 'number', ''].includes((el.type || '').toLowerCase()));
        },
        typing() {
            const el = document.activeElement;
            if (! this.editable(el)) return false;
            const hasText = el.isContentEditable ? (el.textContent || '').trim() !== '' : String(el.value || '').trim() !== '';
            return hasText && Date.now() - this.lastTyping < 6000;
        },
        arrive() {
            this.held = this.typing();
            this.heldSince = Date.now();
            this.chime();
            this.$nextTick(() => {
                if (! this.held) this.$root.querySelector('[data-crm-ia-primary]')?.focus({ preventScroll: true });
                this.blinkTitle();
            });
        },
        ensureAudio() {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (this.audio || ! AudioCtx) return;
            try { this.audio = new AudioCtx(); } catch (_) {}
        },
        chime() {
            this.ensureAudio();
            const ctx = this.audio;
            if (! ctx) return;
            try {
                if (ctx.state === 'suspended') ctx.resume();
                const start = ctx.currentTime + 0.02;
                const tone = (frequency, at, duration) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(frequency, at);
                    gain.gain.setValueAtTime(0.0001, at);
                    gain.gain.exponentialRampToValueAtTime(0.2, at + 0.02);
                    gain.gain.exponentialRampToValueAtTime(0.0001, at + duration);
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start(at);
                    osc.stop(at + duration + 0.05);
                };
                [0, 0.62].forEach((offset) => {
                    tone(659.25, start + offset, 0.14);
                    tone(880, start + offset + 0.15, 0.14);
                    tone(1174.66, start + offset + 0.3, 0.26);
                });
            } catch (_) {}
        },
        blinkTitle() {
            if (! document.hidden || ! this.visible()) {
                this.stopBlink();
                return;
            }
            if (this.titleTimer) return;
            this.baseTitle = document.title;
            let on = false;
            this.titleTimer = setInterval(() => {
                on = ! on;
                document.title = on ? '💬 Nueva conversación' : this.baseTitle;
                if (! document.hidden || ! this.visible()) this.stopBlink();
            }, 1000);
        },
        stopBlink() {
            if (! this.titleTimer) return;
            clearInterval(this.titleTimer);
            this.titleTimer = null;
            document.title = this.baseTitle;
        },
        elapsed(since) {
            if (! since) return 'recién';
            const seconds = Math.max(0, this.now - since);
            if (seconds < 3600) {
                const minutes = Math.floor(seconds / 60);
                return minutes + ':' + String(seconds % 60).padStart(2, '0');
            }
            const hours = Math.floor(seconds / 3600);
            return hours + ' h ' + String(Math.floor((seconds % 3600) / 60)).padStart(2, '0') + ' min';
        },
        level(since) {
            if (! since) return 'ok';
            const seconds = this.now - since;
            if (seconds >= {{ $lateSeconds }}) return 'late';
            if (seconds >= {{ $warnSeconds }}) return 'warn';
            return 'ok';
        },
    }"
    x-on:{{ $soundEvent }}.window="$nextTick(() => blinkTitle())"
    x-on:keydown.escape.window="if (visible()) { $event.preventDefault(); $wire.remind(); }"
    class="crm-ia-root"
>
    <div wire:init="refresh" wire:poll.keep-alive.{{ $pollSeconds }}s="refresh" hidden></div>

    @if ($current)
        <div
            wire:key="crm-ia-{{ $current['id'] }}"
            class="crm-ia-backdrop"
            x-cloak
            x-init="arrive()"
            x-show="! held"
        >
            <section
                class="crm-ia-dialog"
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="crm-ia-title"
                aria-describedby="crm-ia-detail"
                x-trap.noscroll="! held"
            >
                <header class="crm-ia-head" :class="'is-' + level({{ (int) $current['since'] }})">
                    <div class="crm-ia-badge" aria-hidden="true">
                        <span class="crm-ia-ping"></span>
                        <span class="crm-ia-ping is-late"></span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.4 8.4 0 0 1-12.2 7.5L3 21l2-5.6A8.5 8.5 0 1 1 21 11.5z"/><path d="M8.5 11.5h.01M12 11.5h.01M15.5 11.5h.01"/></svg>
                    </div>
                    <div class="crm-ia-head-text">
                        <p class="crm-ia-eyebrow">Atención WhatsApp · {{ $current['area'] }}</p>
                        <h2 id="crm-ia-title" class="crm-ia-title">
                            @if ($others === [] && $more === 0)
                                {{ $current['title'] }}
                            @else
                                {{ count($others) + $more + 1 }} conversaciones esperan
                            @endif
                        </h2>
                    </div>
                    <div class="crm-ia-timer" :class="'is-' + level({{ (int) $current['since'] }})" role="timer" aria-live="off">
                        <span class="crm-ia-timer-label">Esperando</span>
                        <span class="crm-ia-timer-value" x-text="elapsed({{ (int) $current['since'] }})"></span>
                    </div>
                </header>

                <div id="crm-ia-detail" class="crm-ia-body">
                    <div class="crm-ia-client">
                        <span class="crm-ia-avatar" aria-hidden="true">{{ $current['initials'] }}</span>
                        <div class="crm-ia-client-text">
                            <p class="crm-ia-name">{{ $current['name'] }}</p>
                            <p class="crm-ia-phone">{{ $current['phone'] }}</p>
                        </div>
                    </div>

                    <div class="crm-ia-chips">
                        <span class="crm-ia-chip">{{ $current['area'] }}</span>
                        @if ($current['kind'] === 'assign')
                            <span class="crm-ia-chip is-accent">{{ $current['by'] ? 'Te lo pasó '.$current['by'] : 'Te lo pasaron a ti' }}</span>
                        @elseif ($current['kind'] === 'move')
                            <span class="crm-ia-chip is-accent">{{ $current['by'] ? 'Lo envió '.$current['by'].' desde otra área' : 'Llegó desde otra área' }}</span>
                        @else
                            <span class="crm-ia-chip is-new">Nuevo · nadie lo ha tomado</span>
                        @endif
                    </div>

                    @if ($current['motivo'])
                        <div class="crm-ia-reason">
                            <span class="crm-ia-label">Lo que necesita</span>
                            <p>{{ $current['motivo'] }}</p>
                        </div>
                    @endif

                    @if ($current['last_line'])
                        <figure class="crm-ia-bubble">
                            <figcaption class="crm-ia-label">Último mensaje del cliente</figcaption>
                            <blockquote>{{ $current['last_line'] }}</blockquote>
                        </figure>
                    @endif

                    <p class="crm-ia-hint">Mientras nadie lo tome, SolIA sigue conversando con el cliente.</p>

                    @if ($others !== [])
                        <div class="crm-ia-queue">
                            <p class="crm-ia-label">También esperan</p>
                            <ul>
                                @foreach ($others as $other)
                                    <li wire:key="crm-ia-other-{{ $other['id'] }}">
                                        <span class="crm-ia-avatar is-small" aria-hidden="true">{{ $other['initials'] }}</span>
                                        <span class="crm-ia-queue-text">
                                            <span class="crm-ia-queue-name">{{ $other['name'] }}</span>
                                            <span class="crm-ia-queue-meta">
                                                {{ $other['area'] }}{{ $other['kind'] === 'assign' ? ' · te lo pasaron' : '' }}
                                            </span>
                                        </span>
                                        <span class="crm-ia-queue-time" :class="'is-' + level({{ (int) $other['since'] }})" x-text="elapsed({{ (int) $other['since'] }})"></span>
                                        <button
                                            type="button"
                                            class="crm-ia-mini"
                                            wire:click="take('{{ $other['id'] }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="take, open, remind, away"
                                            aria-label="Tomar la conversación de {{ $other['name'] }}"
                                        >Tomar</button>
                                    </li>
                                @endforeach
                            </ul>
                            @if ($more > 0)
                                <p class="crm-ia-more">y {{ $more }} más en la bandeja</p>
                            @endif
                        </div>
                    @elseif ($more > 0)
                        <p class="crm-ia-more">y {{ $more }} más en la bandeja</p>
                    @endif
                </div>

                <footer class="crm-ia-actions">
                    <button
                        type="button"
                        data-crm-ia-primary
                        class="crm-ia-btn is-primary"
                        wire:click="take('{{ $current['id'] }}')"
                        wire:loading.attr="disabled"
                        wire:target="take, open, remind, away"
                    >
                        <span wire:loading.remove wire:target="take('{{ $current['id'] }}')" class="crm-ia-btn-inner">
                            Tomar y responder
                            <kbd>Enter</kbd>
                        </span>
                        <span wire:loading wire:target="take('{{ $current['id'] }}')" class="crm-ia-btn-inner">
                            <span class="crm-ia-spinner" aria-hidden="true"></span>
                            Tomando el caso…
                        </span>
                    </button>

                    <div class="crm-ia-row">
                        <button
                            type="button"
                            class="crm-ia-btn is-secondary"
                            wire:click="open('{{ $current['id'] }}')"
                            wire:loading.attr="disabled"
                            wire:target="take, open, remind, away"
                        >
                            <span wire:loading.remove wire:target="open">Ver en la bandeja</span>
                            <span wire:loading wire:target="open">Abriendo…</span>
                        </button>
                        <button
                            type="button"
                            class="crm-ia-btn is-secondary"
                            wire:click="remind"
                            wire:loading.attr="disabled"
                            wire:target="take, open, remind, away"
                        >
                            <span class="crm-ia-btn-inner">Recordar en 1 min <kbd>Esc</kbd></span>
                        </button>
                    </div>

                    <button type="button" class="crm-ia-link" wire:click="away" wire:loading.attr="disabled" wire:target="take, open, remind, away">
                        ¿No puedes atender ahora? Marcar como ausente
                    </button>
                </footer>
            </section>
        </div>
    @endif

    <style>
        .crm-ia-backdrop {
            position: fixed;
            inset: 0;
            z-index: 70;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            background: rgba(2, 6, 23, 0.62);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
        }
        @keyframes crm-ia-in {
            from { opacity: 0; transform: translateY(8px) scale(0.97); }
            to { opacity: 1; transform: none; }
        }
        .crm-ia-dialog {
            width: 100%;
            max-width: 520px;
            max-height: calc(100vh - 32px);
            overflow-y: auto;
            box-sizing: border-box;
            border-radius: 20px;
            background: #ffffff;
            color: #0f172a;
            box-shadow: 0 30px 60px -12px rgba(2, 6, 23, 0.45), 0 0 0 1px rgba(15, 23, 42, 0.06);
            font-size: 14px;
            animation: crm-ia-in 0.24s cubic-bezier(0.2, 0.9, 0.3, 1.15);
        }
        .crm-ia-head {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 18px 20px;
            border-radius: 20px 20px 0 0;
            color: #ffffff;
            background: linear-gradient(135deg, #16a34a 0%, #0d9488 100%);
        }
        .crm-ia-head.is-warn { background: linear-gradient(135deg, #d97706 0%, #ea580c 100%); }
        .crm-ia-head.is-late { background: linear-gradient(135deg, #dc2626 0%, #be123c 100%); }
        .crm-ia-badge {
            position: relative;
            flex: none;
            width: 48px;
            height: 48px;
            display: grid;
            place-items: center;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.2);
        }
        .crm-ia-badge svg { width: 26px; height: 26px; position: relative; }
        .crm-ia-ping {
            position: absolute;
            inset: 0;
            border-radius: 999px;
            border: 2px solid rgba(255, 255, 255, 0.7);
            animation: crm-ia-ping 1.8s cubic-bezier(0, 0, 0.2, 1) infinite;
        }
        .crm-ia-ping.is-late { animation-delay: 0.9s; }
        @keyframes crm-ia-ping {
            0% { transform: scale(1); opacity: 0.9; }
            100% { transform: scale(1.75); opacity: 0; }
        }
        .crm-ia-head-text { flex: 1; min-width: 0; }
        .crm-ia-eyebrow {
            margin: 0;
            font-size: 11.5px;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            opacity: 0.9;
        }
        .crm-ia-title { margin: 2px 0 0; font-size: 19px; font-weight: 700; line-height: 1.25; }
        .crm-ia-timer {
            flex: none;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            padding: 6px 10px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.18);
        }
        .crm-ia-timer.is-late { animation: crm-ia-pulse 1.2s ease-in-out infinite; }
        @keyframes crm-ia-pulse { 50% { background: rgba(255, 255, 255, 0.34); } }
        .crm-ia-timer-label { font-size: 10.5px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.9; }
        .crm-ia-timer-value { font-size: 18px; font-weight: 700; font-variant-numeric: tabular-nums; line-height: 1.2; }
        .crm-ia-body { display: flex; flex-direction: column; gap: 14px; padding: 18px 20px 4px; }
        .crm-ia-client { display: flex; align-items: center; gap: 12px; }
        .crm-ia-avatar {
            flex: none;
            width: 46px;
            height: 46px;
            display: grid;
            place-items: center;
            border-radius: 999px;
            background: #dcfce7;
            color: #15803d;
            font-weight: 700;
            font-size: 16px;
        }
        .crm-ia-avatar.is-small { width: 32px; height: 32px; font-size: 12px; }
        .crm-ia-client-text { min-width: 0; }
        .crm-ia-name { margin: 0; font-size: 18px; font-weight: 700; overflow-wrap: anywhere; }
        .crm-ia-phone { margin: 2px 0 0; color: #64748b; font-variant-numeric: tabular-nums; }
        .crm-ia-chips { display: flex; flex-wrap: wrap; gap: 6px; }
        .crm-ia-chip {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            background: #f1f5f9;
            color: #334155;
        }
        .crm-ia-chip.is-new { background: #dcfce7; color: #166534; }
        .crm-ia-chip.is-accent { background: #e0e7ff; color: #3730a3; }
        .crm-ia-label {
            display: block;
            margin: 0 0 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
        }
        .crm-ia-reason p { margin: 0; font-weight: 600; color: #0f172a; overflow-wrap: anywhere; }
        .crm-ia-bubble { margin: 0; }
        .crm-ia-bubble blockquote {
            position: relative;
            margin: 0;
            padding: 10px 14px;
            border-radius: 4px 14px 14px 14px;
            background: #f1f5f9;
            color: #1e293b;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }
        .crm-ia-hint { margin: 0; font-size: 12.5px; color: #64748b; }
        .crm-ia-queue ul { list-style: none; margin: 0; padding: 0; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
        .crm-ia-queue li { display: flex; align-items: center; gap: 10px; padding: 8px 10px; }
        .crm-ia-queue li + li { border-top: 1px solid #e2e8f0; }
        .crm-ia-queue-text { flex: 1; min-width: 0; display: flex; flex-direction: column; }
        .crm-ia-queue-name { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .crm-ia-queue-meta { font-size: 12px; color: #64748b; }
        .crm-ia-queue-time { font-size: 12.5px; font-weight: 600; font-variant-numeric: tabular-nums; color: #15803d; }
        .crm-ia-queue-time.is-warn { color: #b45309; }
        .crm-ia-queue-time.is-late { color: #dc2626; }
        .crm-ia-mini {
            padding: 6px 12px;
            border: 0;
            border-radius: 8px;
            background: #dcfce7;
            color: #166534;
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
        }
        .crm-ia-mini:hover { background: #bbf7d0; }
        .crm-ia-more { margin: 6px 0 0; font-size: 12.5px; color: #64748b; text-align: center; }
        .crm-ia-actions { display: flex; flex-direction: column; gap: 10px; padding: 16px 20px 18px; }
        .crm-ia-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .crm-ia-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 14px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.15s, transform 0.05s;
        }
        .crm-ia-btn:active { transform: translateY(1px); }
        .crm-ia-btn:disabled, .crm-ia-mini:disabled, .crm-ia-link:disabled { opacity: 0.55; cursor: wait; }
        .crm-ia-btn.is-primary { min-height: 52px; border: 0; background: #16a34a; color: #ffffff; font-size: 15.5px; box-shadow: 0 8px 18px -6px rgba(22, 163, 74, 0.6); }
        .crm-ia-btn.is-primary:hover { background: #15803d; }
        .crm-ia-btn.is-secondary { border: 1px solid #cbd5e1; background: #ffffff; color: #334155; }
        .crm-ia-btn.is-secondary:hover { background: #f8fafc; }
        .crm-ia-btn-inner { display: inline-flex; align-items: center; gap: 8px; }
        .crm-ia-btn kbd {
            padding: 1px 6px;
            border-radius: 6px;
            font: 600 11px/1.5 ui-sans-serif, system-ui, sans-serif;
            background: rgba(15, 23, 42, 0.08);
            color: inherit;
        }
        .crm-ia-btn.is-primary kbd { background: rgba(255, 255, 255, 0.22); }
        .crm-ia-btn:focus-visible, .crm-ia-mini:focus-visible, .crm-ia-link:focus-visible { outline: 3px solid #86efac; outline-offset: 2px; }
        .crm-ia-link { align-self: center; border: 0; background: none; color: #64748b; font-size: 12.5px; text-decoration: underline; text-underline-offset: 3px; cursor: pointer; }
        .crm-ia-link:hover { color: #334155; }
        .crm-ia-spinner {
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.45);
            border-top-color: #ffffff;
            border-radius: 999px;
            animation: crm-ia-spin 0.8s linear infinite;
        }
        @keyframes crm-ia-spin { to { transform: rotate(360deg); } }
        @media (max-width: 520px) {
            .crm-ia-backdrop { align-items: flex-end; padding: 0; }
            .crm-ia-dialog { max-width: none; border-radius: 20px 20px 0 0; max-height: 92vh; }
            .crm-ia-head { padding: 16px; }
            .crm-ia-body { padding: 16px 16px 4px; }
            .crm-ia-actions { padding: 14px 16px 18px; }
            .crm-ia-btn kbd { display: none; }
        }
        @media (prefers-reduced-motion: reduce) {
            .crm-ia-ping, .crm-ia-timer.is-late, .crm-ia-spinner, .crm-ia-dialog { animation: none; }
        }
        .dark .crm-ia-dialog { background: #0f172a; color: #e2e8f0; box-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.7), 0 0 0 1px rgba(148, 163, 184, 0.16); }
        .dark .crm-ia-name, .dark .crm-ia-reason p { color: #f8fafc; }
        .dark .crm-ia-phone, .dark .crm-ia-label, .dark .crm-ia-hint, .dark .crm-ia-queue-meta, .dark .crm-ia-more, .dark .crm-ia-link { color: #94a3b8; }
        .dark .crm-ia-chip { background: #1e293b; color: #cbd5e1; }
        .dark .crm-ia-chip.is-new, .dark .crm-ia-avatar, .dark .crm-ia-mini { background: rgba(34, 197, 94, 0.16); color: #4ade80; }
        .dark .crm-ia-chip.is-accent { background: rgba(99, 102, 241, 0.2); color: #a5b4fc; }
        .dark .crm-ia-bubble blockquote { background: #1e293b; color: #e2e8f0; }
        .dark .crm-ia-queue ul, .dark .crm-ia-queue li + li { border-color: #334155; }
        .dark .crm-ia-queue-time { color: #4ade80; }
        .dark .crm-ia-queue-time.is-warn { color: #fbbf24; }
        .dark .crm-ia-queue-time.is-late { color: #f87171; }
        .dark .crm-ia-btn.is-secondary { border-color: #475569; background: #1e293b; color: #e2e8f0; }
        .dark .crm-ia-btn.is-secondary:hover { background: #273449; }
        .dark .crm-ia-btn kbd { background: rgba(255, 255, 255, 0.1); }
        .dark .crm-ia-link:hover { color: #e2e8f0; }
    </style>
</div>
