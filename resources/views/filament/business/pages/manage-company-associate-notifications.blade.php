<x-filament-panels::page>
    @php
        $activeKey = $this->getActiveNotificationKeyEnum();
        $settings = $this->settingsRecord;
        $emailCount = $this->configuredEmailCount;
        $phoneCount = $this->configuredPhoneCount;
        $hasRecipients = $this->hasRecipients;
        $isTaskActive = (bool) ($this->data['is_active'] ?? $settings->isActive());
        $lastUpdated = $settings->updated_at?->timezone(config('app.timezone'))->format('d/m/Y H:i');
        $managedKeys = \App\Enums\SystemNotificationKey::managed();
    @endphp

    @push('styles')
        <style>
            .can-settings-page {
                --can-accent: #0284c7;
                --can-accent-soft: rgb(14 165 233 / 12%);
                --can-success: #16a34a;
                --can-warning: #ea580c;
                --can-line: rgb(148 163 184 / 28%);
                --can-panel: rgb(255 255 255 / 72%);
                --can-text: #0f172a;
                --can-muted: #64748b;
            }

            .dark .can-settings-page {
                --can-accent: #38bdf8;
                --can-accent-soft: rgb(56 189 248 / 14%);
                --can-success: #4ade80;
                --can-warning: #fb923c;
                --can-line: rgb(148 163 184 / 18%);
                --can-panel: rgb(15 23 42 / 55%);
                --can-text: #f8fafc;
                --can-muted: #94a3b8;
            }

            .can-settings-page .can-hero {
                display: grid;
                gap: 1rem;
                margin-bottom: 1rem;
                padding: 1.25rem 1.35rem;
                border: 1px solid var(--can-line);
                border-radius: 1.25rem;
                background: linear-gradient(135deg, var(--can-panel), rgb(255 255 255 / 35%));
                box-shadow: 0 18px 50px -28px rgb(15 23 42 / 35%);
            }

            .dark .can-settings-page .can-hero {
                background: linear-gradient(135deg, rgb(15 23 42 / 88%), rgb(30 41 59 / 72%));
            }

            .can-settings-page .can-hero-top {
                display: flex;
                flex-wrap: wrap;
                align-items: flex-start;
                justify-content: space-between;
                gap: 1rem;
            }

            .can-settings-page .can-hero-copy {
                display: flex;
                gap: 0.9rem;
                min-width: 0;
                flex: 1 1 18rem;
            }

            .can-settings-page .can-hero-icon {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 3rem;
                height: 3rem;
                border-radius: 1rem;
                background: var(--can-accent-soft);
                color: var(--can-accent);
                flex-shrink: 0;
            }

            .can-settings-page .can-hero h2 {
                margin: 0 0 0.35rem;
                font-size: 1.2rem;
                font-weight: 800;
                letter-spacing: -0.02em;
                color: var(--can-text);
            }

            .can-settings-page .can-hero p {
                margin: 0;
                max-width: 46rem;
                font-size: 0.92rem;
                line-height: 1.55;
                color: var(--can-muted);
            }

            .can-settings-page .can-stats {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 0.75rem;
                width: 100%;
            }

            @media (max-width: 900px) {
                .can-settings-page .can-stats {
                    grid-template-columns: 1fr;
                }
            }

            .can-settings-page .can-stat {
                padding: 0.9rem 1rem;
                border: 1px solid var(--can-line);
                border-radius: 1rem;
                background: rgb(255 255 255 / 55%);
            }

            .dark .can-settings-page .can-stat {
                background: rgb(15 23 42 / 45%);
            }

            .can-settings-page .can-stat-label {
                display: block;
                margin-bottom: 0.35rem;
                font-size: 0.72rem;
                font-weight: 700;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                color: var(--can-muted);
            }

            .can-settings-page .can-stat-value {
                font-size: 1.45rem;
                font-weight: 800;
                line-height: 1.1;
                color: var(--can-text);
            }

            .can-settings-page .can-stat-meta {
                margin-top: 0.25rem;
                font-size: 0.78rem;
                color: var(--can-muted);
            }

            .can-settings-page .can-callout {
                display: flex;
                gap: 0.85rem;
                align-items: flex-start;
                margin-bottom: 1rem;
                padding: 1rem 1.1rem;
                border: 1px solid rgb(251 146 60 / 35%);
                border-radius: 1rem;
                background: linear-gradient(135deg, rgb(255 247 237 / 95%), rgb(255 255 255 / 70%));
            }

            .dark .can-settings-page .can-callout {
                border-color: rgb(251 146 60 / 28%);
                background: linear-gradient(135deg, rgb(67 20 7 / 55%), rgb(15 23 42 / 55%));
            }

            .can-settings-page .can-callout strong {
                color: #c2410c;
            }

            .dark .can-settings-page .can-callout strong {
                color: #fdba74;
            }

            .can-settings-page .can-callout p {
                margin: 0;
                font-size: 0.88rem;
                line-height: 1.55;
                color: var(--can-muted);
            }

            .can-settings-page .can-flow {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
                margin-top: 0.85rem;
            }

            .can-settings-page .can-flow-step {
                display: inline-flex;
                align-items: center;
                gap: 0.4rem;
                padding: 0.35rem 0.7rem;
                border-radius: 999px;
                border: 1px solid var(--can-line);
                background: rgb(255 255 255 / 65%);
                font-size: 0.75rem;
                font-weight: 600;
                color: var(--can-muted);
            }

            .dark .can-settings-page .can-flow-step {
                background: rgb(15 23 42 / 55%);
            }

            .can-settings-page .can-settings-shell .fi-fo-repeater-item {
                border-radius: 1rem !important;
                border-color: var(--can-line) !important;
                background: rgb(255 255 255 / 72%) !important;
                box-shadow: 0 10px 30px -24px rgb(15 23 42 / 45%);
            }

            .dark .can-settings-page .can-settings-shell .fi-fo-repeater-item {
                background: rgb(15 23 42 / 55%) !important;
            }

            .can-settings-page .can-settings-shell .fi-fo-repeater-item-header {
                border-top-left-radius: 1rem !important;
                border-top-right-radius: 1rem !important;
            }

            .can-settings-page .can-settings-panel--email .fi-section-header-heading {
                color: #0369a1;
            }

            .dark .can-settings-page .can-settings-panel--email .fi-section-header-heading {
                color: #7dd3fc;
            }

            .can-settings-page .can-settings-panel--phone .fi-section-header-heading {
                color: #15803d;
            }

            .dark .can-settings-page .can-settings-panel--phone .fi-section-header-heading {
                color: #86efac;
            }

            .can-settings-page .can-settings-actions {
                margin-top: 0.25rem;
                padding-top: 0.25rem;
            }

            .can-settings-page .can-empty-hint {
                margin-bottom: 1rem;
                padding: 0.9rem 1rem;
                border: 1px dashed var(--can-line);
                border-radius: 1rem;
                font-size: 0.86rem;
                color: var(--can-muted);
                text-align: center;
            }

            .can-settings-page .can-tabs {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
                margin-bottom: 1rem;
            }

            .can-settings-page .can-tab {
                border: 1px solid var(--can-line);
                background: var(--can-panel);
                color: var(--can-muted);
                border-radius: 999px;
                padding: 0.55rem 0.95rem;
                font-size: 0.86rem;
                font-weight: 650;
                transition: background 120ms ease, color 120ms ease, border-color 120ms ease;
            }

            .can-settings-page .can-tab:hover {
                color: var(--can-text);
                border-color: rgb(14 165 233 / 35%);
            }

            .can-settings-page .can-tab.is-active {
                background: var(--can-accent-soft);
                border-color: rgb(14 165 233 / 45%);
                color: var(--can-accent);
            }

            .can-settings-page .can-tab-status {
                display: inline-block;
                width: 0.45rem;
                height: 0.45rem;
                border-radius: 999px;
                margin-right: 0.35rem;
                vertical-align: middle;
                background: #16a34a;
            }

            .can-settings-page .can-tab-status.is-off {
                background: #dc2626;
            }

            .can-settings-page .can-status-pill {
                display: inline-flex;
                align-items: center;
                gap: 0.35rem;
                padding: 0.25rem 0.65rem;
                border-radius: 999px;
                font-size: 0.75rem;
                font-weight: 700;
                letter-spacing: 0.02em;
                text-transform: uppercase;
            }

            .can-settings-page .can-status-pill.is-on {
                background: rgb(22 163 74 / 12%);
                color: #15803d;
            }

            .can-settings-page .can-status-pill.is-off {
                background: rgb(220 38 38 / 12%);
                color: #b91c1c;
            }
        </style>
    @endpush

    <div class="can-settings-page space-y-0" wire:key="notification-center-{{ $activeKey->value }}">
        <div class="can-tabs" role="tablist" aria-label="Tipos de notificación">
            @foreach ($managedKeys as $key)
                @php
                    $keyIsActive = \App\Support\SystemNotificationRecipients::isActive($key);
                @endphp
                <button
                    type="button"
                    role="tab"
                    class="can-tab {{ $activeKey === $key ? 'is-active' : '' }}"
                    wire:click="selectNotificationKey('{{ $key->value }}')"
                    aria-selected="{{ $activeKey === $key ? 'true' : 'false' }}"
                >
                    <span class="can-tab-status {{ $keyIsActive ? '' : 'is-off' }}" aria-hidden="true"></span>
                    {{ $key->label() }}
                </button>
            @endforeach
        </div>

        <section class="can-hero">
            <div class="can-hero-top">
                <div class="can-hero-copy">
                    <div class="can-hero-icon" aria-hidden="true">
                        <x-filament::icon icon="heroicon-o-bell-alert" class="h-6 w-6" />
                    </div>
                    <div>
                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <h2 class="!mb-0">{{ $activeKey->heroTitle() }}</h2>
                            <span class="can-status-pill {{ $isTaskActive ? 'is-on' : 'is-off' }}" wire:key="task-status-{{ $activeKey->value }}-{{ $isTaskActive ? 'on' : 'off' }}">
                                {{ $isTaskActive ? 'Activa' : 'Inactiva' }}
                            </span>
                        </div>
                        <p>{{ $activeKey->heroBody() }}</p>
                        <p class="mt-2 text-sm" style="color: var(--can-muted);">{{ $activeKey->activationHelp() }}</p>
                        <div class="can-flow">
                            @foreach ($activeKey->flowSteps() as $step)
                                <span class="can-flow-step">{{ $step }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="can-stats">
                <div class="can-stat">
                    <span class="can-stat-label">Estado</span>
                    <span class="can-stat-value text-base sm:text-lg" wire:key="status-stat-{{ $activeKey->value }}-{{ $isTaskActive ? 'on' : 'off' }}">
                        {{ $isTaskActive ? 'Activa' : 'Inactiva' }}
                    </span>
                    <span class="can-stat-meta">
                        {{ $activeKey->pausesScheduledTask() ? 'Controla la tarea programada' : 'Controla el envío de alertas' }}
                    </span>
                </div>
                <div class="can-stat">
                    <span class="can-stat-label">Correos activos</span>
                    <span class="can-stat-value" wire:key="email-count-{{ $activeKey->value }}-{{ $emailCount }}">{{ $emailCount }}</span>
                    <span class="can-stat-meta">Destinatarios por email</span>
                </div>
                <div class="can-stat">
                    <span class="can-stat-label">WhatsApp activos</span>
                    <span class="can-stat-value" wire:key="phone-count-{{ $activeKey->value }}-{{ $phoneCount }}">{{ $phoneCount }}</span>
                    <span class="can-stat-meta">Teléfonos configurados</span>
                </div>
                <div class="can-stat">
                    <span class="can-stat-label">Última actualización</span>
                    <span class="can-stat-value text-base sm:text-lg">{{ $lastUpdated ?? '—' }}</span>
                    <span class="can-stat-meta">
                        {{ filled($settings->updated_by) ? 'Por '.$settings->updated_by : 'Sin cambios guardados aún' }}
                    </span>
                </div>
            </div>
        </section>

        <section class="can-callout">
            <div class="can-hero-icon" style="width:2.4rem;height:2.4rem;border-radius:0.8rem;" aria-hidden="true">
                <x-filament::icon :icon="$activeKey->calloutIcon()" class="h-5 w-5" />
            </div>
            <div>
                <p>
                    <strong>{{ $activeKey->calloutTitle() }}</strong>
                    {{ $activeKey->calloutBody() }}
                </p>
            </div>
        </section>

        @unless ($hasRecipients)
            <div class="can-empty-hint">
                {{ $activeKey->emptyRecipientsHint() }}
            </div>
        @endunless

        {{ $this->content }}
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
