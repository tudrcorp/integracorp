@php
    $serviceMenuOptions = $this->serviceMenuOptions();
    $integracorpLoginPanels = $this->integracorpLoginPanels();
@endphp

<div
    x-data="{
        open: false,
        view: 'main',
        panelStyle: '',
        closeIfOutside(event) {
            if (! this.open || window.matchMedia('(max-width: 639px)').matches) {
                return;
            }

            const target = event.target;

            if (
                this.$refs.trigger?.contains(target)
                || this.$refs.panelDesktop?.contains(target)
            ) {
                return;
            }

            this.closeMenu();
        },
        updatePanelPosition() {
            if (window.matchMedia('(max-width: 639px)').matches) {
                this.panelStyle = '';
                return;
            }

            const trigger = this.$refs.trigger;

            if (! trigger) {
                return;
            }

            const rect = trigger.getBoundingClientRect();
            const panelWidth = Math.min(360, window.innerWidth - 32);
            const left = Math.min(
                Math.max(16, rect.right - panelWidth),
                window.innerWidth - panelWidth - 16,
            );
            const spaceAbove = rect.top - 16;
            const spaceBelow = window.innerHeight - rect.bottom - 16;
            const openUp = spaceBelow < 420 && spaceAbove > spaceBelow;

            if (openUp) {
                this.panelStyle = `position: fixed; left: ${left}px; bottom: ${window.innerHeight - rect.top + 8}px; width: ${panelWidth}px; z-index: 220;`;
            } else {
                this.panelStyle = `position: fixed; left: ${left}px; top: ${rect.bottom + 8}px; width: ${panelWidth}px; z-index: 220;`;
            }
        },
        openMenu() {
            this.view = 'main';
            this.open = true;
            this.$nextTick(() => {
                this.updatePanelPosition();
                window.dispatchEvent(new CustomEvent('guia-chat-menu-opened'));
            });
        },
        closeMenu() {
            this.open = false;
            this.view = 'main';
        },
        toggleMenu() {
            if (this.open) {
                this.closeMenu();
                return;
            }

            this.openMenu();
        },
        showLoginPanels() {
            this.view = 'login';
            this.$nextTick(() => this.updatePanelPosition());
        },
        selectOption(key) {
            if (key === 'integracorp_login') {
                this.showLoginPanels();
                return;
            }

            this.closeMenu();
            guiaChatSelectServiceOption(key);
        },
    }"
    x-on:click.window="closeIfOutside($event)"
    x-on:keydown.escape.window="closeMenu()"
    x-on:resize.window="if (open) updatePanelPosition()"
    x-on:chat-composer-focus.window="closeMenu()"
    class="relative shrink-0"
>
    <button
        type="button"
        x-ref="trigger"
        x-on:click="toggleMenu()"
        @disabled($handoffRequested)
        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-white/70 transition hover:bg-white/15 hover:text-white disabled:opacity-50"
        aria-haspopup="dialog"
        x-bind:aria-expanded="open"
        aria-label="Menú de servicio GUIA-CHAT"
        title="Menú de servicio"
    >
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/>
        </svg>
    </button>

    <template x-teleport="body">
        <div x-show="open" x-cloak class="guia-chat-menu-overlay fixed inset-0 z-[210]" data-guia-chat-overlay role="presentation">
            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="absolute inset-0 bg-[#041126]/70 backdrop-blur-[2px] sm:bg-black/45"
                x-on:click="closeMenu()"
            ></div>

            {{-- Móvil: bottom sheet --}}
            <div
                x-ref="panel"
                x-show="open"
                x-transition:enter="transition ease-out duration-250"
                x-transition:enter-start="opacity-0 translate-y-full"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 translate-y-full"
                class="guia-chat-menu-sheet absolute inset-x-0 bottom-0 max-h-[min(88dvh,640px)] overflow-hidden rounded-t-[1.35rem] border border-white/15 bg-[#071a3d]/95 shadow-[0_-24px_80px_-20px_rgba(0,0,0,0.75)] backdrop-blur-2xl sm:hidden"
                role="dialog"
                aria-modal="true"
                aria-labelledby="guia-chat-service-menu-title-mobile"
            >
                @include('pwa.partials.guia-chat-service-menu-panel', [
                    'panelIdPrefix' => 'mobile',
                    'serviceMenuOptions' => $serviceMenuOptions,
                    'integracorpLoginPanels' => $integracorpLoginPanels,
                ])
            </div>

            {{-- Escritorio: panel flotante --}}
            <div
                x-show="open"
                x-bind:style="panelStyle"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 translate-y-1 scale-[0.98]"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 translate-y-1 scale-[0.98]"
                class="hidden max-h-[min(80dvh,560px)] overflow-hidden rounded-2xl border border-white/20 bg-[#071a3d]/95 shadow-[0_24px_80px_-20px_rgba(0,0,0,0.85)] backdrop-blur-2xl sm:block"
                x-ref="panelDesktop"
                role="dialog"
                aria-modal="true"
                aria-labelledby="guia-chat-service-menu-title-desktop"
            >
                @include('pwa.partials.guia-chat-service-menu-panel', [
                    'panelIdPrefix' => 'desktop',
                    'serviceMenuOptions' => $serviceMenuOptions,
                    'integracorpLoginPanels' => $integracorpLoginPanels,
                ])
            </div>
        </div>
    </template>
</div>
