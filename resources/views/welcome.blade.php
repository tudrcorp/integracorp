<!DOCTYPE html>
<html lang="es" class="scroll-smooth" style="scroll-behavior: smooth">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>IIntegraCorp - Sistema Integral de Gestión para Empresas</title>

    <!-- Descripción SEO (entre 150-160 caracteres) -->
    <meta name="description" content="Integracorp combina salud y tecnología para ofrecer soluciones médicas digitales, telemedicina y plataformas avanzadas para agentes, agencias y pacientes.">

    <!-- Palabras clave (opcional, pero útil) -->
    <meta name="keywords" content="salud, tecnología, telemedicina, plataforma médica, digitalización salud, agencias médicas, Venezuela, Integracorp, telesalud, innovación médica">

    <!-- Canonical URL -->
    <link rel="canonical" href="https://integracorp.tudrgroup.com" />

    <!-- Open Graph (para compartir en redes sociales) -->
    <meta property="og:title" content="Integracorp | Salud y Tecnología">
    <meta property="og:description" content="Soluciones integrales que fusionan salud y tecnología para transformar el sector médico.">
    <meta property="og:image" content="https://integracorp.tudrgroup.com/image/logoTDG.png">
    <meta property="og:url" content="https://integracorp.tudrgroup.com">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Integracorp">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:site" content="@integracorp">
    <meta name="twitter:title" content="Integracorp | Salud y Tecnología">
    <meta name="twitter:description" content="Plataforma líder en integración de servicios médicos y tecnología digital.">
    <meta name="twitter:image" content="https://integracorp.tudrgroup.com/image/logoTDG.png">

    <!-- Favicon e icono de app (Android / iOS) -->
    <link rel="icon" href="{{ asset('image/ico_Android_IOS.png') }}" type="image/jpeg">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('image/ico_Android_IOS.png') }}">
    <link rel="apple-touch-icon" sizes="152x152" href="{{ asset('image/ico_Android_IOS.png') }}">
    <link rel="apple-touch-icon" sizes="120x120" href="{{ asset('image/ico_Android_IOS.png') }}">
    <link rel="icon" type="image/jpeg" sizes="32x32" href="{{ asset('image/ico_Android_IOS.png') }}">
    <link rel="icon" type="image/jpeg" sizes="16x16" href="{{ asset('image/ico_Android_IOS.png') }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">



    <!-- Tailwind CSS via Vite o CDN (solo para desarrollo) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <!-- O si usas CDN (prototipado rápido): -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>


    <!-- Google Fonts (Montserrat para elegancia) -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Estilos personalizados -->
    <style>
        body,
        html {
            margin: 0;
            padding: 0;
            height: 100%;
            font-family: 'Montserrat', sans-serif;
            overflow: hidden;
        }

        .glow-button {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.4s ease;
            background: white;
            color: #1e40af;
            position: relative;
            overflow: hidden;
        }

        .glow-button::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 10px;
            height: 10px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.8) 0%, transparent 70%);
            transform: scale(0) translate(-50%, -50%);
            opacity: 0;
            transition: all 0.6s ease;
            pointer-events: none;
        }

        .glow-button:hover::before {
            transform: scale(80) translate(-50%, -50%);
            opacity: 1;
        }

        .glow-button:hover {
            transform: translateY(-8px) scale(1.05);
            box-shadow: 0 15px 30px rgba(30, 64, 175, 0.35), 0 0 20px rgba(30, 64, 175, 0.25);
        }

        .menu-palette-item {
            --menu-hover-color: #4E8EA2;
            transition: transform 0.2s ease, box-shadow 0.22s ease, border-color 0.22s ease;
        }

        .menu-palette-item:hover {
            border-color: rgba(255, 255, 255, 0.28);
            box-shadow: 0 14px 26px -18px var(--menu-hover-color), 0 0 20px -12px var(--menu-hover-color);
        }

    </style>
