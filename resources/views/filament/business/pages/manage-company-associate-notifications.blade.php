<x-filament-panels::page>
    @php
        $activeKey = $this->getActiveNotificationKeyEnum();
        $settings = $this->settingsRecord;
        $emailCount = $this->configuredEmailCount;
        $phoneCount = $this->configuredPhoneCount;
        $hasRecipients = $this->hasRecipients;
        $isTaskActive = (bool) ($this->data['is_active'] ?? $settings->isActive());
        $lastUpdated = $settings->updated_at?->timezone(config('app.timezone'))->format('d/m/Y H:i');
        $catalog = $this->keyCatalog;
        $activeChannel = collect($catalog['areas'])->flatMap(fn (array $area): array => $area['items'])->firstWhere('key', $activeKey->value);
        $areaLabel = \App\Enums\SystemNotificationKey::AREAS[$activeKey->area()] ?? '';
        $telegramMode = \App\Enums\TelegramDeliveryMode::tryFrom((string) ($this->data['telegram_mode'] ?? '')) ?? \App\Enums\TelegramDeliveryMode::Off;
        $telegramPeople = count((array) ($this->data['telegram_recipient_ids'] ?? []));
        $telegramGroups = count((array) ($this->data['telegram_group_ids'] ?? []));
        $usesLists = $activeKey->usesEmailRecipients() || $activeKey->usesPhoneRecipients();

        $channelIcon = fn (string $channel): string => match ($channel) {
            'telegram' => 'heroicon-m-paper-airplane',
            'ready' => 'heroicon-o-paper-airplane',
            default => 'heroicon-m-chat-bubble-left-right',
        };

        $filters = [
            ['id' => 'all', 'label' => 'Todos', 'count' => $catalog['counts']['total']],
            ['id' => 'telegram', 'label' => 'Por Telegram', 'count' => $catalog['counts']['telegram']],
            ['id' => 'ready', 'label' => 'Listos', 'count' => $catalog['counts']['ready']],
            ['id' => 'whatsapp', 'label' => 'Solo WhatsApp', 'count' => $catalog['counts']['whatsapp']],
            ['id' => 'paused', 'label' => 'Pausados', 'count' => $catalog['counts']['paused']],
        ];
    @endphp

    @push('styles')
        <style>
            .can-settings-page {
                --can-accent: #0284c7;
                --can-accent-soft: rgb(14 165 233 / 10%);
                --can-tg: #0284c7;
                --can-tg-soft: rgb(14 165 233 / 12%);
                --can-ready: #7c3aed;
                --can-ready-soft: rgb(124 58 237 / 10%);
                --can-wa: #15803d;
                --can-wa-soft: rgb(22 163 74 / 10%);
                --can-off: #b91c1c;
                --can-off-soft: rgb(220 38 38 / 10%);
                --can-line: rgb(148 163 184 / 26%);
                --can-surface: #ffffff;
                --can-surface-2: rgb(248 250 252);
                --can-text: #0f172a;
                --can-muted: #64748b;
            }

            .dark .can-settings-page {
                --can-accent: #38bdf8;
                --can-accent-soft: rgb(56 189 248 / 12%);
                --can-tg: #38bdf8;
                --can-tg-soft: rgb(56 189 248 / 14%);
                --can-ready: #a78bfa;
                --can-ready-soft: rgb(167 139 250 / 14%);
                --can-wa: #4ade80;
                --can-wa-soft: rgb(74 222 128 / 12%);
                --can-off: #f87171;
                --can-off-soft: rgb(248 113 113 / 14%);
                --can-line: rgb(148 163 184 / 16%);
                --can-surface: rgb(17 24 39);
                --can-surface-2: rgb(15 23 42 / 70%);
                --can-text: #f8fafc;
                --can-muted: #94a3b8;
            }

            .can-settings-page .can-layout {
                display: grid;
                gap: 1.25rem;
                align-items: start;
            }

            @media (min-width: 1280px) {
                .can-settings-page .can-layout {
                    grid-template-columns: 21rem minmax(0, 1fr);
                }

                .can-settings-page .can-aside {
                    position: sticky;
                    top: 5rem;
                    max-height: calc(100vh - 6rem);
                }
            }

            /* ── Lista de avisos ── */
            .can-settings-page .can-aside {
                display: flex;
                flex-direction: column;
                border: 1px solid var(--can-line);
                border-radius: 1rem;
                background: var(--can-surface);
                overflow: hidden;
            }

            .can-settings-page .can-aside-head {
                display: grid;
                gap: 0.65rem;
                padding: 0.9rem 0.9rem 0.75rem;
                border-bottom: 1px solid var(--can-line);
            }

            .can-settings-page .can-search {
                width: 100%;
                padding: 0.5rem 0.75rem 0.5rem 2.1rem;
                border: 1px solid var(--can-line);
                border-radius: 0.65rem;
                background: var(--can-surface-2);
                color: var(--can-text);
                font-size: 0.85rem;
            }

            .can-settings-page .can-search:focus {
                outline: none;
                border-color: var(--can-accent);
                box-shadow: 0 0 0 3px var(--can-accent-soft);
            }

            .can-settings-page .can-filters {
                display: flex;
                flex-wrap: wrap;
                gap: 0.3rem;
            }

            .can-settings-page .can-filter {
                display: inline-flex;
                align-items: center;
                gap: 0.35rem;
                padding: 0.25rem 0.55rem;
                border: 1px solid var(--can-line);
                border-radius: 999px;
                background: transparent;
                color: var(--can-muted);
                font-size: 0.74rem;
                font-weight: 600;
                cursor: pointer;
                transition: background 120ms ease, color 120ms ease, border-color 120ms ease;
            }

            .can-settings-page .can-filter:hover {
                color: var(--can-text);
            }

            .can-settings-page .can-filter.is-on {
                background: var(--can-text);
                border-color: var(--can-text);
                color: var(--can-surface);
            }

            .can-settings-page .can-filter-count {
                font-variant-numeric: tabular-nums;
                opacity: 0.75;
            }

            .can-settings-page .can-aside-list {
                flex: 1;
                overflow-y: auto;
                padding: 0.4rem 0.45rem 0.75rem;
            }

            .can-settings-page .can-area-title {
                margin: 0.75rem 0.5rem 0.3rem;
                font-size: 0.68rem;
                font-weight: 700;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                color: var(--can-muted);
            }

            .can-settings-page .can-chips {
                display: grid;
                gap: 0.1rem;
            }

            .can-settings-page .can-chip {
                position: relative;
                display: flex;
                width: 100%;
                align-items: center;
                gap: 0.55rem;
                padding: 0.5rem 0.55rem;
                border: 0;
                border-radius: 0.6rem;
                background: transparent;
                color: var(--can-text);
                font-size: 0.83rem;
                font-weight: 500;
                text-align: left;
                cursor: pointer;
                transition: background 120ms ease;
            }

            .can-settings-page .can-chip:hover {
                background: var(--can-surface-2);
            }

            .can-settings-page .can-chip.is-telegram { --can-chip: var(--can-tg); --can-chip-soft: var(--can-tg-soft); }
            .can-settings-page .can-chip.is-ready { --can-chip: var(--can-ready); --can-chip-soft: var(--can-ready-soft); }
            .can-settings-page .can-chip.is-whatsapp { --can-chip: var(--can-wa); --can-chip-soft: var(--can-wa-soft); }

            .can-settings-page .can-chip.is-selected {
                background: var(--can-chip-soft);
                font-weight: 650;
            }

            .can-settings-page .can-chip.is-selected::before {
                content: '';
                position: absolute;
                left: 0;
                top: 0.45rem;
                bottom: 0.45rem;
                width: 3px;
                border-radius: 999px;
                background: var(--can-chip);
            }

            .can-settings-page .can-chip.is-paused .can-chip-label {
                color: var(--can-muted);
            }

            .can-settings-page .can-chip-icon {
                color: var(--can-chip);
                flex-shrink: 0;
            }

            .can-settings-page .can-chip-label {
                flex: 1;
                min-width: 0;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .can-settings-page .can-chip-tag {
                flex-shrink: 0;
                padding: 0.05rem 0.4rem;
                border-radius: 999px;
                font-size: 0.66rem;
                font-weight: 700;
                color: var(--can-chip);
                background: var(--can-chip-soft);
                white-space: nowrap;
            }

            .can-settings-page .can-chip-tag.is-paused {
                color: var(--can-off);
                background: var(--can-off-soft);
            }

            /* ── Detalle del aviso ── */
            .can-settings-page .can-main {
                display: grid;
                gap: 1rem;
                min-width: 0;
            }

            .can-settings-page .can-hero {
                display: grid;
                gap: 1rem;
                padding: 1.25rem;
                border: 1px solid var(--can-line);
                border-radius: 1rem;
                background: var(--can-surface);
            }

            .can-settings-page .can-hero-top {
                display: flex;
                gap: 0.9rem;
                align-items: flex-start;
            }

            .can-settings-page .can-hero-icon {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 2.75rem;
                height: 2.75rem;
                border-radius: 0.85rem;
                background: var(--can-accent-soft);
                color: var(--can-accent);
                flex-shrink: 0;
            }

            .can-settings-page .can-breadcrumb {
                font-size: 0.74rem;
                font-weight: 600;
                color: var(--can-muted);
            }

            .can-settings-page .can-hero h2 {
                margin: 0.1rem 0 0.4rem;
                font-size: 1.25rem;
                font-weight: 700;
                line-height: 1.3;
                color: var(--can-text);
            }

            .can-settings-page .can-hero p {
                margin: 0;
                font-size: 0.88rem;
                line-height: 1.55;
                color: var(--can-muted);
            }

            .can-settings-page .can-pill {
                display: inline-flex;
                align-items: center;
                gap: 0.3rem;
                padding: 0.18rem 0.6rem;
                border-radius: 999px;
                font-size: 0.72rem;
                font-weight: 700;
            }

            .can-settings-page .can-pill.is-on { color: var(--can-wa); background: var(--can-wa-soft); }
            .can-settings-page .can-pill.is-off { color: var(--can-off); background: var(--can-off-soft); }
            .can-settings-page .can-pill.is-telegram { color: var(--can-tg); background: var(--can-tg-soft); }
            .can-settings-page .can-pill.is-ready { color: var(--can-ready); background: var(--can-ready-soft); }
            .can-settings-page .can-pill.is-whatsapp { color: var(--can-wa); background: var(--can-wa-soft); }

            .can-settings-page .can-stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(9.5rem, 1fr));
                gap: 0.6rem;
            }

            .can-settings-page .can-stat {
                display: grid;
                gap: 0.15rem;
                padding: 0.7rem 0.85rem;
                border-radius: 0.8rem;
                background: var(--can-surface-2);
            }

            .can-settings-page .can-stat-label {
                font-size: 0.7rem;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: var(--can-muted);
            }

            .can-settings-page .can-stat-value {
                font-size: 1.15rem;
                font-weight: 700;
                color: var(--can-text);
                font-variant-numeric: tabular-nums;
            }

            .can-settings-page .can-stat-meta {
                font-size: 0.72rem;
                color: var(--can-muted);
            }

            .can-settings-page .can-howto {
                border: 1px solid var(--can-line);
                border-radius: 1rem;
                background: var(--can-surface);
            }

            .can-settings-page .can-howto > summary {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.8rem 1.1rem;
                font-size: 0.85rem;
                font-weight: 600;
                color: var(--can-text);
                cursor: pointer;
                list-style: none;
            }

            .can-settings-page .can-howto > summary::-webkit-details-marker { display: none; }
            .can-settings-page .can-howto[open] > summary .can-howto-chevron { transform: rotate(90deg); }
            .can-settings-page .can-howto-chevron { transition: transform 150ms ease; color: var(--can-muted); }

            .can-settings-page .can-howto-body {
                display: grid;
                gap: 0.9rem;
                padding: 0 1.1rem 1.1rem;
            }

            .can-settings-page .can-flow {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr));
                gap: 0.5rem;
                counter-reset: can-step;
            }

            .can-settings-page .can-flow-step {
                padding: 0.55rem 0.7rem;
                border-radius: 0.7rem;
                background: var(--can-surface-2);
                font-size: 0.78rem;
                line-height: 1.4;
                color: var(--can-text);
            }

            .can-settings-page .can-callout {
                display: flex;
                gap: 0.6rem;
                padding: 0.75rem 0.9rem;
                border-radius: 0.8rem;
                background: var(--can-accent-soft);
                font-size: 0.84rem;
                line-height: 1.5;
                color: var(--can-text);
            }

            .can-settings-page .can-empty-hint {
                padding: 0.7rem 0.9rem;
                border: 1px dashed var(--can-line);
                border-radius: 0.8rem;
                font-size: 0.84rem;
                color: var(--can-muted);
            }

            /* ── Formulario en pestañas ── */
            .can-settings-page .can-settings-shell {
                border: 1px solid var(--can-line) !important;
                border-radius: 1rem !important;
                background: var(--can-surface) !important;
            }

            .can-settings-page .can-panel {
                border: 1px solid var(--can-line) !important;
                border-radius: 0.9rem !important;
                box-shadow: none !important;
            }

            .can-settings-page .can-settings-shell .fi-fo-repeater-item {
                border-radius: 0.75rem !important;
                border-color: var(--can-line) !important;
                box-shadow: none !important;
            }

            .can-settings-page .can-settings-actions {
                padding-top: 0.25rem;
            }
        </style>
    @endpush

    @php
        $sections = [
            'catalog' => ['label' => 'Notificaciones', 'icon' => 'heroicon-m-squares-2x2', 'hint' => 'Todo lo que envía el sistema'],
            'log' => ['label' => 'Bitácora', 'icon' => 'heroicon-m-queue-list', 'hint' => 'Qué salió, a quién y cómo'],
            'channels' => ['label' => 'Canales', 'icon' => 'heroicon-m-signal', 'hint' => 'WhatsApp, correo y Telegram'],
            'internal' => ['label' => 'Avisos internos', 'icon' => 'heroicon-m-users', 'hint' => 'Destinatarios del personal'],
        ];
    @endphp

    <div class="can-settings-page" wire:key="notification-center-{{ $section }}-{{ $activeKey->value }}">
        <style>
            .can-sections { display: flex; flex-wrap: wrap; gap: .4rem; padding: .3rem; margin-bottom: 1.1rem; border: 1px solid var(--can-line); border-radius: 1rem; background: var(--can-surface); }
            .can-section-tab { display: inline-flex; align-items: center; gap: .55rem; padding: .5rem .85rem; border-radius: .75rem; border: 0; background: transparent; color: var(--can-muted); cursor: pointer; text-align: left; transition: background 120ms ease, color 120ms ease; }
            .can-section-tab:hover { color: var(--can-text); background: var(--can-surface-2); }
            .can-section-tab.is-on { background: var(--can-accent-soft); color: var(--can-accent); }
            .can-section-tab b { display: block; font-size: .85rem; font-weight: 700; }
            .can-section-tab small { display: block; font-size: .7rem; opacity: .85; }
            @media (max-width: 640px) { .can-section-tab small { display: none; } }
        </style>

        <nav class="can-sections" role="tablist" aria-label="Secciones del Centro de notificaciones">
            @foreach ($sections as $id => $tab)
                <button
                    type="button"
                    role="tab"
                    class="can-section-tab {{ $section === $id ? 'is-on' : '' }}"
                    aria-selected="{{ $section === $id ? 'true' : 'false' }}"
                    wire:click="showSection('{{ $id }}')"
                    wire:loading.attr="disabled"
                    wire:target="showSection"
                >
                    <x-filament::icon :icon="$tab['icon']" class="h-5 w-5" />
                    <span><b>{{ $tab['label'] }}</b><small>{{ $tab['hint'] }}</small></span>
                </button>
            @endforeach
        </nav>

        @if ($section !== 'internal' && ! \App\Support\NotificationCenter\NotificationCatalog::tablesExist())
            <div class="can-empty-hint">
                Falta preparar la base de datos del Centro de notificaciones. Corra
                <code>php artisan migrate --path=database/migrations/2026_11_21_120000_create_notification_center_tables.php</code>
                y vuelva a abrir esta página. Mientras tanto, los mensajes salen como siempre y «Avisos internos» funciona normal.
            </div>
        @elseif ($section === 'catalog')
            @livewire(\App\Livewire\NotificationCenter\NotificationCatalogTable::class, [], key('nc-catalog'))
        @elseif ($section === 'log')
            @livewire(\App\Livewire\NotificationCenter\OutboundMessagesTable::class, ['catalogKey' => $logCatalogKey], key('nc-log-'.($logCatalogKey ?? 'all')))
        @elseif ($section === 'channels')
            @livewire(\App\Livewire\NotificationCenter\NotificationChannelsPanel::class, [], key('nc-channels'))
        @else
        <div class="can-layout">
            {{-- Lista de avisos --}}
            <aside
                class="can-aside"
                x-data="{
                    filter: 'all',
                    search: '',
                    normalize(text) { return (text || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); },
                    matches(chip) {
                        const byFilter = this.filter === 'all'
                            || (this.filter === 'paused' ? chip.dataset.active === '0' : chip.dataset.channel === this.filter);

                        return byFilter && this.normalize(chip.dataset.label).includes(this.normalize(this.search));
                    },
                    areaHasMatches(area) { return [...area.querySelectorAll('[data-chip]')].some((chip) => this.matches(chip)); },
                }"
            >
                <div class="can-aside-head">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold" style="color: var(--can-text);">Avisos</span>
                        <span class="text-xs" style="color: var(--can-muted);">{{ $catalog['counts']['total'] }} en total</span>
                    </div>

                    <div class="relative">
                        <x-filament::icon icon="heroicon-m-magnifying-glass" class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input type="search" class="can-search" placeholder="Buscar aviso…" x-model.debounce.150ms="search" aria-label="Buscar aviso">
                    </div>

                    <div class="can-filters" role="tablist" aria-label="Filtrar avisos por canal">
                        @foreach ($filters as $filter)
                            @continue($filter['id'] !== 'all' && $filter['count'] === 0)
                            <button
                                type="button"
                                role="tab"
                                class="can-filter"
                                x-bind:class="{ 'is-on': filter === '{{ $filter['id'] }}' }"
                                x-bind:aria-selected="filter === '{{ $filter['id'] }}'"
                                x-on:click="filter = '{{ $filter['id'] }}'"
                            >
                                {{ $filter['label'] }} <span class="can-filter-count">{{ $filter['count'] }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <nav class="can-aside-list" aria-label="Tipos de notificación">
                    @foreach ($catalog['areas'] as $areaId => $area)
                        <div x-show="areaHasMatches($el)" wire:key="can-area-{{ $areaId }}">
                            <p class="can-area-title">{{ $area['label'] }}</p>
                            <div class="can-chips" role="tablist" aria-label="{{ $area['label'] }}">
                                @foreach ($area['items'] as $item)
                                    @php
                                        $isSelected = $activeKey->value === $item['key'];
                                        $tooltip = match ($item['channel']) {
                                            'telegram' => 'Sale por Telegram ('.$item['mode'].') a los vinculados; el resto, por WhatsApp. El correo no cambia.',
                                            'ready' => 'Listo para Telegram: elija Persona, Grupo o Ambos en la pestaña Telegram. Hoy sale por WhatsApp.',
                                            default => 'Hoy sale solo por WhatsApp y correo.',
                                        };
                                    @endphp
                                    <button
                                        type="button"
                                        role="tab"
                                        data-chip
                                        data-label="{{ $item['label'] }}"
                                        data-channel="{{ $item['channel'] }}"
                                        data-active="{{ $item['active'] ? '1' : '0' }}"
                                        x-show="matches($el)"
                                        class="can-chip is-{{ $item['channel'] }} {{ $isSelected ? 'is-selected' : '' }} {{ $item['active'] ? '' : 'is-paused' }}"
                                        wire:click="selectNotificationKey('{{ $item['key'] }}')"
                                        wire:loading.attr="disabled"
                                        aria-selected="{{ $isSelected ? 'true' : 'false' }}"
                                        title="{{ $tooltip }}"
                                    >
                                        <x-filament::icon :icon="$channelIcon($item['channel'])" class="can-chip-icon h-4 w-4" />
                                        <span class="can-chip-label">{{ $item['label'] }}</span>
                                        @if (! $item['active'])
                                            <span class="can-chip-tag is-paused">Pausado</span>
                                        @elseif ($item['channel'] === 'telegram')
                                            <span class="can-chip-tag">{{ $item['mode'] }}</span>
                                        @elseif ($item['channel'] === 'whatsapp' && $item['stage'])
                                            <span class="can-chip-tag">{{ $item['stage'] }}</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <p class="can-empty-hint m-2" x-show="! [...$root.querySelectorAll('[data-chip]')].some((chip) => matches(chip))" x-cloak>
                        Ningún aviso coincide con la búsqueda o el filtro.
                    </p>
                </nav>
            </aside>

            {{-- Detalle del aviso elegido --}}
            <div class="can-main">
                <section class="can-hero">
                    <div class="can-hero-top">
                        <div class="can-hero-icon" aria-hidden="true">
                            <x-filament::icon :icon="$activeKey->calloutIcon()" class="h-6 w-6" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <span class="can-breadcrumb">{{ $areaLabel }}</span>
                            <h2>{{ $activeKey->heroTitle() }}</h2>
                            <div class="mb-2 flex flex-wrap items-center gap-1.5">
                                <span class="can-pill {{ $isTaskActive ? 'is-on' : 'is-off' }}" wire:key="task-status-{{ $activeKey->value }}-{{ $isTaskActive ? 'on' : 'off' }}">
                                    <x-filament::icon :icon="$isTaskActive ? 'heroicon-m-check-circle' : 'heroicon-m-pause-circle'" class="h-3.5 w-3.5" />
                                    {{ $isTaskActive ? 'Activa' : 'Pausada' }}
                                </span>
                                @if ($activeChannel)
                                    <span class="can-pill is-{{ $activeChannel['channel'] }}">
                                        <x-filament::icon :icon="$channelIcon($activeChannel['channel'])" class="h-3.5 w-3.5" />
                                        {{ match ($activeChannel['channel']) {
                                            'telegram' => 'Telegram · '.$activeChannel['mode'],
                                            'ready' => 'Listo para Telegram',
                                            default => 'Solo WhatsApp',
                                        } }}
                                    </span>
                                @endif
                            </div>
                            <p>{{ $activeKey->heroBody() }}</p>
                        </div>
                    </div>

                    <div class="can-stats">
                        @if ($activeKey->usesEmailRecipients())
                            <div class="can-stat">
                                <span class="can-stat-label">Correos</span>
                                <span class="can-stat-value" wire:key="email-count-{{ $activeKey->value }}-{{ $emailCount }}">{{ $emailCount }}</span>
                                <span class="can-stat-meta">Destinatarios por email</span>
                            </div>
                        @endif
                        @if ($activeKey->usesPhoneRecipients())
                            <div class="can-stat">
                                <span class="can-stat-label">WhatsApp</span>
                                <span class="can-stat-value" wire:key="phone-count-{{ $activeKey->value }}-{{ $phoneCount }}">{{ $phoneCount }}</span>
                                <span class="can-stat-meta">Teléfonos configurados</span>
                            </div>
                        @endif
                        <div class="can-stat">
                            <span class="can-stat-label">Telegram</span>
                            <span class="can-stat-value">{{ match ($telegramMode) {
                                \App\Enums\TelegramDeliveryMode::Person => 'Persona',
                                \App\Enums\TelegramDeliveryMode::Group => 'Grupo',
                                \App\Enums\TelegramDeliveryMode::Both => 'Ambos',
                                \App\Enums\TelegramDeliveryMode::Off => 'Apagado',
                            } }}</span>
                            <span class="can-stat-meta">
                                @if ($activeKey->isPersonTargeted())
                                    A quien le corresponde
                                @else
                                    {{ $telegramPeople }} {{ $telegramPeople === 1 ? 'persona elegida' : 'personas elegidas' }} · {{ $telegramGroups }} {{ $telegramGroups === 1 ? 'grupo' : 'grupos' }}
                                @endif
                            </span>
                        </div>
                        <div class="can-stat">
                            <span class="can-stat-label">Actualizado</span>
                            <span class="can-stat-value text-base">{{ $lastUpdated ?? '—' }}</span>
                            <span class="can-stat-meta">{{ filled($settings->updated_by) ? 'Por '.$settings->updated_by : 'Sin cambios guardados' }}</span>
                        </div>
                    </div>
                </section>

                <details class="can-howto">
                    <summary>
                        <x-filament::icon icon="heroicon-m-chevron-right" class="can-howto-chevron h-4 w-4" />
                        Cómo funciona este aviso
                    </summary>
                    <div class="can-howto-body">
                        <div class="can-flow">
                            @foreach ($activeKey->flowSteps() as $step)
                                <span class="can-flow-step">{{ $step }}</span>
                            @endforeach
                        </div>
                        <div class="can-callout">
                            <x-filament::icon :icon="$activeKey->calloutIcon()" class="mt-0.5 h-4 w-4 shrink-0" style="color: var(--can-accent);" />
                            <p class="m-0"><strong>{{ $activeKey->calloutTitle() }}</strong> {{ $activeKey->calloutBody() }}</p>
                        </div>
                        <p class="m-0 text-sm" style="color: var(--can-muted);">{{ $activeKey->activationHelp() }}</p>
                    </div>
                </details>

                @if ($usesLists && ! $hasRecipients)
                    <div class="can-empty-hint">
                        {{ $activeKey->emptyRecipientsHint() }}
                    </div>
                @endif

                {{ $this->content }}
            </div>
        </div>
        @endif
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
