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
        registerServiceWorker() {
            if (!('serviceWorker' in navigator)) {
                return;
            }

            navigator.serviceWorker.register('/sw-guia-chat.js?v=3', { scope: '/chat/publico' }).catch(() => {});
        },
        init() {
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
    <div x-cloak x-show="canInstall" class="fixed bottom-24 right-4 z-[70] sm:bottom-28 sm:right-6">
        <button
            type="button"
            x-on:click="openInstall()"
            class="group inline-flex h-11 items-center justify-center gap-2 rounded-full border border-white/25 bg-black/55 px-4 text-white shadow-lg backdrop-blur-md transition hover:scale-105 hover:border-white/40 hover:bg-black/70 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/70"
            title="Instalar GUIA-CHAT"
            aria-label="Instalar GUIA-CHAT en tu dispositivo"
        >
            <img
                src="{{ asset('pwa/guia-chat/icon-192.png') }}"
                alt=""
                class="h-6 w-6 rounded-md"
                aria-hidden="true"
            >
            <span class="text-sm font-medium tracking-wide text-white/90">Instalar app</span>
        </button>
    </div>
</div>
