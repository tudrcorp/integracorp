@php
    $showInstallTrigger = $showInstallTrigger ?? request()->routeIs('home');
@endphp

@if ($showInstallTrigger)
    <div
        x-data="{
            deferredPrompt: null,
            installMode: null,
            canInstall: false,
            showInstallHelpModal: false,
            isInstalled() {
                const isStandalone = window.matchMedia?.('(display-mode: standalone)')?.matches ?? false;
                const isIosStandalone = window.navigator.standalone === true;
                return isStandalone || isIosStandalone;
            },
            isIosDevice() {
                const userAgent = window.navigator.userAgent || '';
                const platform = window.navigator.platform || '';
                const maxTouchPoints = window.navigator.maxTouchPoints || 0;

                const iOSUserAgent = /iPad|iPhone|iPod/.test(userAgent);
                const iPadOS = platform === 'MacIntel' && maxTouchPoints > 1;

                return iOSUserAgent || iPadOS;
            },
            ensureHeadLinks() {
                const manifestHref = '/manifest.json';
                const existingManifest = document.querySelector('link[rel=\'manifest\']');

                if (existingManifest) {
                    const currentHref = existingManifest.getAttribute('href') ?? '';
                    if (currentHref !== manifestHref) {
                        existingManifest.setAttribute('href', manifestHref);
                    }
                } else {
                    const manifest = document.createElement('link');
                    manifest.rel = 'manifest';
                    manifest.href = manifestHref;
                    document.head.appendChild(manifest);
                }

                if (!document.querySelector('link[rel=\'apple-touch-icon\']')) {
                    const appleTouchIcon = document.createElement('link');
                    appleTouchIcon.rel = 'apple-touch-icon';
                    appleTouchIcon.href = '/pwa/apple-touch-icon.png';
                    document.head.appendChild(appleTouchIcon);
                }

                if (!document.querySelector('meta[name=\'theme-color\']')) {
                    const themeColor = document.createElement('meta');
                    themeColor.name = 'theme-color';
                    themeColor.content = '#4c566a';
                    document.head.appendChild(themeColor);
                }
            },
            registerServiceWorker() {
                if (!('serviceWorker' in navigator)) {
                    return;
                }

                navigator.serviceWorker.register('/sw.js').catch(() => {});
            },
            init() {
                this.ensureHeadLinks();
                this.registerServiceWorker();

                if (this.isInstalled()) {
                    return;
                }

                if (this.isIosDevice()) {
                    this.installMode = 'ios';
                    this.canInstall = true;
                    return;
                }

                this.installMode = 'manual';
                this.canInstall = true;

                window.addEventListener('beforeinstallprompt', (event) => {
                    event.preventDefault();
                    this.deferredPrompt = event;
                    this.installMode = 'prompt';
                    this.canInstall = true;
                });

                window.addEventListener('appinstalled', () => {
                    this.deferredPrompt = null;
                    this.installMode = null;
                    this.canInstall = false;
                    this.showInstallHelpModal = false;
                });
            },
            openInstall() {
                if (this.installMode === 'ios') {
                    this.showInstallHelpModal = true;
                    return;
                }

                if (this.installMode === 'prompt' && this.deferredPrompt) {
                    this.promptInstall();
                    return;
                }

                this.showInstallHelpModal = true;
            },
            async promptInstall() {
                if (!this.deferredPrompt) {
                    this.showInstallHelpModal = true;
                    return;
                }

                await this.deferredPrompt.prompt();
                await this.deferredPrompt.userChoice;
                this.deferredPrompt = null;
                this.installMode = null;
                this.canInstall = false;
            },
        }"
        x-init="init()"
    >
        <div x-cloak x-show="canInstall" class="fixed bottom-6 right-6 z-[70]">
            <button
                type="button"
                x-on:click="openInstall()"
                class="group inline-flex h-12 w-12 items-center justify-center rounded-full border border-white/20 bg-black/55 text-white shadow-lg backdrop-blur-md transition hover:scale-105 hover:border-white/40 hover:bg-black/70 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/70"
                title="Instalar app"
                aria-label="Instalar app"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 3v9" />
                    <path d="M8.75 9.75 12 13l3.25-3.25" />
                    <path d="M7 14.5h10" />
                    <path d="M6.5 14.5v3A2 2 0 0 0 8.5 19.5h7A2 2 0 0 0 17.5 17.5v-3" />
                </svg>
            </button>
        </div>

        <div
            x-cloak
            x-show="showInstallHelpModal"
            x-transition.opacity
            class="fixed inset-0 z-[80] flex items-center justify-center bg-black/70 px-4"
            x-on:click.self="showInstallHelpModal = false"
        >
            <div class="w-full max-w-md rounded-2xl border border-white/15 bg-slate-900/95 p-6 text-white shadow-2xl backdrop-blur">
                <h3 class="text-lg font-semibold" x-text="installMode === 'ios' ? 'Instala Integracorp en tu iPhone' : 'Instala Integracorp en tu navegador'"></h3>
                <p class="mt-2 text-sm text-slate-200">
                    <span x-show="installMode === 'ios'">
                        Safari en iOS no permite instalación automática. Para agregar la app, sigue estos pasos:
                    </span>
                    <span x-show="installMode !== 'ios'">
                        Si tu navegador no muestra el instalador automático, puedes instalar la app desde el menú del navegador.
                    </span>
                </p>
                <ol class="mt-4 list-decimal space-y-2 pl-5 text-sm text-slate-100">
                    <template x-if="installMode === 'ios'">
                        <div class="contents">
                            <li>Toca el botón <span class="font-semibold">Compartir</span> de Safari.</li>
                            <li>Selecciona <span class="font-semibold">Añadir a pantalla de inicio</span>.</li>
                            <li>Confirma en <span class="font-semibold">Añadir</span>.</li>
                        </div>
                    </template>
                    <template x-if="installMode !== 'ios'">
                        <div class="contents">
                            <li>Abre el menú del navegador (⋮ o Ajustes).</li>
                            <li>Busca la opción <span class="font-semibold">Instalar app</span> o <span class="font-semibold">Añadir a pantalla de inicio</span>.</li>
                            <li>Confirma la instalación.</li>
                        </div>
                    </template>
                </ol>
                <button
                    type="button"
                    class="mt-6 inline-flex w-full items-center justify-center rounded-lg bg-white/10 px-4 py-2 text-sm font-medium transition hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/70"
                    x-on:click="showInstallHelpModal = false"
                >
                    Entendido
                </button>
            </div>
        </div>
    </div>
@endif
