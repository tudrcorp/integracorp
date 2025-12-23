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
            /* Altura del 60% del viewport */
            width: 100%;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            background-color: #e5e7eb;
            /* Color de fondo mientras carga */
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

        /* Estilo para el cargando */
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

        /* Estilos adicionales para la leyenda (minimalista) */
        .legend-item {
            /* Fuente más pequeña y sutil */
            font-size: 0.75rem;
            /* text-xs */
            color: #374151;
            /* text-gray-700 */
            transition: transform 0.15s ease-in-out;
        }

        .legend-item:hover {
            transform: translateY(-1px);
        }

        .legend-swatch {
            border: 1px solid #d1d5db;
            /* border-gray-300 */
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
            }
        }

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

        <!-- Formulario de Búsqueda y Radio -->
        <div class="flex flex-col gap-4 mb-6">
            <!-- Campo de Dirección -->
            <input type="text" id="addressInput" placeholder="Ej: Avenida Francisco de Miranda, Caracas" class="flex-grow p-3 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 shadow-sm" />

            <div class="flex flex-col sm:flex-row gap-4">
                <!-- Campo de Radio -->
                <div class="relative flex-grow sm:w-1/3">
                    <label for="radiusInput" class="absolute -top-3 left-3 bg-white px-1 text-xs text-gray-500">Radio (km)</label>
                    <input type="number" id="radiusInput" value="10" min="1" max="50" placeholder="Radio en km" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 shadow-sm pt-4" />
                </div>

                <!-- Botón de Búsqueda -->
                <button id="searchButton" class="px-6 py-3 bg-indigo-600 text-white font-semibold rounded-lg shadow-md hover:bg-indigo-700 transition duration-150 flex items-center justify-center disabled:opacity-50 sm:w-2/3" onclick="searchAndRender()">
                    <span id="buttonText">Buscar Proveedores</span>
                    <span id="loadingIndicator" class="loading-ring hidden ml-2"></span>
                </button>
            </div>
        </div>

        <!-- Contenedor de Mensajes -->
        <div id="statusMessage" class="mb-4 p-3 rounded-lg text-sm text-gray-700 bg-gray-100 hidden"></div>

        <!-- Leyenda Minimalista y de Vanguardia -->
        <div class="mb-6 p-4 border border-gray-300 rounded-xl shadow-inner bg-gray-50/50 backdrop-blur-sm">
            <h3 class="text-xs font-bold text-indigo-700 mb-3 uppercase tracking-wider border-b border-indigo-200 pb-1">
                Tipos de Proveedores
            </h3>
            <div id="legendContainer" class="flex flex-wrap gap-x-6 gap-y-3 justify-start">
                <!-- Los ítems de la leyenda se insertarán aquí por JavaScript -->
            </div>
        </div>

        <!-- Contenedor del Mapa -->
        <div id="map" class="shadow-lg"></div>
    </div>

    <!-- JavaScript de la Lógica del Mapa y API -->
    <script>
        // --- CONFIGURACIÓN DE GOOGLE MAPS ---
        const GOOGLE_API_KEY = "AIzaSyB-lD2RaF292fzeb2TydGYng6cKMuIJMiQ";

        let map;
        let geocoder;
        let service;
        let infoWindow;
        let userMarker;
        let providerMarkers = []; // Array para guardar los marcadores de proveedores

        // Categorías de búsqueda ampliadas para mayor precisión en servicios médicos.
        const POI_TYPES = [
            // Farmacias
            {
                type: 'pharmacy'
                , keyword: 'farmacia'
                , name: 'Farmacia 💊'
                , color: '#3b82f6'
            }, // Azul

            // Clínicas y Hospitales (incluye centros grandes)
            {
                type: 'hospital'
                , keyword: 'hospital'
                , name: 'Hospital/Clínica 🏥'
                , color: '#ef4444'
            }, // Rojo
            {
                type: 'doctor'
                , keyword: 'clínica'
                , name: 'Clínica Privada 🏨'
                , color: '#f97316'
            }, // Naranja

            // Laboratorios, Imagenología, Diagnóstico (CDI)
            {
                type: 'health'
                , keyword: 'laboratorio clínico'
                , name: 'Laboratorio 🔬'
                , color: '#10b981'
            }, // Verde
            {
                type: 'health'
                , keyword: 'centro diagnóstico'
                , name: 'Imagenología/CDI 🖼️'
                , color: '#6366f1'
            }, // Indigo
            {
                type: 'health'
                , keyword: 'laboratorio radiología'
                , name: 'Radiología/Rayos X ☢️'
                , color: '#f59e0b'
            }, // Ámbar

            // Consultorios y centros de salud primaria
            {
                type: 'doctor'
                , keyword: 'consultorio médico'
                , name: 'Consultorio Médico 🩺'
                , color: '#a855f7'
            }, // Púrpura
        ];

        // --- FUNCIONES PARA LA LEYENDA ---

        /**
         * Renderiza la leyenda dinámica basada en el arreglo POI_TYPES.
         */
        function renderLegend() {
            const container = document.getElementById('legendContainer');
            container.innerHTML = ''; // Limpiar contenido previo

            POI_TYPES.forEach(poi => {
                const item = document.createElement('div');
                item.className = 'flex items-center space-x-2 legend-item'; // Clase CSS para el estilo

                // Muestra de color (el punto)
                const swatch = document.createElement('span');
                swatch.className = 'w-3 h-3 rounded-full shadow-lg legend-swatch';
                swatch.style.backgroundColor = poi.color;

                // Nombre de la categoría
                const text = document.createElement('span');
                text.textContent = poi.name;

                item.appendChild(swatch);
                item.appendChild(text);
                container.appendChild(item);
            });
        }

        // --- UTILIDAD PARA EL SCRIPT DE GOOGLE MAPS ---

        // Función para cargar dinámicamente el script de Google Maps
        function loadGoogleMapsScript() {
            return new Promise((resolve, reject) => {
                const existingScript = document.getElementById('googleMapsScript');
                if (existingScript) {
                    if (window.google && window.google.maps) {
                        resolve();
                    } else {
                        existingScript.onload = resolve;
                        existingScript.onerror = reject;
                    }
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

        // Esta función se llama como 'callback' cuando el script de Google Maps carga
        window.initMapServices = function() {
            geocoder = new google.maps.Geocoder();
            infoWindow = new google.maps.InfoWindow();
            // Inicializar un mapa básico para empezar
            initMap({
                lat: 10.4806
                , lng: -66.9036
            }, 1); // Centrado en Caracas, Venezuela
            console.log("Servicios de Google Maps inicializados.");
        }

        // --- FUNCIONES PRINCIPALES ---

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
            if (isLoading) {
                loadingIndicator.classList.remove('hidden');
            } else {
                loadingIndicator.classList.add('hidden');
            }
        }

        /**
         * Limpia todos los marcadores de proveedores del mapa.
         */
        function clearProviderMarkers() {
            for (let i = 0; i < providerMarkers.length; i++) {
                providerMarkers[i].setMap(null);
            }
            providerMarkers = [];
        }

        /**
         * Inicializa o re-centra el mapa.
         * @param {object} center - LatLng object { lat, lng }.
         * @param {number} zoom - Nivel de zoom.
         */
        function initMap(center, zoom) {
            if (!map) {
                map = new google.maps.Map(document.getElementById("map"), {
                    center: center
                    , zoom: zoom
                    , mapId: 'DEMO_MAP_ID'
                , });
            } else {
                map.setCenter(center);
                map.setZoom(zoom);
            }
            if (!service) {
                service = new google.maps.places.PlacesService(map);
            }
        }

        /**
         * 1. Convierte la dirección ingresada a coordenadas LatLng.
         */
        function geocodeAddress(address) {
            return new Promise((resolve, reject) => {
                if (!geocoder) {
                    reject("El servicio de Geocoder no está inicializado. Verifique la clave de API.");
                    return;
                }
                // Añadir un componente de país por defecto para priorizar Venezuela
                geocoder.geocode({
                    address: address
                    , componentRestrictions: {
                        country: 'VE'
                    } // Prioriza resultados en Venezuela
                }, (results, status) => {
                    if (status === "OK" && results[0]) {
                        const location = results[0].geometry.location;
                        resolve({
                            lat: location.lat()
                            , lng: location.lng()
                        });
                    } else {
                        reject(`No se pudo geocodificar la dirección. Estado: ${status}`);
                    }
                });
            });
        }

        /**
         * Coloca un marcador en el mapa.
         */
        function placeMarker(position, title, content, iconColor = 'blue', isUser = false) {
            // Limpia el marcador de usuario anterior si este es el marcador de usuario
            if (isUser && userMarker) {
                userMarker.setMap(null);
            }

            let iconConfig = null; // null usa el pin predeterminado de Google Maps (usado para el usuario)

            if (!isUser) {
                // Icono de proveedor (círculo sólido, usa el color)
                iconConfig = {
                    path: google.maps.SymbolPath.CIRCLE
                    , fillColor: iconColor
                    , fillOpacity: 1
                    , strokeWeight: 0
                    , scale: 7
                , };
            }

            const marker = new google.maps.Marker({
                map: map
                , position: position
                , title: title
                , icon: iconConfig
                , zIndex: isUser ? 10 : 1, // El marcador de usuario siempre va encima
            });

            if (isUser) {
                userMarker = marker;
            } else {
                providerMarkers.push(marker);
            }

            // Añadir manejador de click para la ventana de información
            marker.addListener('click', () => {
                infoWindow.setContent(content);
                infoWindow.open(map, marker);
            });
            return marker;
        }


        /**
         * 2. Realiza la búsqueda de lugares cercanos para cada tipo.
         */
        async function searchNearby(center, searchRadiusKm) {
            clearProviderMarkers(); // Limpiar resultados anteriores de proveedores

            // Convertir kilómetros a metros (API Places requiere metros)
            const searchRadiusMeters = searchRadiusKm * 1000;

            updateStatus(`Buscando ${POI_TYPES.length} tipos de proveedores en un radio de ${searchRadiusKm} km...`, false);

            let totalResults = 0;

            for (const poiType of POI_TYPES) {
                // Usamos 'type' para la categoría principal y 'keyword' para afinar (ej. 'Centro Diagnóstico')
                const request = {
                    location: center
                    , radius: searchRadiusMeters
                    , type: poiType.type
                    , keyword: poiType.keyword, // Usamos la palabra clave para mayor precisión
                    language: 'es', // Preferir resultados en español
                };

                await new Promise((resolve, reject) => {
                    service.nearbySearch(request, (results, status) => {
                        if (status === google.maps.places.PlacesServiceStatus.OK) {
                            results.forEach(place => {
                                const placeLocation = place.geometry.location;
                                // Evitar duplicados si Place API devuelve resultados que ya marcamos
                                if (providerMarkers.some(m => m.getPosition().equals(placeLocation))) {
                                    return;
                                }

                                const content = `
                                    <div class="info-window-content">
                                        <h4>${place.name}</h4>
                                        <p class="text-xs text-gray-600">${place.vicinity}</p>
                                        <p class="text-xs text-gray-500 mt-1 font-semibold">${poiType.name}</p>
                                    </div>
                                `;
                                placeMarker({
                                        lat: placeLocation.lat()
                                        , lng: placeLocation.lng()
                                    }
                                    , place.name
                                    , content
                                    , poiType.color
                                    , false // Es un proveedor
                                );
                                totalResults++;
                            });
                            console.log(`Encontrados ${results.length} resultados para ${poiType.name} (Keyword: ${poiType.keyword})`);
                            resolve();
                        } else if (status === google.maps.places.PlacesServiceStatus.ZERO_RESULTS) {
                            console.log(`Cero resultados para ${poiType.name} (Keyword: ${poiType.keyword})`);
                            resolve();
                        } else {
                            // Registramos el error pero resolvemos la promesa para continuar con el siguiente tipo de búsqueda
                            console.error(`Error en Places API para ${poiType.name}: ${status}`);
                            resolve();
                        }
                    });
                });
            }

            if (totalResults > 0) {
                updateStatus(`✅ ¡Búsqueda completada! Se encontraron ${totalResults} proveedores en un radio de ${searchRadiusKm} km.`, false);
            } else {
                updateStatus(`⚠️ Búsqueda completada, pero no se encontraron proveedores en las categorías especificadas en el radio de ${searchRadiusKm} km. Intente con un radio o dirección diferente.`, true);
            }
        }

        /**
         * 3. Función principal que orquesta la geocodificación y la búsqueda.
         */
        async function searchAndRender() {
            const address = document.getElementById('addressInput').value.trim();
            const radiusKm = parseFloat(document.getElementById('radiusInput').value);

            if (!address) {
                updateStatus('Por favor, introduce una dirección válida para comenzar la búsqueda.', true);
                return;
            }
            if (isNaN(radiusKm) || radiusKm <= 0 || radiusKm > 50) {
                updateStatus('Por favor, introduce un radio válido entre 1 y 50 km.', true);
                return;
            }

            if (GOOGLE_API_KEY === "YOUR_GOOGLE_MAPS_API_KEY") {
                updateStatus('ERROR: La clave de API de Google Maps no ha sido configurada. Reemplaza "YOUR_GOOGLE_MAPS_API_KEY" en el código.', true);
                return;
            }

            toggleLoading(true);
            document.getElementById('statusMessage').classList.add('hidden');

            try {
                // Paso 1: Geocodificar la dirección del usuario
                const userLocation = await geocodeAddress(address);

                // Paso 2: Inicializar/Centrar el mapa en la ubicación del usuario
                initMap(userLocation, 12);

                // Colocar marcador del usuario (pin de Google Maps por defecto)
                const userContent = `
                    <div class="info-window-content">
                        <h4>📍 Tu Ubicación (Cliente)</h4>
                        <p class="text-xs text-gray-600 font-semibold">${address}</p>
                    </div>
                `;
                placeMarker(userLocation, 'Tu Ubicación', userContent, 'red', true);

                // Paso 3: Buscar y renderizar proveedores cercanos
                await searchNearby(userLocation, radiusKm);

            } catch (error) {
                console.error('Error en el proceso de búsqueda:', error);
                updateStatus(`❌ Error en el proceso de búsqueda: ${error}`, true);
            } finally {
                toggleLoading(false);
            }
        }

        // --- INICIALIZACIÓN ---

        // Cargar Google Maps al cargar la ventana
        window.onload = async () => {
            // Dirección de ejemplo en Venezuela para un mejor contexto inicial
            document.getElementById('addressInput').value = 'Av. Principal de Las Mercedes, Caracas';
            document.getElementById('radiusInput').value = 10;

            // Renderizar la leyenda inmediatamente
            renderLegend();

            try {
                await loadGoogleMapsScript();
            } catch (e) {
                console.error("Fallo al cargar el script de Google Maps. Verifique su conexión y la clave de API.", e);
                updateStatus('❌ Error al cargar Google Maps. Verifique su clave de API y conexión a internet.', true);
            }
        };

    </script>
</body>
</html>
