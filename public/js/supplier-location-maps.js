/**
 * Mapa de ubicación de proveedor (Filament modal + maps-tres POI).
 * Requiere window.__supplierMapsApiKey, script #{rootId}-maps-config y contenedor .supplier-location-maps-root.
 */
(function () {
    function isGoogleMapsReady() {
        return typeof google !== 'undefined'
            && google.maps
            && typeof google.maps.Map === 'function';
    }

    function waitForGoogleMapsReady(timeoutMs = 15000) {
        if (isGoogleMapsReady()) {
            return Promise.resolve();
        }

        return new Promise((resolve, reject) => {
            const startedAt = Date.now();
            const tick = () => {
                if (isGoogleMapsReady()) {
                    resolve();

                    return;
                }

                if (Date.now() - startedAt >= timeoutMs) {
                    reject(new Error('Google Maps no terminó de inicializar a tiempo.'));

                    return;
                }

                setTimeout(tick, 50);
            };

            tick();
        });
    }

    const POI_TYPES = [
        { type: 'pharmacy', keyword: 'farmacia', name: 'Farmacia', color: '#3b82f6', icon: '💊' },
        { type: 'hospital', keyword: 'hospital', name: 'Hospital', color: '#ef4444', icon: '🏥' },
        { type: 'doctor', keyword: 'clínica', name: 'Clínica', color: '#f97316', icon: '🏨' },
        { type: 'health', keyword: 'laboratorio clínico', name: 'Laboratorio', color: '#10b981', icon: '🔬' },
        { type: 'health', keyword: 'centro diagnóstico', name: 'Imagenología', color: '#6366f1', icon: '🖼️' },
        { type: 'doctor', keyword: 'consultorio médico', name: 'Consultorio', color: '#a855f7', icon: '🩺' },
    ];

    function parseMapsConfig(rootEl) {
        const configScript = document.getElementById(rootEl.id + '-maps-config');
        if (configScript && configScript.textContent.trim() !== '') {
            return JSON.parse(configScript.textContent);
        }

        const raw = rootEl.getAttribute('data-supplier-maps-config');
        if (!raw) {
            return {};
        }

        return JSON.parse(raw);
    }

    function toLatLngLiteral(point) {
        if (!point) {
            return null;
        }

        if (typeof point.lat === 'function' && typeof point.lng === 'function') {
            return { lat: point.lat(), lng: point.lng() };
        }

        const lat = Number(point.lat);
        const lng = Number(point.lng);

        if (Number.isNaN(lat) || Number.isNaN(lng)) {
            return null;
        }

        return { lat, lng };
    }

    function haversineKm(origin, destination) {
        const toRad = (deg) => (deg * Math.PI) / 180;
        const earthRadiusKm = 6371;
        const dLat = toRad(destination.lat - origin.lat);
        const dLng = toRad(destination.lng - origin.lng);
        const a = Math.sin(dLat / 2) ** 2
            + Math.cos(toRad(origin.lat)) * Math.cos(toRad(destination.lat)) * Math.sin(dLng / 2) ** 2;

        return earthRadiusKm * (2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a)));
    }

    function isDirectionsApiDenied() {
        return window.__supplierMapsDirectionsDenied === true;
    }

    function markDirectionsApiDenied() {
        window.__supplierMapsDirectionsDenied = true;
    }

    function directionsDeniedHint() {
        return 'La clave GOOGLE_MAPS_API_KEY no tiene permiso para Directions API. '
            + 'En Google Cloud: habilite «Directions API» y agréguela a las restricciones de la clave '
            + '(junto a Maps JavaScript API y Places API). Luego recargue la página.';
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) {
            return '';
        }

        const div = document.createElement('div');
        div.textContent = String(text);

        return div.innerHTML;
    }

    /**
     * @param {{ title: string, subtitle?: string, meta?: string, footer?: string }} options
     */
    function buildInfoWindowContent(options) {
        const title = escapeHtml(options.title || 'Ubicación');
        const subtitle = options.subtitle ? escapeHtml(options.subtitle) : '';
        const meta = options.meta ? escapeHtml(options.meta) : '';
        const footer = options.footer ? escapeHtml(options.footer) : '';

        let html = ''
            + '<div style="'
            + 'font-family:system-ui,-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;'
            + 'min-width:220px;max-width:300px;padding:14px 16px;line-height:1.5;'
            + 'color:#0f172a;background:#ffffff;'
            + '">'
            + '<div style="font-size:16px;font-weight:700;color:#0f172a;margin:0 0 8px 0;letter-spacing:-0.01em;">'
            + title
            + '</div>';

        if (subtitle) {
            html += '<div style="font-size:13px;font-weight:500;color:#334155;margin:0 0 10px 0;">'
                + subtitle
                + '</div>';
        }

        if (meta) {
            html += '<div style="font-size:13px;font-weight:600;color:#0369a1;'
                + 'background:#f0f9ff;border:1px solid #7dd3fc;border-radius:10px;'
                + 'padding:10px 12px;margin:0;">'
                + meta
                + '</div>';
        }

        if (footer) {
            html += '<div style="font-size:11px;color:#64748b;margin-top:10px;">'
                + footer
                + '</div>';
        }

        html += '</div>';

        return html;
    }

    function isDarkMode() {
        return document.documentElement.classList.contains('dark')
            || window.matchMedia('(prefers-color-scheme: dark)').matches;
    }

    function applyPoiFilterPillStyle(label, poi, checked) {
        const isDark = isDarkMode();

        if (checked) {
            label.style.borderColor = poi.color;
            label.style.backgroundColor = poi.color + (isDark ? '33' : '18');
            label.style.color = isDark ? poi.color : '#1f2937';
        } else {
            label.style.borderColor = isDark ? '#475569' : '#e2e8f0';
            label.style.backgroundColor = isDark ? '#1e293b' : '#f8fafc';
            label.style.color = isDark ? '#f1f5f9' : '#64748b';
        }
    }

    function directionsErrorMessage(status) {
        const messages = {
            REQUEST_DENIED: directionsDeniedHint(),
            OVER_QUERY_LIMIT: 'Se superó el límite de consultas de rutas. Intente más tarde.',
            ZERO_RESULTS: 'No hay ruta en vehículo entre estos puntos. Elija un destino más cercano o distinto.',
            INVALID_REQUEST: 'La solicitud de ruta no es válida. Vuelva a buscar la dirección de referencia.',
            UNKNOWN_ERROR: 'Error temporal del servicio de rutas. Intente de nuevo.',
        };

        return messages[status] || ('No se pudo calcular la ruta (' + status + ').');
    }

    class SupplierLocationMaps {
        constructor(rootEl) {
            const config = parseMapsConfig(rootEl);
            this.rootEl = rootEl;
            this.rootId = config.rootId;
            this.mapId = config.mapId;
            this.apiKey = config.apiKey || window.__operationsMapsApiKey || window.__supplierMapsApiKey || '';
            this.defaultCenter = config.defaultCenter || { lat: 10.4806, lng: -66.9036 };
            this.initialAddress = config.initialAddress || '';
            this.recordLabel = config.recordLabel || config.supplierName || 'Ubicación';
            this.subjectRoleLabel = config.subjectRoleLabel || 'proveedor';
            this.applyLivewireMethod = config.applyLivewireMethod || 'applySupplierLocationFromMaps';
            this.selectedAddress = this.initialAddress;
            this.lastSearchAddress = this.initialAddress;
            this.selectedDestinationAddress = '';
            this.map = null;
            this.geocoder = null;
            this.placesService = null;
            this.directionsService = null;
            this.directionsRenderer = null;
            this.infoWindow = null;
            this.autocomplete = null;
            this.destinationAutocomplete = null;
            this.supplierMarker = null;
            this.destinationMarker = null;
            this.establishmentMarkers = [];
            this.radiusCircle = null;
            this.mapBooted = false;
            this.lastSearchedLocation = null;
            this.supplierLocation = null;
            this.supplierAddress = '';
            this.fallbackPolyline = null;
            this.filterChecked = POI_TYPES.map(() => true);
            this.poiTypes = POI_TYPES;
            this.isSearching = false;
            this.isRouting = false;
            this.lastRouteSummary = '';
            this.filterPillRefs = [];
        }

        refreshFilterStyles() {
            this.filterPillRefs.forEach((entry) => {
                applyPoiFilterPillStyle(entry.label, entry.poi, this.filterChecked[entry.index]);
            });
        }

        openInfoWindow(marker, options) {
            if (!this.infoWindow || !marker) {
                return;
            }

            this.infoWindow.setContent(buildInfoWindowContent(options));
            if (typeof this.infoWindow.open === 'function') {
                try {
                    this.infoWindow.open({
                        anchor: marker,
                        map: this.map,
                        shouldFocus: false,
                    });
                } catch (error) {
                    this.infoWindow.open(this.map, marker);
                }
            }
        }

        el(suffix) {
            return document.getElementById(this.rootId + suffix);
        }

        setStatus(message, isError = false) {
            const status = this.el('-status');
            if (!status) {
                return;
            }
            status.textContent = message;
            status.className = 'rounded-xl px-4 py-3 text-sm font-medium ' + (
                isError
                    ? 'bg-danger-50 text-danger-700 ring-1 ring-danger-600/20 dark:bg-danger-950/40 dark:text-danger-200'
                    : 'bg-primary-50 text-primary-800 ring-1 ring-primary-600/15 dark:bg-primary-950/40 dark:text-primary-100'
            );
            status.classList.remove('hidden');
        }

        setSelectedAddress(address) {
            this.selectedAddress = (address || '').trim();
            const preview = this.el('-selected-preview');
            if (preview) {
                preview.textContent = this.selectedAddress || 'Seleccione un punto en el mapa o busque una dirección.';
            }
            const applyBtn = this.el('-apply');
            if (applyBtn) {
                applyBtn.disabled = this.selectedAddress === '';
            }
        }

        setDestinationAddress(address) {
            this.selectedDestinationAddress = (address || '').trim();
            const destInput = this.el('-destination-address');
            if (destInput && address) {
                destInput.value = address;
            }
            const useDestBtn = this.el('-use-destination');
            if (useDestBtn) {
                useDestBtn.disabled = this.selectedDestinationAddress === '';
            }
            const routeBtn = this.el('-route-btn');
            if (routeBtn) {
                routeBtn.disabled = !this.supplierLocation || this.selectedDestinationAddress === '';
            }
            const destPreview = this.el('-destination-preview');
            if (destPreview) {
                destPreview.textContent = this.selectedDestinationAddress
                    ? 'Destino: ' + this.selectedDestinationAddress
                    : '';
            }
        }

        showRoutePanel(show) {
            const panel = this.el('-route-panel');
            if (panel) {
                panel.classList.toggle('hidden', !show);
            }
        }

        setRouteInfo(distanceText, durationText, destinationAddress) {
            const summary = this.el('-route-summary');
            this.lastRouteSummary = distanceText + ' · ' + durationText + ' en vehículo';
            if (summary) {
                summary.textContent = this.lastRouteSummary;
            }
            this.setDestinationAddress(destinationAddress);
            this.showRoutePanel(true);
            this.setStatus(
                'Ruta trazada: ' + durationText + ' (' + distanceText + ') hasta el destino seleccionado.'
            );
        }

        clearFallbackPolyline() {
            if (this.fallbackPolyline) {
                this.fallbackPolyline.setMap(null);
                this.fallbackPolyline = null;
            }
        }

        clearRoute() {
            if (this.directionsRenderer) {
                this.directionsRenderer.setMap(null);
            }
            if (this.destinationMarker) {
                this.destinationMarker.setMap(null);
                this.destinationMarker = null;
            }
            this.clearFallbackPolyline();
            this.showRoutePanel(false);
        }

        renderFilters() {
            const container = this.el('-filters');
            if (!container) {
                return;
            }
            container.innerHTML = '';
            this.filterPillRefs = [];
            this.poiTypes.forEach((poi, index) => {
                const inputId = this.rootId + '-filter-' + index;
                const label = document.createElement('label');
                label.setAttribute('for', inputId);
                label.className = 'cursor-pointer flex items-center gap-2 rounded-full border-2 px-3 py-1.5 text-xs font-semibold transition-all';
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.id = inputId;
                checkbox.checked = this.filterChecked[index];
                checkbox.className = 'hidden';
                applyPoiFilterPillStyle(label, poi, checkbox.checked);
                this.filterPillRefs.push({ label, poi, index });
                checkbox.addEventListener('change', (event) => {
                    this.filterChecked[index] = event.target.checked;
                    applyPoiFilterPillStyle(label, poi, event.target.checked);
                    if (this.lastSearchedLocation) {
                        this.searchNearbyEstablishments(this.lastSearchedLocation);
                    }
                });
                label.innerHTML = '<span>' + poi.icon + '</span><span>' + poi.name + '</span>';
                label.prepend(checkbox);
                container.appendChild(label);
            });
        }

        loadGoogleMaps() {
            if (isGoogleMapsReady()) {
                return Promise.resolve();
            }

            if (window.__supplierMapsScriptLoading) {
                return window.__supplierMapsScriptLoading;
            }

            window.__supplierMapsScriptLoading = new Promise((resolve, reject) => {
                const finishWhenReady = () => {
                    waitForGoogleMapsReady()
                        .then(resolve)
                        .catch(reject);
                };

                const existingScript = document.getElementById('operations-google-maps-api')
                    || document.getElementById('supplier-google-maps-api');
                if (existingScript?.src?.includes('loading=async')) {
                    existingScript.remove();
                } else if (existingScript) {
                    finishWhenReady();

                    return;
                }

                const callbackName = '__operationsMapsApiReady';
                window[callbackName] = () => {
                    delete window[callbackName];
                    finishWhenReady();
                };

                const script = document.createElement('script');
                script.id = 'operations-google-maps-api';
                window.__supplierMapsApiReady = window[callbackName];
                script.src = 'https://maps.googleapis.com/maps/api/js?key='
                    + encodeURIComponent(this.apiKey)
                    + '&libraries=places&callback=' + callbackName;
                script.async = true;
                script.defer = true;
                script.onerror = () => reject(new Error('No se pudo cargar Google Maps.'));
                document.head.appendChild(script);
            });

            return window.__supplierMapsScriptLoading;
        }

        resizeMap() {
            if (!this.map || !window.google) {
                return;
            }
            google.maps.event.trigger(this.map, 'resize');
            if (this.lastSearchedLocation) {
                this.map.setCenter(this.lastSearchedLocation);
            }
        }

        scheduleResize() {
            [80, 250, 600].forEach((delay) => {
                setTimeout(() => this.resizeMap(), delay);
            });
        }

        async ensureMap(center = null, zoom = 12) {
            await this.loadGoogleMaps();
            await waitForGoogleMapsReady();
            const mapEl = document.getElementById(this.mapId);
            if (!mapEl) {
                throw new Error('Contenedor del mapa no encontrado.');
            }

            const mapCenter = center || this.defaultCenter;

            if (!this.mapBooted) {
                this.map = new google.maps.Map(mapEl, {
                    center: mapCenter,
                    zoom,
                    disableDefaultUI: false,
                    zoomControl: true,
                    mapTypeControl: true,
                    streetViewControl: false,
                });
                this.infoWindow = new google.maps.InfoWindow({
                    maxWidth: 320,
                });
                this.geocoder = new google.maps.Geocoder();
                this.placesService = new google.maps.places.PlacesService(this.map);
                const addressInput = document.getElementById(this.rootId + '-address');
                if (addressInput && !addressInput.dataset.autocompleteBound) {
                    addressInput.dataset.autocompleteBound = '1';
                    this.autocomplete = new google.maps.places.Autocomplete(addressInput, {
                        componentRestrictions: { country: 've' },
                        fields: ['formatted_address', 'geometry', 'name'],
                    });
                    this.autocomplete.addListener('place_changed', () => {
                        const place = this.autocomplete.getPlace();
                        if (!place.geometry || !place.geometry.location) {
                            return;
                        }
                        const formatted = place.formatted_address || place.name || addressInput.value;
                        addressInput.value = formatted;
                        this.lastSearchAddress = formatted;
                        this.setSelectedAddress(formatted);
                        const supplierLoc = toLatLngLiteral(place.geometry.location);
                        this.lastSearchedLocation = supplierLoc;
                        this.map.setCenter(supplierLoc);
                        this.map.setZoom(15);
                        this.placeSupplierMarker(supplierLoc, formatted);
                        this.searchNearbyEstablishments(supplierLoc);
                    });
                }
                this.bindDestinationAutocomplete();
                this.map.addListener('click', (event) => {
                    if (!this.supplierLocation) {
                        return;
                    }
                    this.geocoder.geocode({ location: event.latLng }, (results, status) => {
                        if (status !== 'OK' || !results[0]) {
                            return;
                        }
                        const literal = toLatLngLiteral(event.latLng);
                        const formatted = results[0].formatted_address;
                        this.selectDestination(literal, formatted, 'Punto en el mapa');
                        if (this.destinationMarker) {
                            this.openInfoWindow(this.destinationMarker, {
                                title: 'Destino seleccionado',
                                subtitle: formatted,
                                meta: this.lastRouteSummary
                                    ? 'Recorrido: ' + this.lastRouteSummary
                                    : null,
                            });
                        }
                    });
                });
                this.mapBooted = true;
            } else if (center) {
                this.map.setCenter(center);
                this.map.setZoom(zoom);
            }

            this.scheduleResize();
        }

        bindDestinationAutocomplete() {
            const destInput = this.el('-destination-address');
            if (!destInput || destInput.dataset.autocompleteBound) {
                return;
            }
            destInput.dataset.autocompleteBound = '1';
            this.destinationAutocomplete = new google.maps.places.Autocomplete(destInput, {
                componentRestrictions: { country: 've' },
                fields: ['formatted_address', 'geometry', 'name'],
            });
            this.destinationAutocomplete.addListener('place_changed', () => {
                const place = this.destinationAutocomplete.getPlace();
                if (!place.geometry || !place.geometry.location) {
                    return;
                }
                const formatted = place.formatted_address || place.name || destInput.value;
                this.selectDestination(
                    toLatLngLiteral(place.geometry.location),
                    formatted,
                    place.name || 'Destino'
                );
            });
        }

        clearEstablishmentMarkers() {
            this.establishmentMarkers.forEach((marker) => marker.setMap(null));
            this.establishmentMarkers = [];
        }

        updateRadiusCircle(location, radiusKm) {
            if (this.radiusCircle) {
                this.radiusCircle.setMap(null);
                this.radiusCircle = null;
            }
            if (!this.map || !location) {
                return;
            }
            this.radiusCircle = new google.maps.Circle({
                map: this.map,
                center: location,
                radius: radiusKm * 1000,
                fillColor: '#0284c7',
                fillOpacity: 0.12,
                strokeColor: '#0284c7',
                strokeOpacity: 0.45,
                strokeWeight: 1,
            });
        }

        placeSupplierMarker(location, address) {
            if (this.supplierMarker) {
                this.supplierMarker.setMap(null);
            }
            this.supplierAddress = (address || '').trim();
            this.supplierLocation = toLatLngLiteral(location) || location;
            this.supplierMarker = new google.maps.Marker({
                map: this.map,
                position: location,
                title: this.recordLabel + ' (' + this.subjectRoleLabel + ')',
                zIndex: google.maps.Marker.MAX_ZINDEX + 2,
            });
            this.supplierMarker.addListener('click', () => {
                this.setSelectedAddress(address);
                this.openInfoWindow(this.supplierMarker, {
                    title: this.recordLabel + ' (' + this.subjectRoleLabel + ')',
                    subtitle: address,
                    footer: 'Punto de referencia para búsqueda y rutas.',
                });
            });
        }

        placeDestinationMarker(location, title) {
            if (this.destinationMarker) {
                this.destinationMarker.setMap(null);
            }
            const destinationTitle = title || 'Destino';
            this.destinationMarker = new google.maps.Marker({
                map: this.map,
                position: location,
                title: destinationTitle,
                zIndex: google.maps.Marker.MAX_ZINDEX + 1,
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    fillColor: '#2563eb',
                    fillOpacity: 1,
                    strokeColor: '#ffffff',
                    strokeWeight: 3,
                    scale: 10,
                },
            });
            this.destinationMarker.addListener('click', () => {
                this.openInfoWindow(this.destinationMarker, {
                    title: destinationTitle,
                    subtitle: this.selectedDestinationAddress,
                    meta: this.lastRouteSummary
                        ? 'Recorrido: ' + this.lastRouteSummary
                        : 'Seleccione «Calcular ruta» o un establecimiento cercano.',
                });
            });
        }

        placeEstablishmentMarker(pos, title, content, color) {
            const marker = new google.maps.Marker({
                map: this.map,
                position: pos,
                title,
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    fillColor: color,
                    fillOpacity: 1,
                    strokeWeight: 2,
                    strokeColor: '#ffffff',
                    scale: 8,
                },
                zIndex: 1,
            });
            this.establishmentMarkers.push(marker);
            marker.addListener('click', () => {
                const literal = toLatLngLiteral(pos);
                if (!literal) {
                    return;
                }
                const subtitle = (content || '').trim();
                this.geocoder.geocode({ location: literal }, (results, status) => {
                    const addressForRoute = status === 'OK' && results[0]
                        ? results[0].formatted_address
                        : (subtitle ? title + ', ' + subtitle + ', Venezuela' : title + ', Venezuela');
                    this.selectDestination(literal, addressForRoute, title);
                    this.openInfoWindow(marker, {
                        title,
                        subtitle: addressForRoute,
                        meta: this.lastRouteSummary
                            ? 'Recorrido: ' + this.lastRouteSummary
                            : 'Calculando ruta…',
                    });
                });
                this.openInfoWindow(marker, {
                    title,
                    subtitle: subtitle || 'Establecimiento cercano',
                    meta: 'Obteniendo dirección y ruta…',
                });
            });
        }

        selectDestination(location, address, title = 'Destino') {
            if (!this.supplierLocation) {
                this.setStatus('Primero busque y ubique el punto de referencia en el mapa.', true);
                return;
            }
            const literal = toLatLngLiteral(location);
            if (!literal) {
                this.setStatus('No se pudo leer la ubicación del destino.', true);
                return;
            }
            this.setDestinationAddress(address);
            this.placeDestinationMarker(literal, title);
            this.drawRouteTo(literal, address);
        }

        drawFallbackRoute(originLiteral, destinationLiteral, destinationAddress) {
            this.clearFallbackPolyline();
            if (this.directionsRenderer) {
                this.directionsRenderer.setMap(null);
            }

            this.fallbackPolyline = new google.maps.Polyline({
                map: this.map,
                path: [originLiteral, destinationLiteral],
                geodesic: true,
                strokeColor: '#2563eb',
                strokeOpacity: 0.75,
                strokeWeight: 4,
            });

            const km = haversineKm(originLiteral, destinationLiteral);
            const minutes = Math.max(1, Math.round((km / 35) * 60));
            this.setRouteInfo(
                km.toFixed(1) + ' km (aprox.)',
                minutes + ' min (estimado)',
                destinationAddress
            );
            if (isDirectionsApiDenied()) {
                this.setStatus(directionsDeniedHint(), true);
            } else {
                this.setStatus(
                    'Ruta aproximada en línea recta. Para rutas por calles, active Directions API en Google Cloud.'
                );
            }
            this.scheduleResize();
        }

        drawRouteTo(destination, destinationAddress) {
            const originLiteral = toLatLngLiteral(this.supplierLocation);
            const destLiteral = toLatLngLiteral(destination);

            if (!originLiteral || !destLiteral) {
                this.setStatus('Coordenadas de origen o destino no válidas.', true);
                return;
            }

            if (this.isRouting) {
                return;
            }

            if (isDirectionsApiDenied()) {
                this.drawFallbackRoute(originLiteral, destLiteral, destinationAddress);
                this.setStatus(directionsDeniedHint(), true);
                return;
            }

            this.isRouting = true;

            if (!this.directionsService) {
                this.directionsService = new google.maps.DirectionsService();
            }
            if (!this.directionsRenderer) {
                this.directionsRenderer = new google.maps.DirectionsRenderer({
                    map: this.map,
                    suppressMarkers: true,
                    polylineOptions: {
                        strokeColor: '#2563eb',
                        strokeOpacity: 0.85,
                        strokeWeight: 5,
                    },
                });
            } else {
                this.directionsRenderer.setMap(this.map);
            }

            const supplierAddress = (this.supplierAddress || this.lastSearchAddress || '').trim();
            const destAddress = (destinationAddress || '').trim();
            const attempts = [];

            if (supplierAddress && destAddress) {
                attempts.push({
                    origin: supplierAddress.includes('Venezuela') ? supplierAddress : supplierAddress + ', Venezuela',
                    destination: destAddress.includes('Venezuela') ? destAddress : destAddress + ', Venezuela',
                });
            }

            attempts.push({ origin: originLiteral, destination: destLiteral });

            if (destAddress) {
                attempts.push({
                    origin: originLiteral,
                    destination: destAddress.includes('Venezuela') ? destAddress : destAddress + ', Venezuela',
                });
            }

            if (supplierAddress) {
                attempts.push({
                    origin: supplierAddress.includes('Venezuela') ? supplierAddress : supplierAddress + ', Venezuela',
                    destination: destLiteral,
                });
            }

            const travelModes = [
                google.maps.TravelMode.DRIVING,
                google.maps.TravelMode.WALKING,
            ];

            const tryNext = (attemptIndex, modeIndex) => {
                if (attemptIndex >= attempts.length) {
                    this.isRouting = false;
                    this.drawFallbackRoute(originLiteral, destLiteral, destinationAddress);
                    return;
                }

                if (modeIndex >= travelModes.length) {
                    tryNext(attemptIndex + 1, 0);
                    return;
                }

                const request = {
                    ...attempts[attemptIndex],
                    travelMode: travelModes[modeIndex],
                    region: 've',
                };

                this.directionsService.route(request, (result, status) => {
                    if (status === 'OK' && result?.routes?.[0]?.legs?.[0]) {
                        this.isRouting = false;
                        this.clearFallbackPolyline();
                        this.directionsRenderer.setMap(this.map);
                        this.directionsRenderer.setDirections(result);
                        const leg = result.routes[0].legs[0];
                        this.setRouteInfo(
                            leg.distance.text,
                            leg.duration.text,
                            destinationAddress
                        );
                        if (this.destinationMarker) {
                            this.openInfoWindow(this.destinationMarker, {
                                title: 'Destino',
                                subtitle: destinationAddress,
                                meta: 'Recorrido: ' + this.lastRouteSummary,
                            });
                        }
                        this.scheduleResize();
                        return;
                    }

                    if (status === 'REQUEST_DENIED') {
                        markDirectionsApiDenied();
                        this.isRouting = false;
                        this.drawFallbackRoute(originLiteral, destLiteral, destinationAddress);
                        this.setStatus(directionsErrorMessage(status), true);
                        return;
                    }

                    if (status === 'OVER_QUERY_LIMIT') {
                        this.isRouting = false;
                        this.drawFallbackRoute(originLiteral, destLiteral, destinationAddress);
                        this.setStatus(directionsErrorMessage(status), true);
                        return;
                    }

                    if (modeIndex + 1 < travelModes.length) {
                        tryNext(attemptIndex, modeIndex + 1);
                    } else {
                        tryNext(attemptIndex + 1, 0);
                    }
                });
            };

            tryNext(0, 0);
        }

        async calculateRouteFromInput() {
            const destInput = this.el('-destination-address');
            const address = (destInput?.value || '').trim();
            if (!this.supplierLocation) {
                this.setStatus('Primero busque la ubicación de referencia en el mapa.', true);
                return;
            }
            if (!address) {
                this.setStatus('Ingrese una dirección de destino.', true);
                return;
            }
            await this.ensureMap();
            this.geocoder.geocode({ address, componentRestrictions: { country: 'VE' } }, (results, status) => {
                if (status !== 'OK' || !results[0]) {
                    this.setStatus('No se encontró el destino indicado.', true);
                    return;
                }
                const loc = toLatLngLiteral(results[0].geometry.location);
                this.selectDestination(loc, results[0].formatted_address, address);
            });
        }

        getRadiusKm() {
            const radiusInput = document.getElementById(this.rootId + '-radius');
            return parseFloat(radiusInput?.value || '10');
        }

        async searchNearbyEstablishments(location) {
            if (!this.placesService || !location) {
                return;
            }
            const radiusKm = this.getRadiusKm();
            if (isNaN(radiusKm) || radiusKm <= 0 || radiusKm > 50) {
                return;
            }

            this.updateRadiusCircle(location, radiusKm);
            this.clearEstablishmentMarkers();

            let foundCount = 0;
            const selectedTypes = this.poiTypes.filter((_, index) => this.filterChecked[index]);

            for (const poi of selectedTypes) {
                await new Promise((resolve) => {
                    this.placesService.nearbySearch(
                        {
                            location,
                            radius: radiusKm * 1000,
                            keyword: poi.keyword,
                        },
                        (places, status) => {
                            if (status === google.maps.places.PlacesServiceStatus.OK && places) {
                                places.forEach((place) => {
                                    if (!place.geometry || !place.geometry.location) {
                                        return;
                                    }
                                    foundCount++;
                                    this.placeEstablishmentMarker(
                                        place.geometry.location,
                                        place.name,
                                        place.vicinity || '',
                                        poi.color
                                    );
                                });
                            }
                            resolve();
                        }
                    );
                });
            }

            this.setStatus(
                'Ubicación de referencia marcada. Se encontraron ' + foundCount + ' establecimientos en un radio de ' + radiusKm + ' km.'
            );
            this.scheduleResize();
        }

        async searchAndRender() {
            if (this.isSearching) {
                return;
            }
            const addressInput = document.getElementById(this.rootId + '-address');
            const address = (addressInput?.value || '').trim();
            const radiusKm = this.getRadiusKm();

            if (!address || isNaN(radiusKm) || radiusKm <= 0 || radiusKm > 50) {
                this.setStatus('Ingrese la dirección de referencia y un radio válido (1–50 km).', true);
                return;
            }

            this.isSearching = true;
            this.lastSearchAddress = address;
            this.setSelectedAddress(address);

            try {
                await this.ensureMap();
                this.geocoder.geocode({ address, componentRestrictions: { country: 'VE' } }, async (results, status) => {
                    this.isSearching = false;
                    if (status !== 'OK' || !results[0]) {
                        this.setStatus('No se encontró la ubicación de referencia. Verifique la dirección.', true);
                        return;
                    }
                    const loc = toLatLngLiteral(results[0].geometry.location);
                    this.lastSearchedLocation = loc;
                    this.clearRoute();
                    this.map.setCenter(loc);
                    this.map.setZoom(14);
                    this.placeSupplierMarker(loc, address);
                    const routeBtn = this.el('-route-btn');
                    if (routeBtn) {
                        routeBtn.disabled = false;
                    }
                    await this.searchNearbyEstablishments(loc);
                });
            } catch (error) {
                this.isSearching = false;
                console.error(error);
                this.setStatus('Error al cargar el mapa. Verifique GOOGLE_MAPS_API_KEY y la consola (F12).', true);
            }
        }

        useSearchAddress() {
            if (this.lastSearchAddress) {
                const addressInput = document.getElementById(this.rootId + '-address');
                if (addressInput) {
                    this.setSelectedAddress(addressInput.value.trim() || this.lastSearchAddress);
                } else {
                    this.setSelectedAddress(this.lastSearchAddress);
                }
                this.setStatus('Dirección de referencia seleccionada para guardar.');
            }
        }

        useDestinationAddress() {
            if (this.selectedDestinationAddress) {
                this.setSelectedAddress(this.selectedDestinationAddress);
                this.setStatus('Destino seleccionado listo para guardar.');
            }
        }

        resolveLivewire() {
            const root = this.rootEl.closest('[wire\\:id]');
            if (!root || !window.Livewire) {
                return null;
            }
            return window.Livewire.find(root.getAttribute('wire:id'));
        }

        bindActions() {
            const searchBtn = document.getElementById(this.rootId + '-search-btn');
            if (searchBtn && !searchBtn.dataset.bound) {
                searchBtn.dataset.bound = '1';
                searchBtn.addEventListener('click', () => this.searchAndRender());
            }
            const useBtn = document.getElementById(this.rootId + '-use-search-address');
            if (useBtn && !useBtn.dataset.bound) {
                useBtn.dataset.bound = '1';
                useBtn.addEventListener('click', () => this.useSearchAddress());
            }
            const useDestBtn = document.getElementById(this.rootId + '-use-destination');
            if (useDestBtn && !useDestBtn.dataset.bound) {
                useDestBtn.dataset.bound = '1';
                useDestBtn.addEventListener('click', () => this.useDestinationAddress());
            }
            const routeBtn = document.getElementById(this.rootId + '-route-btn');
            if (routeBtn && !routeBtn.dataset.bound) {
                routeBtn.dataset.bound = '1';
                routeBtn.addEventListener('click', () => this.calculateRouteFromInput());
            }
            const applyBtn = document.getElementById(this.rootId + '-apply');
            if (applyBtn && !applyBtn.dataset.bound) {
                applyBtn.dataset.bound = '1';
                applyBtn.addEventListener('click', () => {
                    if (!this.selectedAddress) {
                        this.setStatus('Seleccione una dirección antes de guardar.', true);
                        return;
                    }
                    const livewire = this.resolveLivewire();
                    if (livewire) {
                        livewire.call(this.applyLivewireMethod, this.selectedAddress);
                    }
                });
            }
            const radiusInput = document.getElementById(this.rootId + '-radius');
            if (radiusInput && !radiusInput.dataset.bound) {
                radiusInput.dataset.bound = '1';
                radiusInput.addEventListener('change', () => {
                    if (this.lastSearchedLocation) {
                        this.searchNearbyEstablishments(this.lastSearchedLocation);
                    }
                });
            }
        }

        async boot() {
            this.renderFilters();
            this.bindActions();
            this.setSelectedAddress(this.initialAddress);
            try {
                await this.ensureMap();
                this.bindDestinationAutocomplete();
                if (this.initialAddress) {
                    await this.searchAndRender();
                } else {
                    this.setStatus('Mapa listo. Ingrese la dirección de referencia y pulse «Buscar en mapa».');
                }
            } catch (error) {
                console.error(error);
                this.setStatus('No fue posible cargar Google Maps. Revise la clave API.', true);
            }
        }
    }

    window.SupplierLocationMaps = SupplierLocationMaps;

    function bootRoot(rootEl) {
        if (!rootEl || !document.getElementById(rootEl.id + '-maps-config')) {
            return;
        }

        const existing = rootEl.__supplierMapsInstance;
        if (existing && existing.mapBooted) {
            existing.scheduleResize();
            return;
        }

        if (rootEl.dataset.mapsBooting === '1') {
            return;
        }
        rootEl.dataset.mapsBooting = '1';

        const instance = new SupplierLocationMaps(rootEl);
        rootEl.__supplierMapsInstance = instance;

        const run = () => {
            instance.boot().finally(() => {
                rootEl.dataset.mapsBooted = '1';
                rootEl.dataset.mapsBooting = '0';
            });
        };

        requestAnimationFrame(() => setTimeout(run, 120));
    }

    function bootIfPresent() {
        document.querySelectorAll('.supplier-location-maps-root').forEach((rootEl) => {
            if (document.getElementById(rootEl.id + '-maps-config')) {
                bootRoot(rootEl);
            }
        });
    }

    function scheduleBoot() {
        setTimeout(bootIfPresent, 50);
        setTimeout(bootIfPresent, 200);
        setTimeout(bootIfPresent, 500);
    }

    function attachThemeObserver() {
        if (window.__supplierMapsThemeObserver) {
            return;
        }

        window.__supplierMapsThemeObserver = new MutationObserver(() => {
            document.querySelectorAll('.supplier-location-maps-root').forEach((rootEl) => {
                rootEl.__supplierMapsInstance?.refreshFilterStyles?.();
            });
        });

        window.__supplierMapsThemeObserver.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class'],
        });

        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
            document.querySelectorAll('.supplier-location-maps-root').forEach((rootEl) => {
                rootEl.__supplierMapsInstance?.refreshFilterStyles?.();
            });
        });
    }

    if (!window.__supplierMapsListenersAttached) {
        window.__supplierMapsListenersAttached = true;
        attachThemeObserver();
        document.addEventListener('x-modal-opened', scheduleBoot);
        document.addEventListener('open-modal', scheduleBoot);
        document.addEventListener('livewire:navigated', scheduleBoot);
        document.addEventListener('livewire:initialized', scheduleBoot);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scheduleBoot);
    } else {
        scheduleBoot();
    }
})();
