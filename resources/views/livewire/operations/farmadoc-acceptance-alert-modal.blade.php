<div
    x-data="{
        seenKey: 'ops-farmadoc-acceptance-alerts-seen',
        audioContext: null,
        init() {
            const unlock = () => this.ensureAudioContext();
            document.addEventListener('click', unlock, { once: true });
            document.addEventListener('keydown', unlock, { once: true });
            this.$nextTick(() => this.announce(@js(array_column($alerts, 'id'))));
        },
        seenIds() {
            try {
                return JSON.parse(sessionStorage.getItem(this.seenKey) ?? '[]');
            } catch (_) {
                return [];
            }
        },
        announce(ids) {
            const seen = this.seenIds();
            const fresh = (ids ?? []).filter((id) => ! seen.includes(id));

            if (fresh.length === 0) {
                return;
            }

            try {
                sessionStorage.setItem(this.seenKey, JSON.stringify([...seen, ...fresh].slice(-200)));
            } catch (_) {}

            this.playSound();
        },
        ensureAudioContext() {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;

            if (this.audioContext || ! AudioCtx) {
                return;
            }

            try {
                this.audioContext = new AudioCtx();
            } catch (_) {}
        },
        playSound() {
            this.ensureAudioContext();

            const ctx = this.audioContext;

            if (! ctx) {
                return;
            }

            try {
                if (ctx.state === 'suspended') {
                    ctx.resume();
                }

                const now = ctx.currentTime;
                const tone = (frequency, startAt, duration, volume) => {
                    const oscillator = ctx.createOscillator();
                    const gain = ctx.createGain();

                    oscillator.type = 'sine';
                    oscillator.frequency.setValueAtTime(frequency, startAt);
                    gain.gain.setValueAtTime(0.0001, startAt);
                    gain.gain.exponentialRampToValueAtTime(volume, startAt + 0.02);
                    gain.gain.exponentialRampToValueAtTime(0.0001, startAt + duration);
                    oscillator.connect(gain);
                    gain.connect(ctx.destination);
                    oscillator.start(startAt);
                    oscillator.stop(startAt + duration + 0.03);
                };

                tone(523.25, now, 0.16, 0.16);
                tone(659.25, now + 0.17, 0.16, 0.16);
                tone(783.99, now + 0.34, 0.28, 0.16);
            } catch (_) {}
        },
    }"
    x-on:{{ $soundEvent }}.window="announce($event.detail.ids)"
    class="fdaa-root"
