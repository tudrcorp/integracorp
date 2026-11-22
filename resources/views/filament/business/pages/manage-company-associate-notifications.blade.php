<x-filament-panels::page>
    @php
        $tablesReady = \App\Support\NotificationCenter\NotificationCatalog::tablesExist();
        $overview = $this->overview;
        $catalog = $this->keyCatalog;
        $internalPaused = $catalog['counts']['paused'];

        $sections = [
            'catalog' => [
                'label' => 'Notificaciones',
                'icon' => 'heroicon-m-squares-2x2',
                'hint' => $overview['catalog_off'] > 0 ? $overview['catalog_off'].' con algo apagado' : 'Todo lo que envía el sistema',
                'badge' => $overview['catalog_total'] > 0 ? (string) $overview['catalog_total'] : null,
                'tone' => 'neutral',
            ],
            'log' => [
                'label' => 'Bitácora',
                'icon' => 'heroicon-m-queue-list',
                'hint' => $overview['failed_today'] > 0 ? 'Hay envíos fallidos hoy' : 'Qué salió, a quién y cómo',
                'badge' => $overview['failed_today'] > 0 ? $overview['failed_today'].' fallidos' : null,
                'tone' => 'bad',
            ],
            'channels' => [
                'label' => 'Canales',
                'icon' => 'heroicon-m-signal',
                'hint' => $overview['channels_off'] > 0 ? 'Hay canales apagados' : 'WhatsApp, correo y Telegram',
                'badge' => $overview['channels_off'] > 0 ? $overview['channels_off'].' apagado'.($overview['channels_off'] === 1 ? '' : 's') : null,
                'tone' => 'bad',
            ],
            'internal' => [
                'label' => 'Avisos internos',
                'icon' => 'heroicon-m-users',
                'hint' => $internalPaused > 0 ? $internalPaused.' pausado'.($internalPaused === 1 ? '' : 's') : 'Destinatarios del personal',
                'badge' => (string) $catalog['counts']['total'],
                'tone' => 'neutral',
            ],
        ];
    @endphp

    @push('styles')
        <style>
            /* ═══ Centro de notificaciones · tokens ═══ */
            .can-settings-page {
                --can-accent: #0369a1;
                --can-accent-strong: #075985;
                --can-accent-soft: rgb(14 165 233 / 10%);
                --can-tg: #0284c7;
                --can-tg-soft: rgb(14 165 233 / 12%);
                --can-ready: #7c3aed;
                --can-ready-soft: rgb(124 58 237 / 10%);
                --can-wa: #15803d;
                --can-wa-soft: rgb(22 163 74 / 11%);
                --can-mail: #b45309;
                --can-mail-soft: rgb(217 119 6 / 12%);
                --can-off: #b91c1c;
                --can-off-soft: rgb(220 38 38 / 10%);
                --can-warn: #b45309;
                --can-warn-soft: rgb(245 158 11 / 14%);
                --can-line: rgb(148 163 184 / 24%);
                --can-line-strong: rgb(148 163 184 / 40%);
                --can-surface: #ffffff;
                --can-surface-2: rgb(248 250 252);
                --can-surface-3: rgb(241 245 249);
                --can-text: #0f172a;
                --can-muted: #64748b;
                --can-shadow: 0 1px 2px rgb(15 23 42 / 4%), 0 4px 16px -8px rgb(15 23 42 / 12%);
                --can-shadow-lg: 0 18px 40px -18px rgb(15 23 42 / 35%);
                --can-radius: 1rem;
                color: var(--can-text);
            }
            .dark .can-settings-page {
                --can-accent: #38bdf8;
                --can-accent-strong: #7dd3fc;
                --can-accent-soft: rgb(56 189 248 / 12%);
                --can-tg: #38bdf8;
                --can-tg-soft: rgb(56 189 248 / 14%);
                --can-ready: #a78bfa;
                --can-ready-soft: rgb(167 139 250 / 14%);
                --can-wa: #4ade80;
                --can-wa-soft: rgb(74 222 128 / 12%);
                --can-mail: #fbbf24;
                --can-mail-soft: rgb(251 191 36 / 12%);
                --can-off: #f87171;
                --can-off-soft: rgb(248 113 113 / 14%);
                --can-warn: #fbbf24;
                --can-warn-soft: rgb(251 191 36 / 12%);
                --can-line: rgb(148 163 184 / 14%);
                --can-line-strong: rgb(148 163 184 / 28%);
                --can-surface: rgb(17 24 39);
                --can-surface-2: rgb(15 23 42 / 70%);
                --can-surface-3: rgb(30 41 59 / 70%);
                --can-text: #f8fafc;
                --can-muted: #94a3b8;
                --can-shadow: 0 1px 2px rgb(0 0 0 / 30%);
                --can-shadow-lg: 0 18px 40px -18px rgb(0 0 0 / 70%);
            }
            .can-settings-page [x-cloak] { display: none !important; }
            .can-settings-page button:focus-visible,
            .can-settings-page summary:focus-visible { outline: 2px solid var(--can-accent); outline-offset: 2px; }
            @media (prefers-reduced-motion: reduce) {
                .can-settings-page *, .can-settings-page *::before { transition: none !important; animation: none !important; }
            }

            /* ═══ Pestañas del Centro ═══ */
            .can-sections {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: .35rem;
                padding: .35rem;
                margin-bottom: 1.1rem;
                border: 1px solid var(--can-line);
                border-radius: 1.1rem;
                background: var(--can-surface-2);
            }
            @media (max-width: 900px) { .can-sections { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
            .can-section-tab {
                position: relative;
                display: flex;
                align-items: center;
                gap: .65rem;
                min-width: 0;
                padding: .6rem .8rem;
                border: 1px solid transparent;
                border-radius: .85rem;
                background: transparent;
                color: var(--can-muted);
                text-align: left;
                cursor: pointer;
                transition: background 140ms ease, color 140ms ease, box-shadow 140ms ease;
            }
            .can-section-tab:hover { color: var(--can-text); background: var(--can-surface); }
            .can-section-tab.is-on { background: var(--can-surface); color: var(--can-text); border-color: var(--can-line); box-shadow: var(--can-shadow); }
            .can-section-tab-icon { display: grid; place-items: center; width: 2.1rem; height: 2.1rem; flex-shrink: 0; border-radius: .7rem; background: var(--can-surface-3); transition: background 140ms ease, color 140ms ease; }
            .can-section-tab.is-on .can-section-tab-icon { background: var(--can-accent-soft); color: var(--can-accent); }
            .can-section-tab-text { min-width: 0; flex: 1; }
            .can-section-tab-text b { display: block; font-size: .86rem; font-weight: 700; line-height: 1.2; }
            .can-section-tab-text small { display: block; overflow: hidden; font-size: .72rem; line-height: 1.35; white-space: nowrap; text-overflow: ellipsis; opacity: .9; }
            .can-section-tab-badge { flex-shrink: 0; padding: .1rem .45rem; border-radius: 999px; font-size: .68rem; font-weight: 700; font-variant-numeric: tabular-nums; background: var(--can-surface-3); color: var(--can-muted); }
            .can-section-tab-badge.is-bad { background: var(--can-off-soft); color: var(--can-off); }
            @media (max-width: 640px) {
                .can-section-tab-text small { display: none; }
                .can-section-tab-icon { width: 1.8rem; height: 1.8rem; }
            }

            /* ═══ Esqueleto de carga entre pestañas ═══ */
            .can-skeleton { display: grid; gap: .75rem; }
            .can-skeleton-row { height: 3.25rem; border-radius: .9rem; background: linear-gradient(90deg, var(--can-surface-3) 25%, var(--can-surface-2) 37%, var(--can-surface-3) 63%); background-size: 400% 100%; animation: can-shimmer 1.2s ease infinite; }
            .can-skeleton-row.is-tall { height: 7rem; }
            @keyframes can-shimmer { 0% { background-position: 100% 50%; } 100% { background-position: 0 50%; } }

            .can-empty-hint {
                display: flex;
                gap: .6rem;
                align-items: flex-start;
                padding: .8rem 1rem;
                border: 1px dashed var(--can-line-strong);
                border-radius: .9rem;
                font-size: .84rem;
                line-height: 1.5;
                color: var(--can-muted);
            }
            .can-empty-hint code { font-size: .78rem; word-break: break-all; }

            /* ═══ Avisos internos: lista + detalle ═══ */
            .can-layout { display: grid; gap: 1.25rem; align-items: start; }
            @media (min-width: 1280px) {
                .can-layout { grid-template-columns: 20.5rem minmax(0, 1fr); }
                .can-aside { position: sticky; top: 5rem; max-height: calc(100vh - 6rem); }
            }
            .can-aside {
                display: flex;
                flex-direction: column;
                border: 1px solid var(--can-line);
                border-radius: var(--can-radius);
                background: var(--can-surface);
                box-shadow: var(--can-shadow);
                overflow: hidden;
            }
            .can-aside-head { display: grid; gap: .65rem; padding: .9rem .9rem .75rem; border-bottom: 1px solid var(--can-line); }
            .can-aside-title { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
            .can-aside-title span { font-size: .9rem; font-weight: 700; }
            .can-aside-title small { font-size: .72rem; color: var(--can-muted); }
            .can-search-wrap { position: relative; }
            .can-search-wrap > svg { position: absolute; left: .7rem; top: 50%; width: 1rem; height: 1rem; transform: translateY(-50%); color: var(--can-muted); pointer-events: none; }
            .can-search {
                width: 100%;
                padding: .55rem 2.2rem .55rem 2.1rem;
                border: 1px solid var(--can-line);
                border-radius: .75rem;
                background: var(--can-surface-2);
                color: var(--can-text);
                font-size: .85rem;
                transition: border-color 120ms ease, box-shadow 120ms ease;
            }
            .can-search::-webkit-search-cancel-button { display: none; }
            .can-search:focus { outline: none; border-color: var(--can-accent); box-shadow: 0 0 0 3px var(--can-accent-soft); }
            .can-kbd { position: absolute; right: .55rem; top: 50%; transform: translateY(-50%); padding: .05rem .4rem; border: 1px solid var(--can-line-strong); border-radius: .35rem; font-size: .68rem; font-weight: 600; color: var(--can-muted); background: var(--can-surface); }
            .can-search-clear { position: absolute; right: .45rem; top: 50%; display: grid; place-items: center; width: 1.4rem; height: 1.4rem; transform: translateY(-50%); border: 0; border-radius: 999px; background: var(--can-surface-3); color: var(--can-muted); cursor: pointer; }
            .can-filters { display: flex; flex-wrap: wrap; gap: .3rem; }
            .can-filter {
                display: inline-flex;
                align-items: center;
                gap: .35rem;
                padding: .28rem .6rem;
                border: 1px solid var(--can-line);
                border-radius: 999px;
                background: transparent;
                color: var(--can-muted);
                font-size: .74rem;
                font-weight: 600;
                cursor: pointer;
                transition: background 120ms ease, color 120ms ease, border-color 120ms ease;
            }
            .can-filter:hover { color: var(--can-text); border-color: var(--can-line-strong); }
            .can-filter.is-on { background: var(--can-text); border-color: var(--can-text); color: var(--can-surface); }
            .can-filter-count { font-variant-numeric: tabular-nums; opacity: .7; }
            .can-filter i { width: .45rem; height: .45rem; border-radius: 999px; background: currentColor; }
            .can-filter.is-telegram i { background: var(--can-tg); }
            .can-filter.is-ready i { background: var(--can-ready); }
            .can-filter.is-whatsapp i { background: var(--can-wa); }
            .can-filter.is-paused i { background: var(--can-off); }
            .can-results { display: flex; align-items: center; justify-content: space-between; gap: .5rem; margin: 0; font-size: .74rem; color: var(--can-muted); }
            .can-results button { border: 0; background: none; padding: 0; font-weight: 650; color: var(--can-accent); cursor: pointer; }
            .can-results button:hover { text-decoration: underline; }

            /* Resumen del sidebar */
            .can-health { display: grid; gap: .45rem; padding: .65rem .75rem; border-radius: .85rem; background: var(--can-surface-2); }
            .can-health-row { display: flex; align-items: center; justify-content: space-between; gap: .5rem; font-size: .78rem; color: var(--can-muted); }
            .can-health-row span, .can-health-paused { display: inline-flex; align-items: center; gap: .35rem; }
            .can-health-row b { color: var(--can-text); font-variant-numeric: tabular-nums; }
            .can-health-row i { width: .5rem; height: .5rem; border-radius: 999px; }
            .can-health-row i.is-on { background: var(--can-wa); box-shadow: 0 0 0 3px var(--can-wa-soft); }
            .can-health-row i.is-off { background: var(--can-off); box-shadow: 0 0 0 3px var(--can-off-soft); }
            .can-health-paused { padding: .1rem .5rem; border: 0; border-radius: 999px; background: var(--can-off-soft); color: var(--can-off); font-size: .76rem; cursor: pointer; }
            .can-health-paused b { color: inherit !important; }
            .can-health-muted { font-size: .72rem; }
            .can-health-bar { display: flex; height: .35rem; overflow: hidden; border-radius: 999px; background: var(--can-surface-3); }
            .can-health-bar span { display: block; height: 100%; }
            .can-health-legend { display: flex; flex-wrap: wrap; gap: .2rem .7rem; font-size: .68rem; color: var(--can-muted); }
            .can-health-legend span { display: inline-flex; align-items: center; gap: .3rem; }
            .can-health-legend i { width: .45rem; height: .45rem; border-radius: 2px; }

            /* Lista */
            .can-aside-list { position: relative; flex: 1; overflow-y: auto; padding: .25rem .45rem .75rem; overscroll-behavior: contain; scroll-padding-top: 2.5rem; }
            @media (max-width: 1279px) { .can-aside-list { max-height: 24rem; } }
            .can-area + .can-area, .can-recent + .can-area { margin-top: .15rem; }
            .can-area-title {
                position: sticky;
                top: -.25rem;
                z-index: 1;
                display: flex;
                width: 100%;
                align-items: center;
                gap: .4rem;
                margin: 0;
                padding: .65rem .5rem .35rem;
                border: 0;
                background: var(--can-surface);
                color: var(--can-muted);
                font-size: .66rem;
                font-weight: 700;
                letter-spacing: .08em;
                text-align: left;
                text-transform: uppercase;
                cursor: pointer;
            }
            .can-area-title:hover:not(:disabled):not(.is-static) { color: var(--can-text); }
            .can-area-title:disabled, .can-area-title.is-static { cursor: default; }
            .can-area-chevron { display: inline-flex; width: .9rem; height: .9rem; flex-shrink: 0; transition: transform 150ms ease; }
            .can-area-chevron.is-closed { transform: rotate(-90deg); }
            .can-area-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .can-area-paused { padding: .02rem .4rem; border-radius: 999px; letter-spacing: 0; text-transform: none; font-size: .64rem; background: var(--can-off-soft); color: var(--can-off); }
            .can-area-count { min-width: 1.25rem; padding: .02rem .35rem; border-radius: 999px; letter-spacing: 0; text-align: center; font-variant-numeric: tabular-nums; background: var(--can-surface-3); }
            .can-chips { display: grid; gap: .1rem; }
            .can-chip {
                position: relative;
                display: flex;
                width: 100%;
                align-items: center;
                gap: .6rem;
                padding: .5rem .55rem .5rem .65rem;
                border: 0;
                border-radius: .7rem;
                background: transparent;
                color: var(--can-text);
                text-align: left;
                cursor: pointer;
                transition: background 120ms ease;
            }
            .can-chip:hover, .can-chip:focus-visible { background: var(--can-surface-2); outline: none; }
            .can-chip:focus-visible { box-shadow: inset 0 0 0 2px var(--can-accent); }
            .can-chip.is-telegram { --can-chip: var(--can-tg); --can-chip-soft: var(--can-tg-soft); }
            .can-chip.is-ready { --can-chip: var(--can-ready); --can-chip-soft: var(--can-ready-soft); }
            .can-chip.is-whatsapp { --can-chip: var(--can-wa); --can-chip-soft: var(--can-wa-soft); }
            .can-chip.is-paused { --can-chip: var(--can-off); --can-chip-soft: var(--can-off-soft); }
            .can-chip.is-selected { background: var(--can-chip-soft); }
            .can-chip.is-selected::before { content: ''; position: absolute; left: 0; top: .5rem; bottom: .5rem; width: 3px; border-radius: 999px; background: var(--can-chip); }
            .can-chip.is-selected .can-chip-label { font-weight: 650; }
            .can-chip.is-loading { opacity: .75; }
            .can-chip-dot { width: .45rem; height: .45rem; flex-shrink: 0; align-self: flex-start; margin-top: .4rem; border-radius: 999px; background: var(--can-wa); box-shadow: 0 0 0 3px var(--can-wa-soft); }
            .can-chip.is-paused .can-chip-dot, .can-recent-item.is-paused .can-chip-dot { background: var(--can-off); box-shadow: 0 0 0 3px var(--can-off-soft); }
            .can-chip-text { display: grid; flex: 1; gap: .1rem; min-width: 0; }
            .can-chip-label { display: -webkit-box; overflow: hidden; font-size: .83rem; font-weight: 500; line-height: 1.3; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
            .can-chip.is-paused .can-chip-label { color: var(--can-muted); }
            .can-chip-label mark { padding: 0 .1rem; border-radius: .2rem; background: rgb(250 204 21 / 45%); color: inherit; }
            .can-chip-meta { display: inline-flex; align-items: center; gap: .3rem; font-size: .7rem; font-weight: 600; color: var(--can-chip); }
            .can-chip-icon { width: .8rem; height: .8rem; flex-shrink: 0; }
            .can-chip-go { display: inline-flex; width: 1rem; height: 1rem; flex-shrink: 0; color: var(--can-muted); opacity: 0; transform: translateX(-3px); transition: opacity 120ms ease, transform 120ms ease; }
            .can-chip:hover .can-chip-go, .can-chip:focus-visible .can-chip-go, .can-chip.is-selected .can-chip-go { opacity: 1; transform: none; }
            .can-chip.is-selected .can-chip-go { color: var(--can-chip); }
            .can-spinner { width: .9rem; height: .9rem; flex-shrink: 0; border: 2px solid var(--can-line-strong); border-top-color: var(--can-accent); border-radius: 999px; animation: can-spin .7s linear infinite; }
            @keyframes can-spin { to { transform: rotate(360deg); } }

            /* Recientes */
            .can-recent { padding-bottom: .35rem; border-bottom: 1px dashed var(--can-line-strong); }
            .can-recent-list { display: grid; gap: .1rem; }
            .can-recent-item { display: flex; align-items: center; gap: .55rem; width: 100%; padding: .4rem .55rem .4rem .65rem; border: 0; border-radius: .6rem; background: transparent; color: var(--can-text); font-size: .8rem; text-align: left; cursor: pointer; }
            .can-recent-item:hover, .can-recent-item:focus-visible { background: var(--can-surface-2); outline: none; }
            .can-recent-item .can-chip-dot { align-self: center; margin-top: 0; }
            .can-recent-label { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

            /* Vacío y pie */
            .can-aside-empty { display: grid; justify-items: center; gap: .55rem; padding: 1.5rem 1rem; text-align: center; color: var(--can-muted); }
            .can-aside-empty p { margin: 0; font-size: .82rem; }
            .can-aside-empty b { color: var(--can-text); }
            .can-aside-foot { display: flex; flex-wrap: wrap; gap: .3rem .8rem; padding: .55rem .9rem; border-top: 1px solid var(--can-line); font-size: .68rem; color: var(--can-muted); }
            .can-aside-foot span { display: inline-flex; align-items: center; gap: .25rem; }
            .can-aside-foot kbd { min-width: 1.1rem; padding: 0 .3rem; border: 1px solid var(--can-line-strong); border-bottom-width: 2px; border-radius: .3rem; font-family: inherit; font-size: .64rem; font-weight: 650; text-align: center; background: var(--can-surface); }
            @media (hover: none) { .can-aside-foot { display: none; } }

            /* ═══ Detalle del aviso ═══ */
            .can-main { display: grid; gap: 1rem; min-width: 0; transition: opacity 140ms ease; }
            .can-main.is-loading { opacity: .5; pointer-events: none; }
            .can-hero {
                position: relative;
                display: grid;
                gap: 1rem;
                padding: 1.25rem 1.25rem 1.1rem;
                border: 1px solid var(--can-line);
                border-radius: var(--can-radius);
                background:
                    radial-gradient(120% 140% at 100% 0%, var(--can-accent-soft) 0%, transparent 45%),
                    var(--can-surface);
                box-shadow: var(--can-shadow);
                overflow: hidden;
            }
            .can-hero-top { display: flex; gap: .95rem; align-items: flex-start; }
            .can-hero-icon { display: grid; place-items: center; width: 2.9rem; height: 2.9rem; flex-shrink: 0; border-radius: .9rem; background: var(--can-accent-soft); color: var(--can-accent); }
            .can-breadcrumb { font-size: .72rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--can-muted); }
            .can-hero h2 { margin: .15rem 0 .45rem; font-size: 1.3rem; font-weight: 750; line-height: 1.25; letter-spacing: -.01em; }
            .can-hero-body { margin: 0; max-width: 62rem; font-size: .88rem; line-height: 1.6; color: var(--can-muted); }
            .can-pills { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem; margin-bottom: .55rem; }
            .can-pill { display: inline-flex; align-items: center; gap: .3rem; padding: .2rem .6rem; border-radius: 999px; font-size: .72rem; font-weight: 700; }
            .can-pill svg { width: .875rem; height: .875rem; }
            .can-pill.is-on { color: var(--can-wa); background: var(--can-wa-soft); }
            .can-pill.is-off { color: var(--can-off); background: var(--can-off-soft); }
            .can-pill.is-telegram { color: var(--can-tg); background: var(--can-tg-soft); }
            .can-pill.is-ready { color: var(--can-ready); background: var(--can-ready-soft); }
            .can-pill.is-whatsapp { color: var(--can-wa); background: var(--can-wa-soft); }
            .can-pill.is-dirty { color: var(--can-warn); background: var(--can-warn-soft); }
            .can-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(9.5rem, 1fr)); gap: .6rem; }
            .can-stat { display: grid; gap: .15rem; padding: .75rem .9rem; border: 1px solid var(--can-line); border-radius: .85rem; background: var(--can-surface); }
            .can-stat-label { display: flex; align-items: center; gap: .35rem; font-size: .68rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--can-muted); }
            .can-stat-label svg { width: .875rem; height: .875rem; }
            .can-stat-value { font-size: 1.2rem; font-weight: 750; font-variant-numeric: tabular-nums; }
            .can-stat-value.is-small { font-size: .95rem; }
            .can-stat-meta { font-size: .72rem; color: var(--can-muted); }
            .can-howto { border-top: 1px dashed var(--can-line-strong); padding-top: .75rem; }
            .can-howto > summary { display: inline-flex; align-items: center; gap: .4rem; font-size: .82rem; font-weight: 650; color: var(--can-accent); cursor: pointer; list-style: none; border-radius: .4rem; }
            .can-howto > summary::-webkit-details-marker { display: none; }
            .can-howto-chevron { width: 1rem; height: 1rem; transition: transform 150ms ease; }
            .can-howto[open] > summary .can-howto-chevron { transform: rotate(90deg); }
            .can-howto-body { display: grid; gap: .8rem; padding-top: .8rem; }
            .can-flow { display: grid; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); gap: .5rem; counter-reset: can-step; }
            .can-flow-step { position: relative; padding: .6rem .7rem .6rem 2.1rem; border-radius: .75rem; background: var(--can-surface-2); font-size: .78rem; line-height: 1.45; counter-increment: can-step; }
            .can-flow-step::before { content: counter(can-step); position: absolute; left: .6rem; top: .6rem; display: grid; place-items: center; width: 1.1rem; height: 1.1rem; border-radius: 999px; font-size: .65rem; font-weight: 700; background: var(--can-accent-soft); color: var(--can-accent); }
            .can-callout { display: flex; gap: .6rem; padding: .75rem .9rem; border-radius: .8rem; background: var(--can-accent-soft); font-size: .84rem; line-height: 1.5; }
            .can-callout svg { width: 1rem; height: 1rem; margin-top: .15rem; flex-shrink: 0; color: var(--can-accent); }
            .can-help { margin: 0; font-size: .82rem; line-height: 1.5; color: var(--can-muted); }

            /* ═══ Formulario (secciones Filament) ═══ */
            .can-settings-page .can-panel { border: 1px solid var(--can-line) !important; border-radius: var(--can-radius) !important; box-shadow: var(--can-shadow) !important; }
            .can-settings-page .can-settings-panel--status { border-left: 3px solid var(--can-wa) !important; }
            .can-settings-page .can-settings-panel--email .fi-section-header-icon { color: var(--can-mail); }
            .can-settings-page .can-settings-panel--phone .fi-section-header-icon { color: var(--can-wa); }
            .can-settings-page .can-settings-panel--telegram .fi-section-header-icon { color: var(--can-tg); }
            .can-settings-page .fi-fo-table-repeater table { border-radius: .75rem; overflow: hidden; }
            .can-settings-page .can-settings-actions { padding-top: .25rem; }

            /* ═══ Barra de cambios sin guardar ═══ */
            .can-dirty-bar {
                position: fixed;
                left: 50%;
                bottom: 1.25rem;
                z-index: 40;
                display: flex;
                align-items: center;
                gap: .75rem;
                max-width: calc(100vw - 2rem);
                padding: .55rem .6rem .55rem 1rem;
                border: 1px solid var(--can-line-strong);
                border-radius: 999px;
                background: var(--can-surface);
                box-shadow: var(--can-shadow-lg);
                transform: translateX(-50%);
            }
            .can-dirty-bar p { margin: 0; display: flex; align-items: center; gap: .45rem; font-size: .84rem; font-weight: 600; white-space: nowrap; }
            .can-dirty-bar p i { width: .5rem; height: .5rem; border-radius: 999px; background: var(--can-warn); box-shadow: 0 0 0 4px var(--can-warn-soft); }
            .can-btn { display: inline-flex; align-items: center; gap: .35rem; padding: .45rem .9rem; border: 1px solid transparent; border-radius: 999px; font-size: .8rem; font-weight: 650; cursor: pointer; transition: background 120ms ease, opacity 120ms ease; }
            .can-btn:disabled { opacity: .6; cursor: progress; }
            .can-btn svg { width: 1rem; height: 1rem; }
            .can-btn.is-ghost { background: transparent; border-color: var(--can-line-strong); color: var(--can-text); }
            .can-btn.is-ghost:hover { background: var(--can-surface-2); }
            .can-btn.is-primary { background: #16a34a; color: #fff; }
            .can-btn.is-primary:hover { background: #15803d; }
            .can-btn.is-danger { background: #dc2626; color: #fff; }
            .can-btn.is-danger:hover { background: #b91c1c; }
            @media (max-width: 640px) { .can-dirty-bar p span { display: none; } }

            /* ═══ Diálogo de descartar cambios ═══ */
            .can-dialog-backdrop { position: fixed; inset: 0; z-index: 50; display: grid; place-items: center; padding: 1rem; background: rgb(15 23 42 / 45%); backdrop-filter: blur(2px); }
            .can-dialog { width: 100%; max-width: 26rem; padding: 1.25rem; border-radius: 1.1rem; background: var(--can-surface); color: var(--can-text); box-shadow: var(--can-shadow-lg); }
            .can-dialog h3 { margin: 0 0 .35rem; font-size: 1rem; font-weight: 700; }
            .can-dialog p { margin: 0 0 1rem; font-size: .85rem; line-height: 1.5; color: var(--can-muted); }
            .can-dialog-actions { display: flex; justify-content: flex-end; gap: .5rem; }

            /* ═══ Pestaña Notificaciones (catálogo) ═══ */
            .ncat { display: grid; gap: .85rem; }
            .ncat-intro { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .6rem 1rem; padding: .8rem 1rem; border: 1px solid var(--can-line); border-radius: var(--can-radius); background: var(--can-surface); box-shadow: var(--can-shadow); }
            .ncat-intro p { margin: 0; font-size: .84rem; line-height: 1.5; color: var(--can-muted); }
            .ncat-intro p b { color: var(--can-text); }
            .ncat-legend { display: flex; flex-wrap: wrap; gap: .35rem; }
            .ncat-legend span { display: inline-flex; align-items: center; gap: .3rem; padding: .18rem .55rem; border-radius: 999px; font-size: .7rem; font-weight: 650; background: var(--can-surface-3); color: var(--can-muted); }
            .ncat-legend svg { width: .8rem; height: .8rem; }

            /* ═══ Pestaña Bitácora ═══ */
            .ncl { display: grid; gap: 1rem; }
            .ncl-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .5rem; }
            .ncl-title { font-size: .84rem; font-weight: 700; }
            .ncl-title span { font-weight: 400; color: var(--can-muted); }
            .ncl-head-actions { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
            .ncl-cards { display: grid; gap: .6rem; grid-template-columns: repeat(auto-fit, minmax(8.75rem, 1fr)); }
            .ncl-card { position: relative; display: flex; flex-direction: column; gap: .3rem; padding: .75rem .85rem; text-align: left; border: 1px solid var(--can-line); border-radius: .9rem; background: var(--can-surface); box-shadow: var(--can-shadow); cursor: pointer; transition: border-color 120ms ease, box-shadow 120ms ease, transform 120ms ease; }
            .ncl-card:hover { transform: translateY(-1px); border-color: var(--tone); }
            .ncl-card.is-on { border-color: var(--tone); box-shadow: 0 0 0 3px var(--tone-soft); }
            .ncl-card.is-zero .ncl-card-value { color: var(--can-muted); }
            .ncl-card-top { display: flex; align-items: center; gap: .4rem; font-size: .72rem; font-weight: 650; color: var(--can-muted); }
            .ncl-card-top svg { width: 1rem; height: 1rem; color: var(--tone); }
            .ncl-card-value { font-size: 1.5rem; font-weight: 750; line-height: 1.1; font-variant-numeric: tabular-nums; }
            .ncl-card-check { position: absolute; top: .55rem; right: .55rem; width: 1rem; height: 1rem; color: var(--tone); }
            .ncl-card.tone-tg { --tone: var(--can-tg); --tone-soft: var(--can-tg-soft); }
            .ncl-card.tone-wa { --tone: var(--can-wa); --tone-soft: var(--can-wa-soft); }
            .ncl-card.tone-mail { --tone: var(--can-mail); --tone-soft: var(--can-mail-soft); }
            .ncl-card.tone-bad { --tone: var(--can-off); --tone-soft: var(--can-off-soft); }
            .ncl-card.tone-off { --tone: var(--can-muted); --tone-soft: rgb(100 116 139 / 16%); }
            .ncl-card.tone-bad:not(.is-zero) { background: linear-gradient(0deg, var(--can-off-soft), var(--can-off-soft)), var(--can-surface); }
            .ncl-chip { display: inline-flex; align-items: center; gap: .35rem; padding: .3rem .4rem .3rem .65rem; border-radius: 999px; font-size: .75rem; font-weight: 650; background: var(--can-accent-soft); color: var(--can-accent); }
            .ncl-chip svg { width: .875rem; height: .875rem; }
            .ncl-chip button { display: inline-grid; place-items: center; width: 1.2rem; height: 1.2rem; border: 0; border-radius: 999px; background: transparent; color: inherit; cursor: pointer; }
            .ncl-chip button:hover { background: var(--can-accent-soft); }

            /* ═══ Pestaña Canales ═══ */
            .ncc { display: grid; gap: 1rem; }
            .ncc-note { display: flex; gap: .6rem; align-items: flex-start; margin: 0; padding: .8rem 1rem; border-radius: var(--can-radius); background: var(--can-accent-soft); font-size: .84rem; line-height: 1.5; }
            .ncc-note svg { width: 1.1rem; height: 1.1rem; margin-top: .1rem; flex-shrink: 0; color: var(--can-accent); }
            .ncc-grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(18rem, 1fr)); }
            .ncc-card { display: flex; flex-direction: column; gap: .9rem; padding: 1.1rem; border: 1px solid var(--can-line); border-radius: var(--can-radius); background: var(--can-surface); box-shadow: var(--can-shadow); }
            .ncc-card.is-off { border-color: rgb(220 38 38 / 45%); box-shadow: 0 0 0 3px var(--can-off-soft); }
            .ncc-card.tone-whatsapp { --tone: var(--can-wa); --tone-soft: var(--can-wa-soft); }
            .ncc-card.tone-mail { --tone: var(--can-mail); --tone-soft: var(--can-mail-soft); }
            .ncc-card.tone-telegram { --tone: var(--can-tg); --tone-soft: var(--can-tg-soft); }
            .ncc-top { display: flex; align-items: center; gap: .7rem; }
            .ncc-icon { display: grid; place-items: center; width: 2.6rem; height: 2.6rem; flex-shrink: 0; border-radius: .85rem; background: var(--tone-soft); color: var(--tone); }
            .ncc-icon svg { width: 1.3rem; height: 1.3rem; }
            .ncc-name { margin: 0; font-size: 1rem; font-weight: 750; }
            .ncc-state { display: inline-flex; align-items: center; gap: .35rem; margin-top: .15rem; font-size: .72rem; font-weight: 650; }
            .ncc-state i { width: .5rem; height: .5rem; border-radius: 999px; }
            .ncc-state.is-on { color: var(--can-wa); }
            .ncc-state.is-on i { background: var(--can-wa); box-shadow: 0 0 0 3px var(--can-wa-soft); }
            .ncc-state.is-off { color: var(--can-off); }
            .ncc-state.is-off i { background: var(--can-off); box-shadow: 0 0 0 3px var(--can-off-soft); }
            .ncc-desc { margin: 0; font-size: .8rem; line-height: 1.5; color: var(--can-muted); }
            .ncc-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: .5rem; }
            .ncc-stat { padding: .55rem .65rem; border-radius: .75rem; background: var(--can-surface-2); }
            .ncc-stat b { display: block; font-size: 1.15rem; font-weight: 750; font-variant-numeric: tabular-nums; }
            .ncc-stat span { font-size: .68rem; color: var(--can-muted); }
            .ncc-stat.is-bad b { color: var(--can-off); }
            .ncc-bar { display: flex; height: .4rem; margin-top: .55rem; overflow: hidden; border-radius: 999px; background: var(--can-surface-3); }
            .ncc-bar span { display: block; height: 100%; }
            .ncc-split { margin: .4rem 0 0; font-size: .72rem; color: var(--can-muted); }
            .ncc-actions { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; margin-top: auto; padding-top: .25rem; }
            .ncc-health { display: flex; gap: .5rem; align-items: flex-start; padding: .55rem .7rem; border-radius: .7rem; font-size: .76rem; line-height: 1.45; }
            .ncc-health svg { width: 1rem; height: 1rem; margin-top: .05rem; flex-shrink: 0; }
            .ncc-health.is-ok { background: var(--can-wa-soft); color: var(--can-wa); }
            .ncc-health.is-bad { background: var(--can-off-soft); color: var(--can-off); }
            .ncc-health small { opacity: .75; }
            .ncc-meta { font-size: .7rem; color: var(--can-muted); }
        </style>
    @endpush

    <div
        class="can-settings-page"
        x-data="{
            pendingSection: null,
            snapshot: null,
            confirmOpen: false,
            confirmRun: null,
            saving: false,
            syncToken: 0,
            stopCommitHook: null,
            init() {
                {{-- Mientras el servidor recarga el formulario (cambiar de aviso o guardar) no se compara:
                     los datos nuevos llegan antes que la foto nueva y la barra «sin guardar» parpadeaba. --}}
                this.stopCommitHook = Livewire.hook('commit', ({ component, commit, succeed, fail }) => {
                    if (component.id !== $wire.$id) { return; }
                    const refills = (commit.calls ?? []).some((call) => ['save', 'selectNotificationKey'].includes(call.method));
                    if (! refills) { return; }
                    const previous = this.snapshot;
                    const token = ++this.syncToken;
                    this.snapshot = null;
                    const restore = () => { if (token === this.syncToken) { this.snapshot = previous; } };
                    succeed(({ effects }) => {
                        const synced = (effects?.dispatches ?? []).some((event) => event.name === 'can-form-synced');
                        if (! synced) { restore(); }
                    });
                    fail(restore);
                });
            },
            destroy() {
                if (typeof this.stopCommitHook === 'function') { this.stopCommitHook(); }
            },
            serialize() {
                try { return JSON.stringify($wire.data ?? null); } catch (error) { return null; }
            },
            takeSnapshot() {
                const token = ++this.syncToken;
                this.snapshot = null;
                setTimeout(() => { if (token === this.syncToken) { this.snapshot = this.serialize(); } }, 350);
            },
            get dirty() {
                return $wire.section === 'internal' && this.snapshot !== null && this.serialize() !== this.snapshot;
            },
            guard(run) {
                if (! this.dirty) { run(); return; }
                this.confirmRun = run;
                this.confirmOpen = true;
            },
            discardAndRun() {
                const run = this.confirmRun;
                this.confirmOpen = false;
                this.confirmRun = null;
                this.snapshot = null;
                run && run();
            },
            goTo(section) {
                if (section === $wire.section) { return; }
                this.guard(() => { this.pendingSection = section; $wire.showSection(section); });
            },
            async save() {
                this.saving = true;
                try { await $wire.save(); } finally { this.saving = false; }
            },
        }"
        x-on:can-form-synced.window="takeSnapshot()"
        x-on:beforeunload.window="if (dirty) { $event.preventDefault(); $event.returnValue = ''; }"
        x-on:livewire:navigate.document="if (dirty && ! window.confirm('Tiene cambios sin guardar en este aviso. ¿Salir y descartarlos?')) { $event.preventDefault(); }"
    >
        <nav class="can-sections" role="tablist" aria-label="Secciones del Centro de notificaciones">
            @foreach ($sections as $id => $tab)
                <button
                    type="button"
                    role="tab"
                    wire:key="can-tab-{{ $id }}"
                    class="can-section-tab {{ $section === $id ? 'is-on' : '' }}"
                    x-bind:class="{ 'is-on': (pendingSection ?? $wire.section) === @js($id) }"
                    aria-selected="{{ $section === $id ? 'true' : 'false' }}"
                    x-on:click="goTo(@js($id))"
                >
                    <span class="can-section-tab-icon"><x-filament::icon :icon="$tab['icon']" class="h-5 w-5" /></span>
                    <span class="can-section-tab-text"><b>{{ $tab['label'] }}</b><small>{{ $tab['hint'] }}</small></span>
                    @if ($tab['badge'] !== null)
                        <span class="can-section-tab-badge {{ $tab['tone'] === 'bad' ? 'is-bad' : '' }}">{{ $tab['badge'] }}</span>
                    @endif
                </button>
            @endforeach
        </nav>

        <div class="can-skeleton" wire:loading.grid wire:target="showSection" aria-busy="true" aria-label="Cargando sección">
            <div class="can-skeleton-row is-tall"></div>
            <div class="can-skeleton-row"></div>
            <div class="can-skeleton-row"></div>
            <div class="can-skeleton-row"></div>
        </div>

        <div wire:loading.remove wire:target="showSection" wire:key="can-section-{{ $section }}" x-init="pendingSection = null">
            @if ($section !== 'internal' && ! $tablesReady)
                <div class="can-empty-hint">
                    <x-filament::icon icon="heroicon-o-wrench-screwdriver" class="mt-0.5 h-5 w-5 shrink-0" />
                    <span>
                        Falta preparar la base de datos del Centro de notificaciones. Corra
                        <code>php artisan migrate --path=database/migrations/2026_11_21_120000_create_notification_center_tables.php</code>
                        y vuelva a abrir esta página. Mientras tanto, los mensajes salen como siempre y «Avisos internos» funciona normal.
                    </span>
                </div>
            @elseif ($section === 'catalog')
                @livewire(\App\Livewire\NotificationCenter\NotificationCatalogTable::class, [], key('nc-catalog'))
            @elseif ($section === 'log')
                @livewire(\App\Livewire\NotificationCenter\OutboundMessagesTable::class, ['catalogKey' => $logCatalogKey], key('nc-log-'.($logCatalogKey ?? 'all')))
            @elseif ($section === 'channels')
                @livewire(\App\Livewire\NotificationCenter\NotificationChannelsPanel::class, [], key('nc-channels'))
            @else
                @php
                    $activeKey = $this->getActiveNotificationKeyEnum();
                    $settings = $this->settingsRecord;
                    $lastUpdated = $settings->updated_at?->timezone(config('app.timezone'))->format('d/m/Y H:i');
                    $activeChannel = collect($catalog['areas'])->flatMap(fn (array $area): array => $area['items'])->firstWhere('key', $activeKey->value);
                    $areaLabel = \App\Enums\SystemNotificationKey::AREAS[$activeKey->area()] ?? '';
                    $usesEmails = $activeKey->usesEmailRecipients();
                    $usesPhones = $activeKey->usesPhoneRecipients();
                    $usesLists = $usesEmails || $usesPhones;
                    $personTargeted = $activeKey->isPersonTargeted();

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

                <div
                    class="can-layout"
                    x-data="{
                        current: $wire.activeNotificationKey,
                        loadingKey: null,
                        pick(key) {
                            if (key === this.current && ! this.loadingKey) { return; }
                            guard(() => {
                                this.current = key;
                                this.loadingKey = key;
                                $wire.selectNotificationKey(key).finally(() => {
                                    this.loadingKey = null;
                                    this.current = $wire.activeNotificationKey;
                                    if (window.innerWidth < 1280) {
                                        this.$nextTick(() => this.$root.querySelector('.can-main')?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
                                    }
                                });
                            });
                        },
                    }"
                    x-init="takeSnapshot()"
                >
                    {{-- Lista de avisos --}}
                    @php
                        $sidebarItems = collect($catalog['areas'])
                            ->flatMap(fn (array $area, string $areaId): array => array_map(fn (array $item): array => [
                                'key' => $item['key'],
                                'label' => $item['label'],
                                'area' => $areaId,
                                'channel' => $item['channel'],
                                'active' => $item['active'],
                                'meta' => ! $item['active']
                                    ? 'Pausado'
                                    : match ($item['channel']) {
                                        'telegram' => 'Telegram · '.$item['mode'],
                                        'ready' => 'Listo para Telegram',
                                        default => 'Solo WhatsApp'.($item['stage'] ? ' · '.$item['stage'] : ''),
                                    },
                            ], $area['items']))
                            ->values()
                            ->all();
                        $sidebarAreas = collect($catalog['areas'])->map(fn (array $area): string => $area['label'])->all();
                        $countTotal = max(1, $catalog['counts']['total']);
                        $countActive = $catalog['counts']['total'] - $catalog['counts']['paused'];
                    @endphp
                    <aside
                        class="can-aside"
                        aria-label="Lista de avisos internos"
                        x-data="{
                            items: @js($sidebarItems),
                            areas: @js($sidebarAreas),
                            filter: 'all',
                            search: '',
                            collapsed: [],
                            recent: [],
                            storageKey: 'nc-internal-sidebar',
                            init() {
                                try {
                                    const saved = JSON.parse(window.localStorage.getItem(this.storageKey) || '{}');
                                    const known = ['all', 'telegram', 'ready', 'whatsapp', 'paused'];
                                    this.filter = known.includes(saved.filter) ? saved.filter : 'all';
                                    this.collapsed = Array.isArray(saved.collapsed) ? saved.collapsed.filter((area) => area in this.areas) : [];
                                    this.recent = Array.isArray(saved.recent) ? saved.recent.filter((key) => this.byKey(key)).slice(0, 4) : [];
                                } catch (error) { /* almacenamiento bloqueado: se usan los valores por defecto */ }
                                this.$watch('filter', () => this.persist());
                                this.remember(current);
                                this.$watch('current', (key) => this.remember(key));
                            },
                            persist() {
                                try {
                                    window.localStorage.setItem(this.storageKey, JSON.stringify({ filter: this.filter, collapsed: this.collapsed, recent: this.recent }));
                                } catch (error) { /* sin almacenamiento: no pasa nada */ }
                            },
                            remember(key) {
                                if (! this.byKey(key)) { return; }
                                this.recent = [key, ...this.recent.filter((item) => item !== key)].slice(0, 4);
                                this.persist();
                            },
                            byKey(key) { return this.items.find((item) => item.key === key) ?? null; },
                            normalize(text) { return (text || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); },
                            get query() { return this.normalize(this.search.trim()); },
                            get narrowing() { return this.query !== '' || this.filter !== 'all'; },
                            matchesSearch(item) {
                                return this.query === '' || this.normalize(item.label).includes(this.query) || this.normalize(this.areas[item.area]).includes(this.query);
                            },
                            matchesFilter(item, filter) {
                                if (filter === 'all') { return true; }
                                if (filter === 'paused') { return ! item.active; }
                                return item.channel === filter;
                            },
                            get visibleKeys() {
                                return new Set(this.items.filter((item) => this.matchesSearch(item) && this.matchesFilter(item, this.filter)).map((item) => item.key));
                            },
                            countFor(filter) { return this.items.filter((item) => this.matchesSearch(item) && this.matchesFilter(item, filter)).length; },
                            areaCount(area) { return this.items.filter((item) => item.area === area && this.visibleKeys.has(item.key)).length; },
                            areaPaused(area) { return this.items.filter((item) => item.area === area && ! item.active).length; },
                            isOpen(area) { return this.narrowing || ! this.collapsed.includes(area); },
                            toggleArea(area) {
                                this.collapsed = this.collapsed.includes(area) ? this.collapsed.filter((item) => item !== area) : [...this.collapsed, area];
                                this.persist();
                            },
                            chipShown(key, area) { return this.visibleKeys.has(key) && (this.isOpen(area) || key === current); },
                            get recentItems() { return this.narrowing ? [] : this.recent.filter((key) => key !== current).map((key) => this.byKey(key)).filter(Boolean).slice(0, 3); },
                            escape(text) { const node = document.createElement('span'); node.textContent = String(text); return node.innerHTML; },
                            highlight(label) {
                                const query = this.query;
                                if (query === '') { return this.escape(label); }
                                let normalized = '';
                                const map = [];
                                [...label].forEach((char, index) => { for (const piece of this.normalize(char)) { normalized += piece; map.push(index); } });
                                const at = normalized.indexOf(query);
                                if (at === -1) { return this.escape(label); }
                                const chars = [...label];
                                const start = map[at];
                                const end = map[at + query.length - 1] + 1;
                                return this.escape(chars.slice(0, start).join('')) + '<mark>' + this.escape(chars.slice(start, end).join('')) + '</mark>' + this.escape(chars.slice(end).join(''));
                            },
                            clearAll() { this.search = ''; this.filter = 'all'; this.$refs.search.focus(); },
                            focusables() { return [...this.$root.querySelectorAll('[data-chip]')].filter((chip) => chip.offsetParent !== null); },
                            move(step) {
                                const chips = this.focusables();
                                if (chips.length === 0) { return; }
                                const index = chips.indexOf(document.activeElement);
                                const target = step === 'first' ? chips[0]
                                    : step === 'last' ? chips[chips.length - 1]
                                    : chips[Math.min(chips.length - 1, Math.max(0, index + step))] ?? chips[0];
                                target.focus();
                                target.scrollIntoView({ block: 'nearest' });
                            },
                        }"
                        x-on:keydown.slash.window="if (! ['INPUT', 'TEXTAREA', 'SELECT'].includes($event.target.tagName) && ! $event.target.isContentEditable) { $event.preventDefault(); $refs.search.focus(); $refs.search.select(); }"
                    >
                        <div class="can-aside-head">
                            <div class="can-aside-title">
                                <span>Avisos internos</span>
                                <small>{{ $catalog['counts']['total'] }} en total</small>
                            </div>

                            <div class="can-health" aria-label="Resumen de los avisos">
                                <div class="can-health-row">
                                    <span><i class="is-on"></i><b>{{ $countActive }}</b> activos</span>
                                    @if ($catalog['counts']['paused'] > 0)
                                        <button type="button" class="can-health-paused" x-on:click="filter = filter === 'paused' ? 'all' : 'paused'" title="Ver solo los pausados">
                                            <i class="is-off"></i><b>{{ $catalog['counts']['paused'] }}</b> pausados
                                        </button>
                                    @else
                                        <span class="can-health-muted">Ninguno pausado</span>
                                    @endif
                                </div>
                                <div class="can-health-bar" role="img" aria-label="{{ $catalog['counts']['telegram'] }} por Telegram, {{ $catalog['counts']['ready'] }} listos para Telegram y {{ $catalog['counts']['whatsapp'] }} solo por WhatsApp">
                                    <span style="width: {{ round($catalog['counts']['telegram'] * 100 / $countTotal, 2) }}%; background: var(--can-tg);"></span>
                                    <span style="width: {{ round($catalog['counts']['ready'] * 100 / $countTotal, 2) }}%; background: var(--can-ready);"></span>
                                    <span style="width: {{ round($catalog['counts']['whatsapp'] * 100 / $countTotal, 2) }}%; background: var(--can-wa);"></span>
                                </div>
                                <div class="can-health-legend">
                                    <span><i style="background: var(--can-tg);"></i>Telegram {{ $catalog['counts']['telegram'] }}</span>
                                    <span><i style="background: var(--can-ready);"></i>Listos {{ $catalog['counts']['ready'] }}</span>
                                    @if ($catalog['counts']['whatsapp'] > 0)
                                        <span><i style="background: var(--can-wa);"></i>WhatsApp {{ $catalog['counts']['whatsapp'] }}</span>
                                    @endif
                                </div>
                            </div>

                            <div class="can-search-wrap">
                                <x-filament::icon icon="heroicon-m-magnifying-glass" />
                                <input
                                    type="search"
                                    class="can-search"
                                    placeholder="Buscar por nombre o área…"
                                    autocomplete="off"
                                    x-ref="search"
                                    x-model.debounce.100ms="search"
                                    x-on:keydown.down.prevent="move('first')"
                                    x-on:keydown.enter.prevent="const first = focusables()[0]; if (first) { first.click(); }"
                                    x-on:keydown.escape.prevent="search = ''"
                                    aria-label="Buscar aviso (atajo: tecla /)"
                                >
                                <span class="can-kbd" x-show="search === ''" aria-hidden="true">/</span>
                                <button type="button" class="can-search-clear" x-cloak x-show="search !== ''" x-on:click="search = ''; $refs.search.focus()" aria-label="Limpiar búsqueda">
                                    <x-filament::icon icon="heroicon-m-x-mark" class="h-3.5 w-3.5" />
                                </button>
                            </div>

                            <div class="can-filters" role="tablist" aria-label="Filtrar avisos por canal">
                                @foreach ($filters as $filter)
                                    @continue($filter['id'] !== 'all' && $filter['count'] === 0)
                                    <button
                                        type="button"
                                        role="tab"
                                        class="can-filter is-{{ $filter['id'] }}"
                                        x-bind:class="{ 'is-on': filter === @js($filter['id']) }"
                                        x-bind:aria-selected="filter === @js($filter['id'])"
                                        x-on:click="filter = @js($filter['id'])"
                                    >
                                        @if ($filter['id'] !== 'all')<i aria-hidden="true"></i>@endif
                                        {{ $filter['label'] }} <span class="can-filter-count" x-text="countFor(@js($filter['id']))">{{ $filter['count'] }}</span>
                                    </button>
                                @endforeach
                            </div>

                            <p class="can-results" x-cloak x-show="narrowing" aria-live="polite">
                                <span x-text="visibleKeys.size === 1 ? '1 aviso coincide' : visibleKeys.size + ' avisos coinciden'"></span>
                                <button type="button" x-on:click="clearAll()">Limpiar</button>
                            </p>
                        </div>

                        <nav
                            class="can-aside-list"
                            aria-label="Tipos de notificación"
                            x-on:keydown.down.prevent="move(1)"
                            x-on:keydown.up.prevent="move(-1)"
                            x-on:keydown.home.prevent="move('first')"
                            x-on:keydown.end.prevent="move('last')"
                        >
                            {{-- Recientes --}}
                            <div class="can-recent" x-cloak x-show="recentItems.length > 0">
                                <p class="can-area-title is-static">
                                    <x-filament::icon icon="heroicon-m-clock" class="h-3.5 w-3.5" /> Recientes
                                </p>
                                <div class="can-recent-list">
                                    <template x-for="item in recentItems" x-bind:key="'recent-' + item.key">
                                        <button
                                            type="button"
                                            class="can-recent-item"
                                            x-bind:class="{ 'is-paused': ! item.active }"
                                            x-on:click="pick(item.key)"
                                            x-bind:title="item.label + ' · ' + item.meta"
                                        >
                                            <span class="can-chip-dot" aria-hidden="true"></span>
                                            <span class="can-recent-label" x-text="item.label"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>

                            @foreach ($catalog['areas'] as $areaId => $area)
                                <section class="can-area" x-show="areaCount(@js($areaId)) > 0" wire:key="can-area-{{ $areaId }}">
                                    <button
                                        type="button"
                                        class="can-area-title"
                                        x-on:click="toggleArea(@js($areaId))"
                                        x-bind:aria-expanded="isOpen(@js($areaId))"
                                        x-bind:disabled="narrowing"
                                        aria-controls="can-area-list-{{ $areaId }}"
                                    >
                                        <span class="can-area-chevron" x-bind:class="{ 'is-closed': ! isOpen(@js($areaId)) }"><x-filament::icon icon="heroicon-m-chevron-down" class="h-full w-full" /></span>
                                        <span class="can-area-name">{{ $area['label'] }}</span>
                                        <span class="can-area-paused" x-show="areaPaused(@js($areaId)) > 0" x-text="areaPaused(@js($areaId)) + ' pausado' + (areaPaused(@js($areaId)) === 1 ? '' : 's')" x-cloak></span>
                                        <span class="can-area-count" x-text="areaCount(@js($areaId))">{{ count($area['items']) }}</span>
                                    </button>
                                    <div class="can-chips" id="can-area-list-{{ $areaId }}" role="listbox" aria-label="{{ $area['label'] }}">
                                        @foreach ($area['items'] as $item)
                                            @php
                                                $meta = ! $item['active']
                                                    ? 'Pausado'
                                                    : match ($item['channel']) {
                                                        'telegram' => 'Telegram · '.$item['mode'],
                                                        'ready' => 'Listo para Telegram',
                                                        default => 'Solo WhatsApp'.($item['stage'] ? ' · '.$item['stage'] : ''),
                                                    };
                                                $tooltip = match ($item['channel']) {
                                                    'telegram' => 'Sale por Telegram ('.$item['mode'].') a los vinculados; el resto, por WhatsApp. El correo no cambia.',
                                                    'ready' => 'Listo para Telegram: elija Persona, Grupo o Ambos en la sección Telegram. Hoy sale por WhatsApp.',
                                                    default => 'Hoy sale solo por WhatsApp y correo.',
                                                };
                                            @endphp
                                            <button
                                                type="button"
                                                role="option"
                                                data-chip
                                                wire:key="can-chip-{{ $item['key'] }}-{{ $item['active'] ? 'on' : 'off' }}-{{ $item['channel'] }}"
                                                x-show="chipShown(@js($item['key']), @js($areaId))"
                                                class="can-chip is-{{ $item['channel'] }} {{ $item['active'] ? '' : 'is-paused' }}"
                                                x-bind:class="{ 'is-selected': current === @js($item['key']), 'is-loading': loadingKey === @js($item['key']) }"
                                                x-bind:aria-selected="current === @js($item['key'])"
                                                x-on:click="pick(@js($item['key']))"
                                                @if ($activeKey->value === $item['key'])
                                                    x-init="$nextTick(() => { const list = $el.closest('.can-aside-list'); if (list && ($el.offsetTop < list.scrollTop || $el.offsetTop > list.scrollTop + list.clientHeight - 56)) { list.scrollTop = $el.offsetTop - 96; } })"
                                                @endif
                                                title="{{ $tooltip }}"
                                            >
                                                <span class="can-chip-dot" aria-hidden="true"></span>
                                                <span class="can-chip-text">
                                                    <span class="can-chip-label" x-html="highlight(@js($item['label']))">{{ $item['label'] }}</span>
                                                    <span class="can-chip-meta">
                                                        <x-filament::icon :icon="$channelIcon($item['channel'])" class="can-chip-icon" />
                                                        {{ $meta }}
                                                    </span>
                                                </span>
                                                <span class="can-spinner" x-cloak x-show="loadingKey === @js($item['key'])" aria-hidden="true"></span>
                                                <span class="can-chip-go" x-show="loadingKey !== @js($item['key'])"><x-filament::icon icon="heroicon-m-chevron-right" class="h-full w-full" /></span>
                                            </button>
                                        @endforeach
                                    </div>
                                </section>
                            @endforeach

                            <div class="can-aside-empty" x-cloak x-show="visibleKeys.size === 0">
                                <x-filament::icon icon="heroicon-o-magnifying-glass" class="h-6 w-6" />
                                <p>Ningún aviso coincide<span x-show="search !== ''"> con «<b x-text="search"></b>»</span>.</p>
                                <button type="button" class="can-btn is-ghost" x-on:click="clearAll()">Limpiar búsqueda y filtro</button>
                            </div>
                        </nav>

                        <div class="can-aside-foot" aria-hidden="true">
                            <span><kbd>↑</kbd><kbd>↓</kbd> moverse</span>
                            <span><kbd>Enter</kbd> abrir</span>
                            <span><kbd>/</kbd> buscar</span>
                            <span><kbd>Esc</kbd> limpiar</span>
                        </div>
                    </aside>

                    {{-- Detalle del aviso elegido --}}
                    <div class="can-main" wire:key="can-detail-{{ $activeKey->value }}" wire:loading.class="is-loading" wire:target="selectNotificationKey">
                        <section class="can-hero" aria-labelledby="can-hero-title">
                            <div class="can-hero-top">
                                <div class="can-hero-icon" aria-hidden="true">
                                    <x-filament::icon :icon="$activeKey->calloutIcon()" class="h-6 w-6" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <span class="can-breadcrumb">{{ $areaLabel }}</span>
                                    <h2 id="can-hero-title">{{ $activeKey->heroTitle() }}</h2>
                                    <div class="can-pills">
                                        <span class="can-pill is-on" x-show="$wire.data?.is_active" @unless ($settings->isActive()) x-cloak @endunless>
                                            <x-filament::icon icon="heroicon-m-check-circle" /> Activa
                                        </span>
                                        <span class="can-pill is-off" x-show="! $wire.data?.is_active" @if ($settings->isActive()) x-cloak @endif>
                                            <x-filament::icon icon="heroicon-m-pause-circle" /> Pausada
                                        </span>
                                        @if ($activeChannel)
                                            <span class="can-pill is-{{ $activeChannel['channel'] }}">
                                                <x-filament::icon :icon="$channelIcon($activeChannel['channel'])" />
                                                {{ match ($activeChannel['channel']) {
                                                    'telegram' => 'Telegram · '.$activeChannel['mode'],
                                                    'ready' => 'Listo para Telegram',
                                                    default => 'Solo WhatsApp',
                                                } }}
                                            </span>
                                        @endif
                                        <span class="can-pill is-dirty" x-cloak x-show="dirty">
                                            <x-filament::icon icon="heroicon-m-pencil-square" /> Sin guardar
                                        </span>
                                    </div>
                                    <p class="can-hero-body">{{ $activeKey->heroBody() }}</p>
                                </div>
                            </div>

                            <div class="can-stats">
                                @if ($usesEmails)
                                    <div class="can-stat">
                                        <span class="can-stat-label"><x-filament::icon icon="heroicon-m-envelope" /> Correos</span>
                                        <span class="can-stat-value" x-text="Object.keys($wire.data?.notification_emails ?? {}).length">{{ $this->configuredEmailCount }}</span>
                                        <span class="can-stat-meta">Destinatarios por email</span>
                                    </div>
                                @endif
                                @if ($usesPhones)
                                    <div class="can-stat">
                                        <span class="can-stat-label"><x-filament::icon icon="heroicon-m-device-phone-mobile" /> WhatsApp</span>
                                        <span class="can-stat-value" x-text="Object.keys($wire.data?.notification_phones ?? {}).length">{{ $this->configuredPhoneCount }}</span>
                                        <span class="can-stat-meta">Teléfonos configurados</span>
                                    </div>
                                @endif
                                @if ($activeKey->supportsTelegram())
                                    <div class="can-stat">
                                        <span class="can-stat-label"><x-filament::icon icon="heroicon-m-paper-airplane" /> Telegram</span>
                                        <span class="can-stat-value is-small" x-text="({ person: 'Persona', group: 'Grupo', both: 'Ambos' })[$wire.data?.telegram_mode] ?? 'Apagado'">{{ match ($settings->telegramMode()) { \App\Enums\TelegramDeliveryMode::Person => 'Persona', \App\Enums\TelegramDeliveryMode::Group => 'Grupo', \App\Enums\TelegramDeliveryMode::Both => 'Ambos', default => 'Apagado' } }}</span>
                                        <span class="can-stat-meta">
                                            @if ($personTargeted)
                                                A quien le corresponde
                                            @else
                                                <span x-text="(() => { const people = Object.keys($wire.data?.telegram_recipient_ids ?? {}).length; const groups = Object.keys($wire.data?.telegram_group_ids ?? {}).length; return people + (people === 1 ? ' persona' : ' personas') + ' · ' + groups + (groups === 1 ? ' grupo' : ' grupos'); })()"></span>
                                            @endif
                                        </span>
                                    </div>
                                @endif
                                <div class="can-stat">
                                    <span class="can-stat-label"><x-filament::icon icon="heroicon-m-clock" /> Actualizado</span>
                                    <span class="can-stat-value is-small">{{ $lastUpdated ?? '—' }}</span>
                                    <span class="can-stat-meta">{{ filled($settings->updated_by) ? 'Por '.$settings->updated_by : 'Sin cambios guardados' }}</span>
                                </div>
                            </div>

                            <details class="can-howto">
                                <summary>
                                    <x-filament::icon icon="heroicon-m-chevron-right" class="can-howto-chevron" />
                                    ¿Cómo funciona este aviso?
                                </summary>
                                <div class="can-howto-body">
                                    <div class="can-flow">
                                        @foreach ($activeKey->flowSteps() as $step)
                                            <span class="can-flow-step">{{ $step }}</span>
                                        @endforeach
                                    </div>
                                    <div class="can-callout">
                                        <x-filament::icon :icon="$activeKey->calloutIcon()" />
                                        <p class="m-0"><strong>{{ $activeKey->calloutTitle() }}</strong> {{ $activeKey->calloutBody() }}</p>
                                    </div>
                                    <p class="can-help">{{ $activeKey->activationHelp() }}</p>
                                </div>
                            </details>
                        </section>

                        @if ($usesLists)
                            <div
                                class="can-empty-hint"
                                x-show="Object.keys($wire.data?.notification_emails ?? {}).length + Object.keys($wire.data?.notification_phones ?? {}).length === 0"
                                @if ($this->hasRecipients) x-cloak @endif
                            >
                                <x-filament::icon icon="heroicon-o-user-plus" class="mt-0.5 h-5 w-5 shrink-0" />
                                <span>{{ $activeKey->emptyRecipientsHint() }}</span>
                            </div>
                        @endif

                        {{ $this->content }}
                    </div>
                </div>
            @endif
        </div>

        {{-- Cambios sin guardar --}}
        <div class="can-dirty-bar" x-cloak x-show="dirty" x-transition.opacity role="status" aria-live="polite">
            <p><i></i> Cambios sin guardar <span>en este aviso</span></p>
            <button type="button" class="can-btn is-ghost" x-on:click="guard(() => $wire.selectNotificationKey($wire.activeNotificationKey))" x-bind:disabled="saving">
                Descartar
            </button>
            <button type="button" class="can-btn is-primary" x-on:click="save()" x-bind:disabled="saving">
                <x-filament::icon icon="heroicon-m-check" x-show="! saving" />
                <span class="can-spinner" x-show="saving" x-cloak></span>
                <span x-text="saving ? 'Guardando…' : 'Guardar'">Guardar</span>
            </button>
        </div>

        {{-- Confirmación para descartar --}}
        <div class="can-dialog-backdrop" x-cloak x-show="confirmOpen" x-transition.opacity x-on:keydown.escape.window="confirmOpen = false" x-on:click.self="confirmOpen = false">
            <div class="can-dialog" role="alertdialog" aria-modal="true" aria-labelledby="can-discard-title" x-trap.noscroll="confirmOpen">
                <h3 id="can-discard-title">¿Descartar los cambios?</h3>
                <p>Hay cambios en este aviso que todavía no se guardaron. Si continúa, se pierden.</p>
                <div class="can-dialog-actions">
                    <button type="button" class="can-btn is-ghost" x-on:click="confirmOpen = false">Seguir editando</button>
                    <button type="button" class="can-btn is-danger" x-on:click="discardAndRun()">Descartar cambios</button>
                </div>
            </div>
        </div>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
