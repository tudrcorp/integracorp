<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Buscador de Proveedores Médico-Especializados</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        #map {
            height: 60vh;
            width: 100%;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            background-color: #e5e7eb;
        }

        .info-window-content {
            font-family: 'Inter', sans-serif;
            padding: 5px;
        }

        .info-window-content h4 {
            font-weight: 600;
            margin-bottom: 3px;
            color: #1f2937;
        }

        .loading-ring {
            display: inline-block;
            width: 24px;
            height: 24px;
        }

        .loading-ring:after {
            content: " ";
            display: block;
            width: 20px;
            height: 20px;
            margin: 2px;
            border-radius: 50%;
            border: 3px solid #6366f1;
            border-color: #6366f1 transparent #6366f1 transparent;
            animation: loading-ring 1.2s linear infinite;
        }

        @keyframes loading-ring {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif']
                    }
                }
            }
        }

    </script>
</head>
<body class="bg-gray-50 min-h-screen p-4 sm:p-8 font-sans">
    <div class="max-w-7xl mx-auto">
        <!-- Encabezado -->
        <div class="text-center mb-8">
            <h1 class="text-3xl md:text-4xl font-bold text-gray-800">Buscador de Proveedores Médicos</h1>
            <p class="text-gray-600 mt-2 max-w-2xl mx-auto">
                Encuentra clínicas, hospitales, farmacias y centros de diagnóstico cercanos a tu ubicación.
            </p>
        </div>
    </div>
    <div class="max-w-7xl mx-auto bg-white p-6 sm:p-8 rounded-xl shadow-2xl">
        {{-- <h1 class="text-3xl font-bold text-gray-800 mb-6 border-b pb-3">
            Buscador de Proveedores Médico-Especializados
        </h1> --}}
        <p class="text-sm text-gray-500 mb-6">
            Ingrese una dirección y el radio en kilómetros para encontrar clínicas, hospitales, farmacias y centros de diagnóstico.
        </p>

        <div class="flex flex-grow gap-4 mb-6">

            <input type="text" id="addressInput" placeholder="Ej: Avenida Francisco de Miranda, Caracas" class="flex-grow p-3 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 shadow-sm" />
            <div class="flex flex-col sm:flex-row gap-4">
                <div class="relative flex-grow sm:w-1/3">
                    <label for="radiusInput" class="absolute -top-3 left-3 bg-white px-1 text-xs text-gray-500">Radio (km)</label>
                    <input type="number" id="radiusInput" value="10" min="1" max="50" class="w-full h-full p-3 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 shadow-sm pt-4" />
                </div>
                <button id="searchButton" class="px-6 py-3 bg-blue-400 text-white font-semibold rounded-lg shadow-md hover:bg-indigo-700 transition duration-150 flex items-center justify-center disabled:opacity-50 sm:w-2/3" onclick="searchAndRender()">
                    <span id="buttonText">Buscar Proveedores</span>
                    <span id="loadingIndicator" class="loading-ring hidden ml-2"></span>
                </button>
            </div>
        </div>

        <div id="statusMessage" class="mb-4 p-3 rounded-lg text-sm text-gray-700 bg-gray-100 hidden"></div>

        <div class="mb-6 p-4 border border-gray-300 rounded-xl shadow-inner bg-gray-50/50 backdrop-blur-sm">
            <h3 class="text-xs font-bold text-indigo-700 mb-3 uppercase tracking-wider border-b border-indigo-200 pb-1">
                Selecciona los tipos a buscar
            </h3>
            <div id="filtersContainer" class="flex flex-wrap gap-x-6 gap-y-3 justify-start"></div>
        </div>

        <div id="map"></div>
    </div>

    <script>
        const GOOGLE_API_KEY = "AIzaSyB-lD2RaF292fzeb2TydGYng6cKMuIJMiQ";

        let map, geocoder, placesService, infoWindow, userMarker = null
            , providerMarkers = [];

        const POI_TYPES = [{
                type: 'pharmacy'
                , keyword: 'farmacia'
                , name: 'Farmacia 💊'
                , color: '#3b82f6'
            }
            , {
                type: 'hospital'
                , keyword: 'hospital'
                , name: 'Hospital/Clínica 🏥'
                , color: '#ef4444'
            }
            , {
                type: 'doctor'
                , keyword: 'clínica'
                , name: 'Clínica Privada 🏨'
                , color: '#f97316'
            }
            , {
                type: 'health'
                , keyword: 'laboratorio clínico'
                , name: 'Laboratorio 🔬'
                , color: '#10b981'
            }
            , {
                type: 'health'
                , keyword: 'centro diagnóstico'
                , name: 'Imagenología/CDI 🖼️'
                , color: '#6366f1'
            }
            , {
                type: 'health'
                , keyword: 'laboratorio radiología'
                , name: 'Radiología/Rayos X ☢️'
                , color: '#f59e0b'
            }
            , {
                type: 'doctor'
                , keyword: 'consultorio médico'
                , name: 'Consultorio Médico 🩺'
                , color: '#a855f7'
            }
        , ];

        let debounceTimer;

        function debouncedSearch() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(searchAndRender, 500);
        }

        function renderFilters() {
            const container = document.getElementById('filtersContainer');
            container.innerHTML = '';
            POI_TYPES.forEach((poi, index) => {
                const label = document.createElement('label');
                label.className = 'flex items-center space-x-2 cursor-pointer';
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.id = `filter-${index}`;
                checkbox.checked = true;
                checkbox.className = 'h-4 w-4 text-indigo-600 rounded focus:ring-indigo-500';
                checkbox.addEventListener('change', () => {
                    if (window.lastSearchedLocation && window.lastSearchedRadius) {
                        debouncedSearch();
                    }
                });
                const swatch = document.createElement('span');
                swatch.className = 'w-3 h-3 rounded-full shadow';
                swatch.style.backgroundColor = poi.color;
                const text = document.createElement('span');
                text.className = 'text-sm text-gray-700';
                text.textContent = poi.name;
                label.appendChild(checkbox);
                label.appendChild(swatch);
                label.appendChild(text);
                container.appendChild(label);
            });
        }

        function updateStatus(message, isError = false) {
            const el = document.getElementById('statusMessage');
            el.textContent = message;
            el.className = `mb-4 p-3 rounded-lg text-sm ${isError ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'}`;
            el.classList.remove('hidden');
        }

        function toggleLoading(isLoading) {
            const btn = document.getElementById('searchButton');
            const text = document.getElementById('buttonText');
            const loader = document.getElementById('loadingIndicator');
            btn.disabled = isLoading;
            text.textContent = isLoading ? 'Buscando...' : 'Buscar Proveedores';
            loader.classList.toggle('hidden', !isLoading);
        }

        function clearProviderMarkers() {
            providerMarkers.forEach(m => m.setMap(null));
            providerMarkers = [];
        }

        async function loadGoogleMaps() {
            if (window.google && window.google.maps) return;

            const script = document.createElement('script');
            script.src = `https://maps.googleapis.com/maps/api/js?key=${GOOGLE_API_KEY}&libraries=places`;
            script.async = true;
            script.defer = true;
            document.head.appendChild(script);

            return new Promise((resolve, reject) => {
                script.onload = () => resolve();
                script.onerror = () => reject(new Error('Error al cargar Google Maps API'));
            });
        }

        async function initMap(center = {
            lat: 10.4806
            , lng: -66.9036
        }, zoom = 10) {
            if (!window.google || !window.google.maps) await loadGoogleMaps();

            if (!map) {
                map = new google.maps.Map(document.getElementById('map'), {
                    center
                    , zoom
                    , mapId: 'DEMO_MAP_ID'
                });
                infoWindow = new google.maps.InfoWindow();
            } else {
                map.setCenter(center);
                map.setZoom(zoom);
            }

            if (!geocoder) geocoder = new google.maps.Geocoder();
            if (!placesService) placesService = new google.maps.places.PlacesService(map);
        }

        async function geocodeAddress(address) {
            return new Promise((resolve, reject) => {
                geocoder.geocode({
                    address
                    , componentRestrictions: {
                        country: 'VE'
                    }
                }, (results, status) => {
                    if (status === 'OK' && results[0]) {
                        const loc = results[0].geometry.location;
                        resolve({
                            lat: loc.lat()
                            , lng: loc.lng()
                        });
                    } else {
                        reject(new Error(`No se encontró la dirección: ${status}`));
                    }
                });
            });
        }

        function placeMarker(pos, title, content, color, isUser = false) {
            if (isUser && userMarker) userMarker.setMap(null);

            const icon = !isUser ?
                {
                    path: google.maps.SymbolPath.CIRCLE
                    , fillColor: color
                    , fillOpacity: 1
                    , strokeWeight: 0
                    , scale: 7
                } :
                null;

            const marker = new google.maps.Marker({
                map
                , position: pos
                , title
                , icon
                , zIndex: isUser ? 10 : 1
            });

            if (isUser) userMarker = marker;
            else providerMarkers.push(marker);

            marker.addListener('click', () => {
                infoWindow.setContent(content);
                infoWindow.open(map, marker);
            });
        }

        async function searchNearby(center, radiusKm) {
            clearProviderMarkers();
            const radiusMeters = radiusKm * 1000;

            const selected = POI_TYPES.filter((_, i) => {
                const cb = document.getElementById(`filter-${i}`);
                return cb && cb.checked;
            });

            if (selected.length === 0) {
                updateStatus('⚠️ Selecciona al menos un tipo de proveedor.', true);
                return;
            }

            let total = 0;
            updateStatus(`Buscando ${selected.length} tipos en ${radiusKm} km...`);

            for (const type of selected) {
                await new Promise((resolve) => {
                    placesService.nearbySearch({
                            location: center
                            , radius: radiusMeters
                            , type: type.type
                            , keyword: type.keyword
                            , language: 'es'
                        }
                        , (results, status) => {
                            if (status === google.maps.places.PlacesServiceStatus.OK) {
                                results.forEach(place => {
                                    const loc = place.geometry.location;
                                    if (providerMarkers.some(m => m.getPosition().equals(loc))) return;

                                    const content = `
                                        <div class="info-window-content">
                                            <h4>${place.name}</h4>
                                            <p class="text-xs text-gray-600">${place.vicinity}</p>
                                            <p class="text-xs text-gray-500 mt-1 font-semibold">${type.name}</p>
                                        </div>
                                    `;
                                    placeMarker({
                                            lat: loc.lat()
                                            , lng: loc.lng()
                                        }
                                        , place.name
                                        , content
                                        , type.color
                                    );
                                    total++;
                                });
                            }
                            resolve();
                        }
                    );
                });
            }

            if (total > 0) {
                updateStatus(`✅ Encontrados ${total} proveedores.`, false);
            } else {
                updateStatus(`⚠️ No se encontraron resultados con los filtros seleccionados.`, true);
            }
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
                await initMap(); // Asegurar que el mapa ya exista
                const loc = await geocodeAddress(address);
                map.setCenter(loc);
                map.setZoom(12);

                placeMarker(
                    loc
                    , 'Tu ubicación'
                    , `<div class="info-window-content"><h4>📍 Tu Ubicación</h4><p class="text-xs">${address}</p></div>`
                    , 'red'
                    , true
                );

                await searchNearby(loc, radiusKm);

                window.lastSearchedLocation = loc;
                window.lastSearchedRadius = radiusKm;
            } catch (err) {
                console.error(err);
                updateStatus(`❌ Error: ${err.message}`, true);
            } finally {
                toggleLoading(false);
            }
        }

        // Inicialización al cargar
        window.addEventListener('load', async () => {
            document.getElementById('addressInput').value = 'Av. Principal de Las Mercedes, Caracas';
            renderFilters();
            await initMap(); // Inicializa el mapa vacío al inicio
        });

    </script>
</body>
</html>
