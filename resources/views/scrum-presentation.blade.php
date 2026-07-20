<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Scrum en el Desarrollo de Apps — INTEGRACORP · TUDRGROUP</title>
    <link rel="icon" href="{{ asset('image/imagotipo.png') }}" type="image/png">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style>
        :root {
            --white: #FFFFFF;
            --gray: #E5E5E5;
            --accent: #FCA311;
            --navy: #14213D;
            --black: #000000;
            --glass-bg: rgba(255, 255, 255, 0.55);
            --glass-border: rgba(255, 255, 255, 0.72);
            --glass-shadow: rgba(20, 33, 61, 0.08);
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Instrument Sans', ui-sans-serif, -apple-system, BlinkMacSystemFont, 'SF Pro Display', system-ui, sans-serif;
            overflow: hidden;
            color: var(--navy);
            background:
                radial-gradient(1200px 600px at 12% -10%, rgba(252, 163, 17, 0.18), transparent 55%),
                radial-gradient(900px 500px at 95% 10%, rgba(20, 33, 61, 0.10), transparent 50%),
                radial-gradient(700px 400px at 50% 110%, rgba(252, 163, 17, 0.12), transparent 55%),
                linear-gradient(165deg, #FFFFFF 0%, #F4F4F4 45%, #E5E5E5 100%);
            -webkit-font-smoothing: antialiased;
        }

        .bg-mesh {
            background-image:
                linear-gradient(rgba(20, 33, 61, 0.035) 1px, transparent 1px),
                linear-gradient(90deg, rgba(20, 33, 61, 0.035) 1px, transparent 1px);
            background-size: 42px 42px;
            mask-image: radial-gradient(ellipse 80% 70% at 50% 40%, black 20%, transparent 75%);
        }

        .liquid-glass {
            position: relative;
            isolation: isolate;
            border-radius: 1.35rem;
            border: 1px solid var(--glass-border);
            background:
                linear-gradient(
                    155deg,
                    rgba(255, 255, 255, 0.78) 0%,
                    rgba(255, 255, 255, 0.42) 48%,
                    rgba(229, 229, 229, 0.35) 100%
                );
            backdrop-filter: blur(28px) saturate(180%);
            -webkit-backdrop-filter: blur(28px) saturate(180%);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.9),
                inset 0 -1px 0 rgba(20, 33, 61, 0.04),
                0 0 0 1px rgba(255, 255, 255, 0.35),
                0 12px 40px var(--glass-shadow),
                0 2px 10px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }

        .liquid-glass::before {
            content: '';
            pointer-events: none;
            position: absolute;
            inset: 0;
            border-radius: inherit;
            background: linear-gradient(118deg, rgba(255, 255, 255, 0.55) 0%, transparent 48%);
            opacity: 0.7;
            z-index: 0;
        }

        .liquid-glass > * { position: relative; z-index: 1; }

        .liquid-glass--accent {
            border-color: color-mix(in srgb, var(--accent) 35%, white);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.9),
                0 0 0 1px color-mix(in srgb, var(--accent) 18%, transparent),
                0 14px 36px rgba(252, 163, 17, 0.15);
        }

        .liquid-glass--interactive {
            cursor: pointer;
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
        }

        .liquid-glass--interactive:hover,
        .liquid-glass--interactive:focus-visible {
            transform: translateY(-2px) scale(1.01);
            border-color: color-mix(in srgb, var(--accent) 45%, white);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.95),
                0 16px 40px rgba(252, 163, 17, 0.18),
                0 4px 14px rgba(20, 33, 61, 0.08);
        }

        .liquid-glass--interactive.is-active {
            border-color: var(--accent);
            background:
                linear-gradient(
                    155deg,
                    rgba(255, 255, 255, 0.88) 0%,
                    rgba(252, 163, 17, 0.12) 100%
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
            max-width: 72rem;
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

        .slide.active .reveal-item:nth-child(1) { transition-delay: 0.08s; }
        .slide.active .reveal-item:nth-child(2) { transition-delay: 0.16s; }
        .slide.active .reveal-item:nth-child(3) { transition-delay: 0.24s; }
        .slide.active .reveal-item:nth-child(4) { transition-delay: 0.32s; }
        .slide.active .reveal-item:nth-child(5) { transition-delay: 0.40s; }

        .progress-fill {
            transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: linear-gradient(90deg, var(--navy), var(--accent));
        }

        .dot {
            transition: all 0.25s ease;
            background: rgba(20, 33, 61, 0.18);
        }

        .dot.active {
            transform: scale(1.35);
            background: var(--accent);
            box-shadow: 0 0 0 3px rgba(252, 163, 17, 0.25);
        }

        .btn-glass {
            border-radius: 0.9rem;
            border: 1px solid rgba(255, 255, 255, 0.7);
            background: rgba(255, 255, 255, 0.55);
            backdrop-filter: blur(16px) saturate(160%);
            -webkit-backdrop-filter: blur(16px) saturate(160%);
            color: var(--navy);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.85), 0 4px 14px rgba(20, 33, 61, 0.06);
            transition: transform 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-glass:hover:not(:disabled) {
            background: rgba(255, 255, 255, 0.8);
            transform: translateY(-1px);
        }

        .btn-glass:disabled {
            opacity: 0.35;
            cursor: not-allowed;
        }

        .btn-accent {
            border-radius: 0.9rem;
            border: 1px solid color-mix(in srgb, var(--accent) 50%, white);
            background: linear-gradient(145deg, #FFB84A 0%, var(--accent) 55%, #E8920A 100%);
            color: var(--navy);
            font-weight: 600;
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.55),
                0 8px 20px rgba(252, 163, 17, 0.28);
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

        @keyframes float-y {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }

        @keyframes spin-slow {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        @keyframes pulse-ring {
            0% { transform: scale(0.92); opacity: 0.7; }
            70% { transform: scale(1.08); opacity: 0; }
            100% { transform: scale(1.08); opacity: 0; }
        }

        @keyframes orbit {
            from { transform: rotate(0deg) translateX(72px) rotate(0deg); }
            to { transform: rotate(360deg) translateX(72px) rotate(-360deg); }
        }

        .float-y { animation: float-y 4.5s ease-in-out infinite; }
        .spin-slow { animation: spin-slow 18s linear infinite; }
        .pulse-ring { animation: pulse-ring 2.4s ease-out infinite; }

        .cycle-orbit {
            position: absolute;
            width: 0.85rem;
            height: 0.85rem;
            border-radius: 9999px;
            background: var(--accent);
            box-shadow: 0 0 12px rgba(252, 163, 17, 0.6);
            top: 50%;
            left: 50%;
            margin: -0.425rem 0 0 -0.425rem;
            animation: orbit 8s linear infinite;
        }

        .role-card .role-detail,
        .lifecycle-detail,
        .benefit-detail {
            display: none;
        }

        .role-card.is-active .role-detail,
        .lifecycle-step.is-active .lifecycle-detail,
        .benefit-card.is-active .benefit-detail {
            display: block;
        }

        .waterfall-step.is-done {
            opacity: 0.45;
            text-decoration: line-through;
        }

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
            background: rgba(229, 229, 229, 0.55);
            backdrop-filter: blur(18px) saturate(160%);
            -webkit-backdrop-filter: blur(18px) saturate(160%);
        }

        .overview-panel.is-open { display: flex; }

        .timeline-sprint {
            opacity: 0.45;
            filter: grayscale(0.4);
            transition: all 0.35s ease;
        }

        .timeline-sprint.is-unlocked {
            opacity: 1;
            filter: none;
        }

        .phone-mock {
            width: 11.5rem;
            height: 22rem;
            border-radius: 1.75rem;
            border: 3px solid var(--navy);
            background: linear-gradient(160deg, #FFFFFF, #E5E5E5);
            box-shadow:
                inset 0 0 0 2px rgba(255, 255, 255, 0.8),
                0 20px 40px rgba(20, 33, 61, 0.18);
            position: relative;
            overflow: hidden;
        }

        .phone-mock__notch {
            position: absolute;
            top: 0.55rem;
            left: 50%;
            transform: translateX(-50%);
            width: 3.2rem;
            height: 0.7rem;
            border-radius: 9999px;
            background: var(--navy);
            z-index: 2;
        }

        .header-glass, .footer-glass {
            background: rgba(255, 255, 255, 0.62);
            backdrop-filter: blur(22px) saturate(170%);
            -webkit-backdrop-filter: blur(22px) saturate(170%);
            border-color: rgba(255, 255, 255, 0.75);
            box-shadow: 0 1px 0 rgba(255, 255, 255, 0.8), 0 8px 24px rgba(20, 33, 61, 0.05);
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
            border: 1px solid rgba(255, 255, 255, 0.75);
            background: rgba(255, 255, 255, 0.55);
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
        }

        .kb-hint {
            font-size: 10px;
            letter-spacing: 0.02em;
            color: rgba(20, 33, 61, 0.45);
        }

        @media (max-height: 740px) {
            #slides-container {
                top: 3.75rem;
                bottom: 5.75rem;
            }
            .phone-mock { width: 9.5rem; height: 18rem; }
        }
    </style>
</head>
<body class="min-h-screen select-none">

    <div class="fixed inset-0 bg-mesh pointer-events-none" aria-hidden="true"></div>
    <div class="fixed w-[28rem] h-[28rem] rounded-full pointer-events-none blur-3xl opacity-40 transition-colors duration-700"
         id="bg-glow"
         style="top: 8%; right: 6%; background: var(--accent);"
         aria-hidden="true"></div>
    <div class="fixed w-72 h-72 rounded-full pointer-events-none blur-3xl opacity-25"
         style="bottom: 8%; left: 4%; background: var(--navy);"
         aria-hidden="true"></div>

    <header class="header-glass fixed top-0 inset-x-0 z-50 flex items-center justify-between gap-3 px-4 sm:px-6 py-3 border-b">
        <div class="flex items-center gap-2 sm:gap-3 min-w-0">
            <div class="logo-chip logo-chip--mark">
                <img src="{{ asset('image/imagotipo.png') }}" alt="INTEGRACORP">
                <span class="text-[var(--navy)]">INTEGRACORP</span>
            </div>
            <div class="logo-chip">
                <img src="{{ asset('image/logoNewTDG.png') }}" alt="TUDRGROUP">
            </div>
        </div>
        <div class="flex items-center gap-2 sm:gap-3 shrink-0">
            <button id="btn-overview" type="button" class="btn-glass px-2.5 py-1.5 text-xs font-medium" title="Vista general (O)">
                Vista general
            </button>
            <button id="btn-fullscreen" type="button" class="btn-glass px-2.5 py-1.5 text-xs font-medium hidden sm:inline-flex" title="Pantalla completa (F)">
                Pantalla completa
            </button>
            <span id="slide-counter" class="text-sm font-semibold tabular-nums text-[var(--navy)]/70">1 / {{ count($slides) }}</span>
        </div>
    </header>

    <div class="fixed top-[57px] inset-x-0 z-50 h-1 bg-[var(--gray)]/80">
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
                            <div class="grid lg:grid-cols-2 gap-8 lg:gap-12 items-center">
                                <div class="flex flex-col gap-5">
                                    <div class="flex flex-wrap gap-2">
                                        <span class="reveal-item text-[10px] font-semibold uppercase tracking-[0.18em] px-2.5 py-1 rounded-full liquid-glass text-[var(--accent)]">{{ $slide['module'] }}</span>
                                        @foreach ($slide['tags'] as $tag)
                                            <span class="reveal-item text-[10px] px-2 py-1 rounded-full bg-white/50 border border-white/70 text-[var(--navy)]/55">{{ $tag }}</span>
                                        @endforeach
                                    </div>
                                    <h1 class="reveal-item text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight text-[var(--navy)] leading-[1.1]">
                                        {{ $slide['title'] }}
                                    </h1>
                                    <p class="reveal-item text-base sm:text-lg text-[var(--navy)]/65 leading-relaxed max-w-xl">
                                        {{ $slide['subtitle'] }}
                                    </p>
                                    <ul class="grid gap-2.5">
                                        @foreach ($slide['highlights'] as $highlight)
                                            <li class="reveal-item liquid-glass px-4 py-3 text-sm text-[var(--navy)]/80 flex items-start gap-3">
                                                <span class="mt-0.5 shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold bg-[var(--accent)]/20 text-[var(--navy)]">✓</span>
                                                {{ $highlight }}
                                            </li>
                                        @endforeach
                                    </ul>
                                    <div class="reveal-item flex flex-wrap items-center gap-3 pt-1">
                                        <div class="logo-chip logo-chip--mark">
                                            <img src="{{ asset('image/imagotipo.png') }}" alt="">
                                            <span>INTEGRACORP</span>
                                        </div>
                                        <div class="logo-chip">
                                            <img src="{{ asset('image/logoNewTDG.png') }}" alt="TUDRGROUP">
                                        </div>
                                    </div>
                                </div>
                                <div class="reveal-item flex justify-center">
                                    <div class="liquid-glass liquid-glass--accent p-8 sm:p-10 float-y">
                                        <div class="relative w-56 h-56 sm:w-64 sm:h-64 mx-auto">
                                            <div class="absolute inset-6 rounded-full border-2 border-dashed border-[var(--navy)]/15"></div>
                                            <div class="absolute inset-0 flex items-center justify-center">
                                                <svg class="spin-slow w-28 h-28 text-[var(--accent)]" viewBox="0 0 120 120" fill="none" aria-hidden="true">
                                                    <circle cx="60" cy="60" r="18" stroke="currentColor" stroke-width="6"/>
                                                    <g stroke="currentColor" stroke-width="8" stroke-linecap="round">
                                                        <path d="M60 10 v16"/><path d="M60 94 v16"/>
                                                        <path d="M10 60 h16"/><path d="M94 60 h16"/>
                                                        <path d="M24 24 l12 12"/><path d="M84 84 l12 12"/>
                                                        <path d="M96 24 l-12 12"/><path d="M36 84 l-12 12"/>
                                                    </g>
                                                </svg>
                                            </div>
                                            <div class="absolute right-2 bottom-4 phone-mock scale-[0.72] origin-bottom-right">
                                                <div class="phone-mock__notch"></div>
                                                <div class="absolute inset-3 rounded-[1.2rem] bg-[var(--navy)]/5 flex flex-col items-center justify-center gap-2 p-3">
                                                    <div class="w-10 h-10 rounded-2xl bg-[var(--accent)]/90 shadow-lg"></div>
                                                    <div class="w-16 h-1.5 rounded-full bg-[var(--navy)]/15"></div>
                                                    <div class="w-12 h-1.5 rounded-full bg-[var(--navy)]/10"></div>
                                                    <div class="mt-2 grid grid-cols-3 gap-1.5 w-full px-1">
                                                        <div class="h-6 rounded-md bg-[var(--accent)]/25"></div>
                                                        <div class="h-6 rounded-md bg-[var(--navy)]/10"></div>
                                                        <div class="h-6 rounded-md bg-[var(--navy)]/10"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <p class="text-center text-xs text-[var(--navy)]/50 mt-4">Engranaje + App · Agilidad en movimiento</p>
                                    </div>
                                </div>
                            </div>

                        @elseif ($slide['type'] === 'problem')
                            <div class="grid lg:grid-cols-2 gap-8 items-start">
                                <div class="flex flex-col gap-4">
                                    <span class="reveal-item text-[10px] font-semibold uppercase tracking-[0.18em] px-2.5 py-1 rounded-full liquid-glass text-[var(--accent)] w-fit">{{ $slide['module'] }}</span>
                                    <h1 class="reveal-item text-2xl sm:text-3xl lg:text-4xl font-bold tracking-tight leading-tight">{{ $slide['title'] }}</h1>
                                    <p class="reveal-item text-[var(--navy)]/65">{{ $slide['subtitle'] }}</p>
                                    <button type="button" class="reveal-item btn-accent px-4 py-2.5 text-sm w-fit" data-play-waterfall>
                                        Simular cascada → fracaso
                                    </button>
                                    @if ($slide['speaker_note'])
                                        <button type="button" class="reveal-item btn-glass px-3 py-2 text-xs w-fit" data-toggle-speaker>
                                            Nota del orador
                                        </button>
                                        <div class="speaker-note liquid-glass liquid-glass--accent px-4 py-3 text-sm italic text-[var(--navy)]/80" data-speaker-note>
                                            “{{ $slide['speaker_note'] }}”
                                        </div>
                                    @endif
                                </div>
                                <div class="reveal-item liquid-glass p-5 sm:p-6 space-y-3" data-waterfall>
                                    @foreach ($slide['data']['steps'] as $stepIndex => $step)
                                        <div class="waterfall-step liquid-glass liquid-glass--interactive px-4 py-3 flex items-center gap-3"
                                             data-step="{{ $stepIndex }}"
                                             tabindex="0"
                                             role="button">
                                            <span class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold bg-[var(--navy)] text-white shrink-0">{{ $stepIndex + 1 }}</span>
                                            <div>
                                                <p class="font-semibold text-sm">{{ $step['label'] }}</p>
                                                <p class="text-xs text-[var(--navy)]/55">{{ $step['detail'] }}</p>
                                            </div>
                                        </div>
                                    @endforeach
                                    <div class="waterfall-danger hidden liquid-glass px-4 py-4 border-[var(--accent)]! mt-2" data-waterfall-danger>
                                        <p class="font-bold text-[var(--navy)] text-sm">⚠ El peligro</p>
                                        <p class="text-sm text-[var(--navy)]/70 mt-1">Cuando la app sale, los requisitos cambiaron, la tecnología avanzó o Apple/Google cambiaron sus políticas. El proyecto fracasa.</p>
                                    </div>
                                </div>
                            </div>

                        @elseif ($slide['type'] === 'cycle')
                            <div class="grid lg:grid-cols-2 gap-8 items-center">
                                <div class="flex flex-col gap-4">
                                    <span class="reveal-item text-[10px] font-semibold uppercase tracking-[0.18em] px-2.5 py-1 rounded-full liquid-glass text-[var(--accent)] w-fit">{{ $slide['module'] }}</span>
                                    <h1 class="reveal-item text-2xl sm:text-3xl lg:text-4xl font-bold tracking-tight">{{ $slide['title'] }}</h1>
                                    <p class="reveal-item text-[var(--navy)]/65">{{ $slide['subtitle'] }}</p>
                                    <div class="grid gap-2.5">
                                        @foreach ($slide['data']['modules'] as $modIndex => $mod)
                                            <button type="button"
                                                    class="reveal-item liquid-glass liquid-glass--interactive text-left px-4 py-3 module-chip {{ $modIndex === 0 ? 'is-active' : '' }}"
                                                    data-module-index="{{ $modIndex }}">
                                                <span class="text-[10px] font-semibold uppercase tracking-wider text-[var(--accent)]">{{ $mod['sprint'] }}</span>
                                                <p class="font-semibold text-sm mt-0.5">{{ $mod['feature'] }}</p>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="reveal-item flex justify-center">
                                    <div class="liquid-glass liquid-glass--accent p-8 relative w-72 h-72 sm:w-80 sm:h-80 flex items-center justify-center">
                                        <div class="absolute inset-10 rounded-full border-[3px] border-[var(--navy)]/15"></div>
                                        <div class="absolute inset-10 rounded-full border-[3px] border-dashed border-[var(--accent)]/50"></div>
                                        <div class="cycle-orbit" aria-hidden="true"></div>
                                        <div class="text-center z-[1]">
                                            <p class="text-[10px] uppercase tracking-[0.2em] text-[var(--navy)]/45 font-semibold">Ciclo</p>
                                            <p class="text-2xl font-bold text-[var(--navy)] mt-1" data-cycle-label>Sprint 1</p>
                                            <p class="text-sm text-[var(--navy)]/60 mt-1 max-w-[10rem] mx-auto" data-cycle-feature>Login y Registro</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        @elseif ($slide['type'] === 'roles')
                            <div class="flex flex-col gap-5">
                                <div>
                                    <span class="reveal-item text-[10px] font-semibold uppercase tracking-[0.18em] px-2.5 py-1 rounded-full liquid-glass text-[var(--accent)] w-fit inline-block">{{ $slide['module'] }}</span>
                                    <h1 class="reveal-item text-2xl sm:text-3xl lg:text-4xl font-bold tracking-tight mt-3">{{ $slide['title'] }}</h1>
                                    <p class="reveal-item text-[var(--navy)]/65 mt-2">{{ $slide['subtitle'] }}</p>
                                </div>
                                <div class="grid md:grid-cols-3 gap-4">
                                    @foreach ($slide['data']['roles'] as $roleIndex => $role)
                                        <button type="button"
                                                class="reveal-item role-card liquid-glass liquid-glass--interactive text-left p-5 {{ $roleIndex === 0 ? 'is-active' : '' }}"
                                                data-role-index="{{ $roleIndex }}">
                                            <span class="text-3xl" aria-hidden="true">{{ $role['icon'] }}</span>
                                            <h2 class="font-bold text-base mt-3">{{ $role['title'] }}</h2>
                                            <p class="text-xs text-[var(--accent)] font-semibold mt-1">{{ $role['short'] }}</p>
                                            <p class="role-detail text-sm text-[var(--navy)]/70 mt-3 leading-relaxed">{{ $role['responsibility'] }}</p>
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                        @elseif ($slide['type'] === 'artifacts')
                            <div class="grid lg:grid-cols-[1fr_1.1fr] gap-6 items-start">
                                <div class="flex flex-col gap-4">
                                    <span class="reveal-item text-[10px] font-semibold uppercase tracking-[0.18em] px-2.5 py-1 rounded-full liquid-glass text-[var(--accent)] w-fit">{{ $slide['module'] }}</span>
                                    <h1 class="reveal-item text-2xl sm:text-3xl lg:text-4xl font-bold tracking-tight">{{ $slide['title'] }}</h1>
                                    <p class="reveal-item text-[var(--navy)]/65">{{ $slide['subtitle'] }}</p>
                                    <div class="grid gap-2.5">
                                        @foreach ($slide['data']['artifacts'] as $artIndex => $artifact)
                                            <button type="button"
                                                    class="reveal-item artifact-card liquid-glass liquid-glass--interactive text-left px-4 py-3 {{ $artIndex === 0 ? 'is-active' : '' }}"
                                                    data-artifact-index="{{ $artIndex }}">
                                                <span class="text-[10px] font-semibold uppercase tracking-wider text-[var(--accent)]">{{ $artIndex + 1 }}. {{ $artifact['aka'] }}</span>
                                                <p class="font-semibold text-sm mt-0.5">{{ $artifact['title'] }}</p>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="reveal-item">
                                    @foreach ($slide['data']['artifacts'] as $artIndex => $artifact)
                                        <div class="artifact-panel liquid-glass liquid-glass--accent p-6"
                                             data-artifact-panel="{{ $artIndex }}"
                                             style="{{ $artIndex === 0 ? 'display:block' : 'display:none' }}">
                                            <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-[var(--accent)]">{{ $artifact['aka'] }}</p>
                                            <h2 class="text-xl font-bold mt-2">{{ $artifact['title'] }}</h2>
                                            <p class="text-sm text-[var(--navy)]/70 mt-3 leading-relaxed">{{ $artifact['description'] }}</p>
                                            <div class="mt-4 liquid-glass px-4 py-3">
                                                <p class="text-[10px] uppercase tracking-wider text-[var(--navy)]/45 font-semibold">Ejemplo</p>
                                                <p class="text-sm mt-1 italic">“{{ $artifact['example'] }}”</p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                        @elseif ($slide['type'] === 'lifecycle')
                            <div class="flex flex-col gap-5">
                                <div>
                                    <span class="reveal-item text-[10px] font-semibold uppercase tracking-[0.18em] px-2.5 py-1 rounded-full liquid-glass text-[var(--accent)] w-fit inline-block">{{ $slide['module'] }}</span>
                                    <h1 class="reveal-item text-2xl sm:text-3xl lg:text-4xl font-bold tracking-tight mt-3">{{ $slide['title'] }}</h1>
                                    <p class="reveal-item text-[var(--navy)]/65 mt-2">{{ $slide['subtitle'] }}</p>
                                </div>
                                <div class="grid sm:grid-cols-2 gap-3">
                                    @foreach ($slide['data']['steps'] as $lifeIndex => $lifeStep)
                                        <button type="button"
                                                class="reveal-item lifecycle-step liquid-glass liquid-glass--interactive text-left p-4 {{ $lifeIndex === 0 ? 'is-active' : '' }}"
                                                data-life-index="{{ $lifeIndex }}">
                                            <div class="flex items-center gap-3">
                                                <span class="w-8 h-8 rounded-full bg-[var(--navy)] text-white text-xs font-bold flex items-center justify-center shrink-0">{{ $lifeIndex + 1 }}</span>
                                                <h2 class="font-bold text-sm sm:text-base">{{ $lifeStep['title'] }}</h2>
                                            </div>
                                            <p class="lifecycle-detail text-sm text-[var(--navy)]/70 mt-3 leading-relaxed">{{ $lifeStep['detail'] }}</p>
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                        @elseif ($slide['type'] === 'timeline')
                            <div class="flex flex-col gap-5">
                                <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
                                    <div>
                                        <span class="reveal-item text-[10px] font-semibold uppercase tracking-[0.18em] px-2.5 py-1 rounded-full liquid-glass text-[var(--accent)] w-fit inline-block">{{ $slide['module'] }}</span>
                                        <h1 class="reveal-item text-2xl sm:text-3xl lg:text-4xl font-bold tracking-tight mt-3">{{ $slide['title'] }}</h1>
                                        <p class="reveal-item text-[var(--navy)]/65 mt-2">{{ $slide['subtitle'] }}</p>
                                        <p class="reveal-item text-sm font-semibold text-[var(--accent)] mt-2">Objetivo: {{ $slide['data']['goal'] }}</p>
                                    </div>
                                    <div class="reveal-item flex items-center gap-2">
                                        <button type="button" class="btn-glass px-3 py-2 text-xs" data-timeline-reset>Reiniciar</button>
                                        <button type="button" class="btn-accent px-3 py-2 text-xs" data-timeline-next>Avanzar Sprint →</button>
                                    </div>
                                </div>
                                <div class="grid md:grid-cols-3 gap-3" data-timeline>
                                    @foreach ($slide['data']['sprints'] as $sprintIndex => $sprint)
                                        <div class="reveal-item timeline-sprint liquid-glass p-4 {{ $sprintIndex === 0 ? 'is-unlocked liquid-glass--accent' : '' }}"
                                             data-sprint="{{ $sprintIndex }}">
                                            <p class="text-[10px] font-semibold uppercase tracking-wider text-[var(--accent)]">{{ $sprint['label'] }}</p>
                                            <p class="text-xs text-[var(--navy)]/50 mt-0.5">{{ $sprint['weeks'] }}</p>
                                            <ul class="mt-3 space-y-1.5">
                                                @foreach ($sprint['items'] as $item)
                                                    <li class="text-sm flex items-start gap-2">
                                                        <span class="text-[var(--accent)]">•</span>
                                                        {{ $item }}
                                                    </li>
                                                @endforeach
                                            </ul>
                                            <p class="mt-3 text-xs font-semibold liquid-glass px-2.5 py-1.5 inline-block">{{ $sprint['result'] }}</p>
                                        </div>
                                    @endforeach
                                </div>
                                @foreach ($slide['highlights'] as $highlight)
                                    <p class="reveal-item liquid-glass liquid-glass--accent px-4 py-3 text-sm font-medium" data-mvp-banner style="display:none;">
                                        {{ $highlight }}
                                    </p>
                                @endforeach
                            </div>

                        @elseif ($slide['type'] === 'benefits')
                            <div class="flex flex-col gap-5">
                                <div>
                                    <span class="reveal-item text-[10px] font-semibold uppercase tracking-[0.18em] px-2.5 py-1 rounded-full liquid-glass text-[var(--accent)] w-fit inline-block">{{ $slide['module'] }}</span>
                                    <h1 class="reveal-item text-2xl sm:text-3xl lg:text-4xl font-bold tracking-tight mt-3">{{ $slide['title'] }}</h1>
                                    <p class="reveal-item text-[var(--navy)]/65 mt-2">{{ $slide['subtitle'] }}</p>
                                </div>
                                <div class="grid md:grid-cols-3 gap-4">
                                    @foreach ($slide['data']['benefits'] as $benIndex => $benefit)
                                        <button type="button"
                                                class="reveal-item benefit-card liquid-glass liquid-glass--interactive text-left p-5 {{ $benIndex === 0 ? 'is-active' : '' }}"
                                                data-benefit-index="{{ $benIndex }}">
                                            <span class="text-3xl" aria-hidden="true">{{ $benefit['emoji'] }}</span>
                                            <h2 class="font-bold text-base mt-3">{{ $benefit['title'] }}</h2>
                                            <p class="text-xs font-semibold text-[var(--accent)] mt-1">{{ $benefit['aka'] }}</p>
                                            <p class="benefit-detail text-sm text-[var(--navy)]/70 mt-3 leading-relaxed">{{ $benefit['description'] }}</p>
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                        @elseif ($slide['type'] === 'conclusion')
                            <div class="grid lg:grid-cols-2 gap-8 items-center">
                                <div class="flex flex-col gap-4">
                                    <span class="reveal-item text-[10px] font-semibold uppercase tracking-[0.18em] px-2.5 py-1 rounded-full liquid-glass text-[var(--accent)] w-fit">{{ $slide['module'] }}</span>
                                    <h1 class="reveal-item text-2xl sm:text-3xl lg:text-4xl font-bold tracking-tight">{{ $slide['title'] }}</h1>
                                    <p class="reveal-item text-[var(--navy)]/65">{{ $slide['subtitle'] }}</p>
                                    <ul class="grid gap-2.5">
                                        @foreach ($slide['highlights'] as $highlight)
                                            <li class="reveal-item liquid-glass px-4 py-3 text-sm text-[var(--navy)]/80">{{ $highlight }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                                <div class="reveal-item liquid-glass liquid-glass--accent p-6 sm:p-8">
                                    <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-[var(--navy)]/45 text-center">Pilares</p>
                                    <div class="mt-5 grid gap-3">
                                        @foreach ($slide['data']['pillars'] as $pillarIndex => $pillar)
                                            <button type="button"
                                                    class="pillar-btn liquid-glass liquid-glass--interactive px-4 py-3 text-center font-bold text-lg tracking-tight {{ $pillarIndex === 0 ? 'is-active' : '' }}"
                                                    data-pillar="{{ $pillar }}">
                                                {{ $pillar }}
                                            </button>
                                        @endforeach
                                    </div>
                                    <p class="mt-6 text-center text-sm italic text-[var(--navy)]/70">“{{ $slide['data']['closing'] }}”</p>
                                </div>
                            </div>

                        @elseif ($slide['type'] === 'qa')
                            <div class="flex flex-col items-center text-center gap-6 max-w-2xl mx-auto">
                                <span class="reveal-item text-[10px] font-semibold uppercase tracking-[0.18em] px-2.5 py-1 rounded-full liquid-glass text-[var(--accent)]">{{ $slide['module'] }}</span>
                                <h1 class="reveal-item text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight">{{ $slide['title'] }}</h1>
                                <p class="reveal-item text-base sm:text-lg text-[var(--navy)]/65">{{ $slide['subtitle'] }}</p>
                                <div class="reveal-item liquid-glass liquid-glass--accent w-full p-6 sm:p-8 space-y-4">
                                    <p class="text-xl font-bold">{{ $slide['data']['contact']['name'] }}</p>
                                    <p class="text-sm text-[var(--navy)]/55">{{ $slide['data']['contact']['org'] }}</p>
                                    <div class="flex flex-col sm:flex-row gap-3 justify-center pt-2">
                                        <a href="mailto:{{ $slide['data']['contact']['email'] }}"
                                           class="btn-accent px-4 py-2.5 text-sm inline-flex items-center justify-center gap-2">
                                            ✉ {{ $slide['data']['contact']['email'] }}
                                        </a>
                                        <a href="https://{{ $slide['data']['contact']['linkedin'] }}"
                                           target="_blank"
                                           rel="noopener"
                                           class="btn-glass px-4 py-2.5 text-sm inline-flex items-center justify-center gap-2">
                                            in LinkedIn
                                        </a>
                                    </div>
                                </div>
                                <div class="reveal-item flex flex-wrap items-center justify-center gap-3">
                                    <div class="logo-chip logo-chip--mark">
                                        <img src="{{ asset('image/imagotipo.png') }}" alt="">
                                        <span>INTEGRACORP</span>
                                    </div>
                                    <div class="logo-chip">
                                        <img src="{{ asset('image/logoNewTDG.png') }}" alt="TUDRGROUP">
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </main>

    <footer class="footer-glass fixed bottom-0 inset-x-0 z-50 px-4 sm:px-6 py-4 border-t">
        <div class="max-w-6xl mx-auto flex items-center justify-between gap-3">
            <button id="btn-prev" type="button" class="btn-glass flex items-center gap-2 px-3 sm:px-4 py-2.5 text-sm font-medium" aria-label="Anterior">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                <span class="hidden sm:inline">Anterior</span>
            </button>

            <div id="dots" class="flex items-center gap-1.5 flex-wrap justify-center max-w-[12rem] sm:max-w-md">
                @foreach ($slides as $index => $slide)
                    <button type="button"
                            class="dot w-2.5 h-2.5 rounded-full {{ $index === 0 ? 'active' : '' }}"
                            data-goto="{{ $index }}"
                            aria-label="Ir a la diapositiva {{ $index + 1 }}"
                            title="{{ $slide['title'] }}">
                    </button>
                @endforeach
            </div>

            <button id="btn-next" type="button" class="btn-accent flex items-center gap-2 px-3 sm:px-4 py-2.5 text-sm" aria-label="Siguiente">
                <span class="hidden sm:inline">Siguiente</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
        <p class="kb-hint text-center mt-2.5">← → &nbsp;·&nbsp; Espacio &nbsp;·&nbsp; O vista general &nbsp;·&nbsp; F pantalla completa &nbsp;·&nbsp; Desliza en móvil</p>
    </footer>

    <div id="overview-panel" class="overview-panel" role="dialog" aria-modal="true" aria-label="Vista general de diapositivas">
        <div class="liquid-glass w-full max-w-4xl max-h-[85vh] overflow-auto p-5 sm:p-6">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h2 class="font-bold text-lg">Todas las diapositivas</h2>
                <button type="button" class="btn-glass px-3 py-1.5 text-xs" data-close-overview>Cerrar</button>
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach ($slides as $index => $slide)
                    <button type="button"
                            class="liquid-glass liquid-glass--interactive text-left p-4 overview-item"
                            data-overview-goto="{{ $index }}">
                        <span class="text-[10px] font-semibold text-[var(--accent)]">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }} · {{ $slide['module'] }}</span>
                        <p class="font-semibold text-sm mt-1 leading-snug">{{ $slide['title'] }}</p>
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    <script>
        (function () {
            const slides = document.querySelectorAll('.slide');
            const total = slides.length;
            let current = 0;
            let isAnimating = false;
            let timelineUnlocked = 0;

            const btnPrev = document.getElementById('btn-prev');
            const btnNext = document.getElementById('btn-next');
            const counter = document.getElementById('slide-counter');
            const progressBar = document.getElementById('progress-bar');
            const bgGlow = document.getElementById('bg-glow');
            const dots = document.querySelectorAll('.dot');
            const overviewPanel = document.getElementById('overview-panel');
            const slideData = @json($slides);

            function updateUI() {
                counter.textContent = `${current + 1} / ${total}`;
                progressBar.style.width = `${((current + 1) / total) * 100}%`;
                btnPrev.disabled = current === 0;
                btnNext.disabled = current === total - 1;

                const color = slideData[current]?.color ?? '#FCA311';
                bgGlow.style.background = color;

                dots.forEach((dot, i) => {
                    dot.classList.toggle('active', i === current);
                });

                initSlideInteractivity(slides[current]);
            }

            function resetSlideState(slide) {
                slide.classList.remove('exit-left', 'exit-right');
                slide.style.transform = '';
                slide.style.opacity = '';
                slide.style.zIndex = '';
            }

            function goTo(index, direction = 1) {
                if (isAnimating || index === current || index < 0 || index >= total) {
                    return;
                }

                isAnimating = true;
                const outgoing = slides[current];
                const incoming = slides[index];
                const exitClass = direction > 0 ? 'exit-left' : 'exit-right';
                const enterFrom = direction > 0 ? 'translateX(36px)' : 'translateX(-36px)';

                resetSlideState(incoming);
                resetSlideState(outgoing);

                incoming.style.transform = enterFrom;
                incoming.style.opacity = '0';
                incoming.style.zIndex = '3';

                outgoing.classList.remove('active');
                outgoing.classList.add(exitClass);

                requestAnimationFrame(() => {
                    incoming.classList.add('active');
                    incoming.style.transform = '';
                    incoming.style.opacity = '';
                    incoming.style.zIndex = '';

                    setTimeout(() => {
                        outgoing.classList.remove('active', 'exit-left', 'exit-right');
                        resetSlideState(outgoing);
                        current = index;
                        updateUI();
                        isAnimating = false;
                    }, 450);
                });
            }

            function next() { goTo(current + 1, 1); }
            function prev() { goTo(current - 1, -1); }

            function setExclusiveActive(nodes, activeNode) {
                nodes.forEach((node) => node.classList.toggle('is-active', node === activeNode));
            }

            function initSlideInteractivity(slideEl) {
                if (! slideEl || slideEl.dataset.bound === 'true') {
                    bindOnceHandlers(slideEl);
                    return;
                }

                bindOnceHandlers(slideEl);
                slideEl.dataset.bound = 'true';
            }

            function bindOnceHandlers(slideEl) {
                if (! slideEl) {
                    return;
                }

                const playWaterfall = slideEl.querySelector('[data-play-waterfall]');
                if (playWaterfall && ! playWaterfall.dataset.ready) {
                    playWaterfall.dataset.ready = '1';
                    playWaterfall.addEventListener('click', () => {
                        const steps = slideEl.querySelectorAll('.waterfall-step');
                        const danger = slideEl.querySelector('[data-waterfall-danger]');
                        steps.forEach((step) => step.classList.remove('is-done'));
                        if (danger) {
                            danger.classList.add('hidden');
                        }
                        steps.forEach((step, i) => {
                            setTimeout(() => {
                                step.classList.add('is-done');
                                if (i === steps.length - 1 && danger) {
                                    danger.classList.remove('hidden');
                                }
                            }, (i + 1) * 450);
                        });
                    });
                }

                const speakerToggle = slideEl.querySelector('[data-toggle-speaker]');
                if (speakerToggle && ! speakerToggle.dataset.ready) {
                    speakerToggle.dataset.ready = '1';
                    speakerToggle.addEventListener('click', () => {
                        slideEl.querySelector('[data-speaker-note]')?.classList.toggle('is-open');
                    });
                }

                slideEl.querySelectorAll('.module-chip').forEach((chip) => {
                    if (chip.dataset.ready) {
                        return;
                    }
                    chip.dataset.ready = '1';
                    chip.addEventListener('click', () => {
                        const chips = slideEl.querySelectorAll('.module-chip');
                        setExclusiveActive(chips, chip);
                        const index = Number(chip.dataset.moduleIndex);
                        const mod = slideData[current]?.data?.modules?.[index];
                        if (mod) {
                            const label = slideEl.querySelector('[data-cycle-label]');
                            const feature = slideEl.querySelector('[data-cycle-feature]');
                            if (label) {
                                label.textContent = mod.sprint;
                            }
                            if (feature) {
                                feature.textContent = mod.feature;
                            }
                        }
                    });
                });

                slideEl.querySelectorAll('.role-card').forEach((card) => {
                    if (card.dataset.ready) {
                        return;
                    }
                    card.dataset.ready = '1';
                    card.addEventListener('click', () => {
                        setExclusiveActive(slideEl.querySelectorAll('.role-card'), card);
                    });
                });

                slideEl.querySelectorAll('.artifact-card').forEach((card) => {
                    if (card.dataset.ready) {
                        return;
                    }
                    card.dataset.ready = '1';
                    card.addEventListener('click', () => {
                        const index = card.dataset.artifactIndex;
                        setExclusiveActive(slideEl.querySelectorAll('.artifact-card'), card);
                        slideEl.querySelectorAll('[data-artifact-panel]').forEach((panel) => {
                            panel.style.display = panel.dataset.artifactPanel === index ? 'block' : 'none';
                        });
                    });
                });

                slideEl.querySelectorAll('.lifecycle-step').forEach((step) => {
                    if (step.dataset.ready) {
                        return;
                    }
                    step.dataset.ready = '1';
                    step.addEventListener('click', () => {
                        setExclusiveActive(slideEl.querySelectorAll('.lifecycle-step'), step);
                    });
                });

                slideEl.querySelectorAll('.benefit-card').forEach((card) => {
                    if (card.dataset.ready) {
                        return;
                    }
                    card.dataset.ready = '1';
                    card.addEventListener('click', () => {
                        setExclusiveActive(slideEl.querySelectorAll('.benefit-card'), card);
                    });
                });

                slideEl.querySelectorAll('.pillar-btn').forEach((btn) => {
                    if (btn.dataset.ready) {
                        return;
                    }
                    btn.dataset.ready = '1';
                    btn.addEventListener('click', () => {
                        setExclusiveActive(slideEl.querySelectorAll('.pillar-btn'), btn);
                    });
                });

                const timelineNext = slideEl.querySelector('[data-timeline-next]');
                const timelineReset = slideEl.querySelector('[data-timeline-reset]');
                if (timelineNext && ! timelineNext.dataset.ready) {
                    timelineNext.dataset.ready = '1';
                    timelineUnlocked = 0;
                    timelineNext.addEventListener('click', () => {
                        const sprints = slideEl.querySelectorAll('.timeline-sprint');
                        if (timelineUnlocked < sprints.length - 1) {
                            timelineUnlocked += 1;
                        }
                        sprints.forEach((sprint, i) => {
                            const unlocked = i <= timelineUnlocked;
                            sprint.classList.toggle('is-unlocked', unlocked);
                            sprint.classList.toggle('liquid-glass--accent', unlocked && i === timelineUnlocked);
                        });
                        const banner = slideEl.querySelector('[data-mvp-banner]');
                        if (banner) {
                            banner.style.display = timelineUnlocked >= sprints.length - 1 ? 'block' : 'none';
                        }
                    });
                }
                if (timelineReset && ! timelineReset.dataset.ready) {
                    timelineReset.dataset.ready = '1';
                    timelineReset.addEventListener('click', () => {
                        timelineUnlocked = 0;
                        const sprints = slideEl.querySelectorAll('.timeline-sprint');
                        sprints.forEach((sprint, i) => {
                            sprint.classList.toggle('is-unlocked', i === 0);
                            sprint.classList.toggle('liquid-glass--accent', i === 0);
                        });
                        const banner = slideEl.querySelector('[data-mvp-banner]');
                        if (banner) {
                            banner.style.display = 'none';
                        }
                    });
                }
            }

            btnNext.addEventListener('click', next);
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
                if (e.target === overviewPanel) {
                    overviewPanel.classList.remove('is-open');
                }
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
                    if (e.key === 'Escape') {
                        overviewPanel.classList.remove('is-open');
                    }
                    return;
                }

                if (e.key === 'ArrowRight' || e.key === ' ') {
                    e.preventDefault();
                    next();
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
            document.addEventListener('touchstart', (e) => {
                touchStartX = e.changedTouches[0].screenX;
            }, { passive: true });

            document.addEventListener('touchend', (e) => {
                if (overviewPanel.classList.contains('is-open')) {
                    return;
                }
                const diff = touchStartX - e.changedTouches[0].screenX;
                if (Math.abs(diff) > 50) {
                    diff > 0 ? next() : prev();
                }
            }, { passive: true });

            updateUI();
        })();
    </script>
</body>
</html>
