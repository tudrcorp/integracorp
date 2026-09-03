<div
    x-data="{
        deferredPrompt: null,
        canNativeInstall: false,
        showInstallPanel: false,
        installPlatform: null,
        registerServiceWorker() {
            if (!('serviceWorker' in navigator)) {
                return;
            }

            navigator.serviceWorker.register('/app/sw.js?v=3', { scope: '/app/' }).catch(() => {});
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
        init() {
            this.registerServiceWorker();
            this.installPlatform = this.isIos() ? 'ios' : (this.isAndroid() ? 'android' : 'desktop');

            if (this.isInstalled()) {
                return;
            }

            if ('onbeforeinstallprompt' in window) {
                window.addEventListener('beforeinstallprompt', (event) => {
                    event.preventDefault();
                    this.deferredPrompt = event;
                    this.canNativeInstall = true;
                });

                window.addEventListener('appinstalled', () => {
                    this.deferredPrompt = null;
                    this.canNativeInstall = false;
                    this.showInstallPanel = false;
                });
            }
        },
        openInstallPanel() {
            if (this.canNativeInstall && this.deferredPrompt) {
                this.openNativeInstall();
                return;
            }

            this.showInstallPanel = true;
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
    x-on:storefront-open-install.window="openInstallPanel()"
>
    <div
        x-cloak
        x-bind:class="showInstallPanel && 'is-open'"
        x-bind:aria-hidden="(! showInstallPanel).toString()"
        x-bind:inert="! showInstallPanel"
        class="storefront-overlay"
        style="z-index: 80;"
        x-on:click.self="showInstallPanel = false"
    >
        <div
            class="storefront-sheet"
            x-on:click.stop
            role="dialog"
            aria-modal="true"
            aria-label="Instalar app"
        >
            <div class="storefront-sheet__grab">
                <span class="storefront-sheet__handle" aria-hidden="true"></span>
                <p class="storefront-sheet__title">Agregar a inicio</p>
            </div>
            <div class="storefront-sheet__body storefront-sheet__body--plain">
                <p class="sf-lead" style="padding: 0.35rem 0.15rem 0.85rem;">
                    <template x-if="installPlatform === 'ios'">
                        <span>En Safari, toca <strong>Compartir</strong> y luego <strong>Agregar a pantalla de inicio</strong>.</span>
                    </template>
                    <template x-if="installPlatform !== 'ios'">
                        <span>Usa el menú del navegador y elige <strong>Instalar app</strong> o <strong>Agregar a pantalla de inicio</strong>.</span>
                    </template>
                </p>
            </div>
            <div class="storefront-sheet__footer">
                <button type="button" class="storefront-sheet__close is-primary" x-on:click="showInstallPanel = false">
                    Entendido
                </button>
                <span class="storefront-sheet__home" aria-hidden="true"></span>
            </div>
        </div>
    </div>
</div>
