@php
    $mapsKey = (string) config('services.google_maps.api_key', '');
    $defaultLat = (float) config('services.google_maps.default_lat', 10.4806);
    $defaultLng = (float) config('services.google_maps.default_lng', -66.9036);
    $initialAddress = filled($initialAddress ?? null) ? trim((string) $initialAddress) : '';
    $recordLabel = filled($recordLabel ?? null) ? trim((string) $recordLabel) : '';
    $recordIdSlug = preg_replace('/[^a-zA-Z0-9_-]/', '-', (string) ($recordId ?? '0')) ?: '0';
    $mapElementId = 'operations-location-map-'.$recordIdSlug;
    $rootId = 'operations-location-maps-root-'.$recordIdSlug;
    $mapsConfig = [
        'rootId' => $rootId,
        'mapId' => $mapElementId,
        'apiKey' => $mapsKey,
        'defaultCenter' => ['lat' => $defaultLat, 'lng' => $defaultLng],
        'initialAddress' => $initialAddress,
        'recordLabel' => $recordLabel,
        'subjectRoleLabel' => (string) ($subjectRoleLabel ?? 'ubicación'),
        'applyLivewireMethod' => (string) ($livewireApplyMethod ?? 'applyLocationFromMaps'),
    ];
@endphp

<div class="fi-operations-location-maps-modal space-y-4">
    <style>
        /* Globo de Google Maps dentro del modal Filament */
        .fi-operations-location-maps-modal .gm-style-iw-c {
            padding: 0 !important;
            max-width: 320px !important;
        }

        .fi-operations-location-maps-modal .gm-style-iw-d {
            overflow: visible !important;
            max-height: none !important;
        }

        .fi-operations-location-maps-modal .gm-style-iw-chr {
            height: 36px !important;
        }

        .fi-operations-location-maps-modal .gm-ui-hover-effect {
            width: 36px !important;
            height: 36px !important;
            top: 2px !important;
            right: 2px !important;
        }

        .fi-operations-location-maps-modal .operations-map-field {
            display: block;
            width: 100%;
            border-radius: 0.75rem;
            border: 1px solid rgb(226 232 240);
            background-color: rgb(255 255 255);
            padding: 0.625rem 0.875rem;
            font-size: 0.875rem;
            line-height: 1.25rem;
            color: rgb(15 23 42);
            box-shadow: 0 1px 2px rgb(15 23 42 / 0.05);
            transition: border-color 0.15s, box-shadow 0.15s;
        }

        .fi-operations-location-maps-modal .operations-map-field::placeholder {
            color: rgb(100 116 139);
        }

        .fi-operations-location-maps-modal .operations-map-field:focus {
            outline: none;
            border-color: rgb(14 165 233);
            box-shadow: 0 0 0 3px rgb(14 165 233 / 0.25);
        }

        .dark .fi-operations-location-maps-modal .operations-map-field {
            border-color: rgb(71 85 105);
            background-color: rgb(30 41 59);
            color: rgb(248 250 252);
            box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.04);
        }

        .dark .fi-operations-location-maps-modal .operations-map-field::placeholder {
            color: rgb(148 163 184);
        }

        .dark .fi-operations-location-maps-modal .operations-map-field:focus {
            border-color: rgb(56 189 248);
            box-shadow: 0 0 0 3px rgb(56 189 248 / 0.2);
        }
    </style>
    @if ($mapsKey === '')
        <div class="rounded-xl border border-amber-200/90 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-500/30 dark:bg-amber-950/40 dark:text-amber-50">
            Configure <code class="font-mono text-xs">GOOGLE_MAPS_API_KEY</code> en el entorno y ejecute <code class="font-mono text-xs">php artisan config:clear</code>.
        </div>
    @else
        <script type="application/json" id="{{ $rootId }}-maps-config">@json($mapsConfig)</script>
        <div
            id="{{ $rootId }}"
            class="supplier-location-maps-root space-y-4"
        >
            <div class="rounded-xl border border-slate-200/90 bg-slate-50/80 px-4 py-3 text-sm text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">
                <p class="font-semibold text-slate-900 dark:text-white">{{ $recordLabel !== '' ? $recordLabel : 'Ubicación' }}</p>
                <p class="mt-1 text-xs opacity-80">{{ $introText }}</p>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
                <div class="md:col-span-6">
                    <label class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200" for="{{ $rootId }}-address">
                        {{ $addressInputLabel }}
                    </label>
                    <input
                        type="text"
                        id="{{ $rootId }}-address"
                        value="{{ $initialAddress }}"
                        placeholder="Ej: Las Mercedes, Caracas"
                        class="operations-map-field"
                    />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200" for="{{ $rootId }}-radius">
                        Radio (km)
                    </label>
                    <input
                        type="number"
                        id="{{ $rootId }}-radius"
                        value="10"
                        min="1"
                        max="50"
                        class="operations-map-field"
                    />
                </div>
                <div class="flex items-end md:col-span-4">
                    <button
                        type="button"
                        id="{{ $rootId }}-search-btn"
                        class="fi-btn fi-btn-size-md relative inline-grid w-full grid-flow-col items-center justify-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm outline-none transition duration-75 hover:bg-primary-500 focus-visible:ring-2 focus-visible:ring-primary-500/40"
                    >
                        Buscar en mapa
                    </button>
                </div>
            </div>

            <div>
                <p class="mb-2 text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">
                    Establecimientos a mostrar (misma lógica que maps-tres)
                </p>
                <div id="{{ $rootId }}-filters" class="flex flex-wrap gap-2"></div>
            </div>

            <div class="rounded-xl border border-sky-200/80 bg-gradient-to-br from-sky-50/90 to-white p-4 dark:border-sky-500/30 dark:from-sky-950/40 dark:to-slate-900/50">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="min-w-0 flex-1">
                        <label class="block text-sm font-semibold text-slate-800 dark:text-slate-100" for="{{ $rootId }}-destination-address">
                            Destino
                        </label>
                        <p class="mb-2 text-xs text-slate-600 dark:text-slate-400">
                            Clic en el mapa, un establecimiento o escriba una dirección cercana.
                        </p>
                        <input
                            type="text"
                            id="{{ $rootId }}-destination-address"
                            placeholder="Ej: Farmacia, clínica, dirección…"
                            class="operations-map-field"
                        />
                    </div>
                    <button
                        type="button"
                        id="{{ $rootId }}-route-btn"
                        class="inline-flex h-[42px] w-full shrink-0 items-center justify-center gap-2 rounded-xl bg-sky-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-400/50 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto sm:min-w-[10.5rem]"
                        disabled
                    >
                        <svg class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m0 0l-3-3m3 3l3-3M4.5 19.5h15" />
                        </svg>
                        Calcular ruta
                    </button>
                </div>
            </div>

            <div id="{{ $rootId }}-status" class="hidden rounded-xl px-4 py-3 text-sm font-medium"></div>

            <div
                id="{{ $rootId }}-route-panel"
                class="hidden rounded-xl border border-sky-200/90 bg-sky-50/80 px-4 py-3 text-sm text-sky-950 dark:border-sky-500/30 dark:bg-sky-950/30 dark:text-sky-50"
            >
                <p class="text-xs font-semibold uppercase tracking-wider text-sky-800/80 dark:text-sky-200/70">{{ $routeFromLabel }}</p>
                <p id="{{ $rootId }}-route-summary" class="mt-1 font-medium"></p>
                <p id="{{ $rootId }}-destination-preview" class="mt-1 text-xs opacity-80"></p>
            </div>

            <div wire:ignore>
                <div
                    id="{{ $mapElementId }}"
                    class="supplier-location-maps-canvas min-h-[320px] h-[min(52vh,420px)] w-full overflow-hidden rounded-xl bg-slate-200 ring-1 ring-gray-950/10 dark:bg-slate-800 dark:ring-white/10"
                    role="region"
                    aria-label="{{ $mapAriaLabel }}"
                ></div>
            </div>

            <p class="text-xs text-gray-500 dark:text-gray-400">
                <span class="inline-block align-middle text-base leading-none" aria-hidden="true">📍</span>
                Pin rojo: punto de referencia.
                <span class="inline-block h-2.5 w-2.5 rounded-full bg-sky-600 align-middle"></span>
                Punto azul: destino seleccionado.
                Círculos de colores: establecimientos cercanos.
                Línea azul: ruta y tiempo estimado en carro.
            </p>

            <div class="rounded-xl border border-primary-200/80 bg-primary-50/60 px-4 py-3 dark:border-primary-500/25 dark:bg-primary-950/30">
                <p class="text-xs font-semibold uppercase tracking-wider text-primary-800/80 dark:text-primary-200/70">Dirección seleccionada</p>
                <p id="{{ $rootId }}-selected-preview" class="mt-1 text-sm font-medium text-primary-950 dark:text-primary-50">
                    {{ $initialAddress !== '' ? $initialAddress : 'Seleccione un punto en el mapa o busque una dirección.' }}
                </p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button
                        type="button"
                        id="{{ $rootId }}-use-search-address"
                        class="fi-btn relative inline-grid grid-flow-col items-center justify-center gap-1.5 rounded-lg bg-white px-3 py-2 text-sm font-semibold text-gray-950 shadow-sm ring-1 ring-gray-950/10 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:ring-white/20 dark:hover:bg-white/15"
                    >
                        {{ $useSubjectAddressButtonLabel }}
                    </button>
                    <button
                        type="button"
                        id="{{ $rootId }}-use-destination"
                        class="fi-btn relative inline-grid grid-flow-col items-center justify-center gap-1.5 rounded-lg bg-white px-3 py-2 text-sm font-semibold text-gray-950 shadow-sm ring-1 ring-gray-950/10 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:ring-white/20 dark:hover:bg-white/15 disabled:opacity-50"
                        disabled
                    >
                        Usar destino seleccionado
                    </button>
                    <button
                        type="button"
                        id="{{ $rootId }}-apply"
                        class="fi-btn relative inline-grid grid-flow-col items-center justify-center gap-1.5 rounded-lg bg-success-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-success-500 disabled:opacity-50"
                        @disabled($initialAddress === '')
                    >
                        {{ $saveButtonLabel }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
