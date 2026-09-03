<!DOCTYPE html>
<html lang="es" class="storefront-app">
<head>
    @include('partials.storefront-head', ['title' => $title ?? 'Planes'])
</head>
<body
    class="storefront-app"
    x-data="{
        menuOpen: false,
        dragY: 0,
        dragging: false,
        startY: 0,
        successOpen: false,
        successCode: '',
        successUrl: '',
        successAsAgent: false,
        headerGlass: false,
        closeMenu() {
            this.menuOpen = false;
            this.dragY = 0;
            this.dragging = false;
        },
        onSheetStart(event) {
            this.dragging = true;
            this.startY = event.touches[0].clientY;
        },
        onSheetMove(event) {
            if (! this.dragging) {
                return;
            }

            this.dragY = Math.max(0, event.touches[0].clientY - this.startY);
        },
        onSheetEnd() {
            if (this.dragY > 88) {
                this.closeMenu();
                return;
            }

            this.dragY = 0;
            this.dragging = false;
        },
        syncHeaderGlass() {
            const y = window.scrollY || document.documentElement.scrollTop || 0;
            this.headerGlass = y > 12;
        },
        successCopy() {
            if (! this.successCode) {
                return 'Tu cotización se creó con éxito.';
            }

            if (this.successAsAgent) {
                return 'Quedó registrada a tu nombre. El código es ' + this.successCode + '.';
            }

            return 'Tu cotización se creó con éxito. El código es ' + this.successCode + '.';
        },
        openSuccess(event) {
            const detail = event.detail ?? {};
            this.closeMenu();
            this.successCode = String(detail.code ?? '');
            this.successUrl = String(detail.url ?? '');
            this.successAsAgent = Boolean(detail.asAgent);
            this.successOpen = true;
            this.$nextTick(() => this.$refs.successDone?.focus());
        },
        dismissSuccess() {
            const url = this.successUrl;
            this.successOpen = false;
            if (! url) {
                return;
            }

            this.$nextTick(() => {
                if (window.Livewire?.navigate) {
                    window.Livewire.navigate(url);
                    return;
                }

                window.location.assign(url);
            });
        },
    }"
    x-init="syncHeaderGlass()"
    x-bind:class="{ 'is-menu-open': menuOpen, 'is-success-open': successOpen, 'is-header-glass': headerGlass }"
    x-on:keydown.escape.window="successOpen ? dismissSuccess() : closeMenu()"
    x-on:scroll.window.passive="syncHeaderGlass()"
    x-on:storefront-close-menu.window="closeMenu()"
    x-on:storefront-quote-success.window="openSuccess($event)"
    x-on:livewire:navigated.window="closeMenu(); successOpen = false; document.body.classList.remove('is-quote-sheet-open'); $nextTick(() => syncHeaderGlass())"
