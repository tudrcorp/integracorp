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

    <!-- Favicon -->
    <link rel="icon" href="{{ asset('image/imagotipo.png') }}" type="image/x-icon">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('image/imagotipo.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('image/imagotipo.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('image/imagotipo.png') }}">
    <link rel="manifest" href="/site.webmanifest">



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


    <!-- Menú desplegable - Esquina superior izquierda con iconos y glow -->
    <div class="absolute top-8 left-6 z-30" x-data="{ open: false }" @click.away="open = false">
        <!-- Botón con ícono + texto "Menú" -->
        <button @click="open = !open" class="flex items-center space-x-2 px-4 py-2 rounded-full bg-black bg-opacity-30 backdrop-blur-sm border border-white border-opacity-20 hover:bg-opacity-50 transition-all duration-200 group focus:outline-none text-white text-sm font-medium" aria-label="Menú">
            <div class="flex space-x-1">
                {{-- <span class="block h-1 w-5 bg-white opacity-70 group-hover:opacity-100 transition"></span> --}}
                <span class="block h-1 w-5 bg-white opacity-70 group-hover:opacity-100 transition"></span>
                <span class="block h-1 w-5 bg-white opacity-70 group-hover:opacity-100 transition"></span>
            </div>
            <span>Menú</span>
        </button>

        <!-- Dropdown con iconos y efecto glow -->
        <div x-show="open" 
        x-transition:enter="transition ease-out duration-150" 
        x-transition:enter-start="opacity-0 transform scale-95" 
        x-transition:enter-end="opacity-100 transform scale-100" 
        x-transition:leave="transition ease-in duration-100" 
        x-transition:leave-start="opacity-100 transform scale-100" 
        x-transition:leave-end="opacity-0 transform scale-95" 
        class="origin-top-left absolute mt-2 w-52 rounded-xl shadow-lg bg-black bg-opacity-20 backdrop-blur-sm border border-white border-opacity-20 hover:bg-opacity-200 overflow-hidden">
            <div class="py-1 text-sm text-gray-200">

                <!-- Item 1: Panel Principal -->
                <a href="{{ route('filament.admin.auth.login') }}" class="flex items-center px-4 py-3 hover:bg-white hover:bg-opacity-10 transition duration-200 group">


                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6 mr-3">

                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.864 4.243A7.5 7.5 0 0 1 19.5 10.5c0 2.92-.556 5.709-1.568 8.268M5.742 6.364A7.465 7.465 0 0 0 4.5 10.5a7.464 7.464 0 0 1-1.15 3.993m1.989 3.559A11.209 11.209 0 0 0 8.25 10.5a3.75 3.75 0 1 1 7.5 0c0 .527-.021 1.049-.064 1.565M12 10.5a14.94 14.94 0 0 1-3.6 9.75m6.633-4.596a18.666 18.666 0 0 1-2.485 5.33" />
                    </svg>                      
                    <span class="group-hover:text-white transition">ADMIN</span>
                    <!-- Efecto glow al hacer hover -->
                    <div class="absolute inset-0 bg-gradient-to-r from-blue-500/10 to-transparent opacity-0 group-hover:opacity-100 blur-sm rounded-xl pointer-events-none"></div>
                </a>

                <!-- Item 1: Agencias Master -->
                <a href="{{ route('filament.master.auth.login') }}" class="flex items-center px-4 py-3 hover:bg-white hover:bg-opacity-10 transition duration-200 group">

                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6 mr-3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z" />
                    </svg>                      
                    <span class="group-hover:text-white transition">AGENCIA MASTER</span>
                    <!-- Efecto glow al hacer hover -->
                    <div class="absolute inset-0 bg-gradient-to-r from-blue-500/10 to-transparent opacity-0 group-hover:opacity-100 blur-sm rounded-xl pointer-events-none"></div>
                </a>

                <!-- Item 2: Agencias Generales -->
                <a href="{{ route('filament.general.auth.login') }}" class="flex items-center px-4 py-3 hover:bg-white hover:bg-opacity-10 transition duration-200 group">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6 mr-3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                    </svg>

                      
                    <span class="group-hover:text-white transition">AGENCIA GENERAL</span>
                    <div class="absolute inset-0 bg-gradient-to-r from-green-500/10 to-transparent opacity-0 group-hover:opacity-100 blur-sm rounded-xl pointer-events-none"></div>
                </a>

                <!-- Item 2: Agentes -->
                <a href="{{ route('filament.agents.auth.login') }}" class="flex items-center px-4 py-3 hover:bg-white hover:bg-opacity-10 transition duration-200 group">

                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6 mr-3">

                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>

                      
                    <span class="group-hover:text-white transition">AGENTE</span>
                    <div class="absolute inset-0 bg-gradient-to-r from-green-500/10 to-transparent opacity-0 group-hover:opacity-100 blur-sm rounded-xl pointer-events-none"></div>
                </a>

                <!-- Item 2: Marketing -->
                <a href="{{ route('filament.marketing.auth.login') }}" class="flex items-center px-4 py-3 hover:bg-white hover:bg-opacity-10 transition duration-200 group">

                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6 mr-3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 0 0 1.5-.189m-1.5.189a6.01 6.01 0 0 1-1.5-.189m3.75 7.478a12.06 12.06 0 0 1-4.5 0m3.75 2.383a14.406 14.406 0 0 1-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 1 0-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" />
                    </svg>

                      
                    <span class="group-hover:text-white transition">MARKETING</span>
                    <div class="absolute inset-0 bg-gradient-to-r from-green-500/10 to-transparent opacity-0 group-hover:opacity-100 blur-sm rounded-xl pointer-events-none"></div>
                </a>

                <!-- Item 2: Telemedicina -->
                <a href="{{ route('filament.telemedicina.auth.login') }}" class="flex items-center px-4 py-3 hover:bg-white hover:bg-opacity-10 transition duration-200 group">

                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6 mr-3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 9.75v-4.5m0 4.5h4.5m-4.5 0 6-6m-3 18c-8.284 0-15-6.716-15-15V4.5A2.25 2.25 0 0 1 4.5 2.25h1.372c.516 0 .966.351 1.091.852l1.106 4.423c.11.44-.054.902-.417 1.173l-1.293.97a1.062 1.062 0 0 0-.38 1.21 12.035 12.035 0 0 0 7.143 7.143c.441.162.928-.004 1.21-.38l.97-1.293a1.125 1.125 0 0 1 1.173-.417l4.423 1.106c.5.125.852.575.852 1.091V19.5a2.25 2.25 0 0 1-2.25 2.25h-2.25Z" />
                    </svg>

                      
                    <span class="group-hover:text-white transition">TELEMEDICINA</span>
                    <div class="absolute inset-0 bg-gradient-to-r from-green-500/10 to-transparent opacity-0 group-hover:opacity-100 blur-sm rounded-xl pointer-events-none"></div>
                </a>

                <!-- Item 2: Negocios -->
                <a href="{{ route('filament.business.auth.login') }}" class="flex items-center px-4 py-3 hover:bg-white hover:bg-opacity-10 transition duration-200 group">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6 mr-3">

                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5m.75-9 3-3 2.148 2.148A12.061 12.061 0 0 1 16.5 7.605" />
                    </svg>

                    <span class="group-hover:text-white transition">NEGOCIOS</span>
                    <div class="absolute inset-0 bg-gradient-to-r from-green-500/10 to-transparent opacity-0 group-hover:opacity-100 blur-sm rounded-xl pointer-events-none"></div>
                </a>

                <!-- Item 2: Administracion -->
                <a href="{{ route('filament.administration.auth.login') }}" class="flex items-center px-4 py-3 hover:bg-white hover:bg-opacity-10 transition duration-200 group">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6 mr-3">

                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 1 0 7.5 7.5h-7.5V6Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0 0 13.5 3v7.5Z" />
                    </svg>
                    <span class="group-hover:text-white transition">ADMINISTRACION</span>
                    <div class="absolute inset-0 bg-gradient-to-r from-green-500/10 to-transparent opacity-0 group-hover:opacity-100 blur-sm rounded-xl pointer-events-none"></div>
                </a>





            </div>
        </div>
    </div>

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