</head>
<body class="relative bg-black text-white">

    <!-- Video Fullscreen de fondo -->
    <div class="absolute inset-0 z-0">
        <img src="{{ asset('image/i2.jpg') }}" class="w-full h-full object-cover"  alt="">
        <div class="absolute inset-0 bg-black bg-opacity-70"></div>
    </div>

    <!-- Logo - Esquina superior derecha -->
    <div class="absolute top-6 right-6 z-20">
        <img src="{{ asset('image/logoTDG.png') }}" alt="Logo" class="h-12 md:h-14 lg:h-16 w-auto drop-shadow-lg">
    </div>

    <!-- Texto principal - Centrado en el medio de la pantalla -->
    <div class="absolute inset-0 flex flex-col items-center justify-center z-10 px-4 text-center">

        <!-- Línea 1: INTEGRACORP -->
        <h1 class="text-5xl md:text-7xl lg:text-8xl font-bold mb-4 leading-tight">
            <span class="bg-gradient-to-b from-white to-gray-300 bg-clip-text drop-shadow-lg">
                INTEGRACORP
            </span>
        </h1>

        <!-- Línea 2: Salud y Tecnología, Tecnología y Salud -->
        <p class="text-lg md:text-xl lg:text-2xl font-light text-gray-200 max-w-2xl mx-auto">
            <span class="mt-1 text-blue-200 font-medium">Salud y Tecnología</span> | <span class="mt-1 ttext-blue-200 font-medium">Tecnología y Salud</span>


        </p>

    </div>


    <!-- Menú desplegable - Esquina superior izquierda -->
    @php
        $menuHoverPalette = ['#001D39', '#0A4174', '#49769F', '#4E8EA2', '#6EA2B3', '#7BBDE8', '#BDD8E9'];

        $panelMenuItems = [
            [
                'label' => 'ADMIN',
                'route' => route('filament.admin.auth.login'),
                'icon' => 'M7.864 4.243A7.5 7.5 0 0 1 19.5 10.5c0 2.92-.556 5.709-1.568 8.268M5.742 6.364A7.465 7.465 0 0 0 4.5 10.5a7.464 7.464 0 0 1-1.15 3.993m1.989 3.559A11.209 11.209 0 0 0 8.25 10.5a3.75 3.75 0 1 1 7.5 0c0 .527-.021 1.049-.064 1.565M12 10.5a14.94 14.94 0 0 1-3.6 9.75m6.633-4.596a18.666 18.666 0 0 1-2.485 5.33',
                'accent' => 'from-blue-500/30 to-cyan-400/15',
            ],
            [
                'label' => 'AGENCIA MASTER',
                'route' => route('filament.master.auth.login'),
                'icon' => 'M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z',
                'accent' => 'from-indigo-500/30 to-blue-400/15',
            ],
            [
                'label' => 'AGENCIA GENERAL',
                'route' => route('filament.general.auth.login'),
                'icon' => 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21',
                'accent' => 'from-emerald-500/30 to-green-400/15',
            ],
            [
                'label' => 'AGENTE',
                'route' => route('filament.agents.auth.login'),
                'icon' => 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z',
                'accent' => 'from-teal-500/30 to-cyan-400/15',
            ],
            [
                'label' => 'MARKETING',
                'route' => route('filament.marketing.auth.login'),
                'icon' => 'M12 18v-5.25m0 0a6.01 6.01 0 0 0 1.5-.189m-1.5.189a6.01 6.01 0 0 1-1.5-.189m3.75 7.478a12.06 12.06 0 0 1-4.5 0m3.75 2.383a14.406 14.406 0 0 1-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 1 0-7.517 0c.85.493 1.509 1.333 1.509 2.316V18',
                'accent' => 'from-fuchsia-500/30 to-purple-400/15',
            ],
            [
                'label' => 'TELEMEDICINA',
                'route' => route('filament.telemedicina.auth.login'),
                'icon' => 'M14.25 9.75v-4.5m0 4.5h4.5m-4.5 0 6-6m-3 18c-8.284 0-15-6.716-15-15V4.5A2.25 2.25 0 0 1 4.5 2.25h1.372c.516 0 .966.351 1.091.852l1.106 4.423c.11.44-.054.902-.417 1.173l-1.293.97a1.062 1.062 0 0 0-.38 1.21 12.035 12.035 0 0 0 7.143 7.143c.441.162.928-.004 1.21-.38l.97-1.293a1.125 1.125 0 0 1 1.173-.417l4.423 1.106c.5.125.852.575.852 1.091V19.5a2.25 2.25 0 0 1-2.25 2.25h-2.25Z',
                'accent' => 'from-cyan-500/30 to-sky-400/15',
            ],
            [
                'label' => 'NEGOCIOS',
                'route' => route('filament.business.auth.login'),
                'icon' => 'M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5m.75-9 3-3 2.148 2.148A12.061 12.061 0 0 1 16.5 7.605',
                'accent' => 'from-violet-500/30 to-indigo-400/15',
            ],
            [
                'label' => 'ADMINISTRACION',
                'route' => route('filament.administration.auth.login'),
                'icon' => 'M10.5 6a7.5 7.5 0 1 0 7.5 7.5h-7.5V6Z M13.5 10.5H21A7.5 7.5 0 0 0 13.5 3v7.5Z',
                'accent' => 'from-rose-500/30 to-orange-400/15',
            ],
            [
                'label' => 'OPERACIONES',
                'route' => route('filament.operations.auth.login'),
                'icon' => 'M9 9V4.5M9 9H4.5M9 9 3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5 5.25 5.25',
                'accent' => 'from-amber-500/30 to-yellow-400/15',
            ],
        ];

        $panelMenuItems = collect($panelMenuItems)
            ->values()
            ->map(function (array $item, int $index) use ($menuHoverPalette): array {
                $item['hover'] = $menuHoverPalette[$index % count($menuHoverPalette)];

                return $item;
            })
            ->all();
    @endphp

    <div class="absolute top-6 left-4 z-40 sm:top-8 sm:left-6" x-data="{ open: false }" @keydown.escape.window="open = false">
        <button
            @click="open = !open"
            class="group inline-flex items-center gap-2 rounded-full border border-white/20 bg-black/45 px-3 py-1.5 text-[11px] font-semibold text-white shadow-lg backdrop-blur-md transition-all duration-200 hover:border-cyan-300/45 hover:bg-black/55 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300/65 sm:text-xs"
            :aria-expanded="open.toString()"
            aria-label="Abrir menú de accesos"
        >
            <div class="grid gap-1.5">
                <span class="block h-0.5 w-5 rounded-full bg-white/80 transition-all duration-200 group-hover:bg-white"></span>
                <span class="block h-0.5 w-5 rounded-full bg-white/80 transition-all duration-200 group-hover:bg-white"></span>
            </div>
            <span>Menú</span>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-white/80 transition-transform duration-200" :class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
            </svg>
        </button>

        <!-- Menú desktop -->
        <div
            x-cloak
            x-show="open"
            @click.away="open = false"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 -translate-y-1 scale-95"
            class="hidden sm:block origin-top-left absolute mt-2.5 w-[16.5rem] overflow-hidden rounded-xl border border-white/20 bg-black/55 p-1.5 text-xs text-gray-200 shadow-2xl backdrop-blur-xl"
        >
            <div class="max-h-[56vh] space-y-1 overflow-y-auto pr-0.5">
                @foreach ($panelMenuItems as $item)
                    <a
                        href="{{ $item['route'] }}"
                        style="--menu-hover-color: {{ $item['hover'] }};"
                        class="menu-palette-item group relative flex items-center gap-2.5 overflow-hidden rounded-lg border border-transparent px-2.5 py-2 text-gray-100 transition-all duration-200 hover:-translate-y-0.5 hover:bg-white/10 focus:outline-none focus-visible:border-cyan-300/50 focus-visible:bg-white/10"
                    >
                        <div class="relative z-10 flex h-8 w-8 items-center justify-center rounded-md bg-gradient-to-br {{ $item['accent'] }} ring-1 ring-white/20">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4.5 w-4.5 text-white">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" />
                            </svg>
                        </div>
                        <div class="relative z-10 min-w-0">
                            <p class="truncate text-[12px] font-semibold tracking-wide text-white">{{ $item['label'] }}</p>
                        </div>
                        <div class="relative z-10 ml-auto text-cyan-200/70 transition-transform duration-200 group-hover:translate-x-0.5">›</div>
                        <div class="absolute inset-0 opacity-0 blur-xl transition-opacity duration-200 group-hover:opacity-35" style="background: radial-gradient(circle at 10% 50%, {{ $item['hover'] }}, transparent 70%);"></div>
                    </a>
                @endforeach
            </div>
        </div>

        <!-- Menú mobile minimalista -->
        <div
            x-cloak
            x-show="open"
            class="fixed inset-0 z-50 sm:hidden"
        >
            <div
                class="absolute inset-0 bg-black/70 backdrop-blur-sm"
                @click="open = false"
            ></div>

            <div
                x-transition:enter="transition ease-out duration-220"
                x-transition:enter-start="opacity-0 translate-y-full"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-180"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 translate-y-full"
                class="absolute inset-x-0 bottom-0 rounded-t-2xl border-t border-white/15 bg-black/90 px-4 pb-6 pt-3 shadow-2xl"
            >
                <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-white/20"></div>

                <div class="max-h-[70vh] space-y-1.5 overflow-y-auto pr-0.5">
                    @foreach ($panelMenuItems as $item)
                        <a
                            href="{{ $item['route'] }}"
                            @click="open = false"
                            style="--menu-hover-color: {{ $item['hover'] }};"
                            class="menu-palette-item flex items-center gap-3 rounded-lg border border-white/10 bg-white/[0.04] px-3 py-2.5 text-xs font-medium text-white/95 transition hover:bg-white/[0.08] focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300/60"
                        >
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-gradient-to-br {{ $item['accent'] }} ring-1 ring-white/20">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 text-white">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" />
                                </svg>
                            </div>
                            <span class="truncate tracking-[0.02em]">{{ $item['label'] }}</span>
                            <span class="ml-auto text-white/45">›</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    @includeWhen(
        request()->routeIs('home'),
        'pwa.install-login',
        ['showInstallTrigger' => true]
    )

   <!-- Footer Full Width - Centrado en la parte inferior -->
   <footer class="absolute bottom-0 left-0 w-full z-20">
       <!-- Contenedor centrado con ancho máximo pero fondo full -->
       <div class="flex items-center justify-center">
           <div class="bg-black bg-opacity-60 backdrop-blur-sm border-t border-gray-700 text-center px-6 py-4 shadow-lg max-w-2xl w-full mx-4 rounded-none sm:rounded-t-2xl">
               <p class="text-sm md:text-base text-gray-200 font-light leading-tight">
                   © {{ date('Y') }} INTEGRACORP. Tu Doctor Group, Todos los derechos reservados.
               </p>
           </div>
       </div>
   </footer>



    <!-- Font Awesome para íconos -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/js/all.min.js" crossorigin="anonymous"></script>


</body>
</html>
