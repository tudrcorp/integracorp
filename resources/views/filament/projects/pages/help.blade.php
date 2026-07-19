<x-filament-panels::page>
    @php
        $sections = $this->sections;
        $toc = $this->toc;
        $sectionCount = count($sections);
        $sectionIcons = [
            'inicio' => 'heroicon-m-home',
            'menu' => 'heroicon-m-bars-3-bottom-left',
            'conceptos' => 'heroicon-m-book-open',
            'paso-a-paso' => 'heroicon-m-queue-list',
            'ejemplo-marketing' => 'heroicon-m-megaphone',
            'ejemplo-sistemas' => 'heroicon-m-cpu-chip',
            'kanban' => 'heroicon-m-view-columns',
            'metricas' => 'heroicon-m-chart-bar',
            'equipos' => 'heroicon-m-user-group',
            'checklist' => 'heroicon-m-clipboard-document-check',
            'faq' => 'heroicon-m-chat-bubble-left-right',
        ];
    @endphp

    <div
        class="pm-help"
        x-data="{
            active: @js($activeSection),
            progress: 0,
            showTop: false,
            setActive(id) {
                this.active = id;
                $wire.setActiveSection(id);
                const el = document.getElementById('pm-help-' + id);
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            },
            onScroll() {
                const max = document.documentElement.scrollHeight - window.innerHeight;
                this.progress = max > 0 ? Math.min(100, Math.round((window.scrollY / max) * 100)) : 0;
                this.showTop = window.scrollY > 420;
            }
        }"
        x-init="
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        active = entry.target.dataset.sectionId;
                    }
                });
            }, { rootMargin: '-18% 0px -62% 0px', threshold: [0.15, 0.35] });
            $nextTick(() => {
                document.querySelectorAll('[data-section-id]').forEach((node) => observer.observe(node));
            });
            onScroll();
            window.addEventListener('scroll', () => onScroll(), { passive: true });
        "
    >
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@450;550;650;750&display=swap');

            .pm-help {
                --pm-glass-bg: rgba(255, 255, 255, 0.48);
                --pm-glass-bg-strong: rgba(255, 255, 255, 0.78);
                --pm-glass-border: rgba(255, 255, 255, 0.62);
                --pm-glass-edge: rgba(15, 23, 42, 0.07);
                --pm-glass-shadow:
                    0 1px 0 rgba(255,255,255,0.7) inset,
                    0 20px 50px -30px rgba(15, 23, 42, 0.42),
                    0 0 0 1px rgba(15, 23, 42, 0.03);
                --pm-ink: #0b1220;
                --pm-muted: #5b6b7c;
                --pm-accent: #0891b2;
                --pm-accent-2: #0d9488;
                --pm-accent-soft: rgba(8, 145, 178, 0.12);
                --pm-warm: #d97706;
                font-family: 'Outfit', ui-sans-serif, system-ui, sans-serif;
                position: relative;
                isolation: isolate;
                color: var(--pm-ink);
                margin: -0.25rem;
            }

            .dark .pm-help {
                --pm-glass-bg: rgba(12, 18, 32, 0.58);
                --pm-glass-bg-strong: rgba(17, 24, 39, 0.82);
                --pm-glass-border: rgba(255, 255, 255, 0.14);
                --pm-glass-edge: rgba(255, 255, 255, 0.08);
                --pm-glass-shadow:
                    0 1px 0 rgba(255,255,255,0.08) inset,
                    0 28px 70px -34px rgba(0, 0, 0, 0.8),
                    0 0 0 1px rgba(255, 255, 255, 0.04);
                --pm-ink: #f1f5f9;
                --pm-muted: #94a3b8;
                --pm-accent: #22d3ee;
                --pm-accent-2: #2dd4bf;
                --pm-accent-soft: rgba(34, 211, 238, 0.14);
                --pm-warm: #fbbf24;
            }

            .pm-help__progress {
                position: sticky;
                top: 0;
                z-index: 40;
                height: 3px;
                margin: 0 0 1rem;
                overflow: hidden;
                border-radius: 999px;
                background: rgba(148, 163, 184, 0.18);
            }

            .pm-help__progress-bar {
                height: 100%;
                width: 0%;
                border-radius: inherit;
                background: linear-gradient(90deg, var(--pm-accent), var(--pm-accent-2));
                box-shadow: 0 0 18px color-mix(in srgb, var(--pm-accent) 55%, transparent);
                transition: width 120ms linear;
            }

            .pm-help__atmosphere {
                pointer-events: none;
                position: absolute;
                inset: -1.5rem -1rem 0;
                z-index: 0;
                overflow: hidden;
                border-radius: 2.25rem;
            }

            .pm-help__mesh {
                position: absolute;
                inset: 0;
                opacity: 0.35;
                background-image:
                    radial-gradient(rgba(15, 23, 42, 0.05) 0.7px, transparent 0.7px);
                background-size: 18px 18px;
                mask-image: linear-gradient(180deg, #000 20%, transparent 90%);
            }

            .dark .pm-help__mesh {
                opacity: 0.2;
                background-image: radial-gradient(rgba(255,255,255,0.08) 0.7px, transparent 0.7px);
            }

            .pm-help__orb {
                position: absolute;
                border-radius: 9999px;
                filter: blur(52px);
                opacity: 0.28;
                will-change: transform;
                animation: pm-help-float 14s ease-in-out infinite;
            }

            .pm-help__orb--a {
                width: 24rem; height: 24rem; left: -5rem; top: -4rem;
                background: radial-gradient(circle at 30% 30%, rgba(255, 255, 255, 0.22), transparent 68%);
            }

            .pm-help__orb--b {
                width: 18rem; height: 18rem; right: -3rem; top: 12rem;
                background: radial-gradient(circle at 40% 40%, rgba(148, 163, 184, 0.18), transparent 70%);
                animation-delay: -5s;
            }

            .pm-help__orb--c {
                width: 16rem; height: 16rem; left: 45%; top: 28rem;
                background: radial-gradient(circle at 50% 50%, rgba(255, 255, 255, 0.1), transparent 70%);
                animation-delay: -9s;
            }

            @keyframes pm-help-float {
                0%, 100% { transform: translate3d(0, 0, 0) scale(1); }
                50% { transform: translate3d(8px, 16px, 0) scale(1.04); }
            }

            .pm-help__glass {
                position: relative;
                z-index: 1;
                border: 1px solid var(--pm-glass-border);
                background:
                    linear-gradient(145deg, rgba(255,255,255,0.42) 0%, rgba(255,255,255,0.08) 38%, rgba(255,255,255,0.18) 100%),
                    var(--pm-glass-bg);
                box-shadow: var(--pm-glass-shadow);
                backdrop-filter: blur(32px) saturate(1.45);
                -webkit-backdrop-filter: blur(32px) saturate(1.45);
            }

            .dark .pm-help__glass {
                background:
                    linear-gradient(145deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.02) 40%, rgba(255,255,255,0.05) 100%),
                    var(--pm-glass-bg);
            }

            .pm-help__specular {
                pointer-events: none;
                position: absolute;
                inset: 0;
                border-radius: inherit;
                background:
                    linear-gradient(120deg, rgba(255,255,255,0.55), transparent 32%),
                    radial-gradient(120% 80% at 0% 0%, rgba(255,255,255,0.28), transparent 45%);
                mix-blend-mode: soft-light;
                opacity: 0.9;
            }

            .pm-help__ring {
                pointer-events: none;
                position: absolute;
                inset: 0;
                border-radius: inherit;
                box-shadow:
                    inset 0 0 0 1px rgba(255,255,255,0.28),
                    inset 0 -20px 40px rgba(8, 145, 178, 0.04);
            }

            .pm-help__hero {
                overflow: hidden;
                border-radius: 2rem;
                padding: 1.35rem 1.35rem 1.5rem;
            }

            @media (min-width: 768px) {
                .pm-help__hero { padding: 1.85rem 2rem 2rem; }
            }

            .pm-help__brand {
                font-size: clamp(2rem, 3.4vw, 2.75rem);
                font-weight: 750;
                letter-spacing: -0.04em;
                line-height: 1.05;
                background: linear-gradient(120deg, var(--pm-ink) 20%, color-mix(in srgb, var(--pm-accent) 70%, var(--pm-ink)));
                -webkit-background-clip: text;
                background-clip: text;
                color: transparent;
            }

            .dark .pm-help__brand {
                background: linear-gradient(120deg, #fff 10%, #67e8f9 80%);
                -webkit-background-clip: text;
                background-clip: text;
            }

            .pm-help__chip {
                display: inline-flex;
                align-items: center;
                gap: 0.4rem;
                border-radius: 9999px;
                border: 1px solid var(--pm-glass-border);
                background: var(--pm-glass-bg-strong);
                padding: 0.4rem 0.8rem;
                font-size: 0.72rem;
                font-weight: 650;
                letter-spacing: 0.06em;
                text-transform: uppercase;
                color: var(--pm-muted);
                backdrop-filter: blur(16px);
            }

            .pm-help__shortcut {
                display: inline-flex;
                align-items: center;
                gap: 0.45rem;
                border-radius: 1rem;
                border: 1px solid var(--pm-glass-edge);
                background: color-mix(in srgb, var(--pm-glass-bg-strong) 90%, transparent);
                padding: 0.65rem 0.85rem;
                font-size: 0.82rem;
                font-weight: 600;
                color: var(--pm-ink);
                transition: transform 160ms ease, border-color 160ms ease, background 160ms ease;
            }

            .pm-help__shortcut:hover {
                transform: translateY(-1px);
                border-color: color-mix(in srgb, var(--pm-accent) 40%, transparent);
                background: var(--pm-accent-soft);
            }

            .pm-help__search-wrap {
                position: relative;
                border-radius: 1.25rem;
                padding: 1px;
                background: linear-gradient(135deg, rgba(255,255,255,0.75), rgba(8,145,178,0.25), rgba(255,255,255,0.2));
            }

            .dark .pm-help__search-wrap {
                background: linear-gradient(135deg, rgba(255,255,255,0.18), rgba(34,211,238,0.25), rgba(255,255,255,0.05));
            }

            .pm-help__search {
                width: 100%;
                border-radius: 1.2rem;
                border: 0;
                background: var(--pm-glass-bg-strong);
                padding: 0.95rem 1rem 0.95rem 2.85rem;
                font-size: 0.95rem;
                font-weight: 500;
                color: var(--pm-ink);
                outline: none;
                box-shadow: inset 0 1px 0 rgba(255,255,255,0.4);
                backdrop-filter: blur(18px);
            }

            .pm-help__toc {
                overflow: hidden;
                border-radius: 1.75rem;
                padding: 0.55rem 0.65rem 0.7rem;
            }

            @media (min-width: 1280px) {
                .pm-help__toc {
                    margin-top: 0;
                    align-self: start;
                }
            }

            .pm-help__toc-scroll {
                display: flex;
                gap: 0.5rem;
                overflow-x: auto;
                padding-bottom: 0.15rem;
                scrollbar-width: none;
            }

            .pm-help__toc-scroll::-webkit-scrollbar { display: none; }

            @media (min-width: 1280px) {
                .pm-help__toc-scroll {
                    display: block;
                    overflow: visible;
                    max-height: calc(100vh - 10rem);
                    overflow-y: auto;
                    padding-right: 0.15rem;
                }
            }

            .pm-help__toc-btn {
                display: flex;
                align-items: center;
                gap: 0.55rem;
                width: auto;
                min-width: max-content;
                text-align: left;
                border-radius: 0.95rem;
                border: 1px solid transparent;
                padding: 0.65rem 0.8rem;
                font-size: 0.84rem;
                font-weight: 550;
                color: var(--pm-muted);
                transition: all 170ms ease;
                white-space: nowrap;
            }

            @media (min-width: 1280px) {
                .pm-help__toc-btn {
                    width: 100%;
                    white-space: normal;
                }
            }

            .pm-help__toc-btn:hover {
                color: var(--pm-ink);
                background: rgba(255,255,255,0.3);
            }

            .dark .pm-help__toc-btn:hover {
                background: rgba(255,255,255,0.05);
            }

            .pm-help__toc-btn.is-active {
                color: var(--pm-ink);
                border-color: color-mix(in srgb, var(--pm-accent) 40%, transparent);
                background: linear-gradient(135deg, var(--pm-accent-soft), transparent 80%);
                box-shadow: inset 0 1px 0 rgba(255,255,255,0.35);
            }

            .pm-help__toc-dot {
                width: 0.45rem;
                height: 0.45rem;
                border-radius: 999px;
                background: currentColor;
                opacity: 0.35;
                flex-shrink: 0;
            }

            .pm-help__toc-btn.is-active .pm-help__toc-dot {
                opacity: 1;
                background: var(--pm-accent);
                box-shadow: 0 0 0 4px var(--pm-accent-soft);
            }

            .pm-help__section {
                overflow: hidden;
                border-radius: 1.85rem;
                padding: 1.35rem 1.25rem 1.5rem;
                scroll-margin-top: 5.5rem;
                animation: pm-help-rise 480ms cubic-bezier(.2,.8,.2,1) both;
            }

            @media (min-width: 768px) {
                .pm-help__section { padding: 1.7rem 1.8rem 1.9rem; }
            }

            @keyframes pm-help-rise {
                from { opacity: 0; transform: translateY(14px) scale(0.995); }
                to { opacity: 1; transform: translateY(0) scale(1); }
            }

            .pm-help__section-kicker {
                display: inline-flex;
                align-items: center;
                gap: 0.45rem;
                border-radius: 999px;
                border: 1px solid var(--pm-glass-edge);
                background: var(--pm-glass-bg-strong);
                padding: 0.3rem 0.65rem;
                font-size: 0.68rem;
                font-weight: 700;
                letter-spacing: 0.12em;
                text-transform: uppercase;
                color: var(--pm-accent);
            }

            .pm-help__card {
                position: relative;
                overflow: hidden;
                border-radius: 1.3rem;
                border: 1px solid var(--pm-glass-edge);
                background:
                    linear-gradient(160deg, rgba(255,255,255,0.35), transparent 55%),
                    color-mix(in srgb, var(--pm-glass-bg-strong) 92%, transparent);
                padding: 1.05rem 1.1rem 1.15rem;
                backdrop-filter: blur(14px);
                transition: transform 180ms ease, box-shadow 180ms ease, border-color 180ms ease;
            }

            .pm-help__card:hover {
                transform: translateY(-3px);
                border-color: color-mix(in srgb, var(--pm-accent) 28%, transparent);
                box-shadow: 0 18px 34px -24px rgba(15, 23, 42, 0.55);
            }

            .pm-help__callout {
                position: relative;
                overflow: hidden;
                border-radius: 1.3rem;
                border: 1px solid color-mix(in srgb, var(--pm-accent) 28%, transparent);
                background:
                    linear-gradient(135deg, var(--pm-accent-soft), transparent 65%),
                    var(--pm-glass-bg-strong);
                padding: 1.05rem 1.15rem;
            }

            .pm-help__callout::before {
                content: '';
                position: absolute;
                left: 0; top: 0; bottom: 0;
                width: 3px;
                background: linear-gradient(180deg, var(--pm-accent), var(--pm-accent-2));
            }

            .pm-help__step {
                display: grid;
                grid-template-columns: auto 1fr;
                gap: 0.9rem;
                padding: 0.95rem 0;
                border-bottom: 1px solid var(--pm-glass-edge);
            }

            .pm-help__step:last-child { border-bottom: 0; padding-bottom: 0; }

            .pm-help__step-index {
                display: grid;
                place-items: center;
                width: 2rem;
                height: 2rem;
                border-radius: 9999px;
                border: 1px solid color-mix(in srgb, var(--pm-accent) 35%, transparent);
                background: linear-gradient(160deg, var(--pm-accent-soft), var(--pm-glass-bg-strong));
                font-size: 0.78rem;
                font-weight: 750;
                color: var(--pm-accent);
            }

            .pm-help__list-item {
                display: grid;
                grid-template-columns: auto 1fr;
                gap: 0.7rem;
                align-items: start;
                border-radius: 1rem;
                border: 1px solid transparent;
                padding: 0.55rem 0.65rem;
                transition: background 150ms ease, border-color 150ms ease;
            }

            .pm-help__list-item:hover {
                border-color: var(--pm-glass-edge);
                background: rgba(255,255,255,0.28);
            }

            .dark .pm-help__list-item:hover {
                background: rgba(255,255,255,0.04);
            }

            .pm-help__top {
                position: fixed;
                right: 1.25rem;
                bottom: 1.25rem;
                z-index: 50;
                display: inline-flex;
                align-items: center;
                gap: 0.4rem;
                border-radius: 999px;
                border: 1px solid var(--pm-glass-border);
                background: var(--pm-glass-bg-strong);
                padding: 0.7rem 0.95rem;
                font-size: 0.8rem;
                font-weight: 650;
                color: var(--pm-ink);
                box-shadow: var(--pm-glass-shadow);
                backdrop-filter: blur(20px);
                transition: opacity 180ms ease, transform 180ms ease;
            }

            .pm-help__top:hover { transform: translateY(-2px); }

            @media (prefers-reduced-motion: reduce) {
                .pm-help__orb,
                .pm-help__section,
                .pm-help__card { animation: none !important; transition: none !important; }
            }
        </style>

        <div class="pm-help__progress" aria-hidden="true">
            <div class="pm-help__progress-bar" :style="`width: ${progress}%`"></div>
        </div>

        <div class="pm-help__atmosphere" aria-hidden="true">
            <div class="pm-help__mesh"></div>
            <div class="pm-help__orb pm-help__orb--a"></div>
            <div class="pm-help__orb pm-help__orb--b"></div>
            <div class="pm-help__orb pm-help__orb--c"></div>
        </div>

        <div class="relative z-[1] space-y-5 md:space-y-6">
            <section class="pm-help__glass pm-help__hero">
                <div class="pm-help__specular"></div>
                <div class="pm-help__ring"></div>

                <div class="relative z-[1] space-y-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <span class="pm-help__chip">
                            <x-heroicon-m-sparkles class="size-3.5 text-cyan-600 dark:text-cyan-300" />
                            Liquid Glass · Guía viva
                        </span>
                        <span class="pm-help__chip normal-case tracking-normal">
                            {{ $sectionCount }} {{ $sectionCount === 1 ? 'sección' : 'secciones' }}
                            @if (filled($search))
                                · filtro activo
                            @endif
                        </span>
                    </div>

                    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.35fr)_minmax(16rem,0.9fr)] lg:items-end">
                        <div class="space-y-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[color:var(--pm-muted)]">
                                Panel de proyectos
                            </p>
                            <h1 class="pm-help__brand">Ayuda</h1>
                            <p class="max-w-xl text-[0.98rem] leading-relaxed text-[color:var(--pm-muted)] md:text-base">
                                Cómo usar el módulo de punta a punta: priorizar, ejecutar en Kanban y cerrar sprints —
                                con ejemplos de Marketing y Sistemas.
                            </p>

                            <div class="flex flex-wrap gap-2 pt-1">
                                <button type="button" class="pm-help__shortcut" @click="setActive('paso-a-paso')">
                                    <x-heroicon-m-play class="size-4 text-cyan-600 dark:text-cyan-300" />
                                    Empezar paso a paso
                                </button>
                                <button type="button" class="pm-help__shortcut" @click="setActive('ejemplo-marketing')">
                                    <x-heroicon-m-megaphone class="size-4 text-teal-600 dark:text-teal-300" />
                                    Ejemplo Marketing
                                </button>
                                <button type="button" class="pm-help__shortcut" @click="setActive('ejemplo-sistemas')">
                                    <x-heroicon-m-cpu-chip class="size-4 text-amber-600 dark:text-amber-300" />
                                    Ejemplo Sistemas
                                </button>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div class="pm-help__search-wrap">
                                <div class="relative">
                                    <x-heroicon-m-magnifying-glass class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                                    <input
                                        type="search"
                                        wire:model.live.debounce.250ms="search"
                                        placeholder="Buscar: sprint, kanban, backlog, puntos…"
                                        class="pm-help__search"
                                        aria-label="Buscar en la ayuda"
                                    />
                                </div>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <div class="pm-help__card !p-3 text-center">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-[color:var(--pm-muted)]">Planear</p>
                                    <p class="mt-1 text-sm font-semibold">Backlog</p>
                                </div>
                                <div class="pm-help__card !p-3 text-center">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-[color:var(--pm-muted)]">Ejecutar</p>
                                    <p class="mt-1 text-sm font-semibold">Kanban</p>
                                </div>
                                <div class="pm-help__card !p-3 text-center">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-[color:var(--pm-muted)]">Medir</p>
                                    <p class="mt-1 text-sm font-semibold">Sprint</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="grid items-start gap-5 xl:grid-cols-[18rem_minmax(0,1fr)] xl:gap-6">
                <aside class="pm-help__glass pm-help__toc xl:sticky xl:top-3 xl:self-start">
                    <div class="pm-help__specular"></div>
                    <div class="pm-help__ring"></div>
                    <div class="relative z-[1]">
                        <div class="mb-1.5 flex items-center justify-between gap-2 px-1.5 pt-0">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-[color:var(--pm-muted)]">
                                Índice
                            </p>
                            <span class="text-[11px] font-semibold text-[color:var(--pm-accent)]" x-text="`${progress}%`"></span>
                        </div>
                        <nav class="pm-help__toc-scroll space-y-1" aria-label="Secciones de ayuda">
                            @forelse ($toc as $item)
                                <button
                                    type="button"
                                    class="pm-help__toc-btn"
                                    :class="{ 'is-active': active === @js($item['id']) }"
                                    @click="setActive(@js($item['id']))"
                                >
                                    <span class="pm-help__toc-dot"></span>
                                    <span>{{ $item['title'] }}</span>
                                </button>
                            @empty
                                <p class="px-2 py-3 text-sm text-[color:var(--pm-muted)]">
                                    Sin resultados para tu búsqueda.
                                </p>
                            @endforelse
                        </nav>
                    </div>
                </aside>

                <div class="space-y-4 md:space-y-5">
                    @forelse ($sections as $index => $section)
                        @php
                            $icon = $sectionIcons[$section['id']] ?? 'heroicon-m-information-circle';
                        @endphp
                        <article
                            id="pm-help-{{ $section['id'] }}"
                            data-section-id="{{ $section['id'] }}"
                            class="pm-help__glass pm-help__section"
                            style="animation-delay: {{ min(10, $index) * 35 }}ms"
                        >
                            <div class="pm-help__specular"></div>
                            <div class="pm-help__ring"></div>

                            <div class="relative z-[1] space-y-5">
                                <header class="space-y-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="pm-help__section-kicker">
                                            <x-dynamic-component :component="$icon" class="size-3.5" />
                                            {{ $section['eyebrow'] }}
                                        </span>
                                        <span class="pm-help__chip normal-case tracking-normal !py-1 !text-[10px]">
                                            {{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }} / {{ str_pad((string) $sectionCount, 2, '0', STR_PAD_LEFT) }}
                                        </span>
                                    </div>
                                    <h2 class="text-[1.55rem] font-semibold tracking-tight md:text-[1.75rem]">
                                        {{ $section['title'] }}
                                    </h2>
                                    <p class="max-w-3xl text-[0.95rem] leading-relaxed text-[color:var(--pm-muted)]">
                                        {{ $section['summary'] }}
                                    </p>
                                </header>

                                @foreach ($section['blocks'] as $block)
                                    @if (($block['type'] ?? null) === 'paragraph')
                                        <p class="text-[0.95rem] leading-relaxed text-[color:var(--pm-muted)]">
                                            {{ $block['body'] }}
                                        </p>
                                    @elseif (($block['type'] ?? null) === 'callout')
                                        <div class="pm-help__callout pl-4">
                                            <p class="text-sm font-semibold">{{ $block['title'] }}</p>
                                            <p class="mt-1 text-sm leading-relaxed text-[color:var(--pm-muted)]">
                                                {{ $block['body'] }}
                                            </p>
                                        </div>
                                    @elseif (($block['type'] ?? null) === 'list')
                                        <div class="space-y-2">
                                            @if (! empty($block['title']))
                                                <p class="text-sm font-semibold">{{ $block['title'] }}</p>
                                            @endif
                                            <ul class="space-y-1">
                                                @foreach ($block['items'] ?? [] as $item)
                                                    <li class="pm-help__list-item text-[0.92rem] leading-relaxed text-[color:var(--pm-muted)]">
                                                        <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-gradient-to-br from-cyan-500 to-teal-500"></span>
                                                        <span>{{ $item }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @elseif (($block['type'] ?? null) === 'cards')
                                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                            @foreach ($block['cards'] ?? [] as $card)
                                                <article class="pm-help__card">
                                                    <h3 class="text-sm font-semibold tracking-tight">{{ $card['title'] }}</h3>
                                                    <p class="mt-1.5 text-sm leading-relaxed text-[color:var(--pm-muted)]">
                                                        {{ $card['body'] }}
                                                    </p>
                                                    @if (! empty($card['meta']))
                                                        <p class="mt-3 text-[10px] font-bold uppercase tracking-[0.14em] text-cyan-700 dark:text-cyan-300">
                                                            {{ $card['meta'] }}
                                                        </p>
                                                    @endif
                                                </article>
                                            @endforeach
                                        </div>
                                    @elseif (($block['type'] ?? null) === 'steps')
                                        <div class="rounded-2xl border border-[color:var(--pm-glass-edge)] bg-white/20 px-3 dark:bg-white/[0.02] sm:px-4">
                                            @foreach ($block['steps'] ?? [] as $stepIndex => $step)
                                                <div class="pm-help__step">
                                                    <span class="pm-help__step-index">{{ $stepIndex + 1 }}</span>
                                                    <div>
                                                        <p class="text-sm font-semibold tracking-tight">{{ $step['title'] }}</p>
                                                        <p class="mt-1 text-sm leading-relaxed text-[color:var(--pm-muted)]">
                                                            {{ $step['body'] }}
                                                        </p>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </article>
                    @empty
                        <div class="pm-help__glass rounded-[1.85rem] px-6 py-14 text-center">
                            <div class="pm-help__specular"></div>
                            <div class="relative z-[1] mx-auto max-w-md space-y-2">
                                <div class="mx-auto mb-3 grid size-12 place-items-center rounded-2xl border border-[color:var(--pm-glass-border)] bg-[color:var(--pm-glass-bg-strong)]">
                                    <x-heroicon-m-magnifying-glass class="size-5 text-[color:var(--pm-muted)]" />
                                </div>
                                <p class="text-base font-semibold">Sin coincidencias</p>
                                <p class="text-sm text-[color:var(--pm-muted)]">Prueba con “sprint”, “kanban”, “marketing” o “story points”.</p>
                                <button
                                    type="button"
                                    wire:click="$set('search', '')"
                                    class="pm-help__shortcut mt-3"
                                >
                                    Limpiar búsqueda
                                </button>
                            </div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <button
            type="button"
            class="pm-help__top"
            x-show="showTop"
            x-transition.opacity.duration.200ms
            @click="window.scrollTo({ top: 0, behavior: 'smooth' })"
        >
            <x-heroicon-m-arrow-up class="size-4" />
            Arriba
        </button>
    </div>
</x-filament-panels::page>
