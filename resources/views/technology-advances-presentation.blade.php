<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#F5F5F7" data-theme-color>
    <meta name="color-scheme" content="light">
    <title>Avances Tecnológicos — INTEGRACORP · tuDrGroup</title>
        <script>
        (function () {
            try {
                var saved = localStorage.getItem('systems_panel_theme');
                var theme = (saved === 'dark' || saved === 'light') ? saved : 'light';
                document.documentElement.setAttribute('data-theme', theme);
                document.documentElement.style.colorScheme = theme;
                var metaColor = document.querySelector('meta[data-theme-color]');
                var metaScheme = document.querySelector('meta[name="color-scheme"]');
                if (metaColor) metaColor.setAttribute('content', theme === 'dark' ? '#050505' : '#F5F5F7');
                if (metaScheme) metaScheme.setAttribute('content', theme);
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>
    <link rel="icon" href="{{ asset('image/imagotipo.png') }}" type="image/png">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700|dm-sans:400,500,600,700" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style>
        :root {
            --white: #FFFFFF;
            --bg-0: #F5F5F7;
            --bg-1: #EEF1F6;
            --ink: #1D1D1F;
            --ink-soft: rgba(29, 29, 31, 0.62);
            --line: rgba(255, 255, 255, 0.78);
            --accent: #007AFF;
            --navy: #14213D;
            --brand: #FCA311;
            --glass-shadow: rgba(20, 33, 61, 0.08);
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Instrument Sans', 'DM Sans', ui-sans-serif, -apple-system, BlinkMacSystemFont, 'SF Pro Display', system-ui, sans-serif;
            overflow: hidden;
            color: var(--ink);
            background:
                radial-gradient(1100px 620px at 8% -8%, rgba(0, 122, 255, 0.14), transparent 55%),
                radial-gradient(900px 520px at 96% 4%, rgba(252, 163, 17, 0.12), transparent 52%),
                radial-gradient(700px 420px at 50% 110%, rgba(88, 86, 214, 0.10), transparent 55%),
                linear-gradient(165deg, #FFFFFF 0%, var(--bg-0) 42%, var(--bg-1) 100%);
            -webkit-font-smoothing: antialiased;
        }

        .bg-mesh {
            background-image:
                linear-gradient(rgba(20, 33, 61, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(20, 33, 61, 0.03) 1px, transparent 1px);
            background-size: 44px 44px;
            mask-image: radial-gradient(ellipse 80% 70% at 50% 40%, black 18%, transparent 75%);
        }

        .liquid-glass {
            position: relative;
            isolation: isolate;
            border-radius: 1.35rem;
            border: 1px solid var(--line);
            background:
                linear-gradient(
                    155deg,
                    rgba(255, 255, 255, 0.82) 0%,
                    rgba(255, 255, 255, 0.48) 48%,
                    rgba(238, 241, 246, 0.42) 100%
                );
            backdrop-filter: blur(30px) saturate(185%);
            -webkit-backdrop-filter: blur(30px) saturate(185%);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.92),
                inset 0 -1px 0 rgba(20, 33, 61, 0.035),
                0 0 0 1px rgba(255, 255, 255, 0.32),
                0 14px 42px var(--glass-shadow),
                0 2px 10px rgba(0, 0, 0, 0.03);
            overflow: hidden;
        }

        .liquid-glass::before {
            content: '';
            pointer-events: none;
            position: absolute;
            inset: 0;
            border-radius: inherit;
            background: linear-gradient(118deg, rgba(255, 255, 255, 0.58) 0%, transparent 48%);
            opacity: 0.75;
            z-index: 0;
        }

        .liquid-glass > * { position: relative; z-index: 1; }

        .liquid-glass--accent {
            border-color: color-mix(in srgb, var(--accent) 32%, white);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.92),
                0 0 0 1px color-mix(in srgb, var(--accent) 16%, transparent),
                0 14px 36px color-mix(in srgb, var(--accent) 18%, transparent);
        }

        .liquid-glass--interactive {
            cursor: pointer;
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
        }

        .liquid-glass--interactive:hover,
        .liquid-glass--interactive:focus-visible {
            transform: translateY(-2px) scale(1.01);
            border-color: color-mix(in srgb, var(--accent) 42%, white);
        }

        .liquid-glass--interactive.is-active {
            border-color: var(--accent);
            background:
                linear-gradient(
                    155deg,
                    rgba(255, 255, 255, 0.9) 0%,
                    color-mix(in srgb, var(--accent) 12%, white) 100%
                );
        }

        #slides-container {
            position: fixed;
            top: 4.25rem;
            bottom: 6.5rem;
            left: 0;
            right: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding-inline: 1rem;
            max-width: 76rem;
            margin-inline: auto;
            width: 100%;
        }

        @media (min-width: 640px) {
            #slides-container { padding-inline: 1.5rem; }
        }

        #slides-viewport {
            position: relative;
            width: 100%;
            height: 100%;
        }

        .slide {
            opacity: 0;
            transform: translateX(36px) scale(0.985);
            transition: opacity 0.45s cubic-bezier(0.4, 0, 0.2, 1),
                        transform 0.45s cubic-bezier(0.4, 0, 0.2, 1);
            pointer-events: none;
            position: absolute;
            inset: 0;
            width: 100%;
            z-index: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: auto;
            padding-block: 0.5rem;
        }

        .slide__inner { width: 100%; max-height: 100%; }

        .slide.active {
            opacity: 1;
            transform: translateX(0) scale(1);
            pointer-events: auto;
            z-index: 2;
        }

        .slide.exit-left {
            opacity: 0;
            transform: translateX(-36px) scale(0.985);
            z-index: 1;
        }

        .slide.exit-right {
            opacity: 0;
            transform: translateX(36px) scale(0.985);
            z-index: 1;
        }

        .reveal-item {
            opacity: 0;
            transform: translateY(14px);
            transition: opacity 0.4s ease, transform 0.4s ease;
        }

        .slide.active .reveal-item {
            opacity: 1;
            transform: translateY(0);
        }

        .slide.active .reveal-item:nth-child(1) { transition-delay: 0.06s; }
        .slide.active .reveal-item:nth-child(2) { transition-delay: 0.12s; }
        .slide.active .reveal-item:nth-child(3) { transition-delay: 0.18s; }
        .slide.active .reveal-item:nth-child(4) { transition-delay: 0.24s; }
        .slide.active .reveal-item:nth-child(5) { transition-delay: 0.30s; }
        .slide.active .reveal-item:nth-child(6) { transition-delay: 0.36s; }

        .progress-fill {
            transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: linear-gradient(90deg, var(--navy), var(--accent), var(--brand));
        }

        .dot {
            transition: all 0.25s ease;
            background: rgba(20, 33, 61, 0.16);
        }

        .dot.active {
            transform: scale(1.35);
            background: var(--accent);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 28%, transparent);
        }

        .btn-glass {
            border-radius: 0.9rem;
            border: 1px solid rgba(255, 255, 255, 0.72);
            background: rgba(255, 255, 255, 0.58);
            backdrop-filter: blur(16px) saturate(160%);
            -webkit-backdrop-filter: blur(16px) saturate(160%);
            color: var(--navy);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.85), 0 4px 14px rgba(20, 33, 61, 0.06);
            transition: transform 0.2s ease, background 0.2s ease;
        }

        .btn-glass:hover:not(:disabled) {
            background: rgba(255, 255, 255, 0.84);
            transform: translateY(-1px);
        }

        .btn-glass:disabled {
            opacity: 0.35;
            cursor: not-allowed;
        }

        .btn-accent {
            border-radius: 0.9rem;
            border: 1px solid color-mix(in srgb, var(--accent) 45%, white);
            background: linear-gradient(145deg, #4DA3FF 0%, var(--accent) 55%, #0062CC 100%);
            color: white;
            font-weight: 600;
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.35),
                0 8px 20px rgba(0, 122, 255, 0.28);
            transition: transform 0.2s ease, filter 0.2s ease;
        }

        .btn-accent:hover:not(:disabled) {
            filter: brightness(1.05);
            transform: translateY(-1px);
        }

        .btn-accent:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        .header-glass, .footer-glass {
            background: rgba(255, 255, 255, 0.66);
            backdrop-filter: blur(24px) saturate(175%);
            -webkit-backdrop-filter: blur(24px) saturate(175%);
            border-color: rgba(255, 255, 255, 0.78);
            box-shadow: 0 1px 0 rgba(255, 255, 255, 0.82), 0 8px 24px rgba(20, 33, 61, 0.05);
        }

        .logo-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
            width: 11.5rem;
            height: 2.75rem;
            padding: 0.35rem 0.75rem;
            border-radius: 9999px;
            border: 1px solid rgba(255, 255, 255, 0.78);
            background: rgba(255, 255, 255, 0.58);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9), 0 4px 12px rgba(20, 33, 61, 0.05);
            flex-shrink: 0;
        }

        .logo-chip img {
            height: 1.55rem;
            width: auto;
            max-width: 7.5rem;
            object-fit: contain;
        }

        .logo-chip--mark img {
            height: 1.55rem;
            width: 1.55rem;
            max-width: 1.55rem;
        }

        .logo-chip span {
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            white-space: nowrap;
            color: var(--navy);
        }

        .pillar-detail, .value-detail, .suite-detail, .future-detail { display: none; }
        .pillar-card.is-active .pillar-detail,
        .value-card.is-active .value-detail,
        .suite-card.is-active .suite-detail,
        .future-card.is-active .future-detail { display: block; }

        .speaker-note {
            max-height: 0;
            opacity: 0;
            overflow: hidden;
            transition: max-height 0.35s ease, opacity 0.35s ease, margin 0.35s ease;
            margin-top: 0;
        }

        .speaker-note.is-open {
            max-height: 12rem;
            opacity: 1;
            margin-top: 0.75rem;
        }

        .overview-panel {
            position: fixed;
            inset: 0;
            z-index: 80;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
            background: rgba(238, 241, 246, 0.58);
            backdrop-filter: blur(18px) saturate(160%);
            -webkit-backdrop-filter: blur(18px) saturate(160%);
        }

        .overview-panel.is-open { display: flex; }

        @keyframes float-y {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }

        @keyframes pulse-soft {
            0%, 100% { opacity: 0.45; transform: scale(1); }
            50% { opacity: 0.85; transform: scale(1.04); }
        }

        @keyframes flow-dash {
            to { stroke-dashoffset: -24; }
        }

        .float-y { animation: float-y 4.8s ease-in-out infinite; }
        .pulse-soft { animation: pulse-soft 2.8s ease-in-out infinite; }

        .infra-node {
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
            cursor: pointer;
        }

        .infra-node:hover,
        .infra-node.is-active {
            transform: translateY(-3px);
            border-color: color-mix(in srgb, var(--accent) 45%, white);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.95),
                0 16px 36px rgba(0, 122, 255, 0.16);
        }

        .infra-node--database:hover,
        .infra-node--database.is-active {
            border-color: color-mix(in srgb, #34C759 45%, white);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.95),
                0 16px 36px rgba(52, 199, 89, 0.18);
        }

        .infra-flow {
            stroke-dasharray: 5 7;
            animation: flow-dash 1.15s linear infinite;
        }

        .infra-hierarchy {
            display: grid;
            gap: 0.55rem;
            position: relative;
        }

        .infra-layer {
            display: grid;
            gap: 0.65rem;
            position: relative;
            z-index: 1;
        }

        .infra-layer--apps {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .infra-layer--api,
        .infra-layer--database {
            grid-template-columns: minmax(0, 1fr);
            justify-items: center;
        }

        .infra-layer--api .infra-node,
        .infra-layer--database .infra-node {
            width: min(100%, 22rem);
        }

        .infra-icon {
            width: 2.35rem;
            height: 2.35rem;
            border-radius: 0.8rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border: 1px solid rgba(255, 255, 255, 0.85);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9), 0 4px 12px rgba(20, 33, 61, 0.06);
        }

        .infra-icon--server {
            background: linear-gradient(160deg, rgba(0, 122, 255, 0.16), rgba(0, 122, 255, 0.05));
            color: #007AFF;
        }

        .infra-icon--api {
            background: linear-gradient(160deg, rgba(88, 86, 214, 0.18), rgba(88, 86, 214, 0.06));
            color: #5856D6;
        }

        .infra-icon--database {
            background: linear-gradient(160deg, rgba(52, 199, 89, 0.18), rgba(52, 199, 89, 0.06));
            color: #34C759;
        }

        .infra-connector {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 1.35rem;
            position: relative;
            z-index: 0;
        }

        .infra-connector svg {
            width: 100%;
            height: 100%;
            overflow: visible;
        }

        .infra-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: center;
        }

        .infra-legend-item {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            border-radius: 9999px;
            border: 1px solid rgba(255, 255, 255, 0.8);
            background: rgba(255, 255, 255, 0.62);
            padding: 0.3rem 0.7rem;
            font-size: 10px;
            font-weight: 600;
            color: var(--navy);
        }

        @media (max-width: 640px) {
            .infra-layer--apps {
                grid-template-columns: 1fr;
            }

            .infra-layer--api .infra-node,
            .infra-layer--database .infra-node {
                width: 100%;
            }
        }

        .quote-mark {
            font-size: clamp(3rem, 8vw, 5.5rem);
            line-height: 0.8;
            color: color-mix(in srgb, var(--accent) 35%, white);
            font-weight: 700;
        }

        .kb-hint {
            font-size: 10px;
            letter-spacing: 0.02em;
            color: rgba(20, 33, 61, 0.42);
        }

        @media (max-height: 740px) {
            #slides-container {
                top: 3.75rem;
                bottom: 5.75rem;
            }
        }
    </style>
    @include('partials.presentation-app-chrome-styles')
    @include('partials.presentation-theme-styles')
