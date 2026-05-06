@php
    $showInstallTrigger = $showInstallTrigger ?? request()->routeIs('home');
@endphp

@if ($showInstallTrigger)
    <div
        x-data="{
            deferredPrompt: null,
            canInstall: false,
            isInstalled() {
                const isStandalone = window.matchMedia?.('(display-mode: standalone)')?.matches ?? false;
                const isIosStandalone = window.navigator.standalone === true;
                return isStandalone || isIosStandalone;
            },
            browserSupportsInstallPrompt() {
                return 'onbeforeinstallprompt' in window;
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

                if (!this.browserSupportsInstallPrompt()) {
                    return;
                }

                window.addEventListener('beforeinstallprompt', (event) => {
                    event.preventDefault();
                    this.deferredPrompt = event;
                    this.canInstall = true;
                });

                window.addEventListener('appinstalled', () => {
                    this.deferredPrompt = null;
                    this.canInstall = false;
                });
            },
            async openInstall() {
                if (!this.deferredPrompt) {
                    return;
                }

                await this.deferredPrompt.prompt();
                await this.deferredPrompt.userChoice;
                this.deferredPrompt = null;
                this.canInstall = false;
            },
        }"
        x-init="init()"
    >
        <div x-cloak x-show="canInstall" class="fixed bottom-6 right-6 z-[70]">
            <button
                type="button"
                x-on:click="openInstall()"
                class="group inline-flex h-12 items-center justify-center gap-2 rounded-full border border-white/20 bg-black/55 px-4 text-white shadow-lg backdrop-blur-md transition hover:scale-105 hover:border-white/40 hover:bg-black/70 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/70"
                title="Instalar app"
                aria-label="Instalar app"
            >
                <span class="text-sm font-medium tracking-wide text-white/90">Instalar</span>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 3v9" />
                    <path d="M8.75 9.75 12 13l3.25-3.25" />
                    <path d="M7 14.5h10" />
                    <path d="M6.5 14.5v3A2 2 0 0 0 8.5 19.5h7A2 2 0 0 0 17.5 17.5v-3" />
                </svg>
            </button>
        </div>
    </div>
@endif
