<div
    x-data="{
        deferredPrompt: null,
        canNativeInstall: false,
        showInstallPanel: false,
        installPlatform: null,
        deferredPromptSupported: false,
        registerServiceWorker() {
            if (!('serviceWorker' in navigator)) {
                return;
            }

            navigator.serviceWorker.register('/chat/publico/sw.js?v=4', { scope: '/chat/publico/' }).catch(() => {});
        },
        isInstalled() {
            const isStandalone = window.matchMedia?.('(display-mode: standalone)')?.matches ?? false;
            const isIosStandalone = window.navigator.standalone === true;
            return isStandalone || isIosStandalone;
        },
        isIos() {
            const ua = window.navigator.userAgent ?? '';
            const isAppleMobile = /iPad|iPhone|iPod/.test(ua);
            const isIpadOs = window.navigator.platform === 'MacIntel' && (window.navigator.maxTouchPoints ?? 0) > 1;
            return isAppleMobile || isIpadOs;
        },
        isAndroid() {
            return /Android/i.test(window.navigator.userAgent ?? '');
        },
        detectInstallPlatform() {
            if (this.isIos()) {
                return 'ios';
            }

            if (this.isAndroid()) {
                return 'android';
            }

            return 'desktop';
        },
        canShowInstallUi() {
            return ! this.isInstalled();
        },
        init() {
            this.registerServiceWorker();

            if (! this.canShowInstallUi()) {
                return;
            }

            this.installPlatform = this.detectInstallPlatform();
            this.deferredPromptSupported = 'onbeforeinstallprompt' in window;

            if (this.deferredPromptSupported) {
                window.addEventListener('beforeinstallprompt', (event) => {
                    event.preventDefault();
                    this.deferredPrompt = event;
                    this.canNativeInstall = true;
                    this.showInstallPanel = false;
                });

                window.addEventListener('appinstalled', () => {
                    this.deferredPrompt = null;
                    this.canNativeInstall = false;
                    this.showInstallPanel = false;
                });
            }

            if (this.isIos()) {
                this.showInstallPanel = true;
            }
        },
        openInstallPanel() {
            if (this.canNativeInstall) {
                this.openNativeInstall();
                return;
            }

            this.showInstallPanel = true;
        },
        closeInstallPanel() {
            this.showInstallPanel = false;
        },
        async openNativeInstall() {
            if (! this.deferredPrompt) {
                this.showInstallPanel = true;
                return;
            }

            await this.deferredPrompt.prompt();
            await this.deferredPrompt.userChoice;
            this.deferredPrompt = null;
            this.canNativeInstall = false;
            this.showInstallPanel = false;
        },
    }"
    x-init="init()"
>
    <div
        x-cloak
        x-show="canShowInstallUi() && (canNativeInstall || installPlatform === 'ios' || installPlatform === 'android')"
        class="fixed bottom-24 right-4 z-[70] sm:bottom-28 sm:right-6"
    >
        <button
            type="button"
            x-on:click="openInstallPanel()"
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

    <div
        x-cloak
        x-show="showInstallPanel"
        x-transition.opacity
        class="fixed inset-0 z-[80] flex items-end justify-center bg-black/50 p-4 sm:items-center"
        x-on:click.self="closeInstallPanel()"
        role="dialog"
        aria-modal="true"
        aria-labelledby="guia-chat-install-title"
    >
        <div class="w-full max-w-md rounded-2xl border border-white/15 bg-[#0b1f4a]/95 p-5 text-white shadow-2xl backdrop-blur-md">
            <div class="mb-4 flex items-start justify-between gap-3">
                <div>
                    <h2 id="guia-chat-install-title" class="text-lg font-semibold">Instalar GUIA-CHAT</h2>
                    <p class="mt-1 text-sm text-white/75">Accede más rápido como app en tu teléfono.</p>
                </div>
                <button
                    type="button"
                    x-on:click="closeInstallPanel()"
                    class="rounded-full p-1 text-white/70 transition hover:bg-white/10 hover:text-white"
                    aria-label="Cerrar"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>

            <template x-if="canNativeInstall">
                <div class="space-y-3">
                    <p class="text-sm text-white/80">Tu navegador permite instalar la app directamente.</p>
                    <button
                        type="button"
                        x-on:click="openNativeInstall()"
                        class="inline-flex w-full items-center justify-center rounded-xl bg-[#14b8a6] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#0d9488]"
                    >
                        Instalar ahora
                    </button>
                </div>
            </template>

            <template x-if="!canNativeInstall && installPlatform === 'ios'">
                <ol class="list-decimal space-y-2 pl-5 text-sm text-white/85">
                    <li>Toca el botón <strong>Compartir</strong> en Safari (cuadrado con flecha hacia arriba).</li>
                    <li>Elige <strong>Agregar a pantalla de inicio</strong>.</li>
                    <li>Confirma con <strong>Agregar</strong>.</li>
                </ol>
            </template>

            <template x-if="!canNativeInstall && installPlatform === 'android'">
                <ol class="list-decimal space-y-2 pl-5 text-sm text-white/85">
                    <li>Abre el menú del navegador (tres puntos).</li>
                    <li>Selecciona <strong>Instalar app</strong> o <strong>Agregar a pantalla de inicio</strong>.</li>
                    <li>Confirma la instalación.</li>
                </ol>
            </template>

            <template x-if="!canNativeInstall && installPlatform === 'desktop'">
                <p class="text-sm text-white/85">
                    En Chrome o Edge, usa el icono de instalación en la barra de direcciones o el menú del navegador.
                </p>
            </template>
        </div>
    </div>
</div>
