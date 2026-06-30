@php
    $actionMenuOptions = $this->actionMenuOptions();
@endphp

<div
    x-data="{
        open: false,
        panelStyle: '',
        activeTrigger: null,
        closeIfOutside(event) {
            if (! this.open || window.matchMedia('(max-width: 639px)').matches) {
                return;
            }

            const target = event.target;

            if (
                this.$refs.triggerDesktop?.contains(target)
                || this.$refs.triggerMobile?.contains(target)
                || this.$refs.panelDesktop?.contains(target)
            ) {
                return;
            }

            this.closeMenu();
        },
        resolveTrigger() {
            const isDesktop = window.matchMedia('(min-width: 640px)').matches;

            return this.activeTrigger
                ?? (isDesktop ? this.$refs.triggerDesktop : this.$refs.triggerMobile);
        },
        updatePanelPosition() {
            if (window.matchMedia('(max-width: 639px)').matches) {
                this.panelStyle = '';
                return;
            }

            const trigger = this.resolveTrigger();

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
        openMenu(trigger = null) {
            this.activeTrigger = trigger;
            this.open = true;
            this.$nextTick(() => this.updatePanelPosition());
        },
        closeMenu() {
            this.open = false;
            this.activeTrigger = null;
        },
        toggleMenu(trigger = null) {
            if (this.open) {
                this.closeMenu();
                return;
            }

            this.openMenu(trigger);
        },
        selectAction(key) {
            this.closeMenu();
            $wire.selectAction(key);
        },
    }"
    x-on:click.window="closeIfOutside($event)"
    x-on:keydown.escape.window="closeMenu()"
    x-on:resize.window="if (open) updatePanelPosition()"
    x-on:scroll.window="if (open) updatePanelPosition()"
    x-on:chat-open-action-menu.window="openMenu(); $nextTick(() => updatePanelPosition())"
    x-on:chat-composer-focus.window="closeMenu()"
    class="relative shrink-0 overflow-visible"
>
    <button
        type="button"
        x-ref="triggerDesktop"
        x-on:click="toggleMenu($refs.triggerDesktop)"
        @disabled($handoffRequested)
        class="{{ $selectedAction !== ''
            ? 'hidden max-w-[10rem] items-center gap-1.5 py-1.5 text-xs font-semibold text-white transition hover:text-white/90 sm:inline-flex sm:max-w-[11rem] sm:py-2 sm:text-sm'
            : 'hidden max-w-[10rem] items-center gap-1.5 py-1.5 text-xs font-semibold text-white/80 transition hover:text-white sm:inline-flex sm:max-w-[11rem] sm:py-2 sm:text-sm' }} disabled:opacity-50"
        aria-haspopup="dialog"
        x-bind:aria-expanded="open"
        aria-label="¿Qué quieres hacer?"
    >
        <span class="truncate text-[13px] font-semibold sm:text-[15px]">
            @if ($selectedAction !== '' && isset($actionOptions[$selectedAction]))
                {{ $actionOptions[$selectedAction]['short'] }}
            @else
                Quiero!
            @endif
        </span>
        <svg
            class="h-3.5 w-3.5 shrink-0 text-white/70 transition-transform duration-200 sm:h-4 sm:w-4"
            x-bind:class="open ? 'rotate-180' : ''"
            viewBox="0 0 20 20"
            fill="currentColor"
            aria-hidden="true"
        >
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
        </svg>
    </button>

    <button
        type="button"
        x-ref="triggerMobile"
        x-on:click="toggleMenu($refs.triggerMobile)"
        @disabled($handoffRequested)
        class="{{ $selectedAction !== ''
            ? 'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-white transition hover:bg-white/10 sm:hidden disabled:opacity-50'
            : 'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-white/70 transition hover:bg-white/15 hover:text-white sm:hidden disabled:opacity-50' }}"
        aria-haspopup="dialog"
        x-bind:aria-expanded="open"
        aria-label="¿Qué quieres hacer?"
        title="¿Qué quieres hacer?"
    >
        <span class="text-[17px] font-semibold leading-none tracking-tight">?</span>
    </button>

    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[210]" role="presentation">
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

            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-250"
                x-transition:enter-start="opacity-0 translate-y-full"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 translate-y-full"
                class="absolute inset-x-0 bottom-0 max-h-[min(88dvh,640px)] overflow-hidden rounded-t-[1.35rem] border border-white/15 bg-[#071a3d]/95 shadow-[0_-24px_80px_-20px_rgba(0,0,0,0.75)] backdrop-blur-2xl sm:hidden"
                role="dialog"
                aria-modal="true"
                aria-labelledby="guia-chat-action-menu-title-mobile"
            >
                @include('pwa.partials.guia-chat-action-menu-panel', [
                    'panelIdPrefix' => 'mobile',
                    'actionMenuOptions' => $actionMenuOptions,
                    'selectedAction' => $selectedAction,
                ])
            </div>

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
                aria-labelledby="guia-chat-action-menu-title-desktop"
            >
                @include('pwa.partials.guia-chat-action-menu-panel', [
                    'panelIdPrefix' => 'desktop',
                    'actionMenuOptions' => $actionMenuOptions,
                    'selectedAction' => $selectedAction,
                ])
            </div>
        </div>
    </template>
</div>
