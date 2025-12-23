<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Buscador de Proveedores Médico-Especializados</title>
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Estilos base del mapa (se ajusta en JS para el modo oscuro) */
        #map {
            height: 60vh;
            width: 100%;
            border-radius: 1rem;
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            background-color: #f3f4f6;
        }

        /* Estilización de la InfoWindow de Google Maps */
        .info-window-content {
            font-family: 'Inter', sans-serif;
            padding: 8px;
            max-width: 200px;
        }

        /* Estilo del Loader */
        .loading-ring {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, .3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

    </style>
    <script>
        // Configuración de Tailwind para soportar dark mode
        tailwind.config = {
            darkMode: 'class', // Habilitar el modo oscuro basado en la clase 'dark' en el <html>
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif']
                    }
                    , colors: {
                        medical: {
                            50: '#f0f9ff'
                            , 100: '#e0f2fe'
                            , 600: '#0284c7'
                            , 700: '#0369a1'
                        , }
                    }
                }
            }
        }

    </script>
</head>
<body class="bg-slate-50 dark:bg-slate-900 min-h-screen font-sans text-slate-900 dark:text-slate-100 transition-colors duration-300">
    <div class="max-w-6xl mx-auto px-4 py-4">

        <!-- Header y Control de Tema -->
        <header class="text-center mb-10 relative">

            <!-- Toggle de Tema (Usando Heroicons SVG) -->
            <button id="themeToggle" class="absolute top-0 right-0 p-2 rounded-full text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors duration-200">
                <!-- Icono de Sol (Modo Claro) / Icono de Luna (Modo Oscuro) -->
                <svg id="sunIcon" class="w-6 h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
                <svg id="moonIcon" class="w-6 h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                </svg>
            </button>
            <div class="flex justify-center items-center p-5">
                <img src="{{ asset('image/logoNewTDG.png') }}" alt="" class="w-1/2 h-auto p-5">
            </div>

            <h1 class="text-4xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100 sm:text-5xl">
                Localizador <span class="text-medical-600 dark:text-medical-100">Médico</span>
            </h1>
            <p class="mt-4 text-lg text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
                Encuentra tu centros de salud y/o especialistas cerca de tu ubicación actual.
            </p>
        </header>

        <main class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl shadow-slate-200/60 dark:shadow-slate-950/50 overflow-hidden border border-slate-100 dark:border-slate-700 transition-colors duration-300">
            <!-- Panel de Controles -->
            <div class="p-6 md:p-8 border-b border-slate-100 dark:border-slate-700 bg-white dark:bg-slate-800">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <!-- Buscador de Dirección -->
                    <div class="md:col-span-6 relative">
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2 ml-1">Dirección de búsqueda</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                                📍
                            </span>
                            <input type="text" id="addressInput" placeholder="Ej: Las Mercedes, Caracas" class="w-full pl-10 pr-4 py-3 bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl focus:ring-2 focus:ring-medical-600 dark:focus:ring-medical-600 focus:bg-white dark:focus:bg-slate-800 transition-all outline-none text-slate-900 dark:text-slate-100" />
                        </div>
                    </div>

                    <!-- Radio -->
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2 ml-1">Radio (km)</label>
                        <input type="number" id="radiusInput" value="10" min="1" max="50" class="w-full p-3 bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl focus:ring-2 focus:ring-medical-600 dark:focus:ring-medical-600 focus:bg-white dark:focus:bg-slate-800 transition-all outline-none text-slate-900 dark:text-slate-100" />
                    </div>

                    <!-- Botón -->
                    <div class="md:col-span-4 flex items-end">
                        <button id="searchButton" onclick="searchAndRender()" class="w-full py-3.5 bg-medical-600 hover:bg-medical-700 text-white font-bold rounded-xl shadow-lg shadow-medical-600/20 transition-all transform active:scale-[0.98] flex items-center justify-center gap-2 disabled:bg-slate-400 disabled:shadow-none">
                            <span id="buttonText">Buscar Ahora</span>
                            <span id="loadingIndicator" class="loading-ring hidden"></span>
                        </button>
                    </div>
                </div>

                <!-- Sección de Filtros Minimalistas -->
                <div class="mt-8">
                    <h3 class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-[0.2em] mb-4 ml-1">
                        Filtrar por tipo de servicio
                    </h3>
                    <div id="filtersContainer" class="flex flex-wrap gap-2 overflow-x-auto pb-2">
                        <!-- Renderizado dinámico aquí -->
                    </div>
                </div>
            </div>

            <!-- Mapa y Mensajes -->
            <div class="p-4 bg-slate-50 dark:bg-slate-900">
                <div id="statusMessage" class="mb-4 p-4 rounded-xl text-sm font-semibold hidden animate-pulse"></div>
                <div id="map"></div>
            </div>
        </main>

        <footer class="mt-8 text-center text-slate-400 dark:text-slate-500 text-sm">
            &copy; 2024 Buscador Especializado INTEGRACORP. Todos los derechos reservados.
        </footer>
    </div>

    <script>
        const GOOGLE_API_KEY = "AIzaSyB-lD2RaF292fzeb2TydGYng6cKMuIJMiQ";
        const DEFAULT_CENTER = {
            lat: 10.4806
            , lng: -66.9036
        };

        let map, geocoder, placesService, infoWindow, userMarker = null
            , providerMarkers = [];
        let mapLoaded = false; // Flag para asegurar la inicialización única
        let mapStyle = 'roadmap'; // Tipo de mapa por defecto

        const POI_TYPES = [{
                type: 'pharmacy'
                , keyword: 'farmacia'
                , name: 'Farmacia'
                , color: '#3b82f6'
                , icon: '💊'
            }
            , {
                type: 'hospital'
                , keyword: 'hospital'
                , name: 'Hospital'
                , color: '#ef4444'
                , icon: '🏥'
            }
            , {
                type: 'doctor'
                , keyword: 'clínica'
                , name: 'Clínica'
                , color: '#f97316'
                , icon: '🏨'
            }
            , {
                type: 'health'
                , keyword: 'laboratorio clínico'
                , name: 'Laboratorio'
                , color: '#10b981'
                , icon: '🔬'
            }
            , {
                type: 'health'
                , keyword: 'centro diagnóstico'
                , name: 'Imagenología'
                , color: '#6366f1'
                , icon: '🖼️'
            }
            , {
                type: 'doctor'
                , keyword: 'consultorio médico'
                , name: 'Consultorio'
                , color: '#a855f7'
                , icon: '🩺'
            }
        ];

        let debounceTimer;

        function debouncedSearch() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(searchAndRender, 500);
        }

        // --- LÓGICA DE MODO OSCURO ---
        function applyTheme(theme) {
            const html = document.documentElement;
            const isDark = theme === 'dark';

            if (isDark) {
                html.classList.add('dark');
                document.getElementById('sunIcon').classList.remove('hidden');
                document.getElementById('moonIcon').classList.add('hidden');
                mapStyle = 'dark_theme'; // Un estilo oscuro predefinido para el mapa
            } else {
                html.classList.remove('dark');
                document.getElementById('sunIcon').classList.add('hidden');
                document.getElementById('moonIcon').classList.remove('hidden');
                mapStyle = 'roadmap';
            }

            // Si el mapa ya está inicializado, actualiza su estilo
            if (mapLoaded) {
                map.setOptions({
                    mapId: isDark ? 'a350436d4090956b' : '4504f8b37365c3d0'
                });
            }
        }

        function toggleTheme() {
            const currentTheme = localStorage.getItem('theme') === 'dark' ? 'dark' : 'light';
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            localStorage.setItem('theme', newTheme);
            applyTheme(newTheme);
        }

        // --- RENDERIZADO DE FILTROS ---
        function renderFilters() {
            const container = document.getElementById('filtersContainer');
            container.innerHTML = '';

            POI_TYPES.forEach((poi, index) => {
                const inputId = `filter-${index}`;

                const label = document.createElement('label');
                label.setAttribute('for', inputId);
                label.className = `
                    cursor-pointer flex items-center gap-2 px-4 py-2 
                    rounded-full border-2 transition-all duration-200 select-none
                    active:scale-[0.98] text-sm font-semibold whitespace-nowrap
                `;

                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.id = inputId;
                checkbox.checked = true;
                checkbox.className = 'hidden';

                const updatePillStyle = (isChecked) => {
                    const isDark = document.documentElement.classList.contains('dark');
                    if (isChecked) {
                        label.style.borderColor = poi.color;
                        label.style.backgroundColor = `${poi.color}15`; // 15% opacity for background
                        label.style.color = isDark ? poi.color : '#1f2937'; // Dark text in light mode, color in dark mode
                    } else {
                        label.style.borderColor = isDark ? '#374151' : '#e2e8f0'; // slate-700 / slate-200
                        label.style.backgroundColor = isDark ? '#1f2937' : '#f8fafc'; // slate-800 / slate-50
                        // FIX: Usar slate-100 para que el texto sea visible en modo oscuro (deseleccionado).
                        label.style.color = isDark ? '#f1f5f9' : '#ffffff'; // slate-100 / slate-400
                    }
                };

                updatePillStyle(true);

                checkbox.addEventListener('change', (e) => {
                    updatePillStyle(e.target.checked);
                    // Re-aplicar estilos si el tema cambia
                    document.documentElement.addEventListener('click', () => updatePillStyle(e.target.checked));
                    if (window.lastSearchedLocation) debouncedSearch();
                });

                label.innerHTML = `<span>${poi.icon}</span> <span>${poi.name}</span>`;
                label.prepend(checkbox);
                container.appendChild(label);
            });
        }

        // --- LÓGICA DE MAPAS ---
        function updateStatus(message, isError = false) {
            const el = document.getElementById('statusMessage');
            const isDark = document.documentElement.classList.contains('dark');

            let baseClasses = 'mb-4 p-4 rounded-xl text-sm font-semibold transition-all';
            let themeClasses = isError ?
                (isDark ? 'bg-red-900/50 text-red-300 border border-red-800' : 'bg-red-50 text-red-700 border border-red-100') :
                (isDark ? 'bg-medical-900/50 text-medical-300 border border-medical-800' : 'bg-medical-50 text-medical-700 border border-medical-100');

            el.textContent = message;
            el.className = `${baseClasses} ${themeClasses}`;
            el.classList.remove('hidden');
        }

        function toggleLoading(isLoading) {
            const btn = document.getElementById('searchButton');
            const text = document.getElementById('buttonText');
            const loader = document.getElementById('loadingIndicator');
            btn.disabled = isLoading;
            text.textContent = isLoading ? 'Buscando...' : 'Buscar Ahora';
            loader.classList.toggle('hidden', !isLoading);

            // Ajuste de color del botón durante la carga
            if (isLoading) {
                btn.classList.remove('bg-medical-600', 'hover:bg-medical-700');
                btn.classList.add('bg-slate-400', 'cursor-not-allowed');
            } else {
                btn.classList.add('bg-medical-600', 'hover:bg-medical-700');
                btn.classList.remove('bg-slate-400', 'cursor-not-allowed');
            }
        }

        async function loadGoogleMaps() {
            if (window.google && window.google.maps) return;

            const script = document.createElement('script');
            script.src = `https://maps.googleapis.com/maps/api/js?key=${GOOGLE_API_KEY}&libraries=places`;
            document.head.appendChild(script);

            return new Promise(resolve => script.onload = resolve);
        }

        async function initMap(center = DEFAULT_CENTER, zoom = 12) {
            await loadGoogleMaps();

            if (!mapLoaded) {
                const isDark = document.documentElement.classList.contains('dark');

                map = new google.maps.Map(document.getElementById('map'), {
                    center
                    , zoom,
                    // IDs de estilo para mapas, el usuario debe configurarlos en la consola de GMaps
                    mapId: isDark ? 'a350436d4090956b' : '4504f8b37365c3d0'
                    , disableDefaultUI: false
                    , zoomControl: true
                });
                infoWindow = new google.maps.InfoWindow();
                geocoder = new google.maps.Geocoder();
                placesService = new google.maps.places.PlacesService(map);
                mapLoaded = true;
            } else {
                map.setCenter(center);
                map.setZoom(zoom);
            }
        }

        function placeMarker(pos, title, content, color, isUser = false) {
            const marker = new google.maps.Marker({
                map
                , position: pos
                , title
                , icon: isUser ? null : {
                    path: google.maps.SymbolPath.CIRCLE
                    , fillColor: color
                    , fillOpacity: 1
                    , strokeWeight: 2
                    , strokeColor: '#ffffff'
                    , scale: 8
                }
                , zIndex: isUser ? 100 : 1
            });
            if (isUser) {
                if (userMarker) userMarker.setMap(null);
                userMarker = marker;
            } else {
                providerMarkers.push(marker);
            }
            marker.addListener('click', () => {
                const isDark = document.documentElement.classList.contains('dark');
                const textColor = isDark ? 'text-slate-100' : 'text-slate-800';
                const subTextColor = isDark ? 'text-slate-400' : 'text-slate-600';

                infoWindow.setContent(`
                    <div class="info-window-content">
                        <strong class="${textColor} text-base">${title}</strong>
                        <p class="text-xs ${subTextColor}">${content}</p>
                    </div>
                `);
                infoWindow.open(map, marker);
            });
        }

        async function searchAndRender() {
            const address = document.getElementById('addressInput').value.trim();
            const radiusKm = parseFloat(document.getElementById('radiusInput').value);

            if (!address || isNaN(radiusKm) || radiusKm <= 0 || radiusKm > 50) {
                updateStatus('Por favor, ingresa una dirección y un radio válido (1–50 km).', true);
                return;
            }

            toggleLoading(true);

            try {
                await initMap();
                geocoder.geocode({
                    address
                    , componentRestrictions: {
                        country: 'VE'
                    }
                }, async (results, status) => {
                    if (status === 'OK') {
                        const loc = results[0].geometry.location;
                        map.setCenter(loc);
                        map.setZoom(14);
                        placeMarker(loc, 'Tu ubicación', address, 'red', true);

                        // Limpiar anteriores
                        providerMarkers.forEach(m => m.setMap(null));
                        providerMarkers = [];

                        const selectedTypes = POI_TYPES.filter((_, i) => document.getElementById(`filter-${i}`).checked);
                        let foundCount = 0;

                        for (const type of selectedTypes) {
                            await new Promise(resolve => {
                                placesService.nearbySearch({
                                    location: loc
                                    , radius: radiusKm * 1000
                                    , keyword: type.keyword
                                }, (results, status) => {
                                    if (status === google.maps.places.PlacesServiceStatus.OK) {
                                        results.forEach(place => {
                                            foundCount++;
                                            placeMarker(place.geometry.location, place.name, place.vicinity, type.color);
                                        });
                                    }
                                    resolve();
                                });
                            });
                        }
                        updateStatus(`Se encontraron ${foundCount} resultados cerca de ti.`);
                        window.lastSearchedLocation = loc;
                    } else {
                        updateStatus('No se pudo encontrar la ubicación, asegúrate de que la dirección es válida.', true);
                    }
                    toggleLoading(false);
                });
            } catch (err) {
                console.error("Error general:", err);
                updateStatus('Ocurrió un error inesperado al buscar.', true);
                toggleLoading(false);
            }
        }

        // --- Inicialización ---
        window.addEventListener('load', () => {
            // 1. Aplicar el tema guardado o el preferido del sistema
            const savedTheme = localStorage.getItem('theme');
            const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const initialTheme = savedTheme || (systemPrefersDark ? 'dark' : 'light');
            applyTheme(initialTheme);

            // 2. Configurar el toggle
            document.getElementById('themeToggle').addEventListener('click', toggleTheme);

            // 3. Renderizar filtros
            renderFilters();

            // 4. Inicializar mapa
            initMap();
        });

    </script>
</body>
</html>