>
    <div class="storefront-atmosphere" aria-hidden="true">
        <span class="storefront-atmosphere__orb storefront-atmosphere__orb--a"></span>
        <span class="storefront-atmosphere__orb storefront-atmosphere__orb--b"></span>
        <span class="storefront-atmosphere__noise"></span>
    </div>

    <div class="storefront-shell">
        <header class="storefront-header">
            @php
                $storefrontBack = \App\Support\Storefront\StorefrontNav::back();
                $storefrontSubtitle = \App\Support\Storefront\StorefrontNav::subtitle();
                $storefrontIsAgent = \App\Support\Storefront\StorefrontAuth::currentIsAgent();
            @endphp
            <div class="storefront-header__lead">
                <a href="{{ route('storefront.home') }}" wire:navigate class="storefront-brand">
                    <img src="{{ asset('image/logoNewPdf.png') }}" alt="Tu Dr En Casa" width="168" height="43">
                    @if ($storefrontIsAgent || $storefrontSubtitle !== '')
                        <span class="storefront-brand__copy">
                            @if ($storefrontIsAgent)
                                <span class="storefront-brand__kicker">Modo agente</span>
                            @endif
                            @if ($storefrontSubtitle !== '')
                                <span class="storefront-brand__name">{{ $storefrontSubtitle }}</span>
                            @endif
                        </span>
                    @endif
                </a>
                @if ($storefrontBack !== null)
                    <a
                        href="{{ route($storefrontBack['route']) }}"
                        wire:navigate
                        class="storefront-back"
                    >
                        <span aria-hidden="true">‹</span>
                        {{ $storefrontBack['label'] }}
                    </a>
                @endif
            </div>

            <button
                type="button"
                class="storefront-menu-btn"
                x-bind:class="menuOpen && 'is-open'"
                x-on:click="menuOpen = ! menuOpen"
                x-bind:aria-expanded="menuOpen.toString()"
                aria-controls="storefront-sheet"
                aria-label="Abrir menú"
            >
                <span class="storefront-burger" aria-hidden="true">
                    <span></span>
                    <span></span>
                    <span></span>
                </span>
            </button>
        </header>

        <div
            class="storefront-overlay"
            x-cloak
            x-bind:class="menuOpen && 'is-open'"
            x-bind:aria-hidden="(! menuOpen).toString()"
            x-bind:inert="! menuOpen"
            x-on:click.self="closeMenu()"
        >
            <nav
                id="storefront-sheet"
                class="storefront-sheet"
                x-bind:style="(dragging || dragY) ? { transform: 'translateY(' + dragY + 'px)', transition: dragging ? 'none' : null } : null"
                x-on:click.stop
                aria-label="Menú principal"
                role="dialog"
                aria-modal="true"
            >
                <div
                    class="storefront-sheet__grab"
                    x-on:touchstart.passive="onSheetStart($event)"
                    x-on:touchmove.passive="onSheetMove($event)"
                    x-on:touchend="onSheetEnd()"
                >
                    <span class="storefront-sheet__handle" aria-hidden="true"></span>
                    <p class="storefront-sheet__title">Menú</p>
                </div>

                <div class="storefront-sheet__body">
                    @foreach (\App\Support\Storefront\StorefrontNav::items() as $item)
                        @if ($item['soon'])
                            <div class="storefront-sheet__row is-soon">
                                <span class="storefront-sheet__icon">
                                    @include('storefront.partials.nav-icon', ['name' => $item['icon']])
                                </span>
                                <span class="storefront-sheet__copy">
                                    <span class="storefront-sheet__label">{{ $item['label'] }}</span>
                                    <span class="storefront-sheet__hint">{{ $item['hint'] }}</span>
                                </span>
                                <span class="storefront-sheet__soon">Pronto</span>
                            </div>
                        @elseif ($item['method'] === 'post' && $item['route'])
                            <form method="POST" action="{{ route($item['route']) }}">
                                @csrf
                                <button type="submit" class="storefront-sheet__row">
                                    <span class="storefront-sheet__icon">
                                        @include('storefront.partials.nav-icon', ['name' => $item['icon']])
                                    </span>
                                    <span class="storefront-sheet__copy">
                                        <span class="storefront-sheet__label">{{ $item['label'] }}</span>
                                        <span class="storefront-sheet__hint">{{ $item['hint'] }}</span>
                                    </span>
                                </button>
                            </form>
                        @elseif (($item['url'] ?? null) && ($item['external'] ?? false))
                            <a
                                href="{{ $item['url'] }}"
                                class="storefront-sheet__row"
                                target="_blank"
                                rel="noopener noreferrer"
                                x-on:click="closeMenu()"
                            >
                                <span @class(['storefront-sheet__icon', 'is-whatsapp' => ($item['icon'] ?? '') === 'whatsapp'])>
                                    @include('storefront.partials.nav-icon', ['name' => $item['icon']])
                                </span>
                                <span class="storefront-sheet__copy">
                                    <span class="storefront-sheet__label">{{ $item['label'] }}</span>
                                    <span class="storefront-sheet__hint">{{ $item['hint'] }}</span>
                                </span>
                            </a>
                        @elseif ($item['route'])
                            <a
                                href="{{ route($item['route']) }}"
                                wire:navigate
                                class="storefront-sheet__row"
                                x-on:click="closeMenu()"
                            >
                                <span class="storefront-sheet__icon">
                                    @include('storefront.partials.nav-icon', ['name' => $item['icon']])
                                </span>
                                <span class="storefront-sheet__copy">
                                    <span class="storefront-sheet__label">{{ $item['label'] }}</span>
                                    <span class="storefront-sheet__hint">{{ $item['hint'] }}</span>
                                </span>
                            </a>
                        @endif
                    @endforeach

                    <button
                        type="button"
                        class="storefront-sheet__row"
                        x-on:click="closeMenu(); $dispatch('storefront-open-install')"
                    >
                        <span class="storefront-sheet__icon">
                            @include('storefront.partials.nav-icon', ['name' => 'install'])
                        </span>
                        <span class="storefront-sheet__copy">
                            <span class="storefront-sheet__label">Instalar app</span>
                            <span class="storefront-sheet__hint">En tu pantalla de inicio, como una app nativa</span>
                        </span>
                    </button>
                </div>

                <div class="storefront-sheet__footer">
                    <button type="button" class="storefront-sheet__close" x-on:click="closeMenu()">
                        Cerrar
                    </button>
                    <span class="storefront-sheet__home" aria-hidden="true"></span>
                </div>
            </nav>
        </div>

        @include('storefront.partials.install')

        <main class="storefront-stage">
            <div class="storefront-page">
                {{ $slot }}
            </div>
        </main>
    </div>

    @include('storefront.partials.quote-success')

    @fluxScripts
    @persist('toast')
        <flux:toast />
    @endpersist
</body>
</html>
