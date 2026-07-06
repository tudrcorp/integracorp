<x-filament-panels::page>
    @php
        $settings = $this->settingsRecord;
        $emailCount = $this->configuredEmailCount;
        $phoneCount = $this->configuredPhoneCount;
        $hasRecipients = $this->hasRecipients;
        $lastUpdated = $settings->updated_at?->timezone(config('app.timezone'))->format('d/m/Y H:i');
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
        </style>
    @endpush

    <div class="can-settings-page space-y-0">
        <section class="can-hero">
            <div class="can-hero-top">
                <div class="can-hero-copy">
                    <div class="can-hero-icon" aria-hidden="true">
                        <x-filament::icon icon="heroicon-o-bell-alert" class="h-6 w-6" />
                    </div>
                    <div>
                        <h2>Alertas de nuevos asociados</h2>
                        <p>
                            Cada registro público dispara de forma asíncrona un correo y un WhatsApp con el detalle del asociado,
                            la empresa, el responsable y un recordatorio para iniciar la gestión del voucher ILS.
                        </p>
                        <div class="can-flow">
                            <span class="can-flow-step">1. Registro público</span>
                            <span class="can-flow-step">2. Cola asíncrona</span>
                            <span class="can-flow-step">3. Email + WhatsApp</span>
                            <span class="can-flow-step">4. Gestión voucher ILS</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="can-stats">
                <div class="can-stat">
                    <span class="can-stat-label">Correos activos</span>
                    <span class="can-stat-value" wire:key="email-count-{{ $emailCount }}">{{ $emailCount }}</span>
                    <span class="can-stat-meta">Destinatarios por email</span>
                </div>
                <div class="can-stat">
                    <span class="can-stat-label">WhatsApp activos</span>
                    <span class="can-stat-value" wire:key="phone-count-{{ $phoneCount }}">{{ $phoneCount }}</span>
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
                <x-filament::icon icon="heroicon-o-ticket" class="h-5 w-5" />
            </div>
            <div>
                <p>
                    <strong>Acción requerida para el analista:</strong>
                    al recibir la alerta debe ingresar a INTEGRACORP → Nuevos Negocios → Asociados y cargar el
                    <strong>voucher ILS</strong> para activar el plan del asociado registrado.
                </p>
            </div>
        </section>

        @unless ($hasRecipients)
            <div class="can-empty-hint">
                Aún no hay destinatarios configurados. Agregue al menos un correo o un teléfono para activar las notificaciones.
            </div>
        @endunless

        {{ $this->content }}
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
