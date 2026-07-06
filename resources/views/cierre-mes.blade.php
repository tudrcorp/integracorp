<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Cierre de Mes — IntegraCorp</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style>
        :root {
            --brand: #0064a1;
            --brand-light: #0ea5e9;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
            overflow: hidden;
            background: #030712;
        }

        #slides-container {
            position: fixed;
            top: 4.125rem;
            bottom: 6.25rem;
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
            #slides-container {
                padding-inline: 1.5rem;
            }
        }

        #slides-viewport {
            position: relative;
            width: 100%;
            height: 100%;
        }

        .slide {
            opacity: 0;
            transform: translateX(40px) scale(0.98);
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
        }

        .slide__inner {
            width: 100%;
        }

        .slide.active {
            opacity: 1;
            transform: translateX(0) scale(1);
            pointer-events: auto;
            z-index: 2;
        }

        .slide.exit-left {
            opacity: 0;
            transform: translateX(-40px) scale(0.98);
            z-index: 1;
        }

        .slide.exit-right {
            opacity: 0;
            transform: translateX(40px) scale(0.98);
            z-index: 1;
        }

        .highlight-item {
            opacity: 0;
            transform: translateY(12px);
            transition: opacity 0.35s ease, transform 0.35s ease;
        }

        .slide.active .highlight-item {
            opacity: 1;
            transform: translateY(0);
        }

        .slide.active .highlight-item:nth-child(1) { transition-delay: 0.1s; }
        .slide.active .highlight-item:nth-child(2) { transition-delay: 0.18s; }
        .slide.active .highlight-item:nth-child(3) { transition-delay: 0.26s; }
        .slide.active .highlight-item:nth-child(4) { transition-delay: 0.34s; }
        .slide.active .highlight-item:nth-child(5) { transition-delay: 0.42s; }

        .slide-image {
            opacity: 0;
            transform: scale(0.92) translateY(10px);
            transition: opacity 0.5s ease 0.08s, transform 0.5s ease 0.08s;
        }

        .slide.active .slide-image {
            opacity: 1;
            transform: scale(1) translateY(0);
        }

        .progress-fill {
            transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .dot {
            transition: all 0.25s ease;
        }

        .dot.active {
            transform: scale(1.4);
        }

        .bg-grid {
            background-image:
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 48px 48px;
        }

        .glow {
            filter: blur(80px);
            opacity: 0.35;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }

        .float-image { animation: float 4s ease-in-out infinite; }

        .tag-pill {
            backdrop-filter: blur(8px);
        }

        .system-preview {
            border-radius: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.12);
            background: rgba(255, 255, 255, 0.04);
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .system-preview__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.625rem 0.875rem;
            background: rgba(0, 0, 0, 0.35);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .system-preview__viewport {
            height: 22rem;
            overflow: hidden;
            background: #f8fafc;
            position: relative;
        }

        .system-preview__viewport iframe {
            width: 1440px;
            height: 900px;
            border: 0;
            transform: scale(var(--preview-scale, 0.3));
            transform-origin: 0 0;
            background: #fff;
        }

        .system-preview__foot {
            padding: 0.5rem 0.875rem 0.625rem;
            font-size: 0.6875rem;
            line-height: 1.4;
            color: rgb(156 163 175);
            border-top: 1px solid rgba(255, 255, 255, 0.06);
        }

        .brand-preview {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1.25rem;
            height: 22rem;
            border-radius: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.12);
            background: rgba(255, 255, 255, 0.04);
            padding: 2rem;
        }
    </style>