>
    <div wire:poll.{{ $pollSeconds }}s="refreshAlerts" hidden></div>

    @if ($current)
        <div wire:key="fdaa-alert-{{ $current['id'] }}" class="fdaa-backdrop">
            <div
                class="fdaa-dialog"
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="fdaa-title-{{ $current['id'] }}"
                aria-describedby="fdaa-body-{{ $current['id'] }}"
                x-trap.noscroll="true"
            >
                @if ($total > 1)
                    <span class="fdaa-counter">Aviso 1 de {{ $total }}</span>
                @endif

                <div class="fdaa-icon" aria-hidden="true">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                </div>

                <h2 id="fdaa-title-{{ $current['id'] }}" class="fdaa-title">Farmadoc aceptó el pedido</h2>

                <p id="fdaa-body-{{ $current['id'] }}" class="fdaa-text">
                    El pedido <strong>{{ $current['order_number'] }}</strong> fue aceptado
                    @if ($current['accepted_by'])
                        por <strong>{{ $current['accepted_by'] }}</strong>
                    @endif
                    @if ($current['accepted_at'] !== '')
                        el {{ $current['accepted_at'] }}.
                    @endif
                    @if ($current['rejected_count'] > 0)
                        <span class="fdaa-warning">{{ $current['rejected_count'] === 1 ? '1 medicamento fue rechazado' : $current['rejected_count'].' medicamentos fueron rechazados' }}.</span>
                    @endif
                </p>

                <dl class="fdaa-facts">
                    <div>
                        <dt>Nota de entrega</dt>
                        <dd>{{ $current['delivery_note_number'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Orden de servicio</dt>
                        <dd>{{ $current['service_order_number'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Coordinación</dt>
                        <dd>{{ $current['coordination_reference'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Paciente</dt>
                        <dd>{{ $current['patient_name'] ?? '—' }}</dd>
                    </div>
                </dl>

                <div class="fdaa-list" role="table" aria-label="Medicamentos del pedido">
                    <div class="fdaa-list-head" role="row">
                        <span role="columnheader">Medicamento</span>
                        <span role="columnheader" class="fdaa-qty">Cant.</span>
                        <span role="columnheader" class="fdaa-state">Estado</span>
                    </div>

                    @forelse ($current['items'] as $index => $item)
                        <div class="fdaa-item" role="row" wire:key="fdaa-item-{{ $current['id'] }}-{{ $index }}">
                            <span class="fdaa-item-text" role="cell">
                                <span class="fdaa-item-name">{{ $item['name'] }}</span>
                                @if ($item['barcode'])
                                    <span class="fdaa-item-detail">{{ $item['barcode'] }}</span>
                                @endif
                                @if (! $item['accepted'] && $item['rejection_note'])
                                    <span class="fdaa-item-note">Motivo: {{ $item['rejection_note'] }}</span>
                                @endif
                            </span>
                            <span class="fdaa-qty" role="cell">{{ $item['quantity'] }}</span>
                            <span class="fdaa-state" role="cell">
                                <span @class(['fdaa-chip', 'fdaa-chip--ok' => $item['accepted'], 'fdaa-chip--no' => ! $item['accepted']])>
                                    {{ $item['accepted'] ? 'Aceptado' : 'Rechazado' }}
                                </span>
                            </span>
                        </div>
                    @empty
                        <p class="fdaa-empty">Farmadoc no informó el detalle por medicamento.</p>
                    @endforelse
                </div>

                <div class="fdaa-actions">
                    <button
                        type="button"
                        class="fdaa-button fdaa-button--secondary"
                        wire:click="dismiss({{ $current['id'] }})"
                        wire:loading.attr="disabled"
                        wire:target="dismiss, openOrder"
                    >
                        <span wire:loading.remove wire:target="dismiss">Entendido</span>
                        <span wire:loading wire:target="dismiss">Cerrando…</span>
                    </button>

                    @if ($current['delivery_note_url'])
                        <a href="{{ $current['delivery_note_url'] }}" target="_blank" rel="noopener" class="fdaa-button fdaa-button--secondary">
                            Ver nota de entrega
                        </a>
                    @endif

                    @if ($current['order_url'])
                        <button
                            type="button"
                            class="fdaa-button fdaa-button--primary"
                            wire:click="openOrder({{ $current['id'] }})"
                            wire:loading.attr="disabled"
                            wire:target="dismiss, openOrder"
                        >
                            <span wire:loading.remove wire:target="openOrder">Ver pedido</span>
                            <span wire:loading wire:target="openOrder">Abriendo…</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <style>
        .fdaa-backdrop {
            position: fixed;
            inset: 0;
            z-index: 60;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            background: rgba(15, 23, 42, 0.55);
        }
        .fdaa-dialog {
            position: relative;
            width: 100%;
            max-width: 560px;
            max-height: calc(100vh - 32px);
            overflow-y: auto;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 14px;
            padding: 30px 26px 22px;
            border-radius: 14px;
            background: #ffffff;
            color: #0f172a;
            text-align: center;
            box-shadow: 0 24px 48px rgba(15, 23, 42, 0.28);
        }
        .fdaa-counter {
            position: absolute;
            top: 14px;
            right: 16px;
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
        }
        .fdaa-icon {
            width: 56px;
            height: 56px;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #dcfce7;
            color: #15803d;
        }
        .fdaa-title {
            margin: 0;
            font-size: 19px;
            font-weight: 700;
        }
        .fdaa-text {
            margin: 0;
            font-size: 14px;
            line-height: 1.55;
            color: #475569;
        }
        .fdaa-text strong {
            color: #0f172a;
        }
        .fdaa-warning {
            display: block;
            margin-top: 4px;
            font-weight: 600;
            color: #b45309;
        }
        .fdaa-facts {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px 16px;
            width: 100%;
            margin: 0;
            padding: 12px 14px;
            box-sizing: border-box;
            border-radius: 10px;
            background: #f8fafc;
            text-align: left;
        }
        .fdaa-facts dt {
            font-size: 11.5px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            color: #64748b;
        }
        .fdaa-facts dd {
            margin: 2px 0 0;
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
            overflow-wrap: anywhere;
        }
        .fdaa-list {
            width: 100%;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            text-align: left;
            overflow: hidden;
        }
        .fdaa-list-head,
        .fdaa-item {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 52px 96px;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
        }
        .fdaa-list-head {
            font-size: 11.5px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            color: #64748b;
            background: #f8fafc;
        }
        .fdaa-item {
            border-top: 1px solid #e2e8f0;
        }
        .fdaa-qty {
            text-align: center;
            font-variant-numeric: tabular-nums;
            font-weight: 600;
        }
        .fdaa-state {
            text-align: right;
        }
        .fdaa-item-text {
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 0;
        }
        .fdaa-item-name {
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
            overflow-wrap: anywhere;
        }
        .fdaa-item-detail {
            font-size: 12px;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            color: #64748b;
        }
        .fdaa-item-note {
            font-size: 12.5px;
            color: #b91c1c;
            overflow-wrap: anywhere;
        }
        .fdaa-chip {
            display: inline-block;
            font-size: 12px;
            font-weight: 600;
            border-radius: 999px;
            padding: 3px 10px;
        }
        .fdaa-chip--ok {
            background: #dcfce7;
            color: #166534;
        }
        .fdaa-chip--no {
            background: #fee2e2;
            color: #991b1b;
        }
        .fdaa-empty {
            margin: 0;
            padding: 12px 14px;
            font-size: 13px;
            color: #64748b;
        }
        .fdaa-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            width: 100%;
            margin-top: 6px;
        }
        .fdaa-button {
            flex: 1 1 140px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            box-sizing: border-box;
            padding: 0 14px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }
        .fdaa-button:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }
        .fdaa-button--secondary {
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #334155;
        }
        .fdaa-button--primary {
            border: 0;
            background: #2d89ca;
            color: #ffffff;
        }
        .fdaa-button--primary:hover {
            background: #2373ab;
        }
        .fdaa-button:focus-visible {
            outline: 2px solid #2d89ca;
            outline-offset: 2px;
        }
        @media (max-width: 480px) {
            .fdaa-facts {
                grid-template-columns: minmax(0, 1fr);
            }
            .fdaa-list-head,
            .fdaa-item {
                grid-template-columns: minmax(0, 1fr) 40px 84px;
                padding: 10px;
            }
        }
        .dark .fdaa-dialog {
            background: #111827;
            color: #f1f5f9;
        }
        .dark .fdaa-title,
        .dark .fdaa-text strong,
        .dark .fdaa-item-name,
        .dark .fdaa-facts dd {
            color: #f1f5f9;
        }
        .dark .fdaa-text,
        .dark .fdaa-item-detail,
        .dark .fdaa-counter,
        .dark .fdaa-facts dt,
        .dark .fdaa-list-head,
        .dark .fdaa-empty {
            color: #94a3b8;
        }
        .dark .fdaa-facts,
        .dark .fdaa-list-head {
            background: #1f2937;
        }
        .dark .fdaa-list,
        .dark .fdaa-item {
            border-color: #334155;
        }
        .dark .fdaa-icon,
        .dark .fdaa-chip--ok {
            background: rgba(34, 197, 94, 0.16);
            color: #4ade80;
        }
        .dark .fdaa-chip--no {
            background: rgba(239, 68, 68, 0.16);
            color: #f87171;
        }
        .dark .fdaa-item-note {
            color: #f87171;
        }
        .dark .fdaa-warning {
            color: #fbbf24;
        }
        .dark .fdaa-button--secondary {
            border-color: #475569;
            background: #1f2937;
            color: #e2e8f0;
        }
    </style>
</div>
