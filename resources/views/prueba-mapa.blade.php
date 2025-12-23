<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscador de Proveedores Médico-Especializados</title>
    <!-- Carga de Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Estilos personalizados para el mapa */
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

        .legend-item {
            font-size: 0.75rem;
            color: #374151;
            transition: transform 0.15s ease-in-out;
        }

        .legend-item:hover {
            transform: translateY(-1px);
        }

        .legend-swatch {
            border: 1px solid #d1d5db;
        }

    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif']
                    , }
                , }
            , }
        , };

    </script>
</head>
<body class="bg-gray-50 min-h-screen p-4 sm:p-8 font-sans">
    <div class="max-w-7xl mx-auto bg-white p-6 sm:p-8 rounded-xl shadow-2xl">
        <h1 class="text-3xl font-bold text-gray-800 mb-6 border-b pb-3">
            Buscador de Proveedores Médico-Especializados
        </h1>
        <p class="text-sm text-gray-500 mb-6">
            Ingrese una dirección y el radio en kilómetros para encontrar Clínicas, Hospitales, Farmacias y Centros de Diagnóstico.
        </p>

        <div class="flex flex-col gap-4 mb-6">
            <input type="text" id="addressInput" placeholder="Ej: Avenida Francisco de Miranda, Caracas" class="flex-grow p-3 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 shadow-sm" />
            <div class="flex flex-col sm:flex-row gap-4">
                <div class="relative flex-grow sm:w-1/3">
                    <label for="radiusInput" class="absolute -top-3 left-3 bg-white px-1 text-xs text-gray-500">Radio (km)</label>
                    <input type="number" id="radiusInput" value="10" min="1" max="50" placeholder="Radio en km" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 shadow-sm pt-4" />
                </div>
                <button id="searchButton" class="px-6 py-3 bg-indigo-600 text-white font-semibold rounded-lg shadow-md hover:bg-indigo-700 transition duration-150 flex items-center justify-center disabled:opacity-50 sm:w-2/3" onclick="searchAndRender()">
                    <span id="buttonText">Buscar Proveedores</span>
                    <span id="loadingIndicator" class="loading-ring hidden ml-2"></span>
                </button>
            </div>
        </div>

        <div id="statusMessage" class="mb-4 p-3 rounded-lg text-sm text-gray-700 bg-gray-100 hidden"></div>

        <div class="mb-6 p-4 border border-gray-300 rounded-xl shadow-inner bg-gray-50/50 backdrop-blur-sm">
            <h3 class="text-xs font-bold text-indigo-700 mb-3 uppercase tracking-wider border-b border-indigo-200 pb-1">
                Tipos de Proveedores
            </h3>
            <div id="legendContainer" class="flex flex-wrap gap-x-6 gap-y-3 justify-start"></div>
        </div>

        <div id="map" class="shadow-lg"></div>
    </div>

    <script>
        const GOOGLE_API_KEY = "AIzaSyB-lD2RaF292fzeb2TydGYng6cKMuIJMiQ"; // Reemplaza con tu clave API válida si es necesario

        let map, geocoder, service, infoWindow, userMarker, providerMarkers = [];

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

        function renderLegend() {
            const container = document.getElementById('legendContainer');
            container.innerHTML = '';
            POI_TYPES.forEach(poi => {
                const item = document.createElement('div');
                item.className = 'flex items-center space-x-2 legend-item';
                const swatch = document.createElement('span');
                swatch.className = 'w-3 h-3 rounded-full shadow-lg legend-swatch';
                swatch.style.backgroundColor = poi.color;
                const text = document.createElement('span');
                text.textContent = poi.name;
                item.appendChild(swatch);
                item.appendChild(text);
                container.appendChild(item);
            });
        }

        function loadGoogleMapsScript() {
            return new Promise((resolve, reject) => {
                const existingScript = document.getElementById('googleMapsScript');
                if (existingScript) {
                    if (window.google && window.google.maps) resolve();
                    else existingScript.onload = resolve, existingScript.onerror = reject;
                    return;
                }
                const script = document.createElement('script');
                script.id = 'googleMapsScript';
                script.src = `https://maps.googleapis.com/maps/api/js?key=${GOOGLE_API_KEY}&callback=initMapServices&libraries=places`;
                script.async = true;
                script.defer = true;
                script.onerror = reject;
                document.head.appendChild(script);
            });
        }

        window.initMapServices = function() {
            geocoder = new google.maps.Geocoder();
            infoWindow = new google.maps.InfoWindow();
            initMap({
                lat: 10.4806
                , lng: -66.9036
            }, 1);
            console.log("Servicios de Google Maps inicializados.");
        };

        function updateStatus(message, isError = false) {
            const statusDiv = document.getElementById('statusMessage');
            statusDiv.textContent = message;
            statusDiv.className = `mb-4 p-3 rounded-lg text-sm ${isError ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'} block`;
        }

        function toggleLoading(isLoading) {
            const button = document.getElementById('searchButton');
            const buttonText = document.getElementById('buttonText');
            const loadingIndicator = document.getElementById('loadingIndicator');
            button.disabled = isLoading;
            buttonText.textContent = isLoading ? 'Buscando...' : 'Buscar Proveedores';
            loadingIndicator.classList.toggle('hidden', !isLoading);
        }

        function clearProviderMarkers() {
            providerMarkers.forEach(marker => marker.setMap(null));
            providerMarkers = [];
        }

        function initMap(center, zoom) {
            if (!map) {
                map = new google.maps.Map(document.getElementById("map"), {
                    center
                    , zoom
                    , mapId: 'DEMO_MAP_ID'
                });
            } else {
                map.setCenter(center);
                map.setZoom(zoom);
            }
            if (!service) service = new google.maps.places.PlacesService(map);
        }

        function geocodeAddress(address) {
            return new Promise((resolve, reject) => {
                if (!geocoder) reject("Geocoder no inicializado.");
                geocoder.geocode({
                    address
                    , componentRestrictions: {
                        country: 'VE'
                    }
                }, (results, status) => {
                    if (status === "OK" && results[0]) {
                        const location = results[0].geometry.location;
                        resolve({
                            lat: location.lat()
                            , lng: location.lng()
                        });
                    } else reject(`Error en geocodificación: ${status}`);
                });
            });
        }

        function placeMarker(position, title, content, iconColor = 'blue', isUser = false) {
            if (isUser && userMarker) userMarker.setMap(null);
            const color = isUser ? '#ef4444' : iconColor;
            const svg = `<svg width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10 0C6.13 0 3 3.13 3 7c0 4.17 4.42 9.92 6.24 12.11.4.48 1.12.48 1.52 0C12.58 16.92 17 11.17 17 7c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" fill="${color}"/></svg>`;
            const iconConfig = {
                url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg)
                , scaledSize: new google.maps.Size(20, 20)
                , anchor: new google.maps.Point(10, 20)
            };
            const marker = new google.maps.Marker({
                map
                , position
                , title
                , icon: iconConfig
                , zIndex: isUser ? 10 : 1
            });
            if (isUser) userMarker = marker;
            else providerMarkers.push(marker);
            marker.addListener('click', () => {
                infoWindow.setContent(content);
                infoWindow.open(map, marker);
            });
            return marker;
        }

        async function searchNearby(center, searchRadiusKm) {
            clearProviderMarkers();
            const searchRadiusMeters = searchRadiusKm * 1000;
            updateStatus(`Buscando ${POI_TYPES.length} tipos de proveedores en un radio de ${searchRadiusKm} km...`, false);
            let totalResults = 0;
            for (const poiType of POI_TYPES) {
                const request = {
                    location: center
                    , radius: searchRadiusMeters
                    , type: poiType.type
                    , keyword: poiType.keyword
                    , language: 'es'
                };
                await new Promise((resolve) => {
                    service.nearbySearch(request, (results, status) => {
                        if (status === google.maps.places.PlacesServiceStatus.OK) {
                            results.forEach(place => {
                                const placeLocation = place.geometry.location;
                                if (providerMarkers.some(m => m.getPosition().equals(placeLocation))) return;
                                const content = `<div class="info-window-content"><h4>${place.name}</h4><p class="text-xs text-gray-600">${place.vicinity}</p><p class="text-xs text-gray-500 mt-1 font-semibold">${poiType.name}</p></div>`;
                                placeMarker({
                                    lat: placeLocation.lat()
                                    , lng: placeLocation.lng()
                                }, place.name, content, poiType.color, false);
                                totalResults++;
                            });
                        }
                        resolve();
                    });
                });
            }
            if (totalResults > 0) {
                updateStatus(`✅ ¡Búsqueda completada! Se encontraron ${totalResults} proveedores en un radio de ${searchRadiusKm} km.`, false);
            } else {
                updateStatus(`⚠️ Búsqueda completada, pero no se encontraron proveedores en las categorías especificadas en el radio de ${searchRadiusKm} km. Intente con un radio o dirección diferente.`, true);
            }
        }

        async function searchAndRender() {
            const address = document.getElementById('addressInput').value.trim();
            const radiusKm = parseFloat(document.getElementById('radiusInput').value);
            if (!address) {
                updateStatus('Por favor, introduce una dirección válida.', true);
                return;
            }
            if (isNaN(radiusKm) || radiusKm <= 0 || radiusKm > 50) {
                updateStatus('Por favor, introduce un radio válido entre 1 y 50 km.', true);
                return;
            }
            if (GOOGLE_API_KEY === "YOUR_GOOGLE_MAPS_API_KEY") {
                updateStatus('ERROR: Configura la clave API.', true);
                return;
            }
            toggleLoading(true);
            document.getElementById('statusMessage').classList.remove('hidden');
            try {
                const userLocation = await geocodeAddress(address);
                initMap(userLocation, 12);
                const userContent = `<div class="info-window-content"><h4>📍 Tu Ubicación (Cliente)</h4><p class="text-xs text-gray-600 font-semibold">${address}</p></div>`;
                placeMarker(userLocation, 'Tu Ubicación', userContent, 'red', true);
                await searchNearby(userLocation, radiusKm);
            } catch (error) {
                updateStatus(`❌ Error: ${error}`, true);
            } finally {
                toggleLoading(false);
            }
        }

        window.onload = async () => {
            document.getElementById('addressInput').value = 'Av. Principal de Las Mercedes, Caracas';
            document.getElementById('radiusInput').value = 10;
            renderLegend();
            try {
                await loadGoogleMapsScript();
            } catch (e) {
                updateStatus('❌ Error al cargar Google Maps. Verifica la clave API.', true);
            }
        };

    </script>
</body>
</html>