</head>
<body class="min-h-screen text-white select-none">

    <div class="fixed inset-0 bg-grid pointer-events-none" aria-hidden="true"></div>
    <div id="bg-glow" class="fixed w-96 h-96 rounded-full glow pointer-events-none transition-colors duration-700"
         style="top: 10%; right: 10%; background: var(--brand);"></div>
    <div class="fixed w-72 h-72 rounded-full glow pointer-events-none opacity-20"
         style="bottom: 5%; left: 5%; background: #8b5cf6;" aria-hidden="true"></div>

    <header class="fixed top-0 inset-x-0 z-50 flex items-center justify-between px-6 py-4 bg-gray-950/60 backdrop-blur-md border-b border-white/5">
        <div class="flex items-center gap-3">
            <img src="{{ asset('image/logo_new.png') }}" alt="Tu Dr. en Casa" class="h-8 opacity-90">
            <span class="text-xs text-gray-500 hidden sm:inline">IntegraCorp</span>
        </div>
        <div class="flex items-center gap-4">
            <span id="slide-counter" class="text-sm font-medium text-gray-400 tabular-nums">1 / {{ count($slides) }}</span>
            <span class="text-xs px-2.5 py-1 rounded-full bg-white/5 text-gray-400 border border-white/10">{{ $period }}</span>
        </div>
    </header>

    <div class="fixed top-[65px] inset-x-0 z-50 h-0.5 bg-white/5">
        <div id="progress-bar" class="progress-fill h-full bg-gradient-to-r from-[#0064a1] to-[#0ea5e9]" style="width: {{ round(100 / count($slides)) }}%"></div>
    </div>

    <main id="slides-container">
        <div id="slides-viewport">
        @foreach ($slides as $index => $slide)
            <article
                class="slide {{ $index === 0 ? 'active' : '' }}"
                data-index="{{ $index }}"
                data-color="{{ $slide['color'] }}"
                data-module="{{ $slide['module'] }}"
            >
                <div class="slide__inner grid lg:grid-cols-2 gap-8 lg:gap-12 items-center">
                    {{-- Texto --}}
                    <div class="flex flex-col gap-6 order-2 lg:order-1">
                        <div>
                            <div class="flex flex-wrap items-center gap-2 mb-3">
                                <span class="text-xs font-semibold uppercase tracking-widest px-2.5 py-1 rounded-md tag-pill border border-white/10"
                                      style="color: {{ $slide['color'] }}; background: {{ $slide['color'] }}18;">
                                    {{ $slide['module'] }}
                                </span>
                                @foreach ($slide['tags'] as $tag)
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-white/5 text-gray-500 border border-white/5">{{ $tag }}</span>
                                @endforeach
                            </div>
                            <div class="flex items-center gap-3 mb-2">
                                <span class="text-3xl sm:text-4xl" aria-hidden="true">{{ $slide['icon'] }}</span>
                                <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold leading-tight tracking-tight">
                                    {{ $slide['title'] }}
                                </h1>
                            </div>
                            <p class="text-base sm:text-lg text-gray-400 leading-relaxed">
                                {{ $slide['subtitle'] }}
                            </p>
                        </div>

                        @if (count($slide['highlights']) > 0)
                            <ul class="grid gap-2.5 sm:gap-3">
                                @foreach ($slide['highlights'] as $highlight)
                                    <li class="highlight-item flex items-start gap-3 p-3.5 sm:p-4 rounded-xl bg-white/[0.03] border border-white/[0.06]">
                                        <span class="mt-0.5 shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold"
                                              style="background: {{ $slide['color'] }}25; color: {{ $slide['color'] }};">✓</span>
                                        <span class="text-sm sm:text-base text-gray-300 leading-relaxed">{{ $highlight }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    {{-- Vista del sistema en vivo --}}
                    <div class="slide-image order-1 lg:order-2 flex justify-center lg:justify-end w-full">
                        <div class="relative w-full max-w-sm lg:max-w-md">
                            <div class="absolute -inset-3 rounded-3xl opacity-30 blur-2xl pointer-events-none"
                                 style="background: {{ $slide['color'] }};"></div>

                            @if (($slide['preview']['type'] ?? '') === 'system' && ! empty($slide['preview']['url']))
                                <div
                                    class="system-preview relative"
                                    data-system-preview
                                    data-slide-index="{{ $index }}"
                                    data-system-url="{{ $slide['preview']['url'] }}"
                                >
                                    <div class="system-preview__head">
                                        <span class="text-[11px] font-medium text-gray-300 truncate">
                                            {{ $slide['preview']['panel'] }}
                                        </span>
                                        <a href="{{ $slide['preview']['url'] }}"
                                           target="_blank"
                                           rel="noopener"
                                           class="shrink-0 text-[11px] font-semibold px-2 py-1 rounded-md hover:brightness-110 transition-all text-white"
                                           style="background: {{ $slide['color'] }};">
                                            Abrir ↗
                                        </a>
                                    </div>
                                    <div class="system-preview__viewport">
                                        <iframe
                                            title="Vista en vivo: {{ $slide['title'] }}"
                                            sandbox="allow-same-origin allow-scripts allow-forms allow-popups allow-modals"
                                        ></iframe>
                                    </div>
                                    @if (! empty($slide['preview']['tip']))
                                        <p class="system-preview__foot">{{ $slide['preview']['tip'] }}</p>
                                    @endif
                                </div>
                            @else
                                <div class="brand-preview float-image relative">
                                    <img src="{{ asset('image/logo_new.png') }}" alt="Tu Dr. en Casa" class="h-16 sm:h-20 opacity-95">
                                    <p class="text-sm text-gray-400 text-center max-w-xs">
                                        Vista en vivo del sistema en cada diapositiva
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </article>
        @endforeach
        </div>
    </main>

    <footer class="fixed bottom-0 inset-x-0 z-50 px-6 py-5 bg-gray-950/70 backdrop-blur-md border-t border-white/5">
        <div class="max-w-6xl mx-auto flex items-center justify-between gap-4">
            <button id="btn-prev" type="button"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-sm font-medium text-gray-300 transition-all disabled:opacity-30 disabled:cursor-not-allowed"
                    aria-label="Anterior">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Anterior
            </button>

            <div id="dots" class="flex items-center gap-1.5 flex-wrap justify-center max-w-xs sm:max-w-md overflow-hidden">
                @foreach ($slides as $index => $slide)
                    <button type="button"
                            class="dot w-2 h-2 rounded-full bg-white/20 hover:bg-white/40 {{ $index === 0 ? 'active !bg-white' : '' }}"
                            data-goto="{{ $index }}"
                            aria-label="Ir a la diapositiva {{ $index + 1 }}"
                            style="{{ $index === 0 ? 'background:' . $slide['color'] : '' }}">
                    </button>
                @endforeach
            </div>

            <button id="btn-next" type="button"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium text-white transition-all hover:brightness-110"
                    style="background: var(--brand);"
                    aria-label="Siguiente">
                Siguiente
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
        <p class="text-center text-[10px] text-gray-600 mt-3">Flechas ← → &nbsp;·&nbsp; Inicia sesión en el módulo si la vista pide login &nbsp;·&nbsp; Desliza en móvil</p>
    </footer>

    <script>
        (function () {
            const slides = document.querySelectorAll('.slide');
            const total = slides.length;
            let current = 0;
            let isAnimating = false;

            const btnPrev = document.getElementById('btn-prev');
            const btnNext = document.getElementById('btn-next');
            const counter = document.getElementById('slide-counter');
            const progressBar = document.getElementById('progress-bar');
            const bgGlow = document.getElementById('bg-glow');
            const dots = document.querySelectorAll('.dot');

            const slideData = @json($slides);

            function updatePreviewScales() {
                document.querySelectorAll('.system-preview__viewport').forEach((viewport) => {
                    const scale = viewport.clientWidth / 1440;
                    viewport.style.setProperty('--preview-scale', String(scale));
                });
            }

            function activateSystemPreview(index) {
                const preview = document.querySelector(`[data-system-preview][data-slide-index="${index}"]`);

                if (! preview) {
                    return;
                }

                const iframe = preview.querySelector('iframe');
                const url = preview.dataset.systemUrl;

                if (iframe && url && iframe.dataset.loaded !== 'true') {
                    iframe.src = url;
                    iframe.dataset.loaded = 'true';
                }

                updatePreviewScales();
            }

            function updateUI() {
                counter.textContent = `${current + 1} / ${total}`;
                progressBar.style.width = `${((current + 1) / total) * 100}%`;
                btnPrev.disabled = current === 0;
                btnNext.disabled = current === total - 1;

                const color = slideData[current]?.color ?? '#0064a1';
                bgGlow.style.background = color;
                document.documentElement.style.setProperty('--brand', color);

                dots.forEach((dot, i) => {
                    const isActive = i === current;
                    dot.classList.toggle('active', isActive);
                    dot.style.background = isActive ? (slideData[i]?.color ?? '#fff') : '';
                });

                activateSystemPreview(current);
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
                const enterFrom = direction > 0 ? 'translateX(40px)' : 'translateX(-40px)';

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

            btnNext.addEventListener('click', next);
            btnPrev.addEventListener('click', prev);

            dots.forEach(dot => {
                dot.addEventListener('click', () => {
                    const target = parseInt(dot.dataset.goto, 10);
                    goTo(target, target > current ? 1 : -1);
                });
            });

            document.addEventListener('keydown', (e) => {
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
                }
            });

            let touchStartX = 0;
            document.addEventListener('touchstart', (e) => {
                touchStartX = e.changedTouches[0].screenX;
            }, { passive: true });

            document.addEventListener('touchend', (e) => {
                const diff = touchStartX - e.changedTouches[0].screenX;
                if (Math.abs(diff) > 50) {
                    diff > 0 ? next() : prev();
                }
            }, { passive: true });

            updateUI();
            window.addEventListener('resize', updatePreviewScales);
        })();
    </script>
</body>
</html>
