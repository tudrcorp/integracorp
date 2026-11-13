<div
    x-data="{
        seenKey: 'ops-non-covered-medication-alerts-seen',
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

                    oscillator.type = 'triangle';
                    oscillator.frequency.setValueAtTime(frequency, startAt);
                    gain.gain.setValueAtTime(0.0001, startAt);
                    gain.gain.exponentialRampToValueAtTime(volume, startAt + 0.02);
                    gain.gain.exponentialRampToValueAtTime(0.0001, startAt + duration);
                    oscillator.connect(gain);
                    gain.connect(ctx.destination);
                    oscillator.start(startAt);
                    oscillator.stop(startAt + duration + 0.03);
                };

                tone(659.25, now, 0.18, 0.16);
                tone(880, now + 0.2, 0.18, 0.16);
                tone(659.25, now + 0.55, 0.18, 0.14);
                tone(880, now + 0.75, 0.24, 0.14);
            } catch (_) {}
        },
    }"
    x-on:{{ $soundEvent }}.window="announce($event.detail.ids)"
    class="ncma-root"
>
    <div wire:poll.{{ $pollSeconds }}s="refreshAlerts" hidden></div>

    @if ($current)
        <div
            wire:key="ncma-alert-{{ $current['id'] }}"
            class="ncma-backdrop"
        >
            <div
                class="ncma-dialog"
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="ncma-title-{{ $current['id'] }}"
                aria-describedby="ncma-body-{{ $current['id'] }}"
                x-trap.noscroll="true"
            >
                @if ($total > 1)
                    <span class="ncma-counter">Aviso 1 de {{ $total }}</span>
                @endif

                <div class="ncma-icon" aria-hidden="true">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                </div>

                <h2 id="ncma-title-{{ $current['id'] }}" class="ncma-title">
                    {{ count($current['medications']) === 1 ? 'Medicamento no cubierto' : 'Medicamentos no cubiertos' }}
                </h2>

                <p id="ncma-body-{{ $current['id'] }}" class="ncma-text">
                    El médico recetó
                    {{ count($current['medications']) === 1 ? 'un medicamento no cubierto' : count($current['medications']).' medicamentos no cubiertos' }}
                    a <strong>{{ $current['patient_name'] }}</strong>
                    (C.I. {{ $current['patient_document'] }}), afiliado al
                    <strong>{{ $current['plan_name'] }} de {{ $current['coverage_label'] }}</strong>.
                </p>

                <div class="ncma-chips">
                    <span class="ncma-chip ncma-chip--plan">{{ $current['plan_name'] }} · {{ $current['coverage_label'] }}</span>
                    <span class="ncma-chip ncma-chip--warn">No cubierto</span>
                    <span class="ncma-chip">Ref. {{ $current['reference'] }}</span>
                </div>

                <fieldset class="ncma-list">
                    <legend class="ncma-legend">
                        Marque los que va a gestionar
                        <span class="ncma-legend-count">{{ count($selectedMedicationIds) }} de {{ count($current['medications']) }}</span>
                    </legend>

                    @foreach ($current['medications'] as $medication)
                        <label class="ncma-item" wire:key="ncma-med-{{ $current['id'] }}-{{ $medication['id'] }}">
                            <input
                                type="checkbox"
                                class="ncma-checkbox"
                                value="{{ $medication['id'] }}"
                                wire:model.live="selectedMedicationIds"
                            >
                            <span class="ncma-item-text">
                                <span class="ncma-item-name">{{ $medication['name'] }}</span>
                                @if ($medication['indications'] !== '')
                                    <span class="ncma-item-detail">{{ $medication['indications'] }}</span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </fieldset>

                @if (count($selectedMedicationIds) === 0)
                    <p class="ncma-hint" role="status">Marque al menos un medicamento para ir a gestionarlo.</p>
                @endif

                <div class="ncma-actions">
                    <button
                        type="button"
                        class="ncma-button ncma-button--secondary"
                        wire:click="dismiss({{ $current['id'] }})"
                        wire:loading.attr="disabled"
                        wire:target="dismiss, manage"
                    >
                        OK
                    </button>
                    <button
                        type="button"
                        class="ncma-button ncma-button--primary"
                        wire:click="manage({{ $current['id'] }})"
                        wire:loading.attr="disabled"
                        wire:target="dismiss, manage"
                        @disabled(count($selectedMedicationIds) === 0)
                    >
                        <span wire:loading.remove wire:target="manage">Ir a gestionar</span>
                        <span wire:loading wire:target="manage">Abriendo…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <style>
        .ncma-backdrop {
            position: fixed;
            inset: 0;
            z-index: 60;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            background: rgba(15, 23, 42, 0.55);
        }
        .ncma-dialog {
            position: relative;
            width: 100%;
            max-width: 500px;
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
        .ncma-counter {
            position: absolute;
            top: 14px;
            right: 16px;
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
        }
        .ncma-icon {
            width: 56px;
            height: 56px;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fef3c7;
            color: #b45309;
        }
        .ncma-title {
            margin: 0;
            font-size: 19px;
            font-weight: 700;
        }
        .ncma-text {
            margin: 0;
            font-size: 14px;
            line-height: 1.55;
            color: #475569;
        }
        .ncma-text strong {
            color: #0f172a;
        }
        .ncma-chips {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 8px;
        }
        .ncma-chip {
            font-size: 12px;
            font-weight: 600;
            border-radius: 999px;
            padding: 4px 10px;
            background: #f1f5f9;
            color: #334155;
        }
        .ncma-chip--plan {
            background: #e0effa;
            color: #00467f;
        }
        .ncma-chip--warn {
            background: #fef3c7;
            color: #92400e;
        }
        .ncma-list {
            width: 100%;
            margin: 4px 0 0;
            padding: 0;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            text-align: left;
        }
        .ncma-legend {
            display: flex;
            width: 100%;
            box-sizing: border-box;
            justify-content: space-between;
            gap: 8px;
            padding: 0 6px;
            font-size: 12.5px;
            font-weight: 600;
            color: #334155;
        }
        .ncma-legend-count {
            color: #64748b;
            font-weight: 500;
        }
        .ncma-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            min-height: 44px;
            box-sizing: border-box;
            padding: 10px 14px;
            border-top: 1px solid #e2e8f0;
            cursor: pointer;
        }
        .ncma-item:first-of-type {
            border-top: 0;
        }
        .ncma-checkbox {
            flex: none;
            width: 18px;
            height: 18px;
            margin-top: 2px;
            accent-color: #2d89ca;
            cursor: pointer;
        }
        .ncma-item-text {
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 0;
        }
        .ncma-item-name {
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
            overflow-wrap: anywhere;
        }
        .ncma-item-detail {
            font-size: 12.5px;
            color: #64748b;
            overflow-wrap: anywhere;
        }
        .ncma-hint {
            margin: 0;
            font-size: 12.5px;
            color: #b45309;
        }
        .ncma-actions {
            display: flex;
            gap: 12px;
            width: 100%;
            margin-top: 6px;
        }
        .ncma-button {
            flex: 1;
            min-height: 44px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        .ncma-button:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }
        .ncma-button--secondary {
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #334155;
        }
        .ncma-button--primary {
            border: 0;
            background: #2d89ca;
            color: #ffffff;
        }
        .ncma-button--primary:not(:disabled):hover {
            background: #2373ab;
        }
        .ncma-button:focus-visible,
        .ncma-checkbox:focus-visible {
            outline: 2px solid #2d89ca;
            outline-offset: 2px;
        }
        .dark .ncma-dialog {
            background: #111827;
            color: #f1f5f9;
        }
        .dark .ncma-title,
        .dark .ncma-text strong,
        .dark .ncma-item-name {
            color: #f1f5f9;
        }
        .dark .ncma-text,
        .dark .ncma-item-detail,
        .dark .ncma-legend-count,
        .dark .ncma-counter {
            color: #94a3b8;
        }
        .dark .ncma-legend {
            color: #cbd5e1;
        }
        .dark .ncma-icon,
        .dark .ncma-chip--warn {
            background: rgba(245, 158, 11, 0.16);
            color: #fbbf24;
        }
        .dark .ncma-chip {
            background: #1f2937;
            color: #cbd5e1;
        }
        .dark .ncma-chip--plan {
            background: rgba(45, 137, 202, 0.2);
            color: #93c5fd;
        }
        .dark .ncma-list,
        .dark .ncma-item {
            border-color: #334155;
        }
        .dark .ncma-button--secondary {
            border-color: #475569;
            background: #1f2937;
            color: #e2e8f0;
        }
        .dark .ncma-hint {
            color: #fbbf24;
        }
    </style>
</div>