</head>
<body class="min-h-screen select-none">

    <div class="fixed inset-0 bg-mesh pointer-events-none" aria-hidden="true"></div>
    <div class="fixed w-[28rem] h-[28rem] rounded-full pointer-events-none blur-3xl opacity-35 transition-colors duration-700"
         id="bg-glow"
         style="top: 8%; right: 6%; background: var(--accent);"
         aria-hidden="true"></div>
    <div class="fixed w-72 h-72 rounded-full pointer-events-none blur-3xl opacity-20"
         style="bottom: 8%; left: 4%; background: var(--brand);"
         aria-hidden="true"></div>

    @include('partials.presentation-app-header', [
        'brandLabel' => 'tuDrGroup',
        'slideCount' => count($slides),
        'access' => $access ?? null,
    ])

    <div class="fixed top-[calc(3.35rem+1px)] md:top-[57px] inset-x-0 z-50 h-1 bg-black/5">
        <div id="progress-bar" class="progress-fill h-full" style="width: {{ round(100 / max(count($slides), 1)) }}%"></div>
    </div>

    <main id="slides-container">
        <div id="slides-viewport">
            @foreach ($slides as $index => $slide)
                <article
                    class="slide {{ $index === 0 ? 'active' : '' }}"
                    data-index="{{ $index }}"
                    data-type="{{ $slide['type'] }}"
                    data-color="{{ $slide['color'] }}"
                    data-id="{{ $slide['id'] }}"
                >
                    <div class="slide__inner">
                        @if ($slide['type'] === 'cover')
                            <div class="grid lg:grid-cols-[1.15fr_0.85fr] gap-8 lg:gap-10 items-center">
                                <div class="flex flex-col gap-5">
                                    <div class="flex flex-wrap gap-2">
                                        <span class="reveal-item text-[10px] font-semibold uppercase tracking-[0.18em] px-2.5 py-1 rounded-full liquid-glass text-[var(--accent)]">{{ $slide['data']['eyebrow'] ?? $slide['module'] }}</span>
                                        @foreach ($slide['tags'] as $tag)
                                            <span class="reveal-item text-[10px] px-2 py-1 rounded-full bg-white/55 border border-white/70 text-[var(--navy)]/55">{{ $tag }}</span>
                                        @endforeach
                                    </div>
                                    <h1 class="reveal-item text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight text-[var(--navy)] leading-[1.08]">
                                        {{ $slide['title'] }}
                                    </h1>
                                    <p class="reveal-item text-base sm:text-lg text-[var(--ink-soft)] leading-relaxed max-w-xl">
                                        {{ $slide['subtitle'] }}
                                    </p>
                                    <ul class="grid gap-2.5">
                                        @foreach ($slide['highlights'] as $highlight)
                                            <li class="reveal-item liquid-glass px-4 py-3 text-sm text-[var(--navy)]/80 flex items-start gap-3">
                                                <span class="mt-0.5 shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold bg-[var(--accent)]/15 text-[var(--accent)]">✓</span>
                                                {{ $highlight }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                                <div class="reveal-item flex justify-center">
                                    <div class="liquid-glass liquid-glass--accent p-8 sm:p-10 float-y w-full max-w-md">
                                        <div class="text-[10px] font-semibold uppercase tracking-[0.2em] text-[var(--accent)] mb-3">{{ $slide['data']['badge'] ?? 'Presentación' }}</div>
                                        <div class="text-2xl font-bold text-[var(--navy)] mb-2">Departamento de Tecnología</div>
                                        <p class="text-sm text-[var(--ink-soft)] leading-relaxed mb-6">
                                            Robustez · Interactividad · Escalabilidad. El trabajo que sostiene Operaciones, Negocios, Marketing y la experiencia del paciente.
                                        </p>
                                        <div class="grid grid-cols-3 gap-2">
                                            @foreach (['Ops', 'API', 'CX'] as $chip)
                                                <div class="rounded-xl bg-white/70 border border-white/80 px-2 py-3 text-center text-xs font-semibold text-[var(--navy)]">{{ $chip }}</div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>

                        @elseif ($slide['type'] === 'pillars')
                            <div class="flex flex-col gap-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="reveal-item text-[10px] font-semibold uppercase tracking-[0.18em] px-2.5 py-1 rounded-full liquid-glass" style="color: {{ $slide['color'] }}">{{ $slide['module'] }}</span>
                                    @foreach ($slide['tags'] as $tag)
                                        <span class="reveal-item text-[10px] px-2 py-1 rounded-full bg-white/55 border border-white/70 text-[var(--navy)]/55">{{ $tag }}</span>
                                    @endforeach
                                </div>
                                <h2 class="reveal-item text-2xl sm:text-3xl lg:text-4xl font-bold tracking-tight text-[var(--navy)]">{{ $slide['title'] }}</h2>
                                <p class="reveal-item text-sm sm:text-base text-[var(--ink-soft)] max-w-3xl">{{ $slide['subtitle'] }}</p>
                                <div class="reveal-item liquid-glass liquid-glass--accent px-4 py-3 text-sm font-medium text-[var(--navy)]">
                                    {{ $slide['data']['robustness'] ?? '' }}
                                </div>
                                <div class="grid sm:grid-cols-2 gap-3">
                                    @foreach ($slide['data']['pillars'] ?? [] as $i => $pillar)
                                        <button type="button" class="pillar-card liquid-glass liquid-glass--interactive text-left px-4 py-4 {{ $i === 0 ? 'is-active' : '' }}">
                                            <div class="text-xs font-semibold uppercase tracking-wide mb-1" style="color: {{ $slide['color'] }}">0{{ $i + 1 }}</div>
                                            <div class="font-semibold text-[var(--navy)]">{{ $pillar['title'] }}</div>
                                            <p class="pillar-detail mt-2 text-sm text-[var(--ink-soft)] leading-relaxed">{{ $pillar['detail'] }}</p>
                                        </button>
                                    @endforeach
                                </div>
                                @if (! empty($slide['data']['company_help']))
                                    <div class="reveal-item liquid-glass px-4 py-4">
                                        <div class="text-xs font-semibold uppercase tracking-[0.14em] text-[var(--accent)] mb-2">Cómo ayuda a la empresa</div>
                                        <ul class="grid sm:grid-cols-2 gap-2">
                                            @foreach ($slide['data']['company_help'] as $help)
                                                <li class="text-sm text-[var(--navy)]/80 flex gap-2"><span class="text-[var(--accent)]">→</span>{{ $help }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                                @if ($slide['speaker_note'])
                                    <button type="button" class="reveal-item text-left text-xs text-[var(--accent)] font-medium" data-toggle-note>Nota del presentador ▾</button>
                                    <div class="speaker-note liquid-glass px-4 py-3 text-sm text-[var(--ink-soft)]">{{ $slide['speaker_note'] }}</div>
                                @endif
                            </div>

                        @elseif ($slide['type'] === 'preview')
                            <div class="flex flex-col gap-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="reveal-item text-[10px] font-semibold uppercase tracking-[0.18em] px-2.5 py-1 rounded-full liquid-glass" style="color: {{ $slide['color'] }}">{{ $slide['module'] }}</span>
                                    <span class="reveal-item text-[10px] font-semibold px-2.5 py-1 rounded-full bg-[color-mix(in_srgb,var(--accent)_12%,white)] text-[var(--accent)] border border-[color-mix(in_srgb,var(--accent)_25%,white)]">{{ $slide['data']['status'] ?? 'Preview' }}</span>
                                </div>
                                <h2 class="reveal-item text-2xl sm:text-3xl lg:text-4xl font-bold tracking-tight text-[var(--navy)]">{{ $slide['title'] }}</h2>
                                <p class="reveal-item text-sm sm:text-base text-[var(--ink-soft)] max-w-3xl">{{ $slide['subtitle'] }}</p>
                                <div class="grid lg:grid-cols-[1fr_1.1fr] gap-4">
                                    <div class="reveal-item liquid-glass p-5">
                                        <div class="text-xs font-semibold uppercase tracking-[0.14em] text-[var(--accent)] mb-3">Módulos en construcción</div>
                                        <div class="grid grid-cols-2 gap-2">
                                            @foreach ($slide['data']['modules'] ?? [] as $module)
                                                <div class="rounded-xl bg-white/70 border border-white/80 px-3 py-3 text-sm font-medium text-[var(--navy)]">{{ $module }}</div>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="reveal-item liquid-glass liquid-glass--accent p-5 flex flex-col gap-3">
                                        <div class="text-xs font-semibold uppercase tracking-[0.14em]" style="color: {{ $slide['color'] }}">Promesa</div>
                                        <p class="text-base font-semibold text-[var(--navy)] leading-relaxed">{{ $slide['data']['promise'] ?? '' }}</p>
                                        <ul class="grid gap-2 mt-1">
                                            @foreach ($slide['highlights'] as $highlight)
                                                <li class="text-sm text-[var(--ink-soft)] flex gap-2"><span style="color: {{ $slide['color'] }}">●</span>{{ $highlight }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </div>

                        @elseif ($slide['type'] === 'value')
                            <div class="flex flex-col gap-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="reveal-item text-[10px] font-semibold uppercase tracking-[0.18em] px-2.5 py-1 rounded-full liquid-glass" style="color: {{ $slide['color'] }}">{{ $slide['module'] }}</span>
                                    @foreach ($slide['tags'] as $tag)
                                        <span class="reveal-item text-[10px] px-2 py-1 rounded-full bg-white/55 border border-white/70 text-[var(--navy)]/55">{{ $tag }}</span>
                                    @endforeach
                                </div>
                                <h2 class="reveal-item text-2xl sm:text-3xl lg:text-4xl font-bold tracking-tight text-[var(--navy)]">{{ $slide['title'] }}</h2>
                                <p class="reveal-item text-sm sm:text-base text-[var(--ink-soft)] max-w-3xl">{{ $slide['subtitle'] }}</p>
                                <div class="reveal-item liquid-glass liquid-glass--accent px-4 py-3 text-sm font-medium text-[var(--navy)]">
                                    {{ $slide['data']['robustness'] ?? '' }}
                                </div>
                                <div class="grid sm:grid-cols-2 gap-3">
                                    <button type="button" class="value-card liquid-glass liquid-glass--interactive text-left px-4 py-4 is-active">
                                        <div class="text-xs font-semibold uppercase tracking-wide mb-1" style="color: {{ $slide['color'] }}">Para la empresa</div>
                                        <div class="font-semibold text-[var(--navy)] mb-2">Valor de negocio</div>
                                        <ul class="value-detail grid gap-2">
                                            @foreach ($slide['data']['for_company'] ?? [] as $item)
                                                <li class="text-sm text-[var(--ink-soft)] flex gap-2"><span style="color: {{ $slide['color'] }}">→</span>{{ $item }}</li>
                                            @endforeach
                                        </ul>
                                    </button>
                                    <button type="button" class="value-card liquid-glass liquid-glass--interactive text-left px-4 py-4">
                                        <div class="text-xs font-semibold uppercase tracking-wide mb-1" style="color: {{ $slide['color'] }}">Para analistas de operaciones</div>
                                        <div class="font-semibold text-[var(--navy)] mb-2">Impacto en el equipo</div>
                                        <ul class="value-detail grid gap-2">
                                            @foreach ($slide['data']['for_analysts'] ?? [] as $item)
                                                <li class="text-sm text-[var(--ink-soft)] flex gap-2"><span style="color: {{ $slide['color'] }}">→</span>{{ $item }}</li>
                                            @endforeach
                                        </ul>
                                    </button>
                                </div>
                            </div>

                        @elseif ($slide['type'] === 'tests')
                            <div class="flex flex-col gap-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="reveal-item text-[10px] font-semibold uppercase tracking-[0.18em] px-2.5 py-1 rounded-full liquid-glass" style="color: {{ $slide['color'] }}">{{ $slide['module'] }}</span>
                                    @foreach ($slide['tags'] as $tag)
                                        <span class="reveal-item text-[10px] px-2 py-1 rounded-full bg-white/55 border border-white/70 text-[var(--navy)]/55">{{ $tag }}</span>
                                    @endforeach
                                </div>
                                <h2 class="reveal-item text-2xl sm:text-3xl lg:text-4xl font-bold tracking-tight text-[var(--navy)]">{{ $slide['title'] }}</h2>
                                <p class="reveal-item text-sm sm:text-base text-[var(--ink-soft)] max-w-3xl">{{ $slide['subtitle'] }}</p>
                                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                                    @foreach ($slide['data']['suites'] ?? [] as $i => $suite)
                                        <button type="button" class="suite-card liquid-glass liquid-glass--interactive text-left px-4 py-4 {{ $i === 0 ? 'is-active' : '' }}">
                                            <div class="text-xs font-semibold uppercase tracking-wide mb-1" style="color: {{ $slide['color'] }}">Suite 0{{ $i + 1 }}</div>
                                            <div class="font-semibold text-[var(--navy)] text-sm">{{ $suite['name'] }}</div>
                                            <ul class="suite-detail mt-2 grid gap-1.5">
                                                @foreach ($suite['items'] as $item)
                                                    <li class="text-xs text-[var(--ink-soft)]">• {{ $item }}</li>
                                                @endforeach
                                            </ul>
                                        </button>
                                    @endforeach
                                </div>
                                <div class="reveal-item liquid-glass px-4 py-3 text-sm text-[var(--navy)]/85">{{ $slide['data']['message'] ?? '' }}</div>
                            </div>

                        @elseif ($slide['type'] === 'api')
                            <div class="flex flex-col gap-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="reveal-item text-[10px] font-semibold uppercase tracking-[0.18em] px-2.5 py-1 rounded-full liquid-glass" style="color: {{ $slide['color'] }}">{{ $slide['module'] }}</span>
                                    @foreach ($slide['tags'] as $tag)
                                        <span class="reveal-item text-[10px] px-2 py-1 rounded-full bg-white/55 border border-white/70 text-[var(--navy)]/55">{{ $tag }}</span>
                                    @endforeach
                                </div>
                                <h2 class="reveal-item text-2xl sm:text-3xl lg:text-4xl font-bold tracking-tight text-[var(--navy)]">{{ $slide['title'] }}</h2>
                                <p class="reveal-item text-sm sm:text-base text-[var(--ink-soft)] max-w-3xl">{{ $slide['subtitle'] }}</p>
                                <div class="grid sm:grid-cols-2 gap-3">
                                    @foreach ($slide['data']['improvements'] ?? [] as $i => $item)
                                        <div class="reveal-item liquid-glass px-4 py-4">
                                            <div class="text-xs font-semibold uppercase tracking-wide mb-1" style="color: {{ $slide['color'] }}">0{{ $i + 1 }}</div>
                                            <div class="font-semibold text-[var(--navy)] mb-1">{{ $item['title'] }}</div>
                                            <p class="text-sm text-[var(--ink-soft)] leading-relaxed">{{ $item['detail'] }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                        @elseif ($slide['type'] === 'feature')
                            <div class="flex flex-col gap-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="reveal-item text-[10px] font-semibold uppercase tracking-[0.18em] px-2.5 py-1 rounded-full liquid-glass" style="color: {{ $slide['color'] }}">{{ $slide['module'] }}</span>
                                    @foreach ($slide['tags'] as $tag)
                                        <span class="reveal-item text-[10px] px-2 py-1 rounded-full bg-white/55 border border-white/70 text-[var(--navy)]/55">{{ $tag }}</span>
                                    @endforeach
                                </div>
                                <h2 class="reveal-item text-2xl sm:text-3xl lg:text-4xl font-bold tracking-tight text-[var(--navy)]">{{ $slide['title'] }}</h2>
                                <p class="reveal-item text-sm sm:text-base text-[var(--ink-soft)] max-w-3xl">{{ $slide['subtitle'] }}</p>
                                <div class="grid sm:grid-cols-2 gap-3">
                                    @foreach ($slide['data']['upgrades'] ?? [] as $upgrade)
                                        <div class="reveal-item liquid-glass px-4 py-4 flex gap-3 items-start">
                                            <span class="mt-0.5 shrink-0 w-6 h-6 rounded-full flex items-center justify-center text-[11px] font-bold text-white" style="background: {{ $slide['color'] }}">✓</span>
                                            <p class="text-sm text-[var(--navy)]/85 leading-relaxed">{{ $upgrade }}</p>
                                        </div>
                                    @endforeach
                                </div>
                                @if ($slide['speaker_note'])
                                    <button type="button" class="reveal-item text-left text-xs text-[var(--accent)] font-medium" data-toggle-note>Nota del presentador ▾</button>
                                    <div class="speaker-note liquid-glass px-4 py-3 text-sm text-[var(--ink-soft)]">{{ $slide['speaker_note'] }}</div>
                                @endif
                            </div>

                        @elseif ($slide['type'] === 'infra')
                            <div class="flex flex-col gap-3 sm:gap-3.5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="reveal-item text-[10px] font-semibold uppercase tracking-[0.18em] px-2.5 py-1 rounded-full liquid-glass text-[var(--accent)]">{{ $slide['module'] }}</span>
                                    @foreach ($slide['tags'] as $tag)
                                        <span class="reveal-item text-[10px] px-2 py-1 rounded-full bg-white/55 border border-white/70 text-[var(--navy)]/55">{{ $tag }}</span>
                                    @endforeach
                                </div>
                                <h2 class="reveal-item text-2xl sm:text-3xl font-bold tracking-tight text-[var(--navy)]">{{ $slide['title'] }}</h2>
                                <p class="reveal-item text-sm text-[var(--ink-soft)] max-w-3xl">{{ $slide['subtitle'] }}</p>

                                <div class="reveal-item liquid-glass p-3.5 sm:p-5">
                                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-3.5">
                                        <div>
                                            <div class="text-[10px] font-semibold uppercase tracking-[0.16em] text-[var(--accent)]">Producción</div>
                                            <div class="text-sm font-semibold text-[var(--navy)]">Jerarquía · Apps → API → Base de datos</div>
                                        </div>
                                        <div id="infra-detail" class="hidden sm:block text-xs text-[var(--ink-soft)] max-w-sm text-right">
                                            Toca un nodo para ver su rol.
                                        </div>
                                    </div>

                                    <div class="infra-legend mb-3.5">
                                        <span class="infra-legend-item">
                                            <span class="infra-icon infra-icon--server !w-5 !h-5 !rounded-md">
                                                <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4" width="18" height="5" rx="1.2"/><rect x="3" y="10" width="18" height="5" rx="1.2"/><rect x="3" y="16" width="18" height="4" rx="1.2"/><circle cx="7" cy="6.5" r="0.8" fill="currentColor"/><circle cx="7" cy="12.5" r="0.8" fill="currentColor"/></svg>
                                            </span>
                                            Servidores app
                                        </span>
                                        <span class="infra-legend-item">
                                            <span class="infra-icon infra-icon--api !w-5 !h-5 !rounded-md">
                                                <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M8 8h2.5a2.5 2.5 0 0 1 0 5H8V8z"/><path d="M14 11h2.2a2.2 2.2 0 1 0 0-4.4H14"/><path d="M8 13v3.2A2.8 2.8 0 0 0 10.8 19"/><path d="M14 11v5.2A2.8 2.8 0 0 1 11.2 19"/><circle cx="18.5" cy="8.8" r="1.1" fill="currentColor" stroke="none"/></svg>
                                            </span>
                                            API
                                        </span>
                                        <span class="infra-legend-item">
                                            <span class="infra-icon infra-icon--database !w-5 !h-5 !rounded-md">
                                                <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><ellipse cx="12" cy="6" rx="7" ry="3"/><path d="M5 6v6c0 1.7 3.1 3 7 3s7-1.3 7-3V6"/><path d="M5 12v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6"/></svg>
                                            </span>
                                            Base de datos
                                        </span>
                                    </div>

                                    <div class="infra-hierarchy">
                                        {{-- Fila 1: Apps --}}
                                        <div class="infra-layer infra-layer--apps" data-infra-layer="apps">
                                            @foreach (($slide['data']['layers'][0]['nodes'] ?? []) as $node)
                                                <button
                                                    type="button"
                                                    class="infra-node liquid-glass text-left px-3 py-3"
                                                    data-infra-id="{{ $node['id'] }}"
                                                    data-infra-role="{{ $node['role'] }}"
                                                    data-infra-detail="{{ $node['detail'] }}"
                                                >
                                                    <div class="flex items-start gap-2.5">
                                                        <span class="infra-icon infra-icon--server" aria-hidden="true">
                                                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="5" rx="1.2"/><rect x="3" y="10" width="18" height="5" rx="1.2"/><rect x="3" y="16" width="18" height="4" rx="1.2"/><circle cx="7" cy="6.5" r="0.85" fill="currentColor"/><circle cx="7" cy="12.5" r="0.85" fill="currentColor"/><circle cx="7" cy="18" r="0.75" fill="currentColor"/></svg>
                                                        </span>
                                                        <div class="min-w-0">
                                                            <div class="flex items-center gap-1.5 mb-1">
                                                                <span class="w-1.5 h-1.5 rounded-full pulse-soft bg-[#007AFF]"></span>
                                                                <span class="text-[9px] font-semibold uppercase tracking-wide text-[var(--accent)]">PROD · APP</span>
                                                            </div>
                                                            <div class="text-[11px] sm:text-xs font-bold text-[var(--navy)] leading-snug break-all">{{ $node['id'] }}</div>
                                                            <div class="mt-1 text-[11px] text-[var(--ink-soft)]">{{ $node['role'] }}</div>
                                                        </div>
                                                    </div>
                                                </button>
                                            @endforeach
                                        </div>

                                        <div class="infra-connector" aria-hidden="true">
                                            <svg viewBox="0 0 1000 28" preserveAspectRatio="none">
                                                <path class="infra-flow" d="M167 2 C 167 14, 500 8, 500 26" fill="none" stroke="rgba(0,122,255,0.38)" stroke-width="2"/>
                                                <path class="infra-flow" d="M500 2 C 500 10, 500 16, 500 26" fill="none" stroke="rgba(88,86,214,0.45)" stroke-width="2" style="animation-delay:.15s"/>
                                                <path class="infra-flow" d="M833 2 C 833 14, 500 8, 500 26" fill="none" stroke="rgba(0,122,255,0.38)" stroke-width="2" style="animation-delay:.3s"/>
                                            </svg>
                                        </div>

                                        {{-- Fila 2: API --}}
                                        <div class="infra-layer infra-layer--api" data-infra-layer="api">
                                            @foreach (($slide['data']['layers'][1]['nodes'] ?? []) as $node)
                                                <button
                                                    type="button"
                                                    class="infra-node liquid-glass liquid-glass--accent text-left px-4 py-3.5"
                                                    data-infra-id="{{ $node['id'] }}"
                                                    data-infra-role="{{ $node['role'] }}"
                                                    data-infra-detail="{{ $node['detail'] }}"
                                                >
                                                    <div class="flex items-center gap-3">
                                                        <span class="infra-icon infra-icon--api !w-11 !h-11 !rounded-xl" aria-hidden="true">
                                                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M7.5 7.2h3.1a3 3 0 0 1 0 6H7.5V7.2z"/><path d="M14 11h2.4a2.4 2.4 0 1 0 0-4.8H14"/><path d="M7.5 13.2v3.4A3.1 3.1 0 0 0 10.6 19.7"/><path d="M14 11v5.6A3.1 3.1 0 0 1 10.9 19.7"/><circle cx="18.8" cy="8.6" r="1.2" fill="currentColor" stroke="none"/></svg>
                                                        </span>
                                                        <div class="min-w-0">
                                                            <div class="flex items-center gap-1.5 mb-1">
                                                                <span class="w-1.5 h-1.5 rounded-full pulse-soft bg-[#5856D6]"></span>
                                                                <span class="text-[9px] font-semibold uppercase tracking-wide text-[#5856D6]">PROD · API</span>
                                                            </div>
                                                            <div class="text-xs sm:text-sm font-bold text-[var(--navy)] leading-snug break-all">{{ $node['id'] }}</div>
                                                            <div class="mt-0.5 text-[11px] text-[var(--ink-soft)]">{{ $node['role'] }} · puente entre apps y datos</div>
                                                        </div>
                                                    </div>
                                                </button>
                                            @endforeach
                                        </div>

                                        <div class="infra-connector" aria-hidden="true">
                                            <svg viewBox="0 0 1000 28" preserveAspectRatio="none">
                                                <path class="infra-flow" d="M500 2 C 500 10, 500 18, 500 26" fill="none" stroke="rgba(52,199,89,0.5)" stroke-width="2.2"/>
                                                <circle cx="500" cy="26" r="2.5" fill="rgba(52,199,89,0.75)"/>
                                            </svg>
                                        </div>

                                        {{-- Fila 3: BD --}}
                                        <div class="infra-layer infra-layer--database" data-infra-layer="database">
                                            @foreach (($slide['data']['layers'][2]['nodes'] ?? []) as $node)
                                                <button
                                                    type="button"
                                                    class="infra-node infra-node--database liquid-glass text-left px-4 py-3.5"
                                                    style="border-color: color-mix(in srgb, #34C759 35%, white);"
                                                    data-infra-id="{{ $node['id'] }}"
                                                    data-infra-role="{{ $node['role'] }}"
                                                    data-infra-detail="{{ $node['detail'] }}"
                                                >
                                                    <div class="flex items-center gap-3">
                                                        <span class="infra-icon infra-icon--database !w-11 !h-11 !rounded-xl" aria-hidden="true">
                                                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7"><ellipse cx="12" cy="6" rx="7.2" ry="3.1"/><path d="M4.8 6v6.2c0 1.8 3.2 3.2 7.2 3.2s7.2-1.4 7.2-3.2V6"/><path d="M4.8 12.2v5.8c0 1.8 3.2 3.2 7.2 3.2s7.2-1.4 7.2-3.2v-5.8"/></svg>
                                                        </span>
                                                        <div class="min-w-0">
                                                            <div class="flex items-center gap-1.5 mb-1">
                                                                <span class="w-1.5 h-1.5 rounded-full pulse-soft bg-[#34C759]"></span>
                                                                <span class="text-[9px] font-semibold uppercase tracking-wide text-[#34C759]">PROD · BD</span>
                                                            </div>
                                                            <div class="text-xs sm:text-sm font-bold text-[var(--navy)] leading-snug break-all">{{ $node['id'] }}</div>
                                                            <div class="mt-0.5 text-[11px] text-[var(--ink-soft)]">{{ $node['role'] }} · núcleo de persistencia</div>
                                                        </div>
                                                    </div>
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div class="mt-3.5 flex justify-center">
                                        <div class="liquid-glass liquid-glass--accent px-4 py-2 text-[11px] font-semibold text-[var(--navy)] text-center">
                                            Flujo: Apps (fila 1) → API (fila 2) → BD (fila 3)
                                        </div>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    class="reveal-item infra-node liquid-glass text-left px-4 py-3.5 w-full"
                                    data-infra-id="{{ $slide['data']['dev']['id'] ?? '' }}"
                                    data-infra-role="{{ $slide['data']['dev']['role'] ?? '' }}"
                                    data-infra-detail="{{ $slide['data']['dev']['detail'] ?? '' }}"
                                >
                                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                        <div class="flex items-start gap-3">
                                            <span class="infra-icon infra-icon--server !w-10 !h-10 !rounded-xl" style="background: linear-gradient(160deg, rgba(252,163,17,0.2), rgba(252,163,17,0.06)); color: #E8920A;" aria-hidden="true">
                                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="5" rx="1.2"/><rect x="3" y="10" width="18" height="5" rx="1.2"/><rect x="3" y="16" width="18" height="4" rx="1.2"/><circle cx="7" cy="6.5" r="0.85" fill="currentColor"/><circle cx="7" cy="12.5" r="0.85" fill="currentColor"/></svg>
                                            </span>
                                            <div>
                                                <div class="text-[10px] font-semibold uppercase tracking-[0.16em] text-[var(--brand)] mb-1">Desarrollo</div>
                                                <div class="text-sm sm:text-base font-bold text-[var(--navy)]">{{ $slide['data']['dev']['id'] ?? '' }}</div>
                                                <div class="text-sm text-[var(--ink-soft)] mt-1">{{ $slide['data']['dev']['detail'] ?? '' }}</div>
                                            </div>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach (['Ambientes', 'Bases de datos', 'Integraciones'] as $chip)
                                                <span class="rounded-full bg-white/75 border border-white/85 px-3 py-1 text-[11px] font-semibold text-[var(--navy)]">{{ $chip }}</span>
                                            @endforeach
                                        </div>
                                    </div>
                                </button>

                                <div class="sm:hidden liquid-glass px-3 py-2 text-xs text-[var(--ink-soft)]" id="infra-detail-mobile">Toca un nodo para ver su rol.</div>
                            </div>

                        @elseif ($slide['type'] === 'future')
                            <div class="flex flex-col gap-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="reveal-item text-[10px] font-semibold uppercase tracking-[0.18em] px-2.5 py-1 rounded-full liquid-glass" style="color: {{ $slide['color'] }}">{{ $slide['module'] }}</span>
                                    <span class="reveal-item text-[10px] font-semibold px-2.5 py-1 rounded-full bg-white/70 border border-white/80" style="color: {{ $slide['color'] }}">Próximo</span>
                                    @foreach ($slide['tags'] as $tag)
                                        <span class="reveal-item text-[10px] px-2 py-1 rounded-full bg-white/55 border border-white/70 text-[var(--navy)]/55">{{ $tag }}</span>
                                    @endforeach
                                </div>
                                <h2 class="reveal-item text-2xl sm:text-3xl lg:text-4xl font-bold tracking-tight text-[var(--navy)]">{{ $slide['title'] }}</h2>
                                <p class="reveal-item text-sm sm:text-base text-[var(--ink-soft)] max-w-3xl">{{ $slide['subtitle'] }}</p>
                                <div class="grid sm:grid-cols-2 gap-3">
                                    @foreach ($slide['data']['items'] ?? [] as $i => $item)
                                        <button type="button" class="future-card liquid-glass liquid-glass--interactive text-left px-4 py-4 {{ $i === 0 ? 'is-active' : '' }}">
                                            <div class="flex items-center justify-between gap-2 mb-2">
                                                <span class="text-xs font-semibold uppercase tracking-wide" style="color: {{ $slide['color'] }}">0{{ $i + 1 }} · {{ $item['tag'] }}</span>
                                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-white/75 border border-white/85 text-[var(--navy)]/55">Roadmap</span>
                                            </div>
                                            <div class="font-semibold text-[var(--navy)]">{{ $item['title'] }}</div>
                                            <p class="future-detail mt-2 text-sm text-[var(--ink-soft)] leading-relaxed">{{ $item['detail'] }}</p>
                                        </button>
                                    @endforeach
                                </div>
                                <div class="reveal-item liquid-glass liquid-glass--accent px-4 py-3 text-sm font-medium text-[var(--navy)]">
                                    {{ $slide['data']['promise'] ?? '' }}
                                </div>
                                @if ($slide['speaker_note'])
                                    <button type="button" class="reveal-item text-left text-xs text-[var(--accent)] font-medium" data-toggle-note>Nota del presentador ▾</button>
                                    <div class="speaker-note liquid-glass px-4 py-3 text-sm text-[var(--ink-soft)]">{{ $slide['speaker_note'] }}</div>
                                @endif
                            </div>

                        @elseif ($slide['type'] === 'closing')
                            <div class="flex flex-col items-center text-center gap-5 max-w-3xl mx-auto">
                                <span class="reveal-item text-[10px] font-semibold uppercase tracking-[0.18em] px-2.5 py-1 rounded-full liquid-glass text-[var(--accent)]">{{ $slide['module'] }}</span>
                                <h2 class="reveal-item text-2xl sm:text-3xl lg:text-4xl font-bold tracking-tight text-[var(--navy)]">{{ $slide['title'] }}</h2>
                                <p class="reveal-item text-sm sm:text-base text-[var(--ink-soft)]">{{ $slide['subtitle'] }}</p>
                                <div class="reveal-item liquid-glass liquid-glass--accent px-6 sm:px-10 py-8 w-full">
                                    <div class="quote-mark mb-3">“</div>
                                    <p class="text-xl sm:text-2xl lg:text-3xl font-semibold text-[var(--navy)] leading-snug tracking-tight">
                                        {{ $slide['data']['quote'] ?? '' }}
                                    </p>
                                    <div class="mt-5 text-xs font-semibold uppercase tracking-[0.16em] text-[var(--accent)]">
                                        {{ $slide['data']['attribution'] ?? '' }}
                                    </div>
                                </div>
                                <p class="reveal-item text-sm font-medium text-[var(--navy)]/80">{{ $slide['data']['tagline'] ?? '' }}</p>
                                <div class="reveal-item flex flex-wrap justify-center gap-2">
                                    <div class="logo-chip logo-chip--mark">
                                        <img src="{{ asset('image/imagotipo.png') }}" alt="">
                                        <span>INTEGRACORP</span>
                                    </div>
                                    <div class="logo-chip">
                                        <img src="{{ asset('image/logoNewTDG.png') }}" alt="tuDrGroup">
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </main>

    <footer class="footer-glass fixed bottom-0 inset-x-0 z-50 border-t px-4 sm:px-6 py-3">
        <div class="max-w-6xl mx-auto">
            <div class="presentation-footer-mobile">
                <div class="flex items-center justify-center gap-1.5 overflow-x-auto py-0.5 max-w-full">
                    @foreach ($slides as $i => $slide)
                        <button type="button" class="dot w-2 h-2 rounded-full shrink-0 {{ $i === 0 ? 'active' : '' }}" data-goto="{{ $i }}" title="{{ $slide['module'] }}"></button>
                    @endforeach
                </div>
                <p class="presentation-swipe-hint" aria-hidden="true">
                    <span>Desliza</span>
                    <span>←</span>
                    <span>→</span>
                </p>
            </div>

            <div class="presentation-nav-desktop mt-2">
                <button id="btn-prev" type="button" class="btn-glass px-3 sm:px-4 py-2 text-sm font-medium" disabled>← Anterior</button>
                <div class="hidden md:flex items-center gap-3 kb-hint">
                    <span>← → navegar</span>
                    <span>Espacio siguiente</span>
                    <span>O vista general</span>
                    <span>F pantalla completa</span>
                </div>
                <button id="btn-next" type="button" class="btn-accent px-3 sm:px-4 py-2 text-sm">Siguiente →</button>
            </div>
        </div>
    </footer>

    <div id="overview-panel" class="overview-panel" role="dialog" aria-modal="true">
        <div class="liquid-glass w-full max-w-4xl max-h-[85vh] overflow-auto p-5 sm:p-6">
            <div class="flex items-center justify-between gap-3 mb-4">
                <div>
                    <div class="text-[10px] font-semibold uppercase tracking-[0.16em] text-[var(--accent)]">Vista general</div>
                    <div class="text-lg font-bold text-[var(--navy)]">Índice de la presentación</div>
                </div>
                <button type="button" class="btn-glass px-3 py-1.5 text-xs" data-close-overview>Cerrar</button>
            </div>
            <div class="grid sm:grid-cols-2 gap-2.5">
                @foreach ($slides as $i => $slide)
                    <button type="button" class="liquid-glass liquid-glass--interactive text-left px-4 py-3" data-overview-goto="{{ $i }}">
                        <div class="text-[10px] font-semibold uppercase tracking-wide text-[var(--accent)]">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }} · {{ $slide['module'] }}</div>
                        <div class="text-sm font-semibold text-[var(--navy)] mt-0.5">{{ $slide['title'] }}</div>
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    <script>
        (() => {
            const slidesData = @json($slides);
            const slides = Array.from(document.querySelectorAll('.slide'));
            const total = slides.length;
            const btnNext = document.getElementById('btn-next');
            const btnPrev = document.getElementById('btn-prev');
            const counter = document.getElementById('slide-counter');
            const progress = document.getElementById('progress-bar');
            const glow = document.getElementById('bg-glow');
            const dots = Array.from(document.querySelectorAll('.dot'));
            const overviewPanel = document.getElementById('overview-panel');
            let current = 0;

            function setExclusiveActive(nodes, active) {
                nodes.forEach((n) => n.classList.toggle('is-active', n === active));
            }

            function bindSlideInteractions(slideEl) {
                slideEl.querySelectorAll('.pillar-card').forEach((card) => {
                    if (card.dataset.ready) return;
                    card.dataset.ready = '1';
                    card.addEventListener('click', () => {
                        setExclusiveActive(slideEl.querySelectorAll('.pillar-card'), card);
                    });
                });

                slideEl.querySelectorAll('.value-card').forEach((card) => {
                    if (card.dataset.ready) return;
                    card.dataset.ready = '1';
                    card.addEventListener('click', () => {
                        setExclusiveActive(slideEl.querySelectorAll('.value-card'), card);
                    });
                });

                slideEl.querySelectorAll('.suite-card').forEach((card) => {
                    if (card.dataset.ready) return;
                    card.dataset.ready = '1';
                    card.addEventListener('click', () => {
                        setExclusiveActive(slideEl.querySelectorAll('.suite-card'), card);
                    });
                });

                slideEl.querySelectorAll('.future-card').forEach((card) => {
                    if (card.dataset.ready) return;
                    card.dataset.ready = '1';
                    card.addEventListener('click', () => {
                        setExclusiveActive(slideEl.querySelectorAll('.future-card'), card);
                    });
                });

                slideEl.querySelectorAll('[data-toggle-note]').forEach((btn) => {
                    if (btn.dataset.ready) return;
                    btn.dataset.ready = '1';
                    btn.addEventListener('click', () => {
                        const note = btn.nextElementSibling;
                        if (note?.classList.contains('speaker-note')) {
                            note.classList.toggle('is-open');
                        }
                    });
                });

                slideEl.querySelectorAll('.infra-node').forEach((node) => {
                    if (node.dataset.ready) return;
                    node.dataset.ready = '1';
                    node.addEventListener('click', () => {
                        setExclusiveActive(slideEl.querySelectorAll('.infra-node'), node);
                        const text = `${node.dataset.infraId} · ${node.dataset.infraRole} — ${node.dataset.infraDetail}`;
                        const desk = document.getElementById('infra-detail');
                        const mobile = document.getElementById('infra-detail-mobile');
                        if (desk) desk.textContent = text;
                        if (mobile) mobile.textContent = text;
                    });
                });
            }

            function updateUI() {
                counter.textContent = `${current + 1} / ${total}`;
                progress.style.width = `${((current + 1) / total) * 100}%`;
                btnPrev.disabled = current === 0;
                btnNext.disabled = current === total - 1;
                btnNext.textContent = current === total - 1 ? 'Fin' : 'Siguiente →';
                dots.forEach((dot, i) => dot.classList.toggle('active', i === current));
                const color = slides[current]?.dataset.color || '#007AFF';
                document.documentElement.style.setProperty('--accent', color);
                if (glow) glow.style.background = color;
                bindSlideInteractions(slides[current]);
            }

            function goTo(index, direction = 1) {
                if (index < 0 || index >= total || index === current) {
                    updateUI();
                    return;
                }
                const prev = slides[current];
                const next = slides[index];
                prev.classList.remove('active');
                prev.classList.add(direction >= 0 ? 'exit-left' : 'exit-right');
                next.classList.remove('exit-left', 'exit-right');
                next.classList.add('active');
                window.setTimeout(() => prev.classList.remove('exit-left', 'exit-right'), 450);
                current = index;
                updateUI();
            }

            const next = () => goTo(current + 1, 1);
            const prev = () => goTo(current - 1, -1);

            btnNext.addEventListener('click', () => {
                if (current < total - 1) next();
            });
            btnPrev.addEventListener('click', prev);

            dots.forEach((dot) => {
                dot.addEventListener('click', () => {
                    const target = parseInt(dot.dataset.goto, 10);
                    goTo(target, target > current ? 1 : -1);
                });
            });

            document.getElementById('btn-overview')?.addEventListener('click', () => {
                overviewPanel.classList.add('is-open');
            });

            overviewPanel.querySelector('[data-close-overview]')?.addEventListener('click', () => {
                overviewPanel.classList.remove('is-open');
            });

            overviewPanel.addEventListener('click', (e) => {
                if (e.target === overviewPanel) overviewPanel.classList.remove('is-open');
            });

            overviewPanel.querySelectorAll('[data-overview-goto]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const target = parseInt(btn.dataset.overviewGoto, 10);
                    overviewPanel.classList.remove('is-open');
                    goTo(target, target > current ? 1 : -1);
                });
            });

            document.getElementById('btn-fullscreen')?.addEventListener('click', async () => {
                if (! document.fullscreenElement) {
                    await document.documentElement.requestFullscreen?.();
                } else {
                    await document.exitFullscreen?.();
                }
            });

            document.addEventListener('keydown', (e) => {
                if (overviewPanel.classList.contains('is-open')) {
                    if (e.key === 'Escape') overviewPanel.classList.remove('is-open');
                    return;
                }
                if (e.key === 'ArrowRight' || e.key === ' ') {
                    e.preventDefault();
                    if (current < total - 1) next();
                } else if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    prev();
                } else if (e.key === 'Home') {
                    e.preventDefault();
                    goTo(0, -1);
                } else if (e.key === 'End') {
                    e.preventDefault();
                    goTo(total - 1, 1);
                } else if (e.key === 'o' || e.key === 'O') {
                    e.preventDefault();
                    overviewPanel.classList.add('is-open');
                } else if (e.key === 'f' || e.key === 'F') {
                    e.preventDefault();
                    document.getElementById('btn-fullscreen')?.click();
                }
            });

            let touchStartX = 0;
            let touchStartY = 0;
            const swipeTarget = document.getElementById('slides-container') || document;

            swipeTarget.addEventListener('touchstart', (e) => {
                touchStartX = e.changedTouches[0].screenX;
                touchStartY = e.changedTouches[0].screenY;
            }, { passive: true });

            swipeTarget.addEventListener('touchend', (e) => {
                if (overviewPanel.classList.contains('is-open')) return;
                const diffX = touchStartX - e.changedTouches[0].screenX;
                const diffY = touchStartY - e.changedTouches[0].screenY;
                if (Math.abs(diffX) < 48 || Math.abs(diffX) < Math.abs(diffY)) {
                    return;
                }
                if (diffX > 0) {
                    if (current < total - 1) next();
                } else {
                    prev();
                }
            }, { passive: true });

            void slidesData;
            updateUI();
        })();
    </script>
    @include('partials.presentation-theme-script')
    @include('partials.presentation-idle-watchdog', [
        'isAuthenticated' => true,
        'idleTimeoutSeconds' => $idleTimeoutSeconds ?? \App\Support\PresentationHubGate::idleTimeoutSeconds(),
    ])
</body>
</html>
