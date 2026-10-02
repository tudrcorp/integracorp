<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Integracorp\IntegracorpHubAccessibleModules;
use App\Support\Integracorp\IntegracorpHubGreeting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.integracorp-hub')] #[Title('IntegraCorp - Sistema Integral de Gestión para Empresas')] class extends Component
{
    /** @var list<array{id: string, name: string, objective: string, image_url: string, url: string, badge: string, tags: list<string>}> */
    public array $modules = [];

    public string $userFirstName = '';

    public string $salutation = '';

    public string $modulesLead = '';

    public string $modulesCountLabel = '';

    public string $userInitial = '';

    public function mount(): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            $this->redirect(route('home'), navigate: true);

            return;
        }

        $this->modules = IntegracorpHubAccessibleModules::forUser($user);
        $this->userFirstName = IntegracorpHubGreeting::firstName($user);
        $this->salutation = IntegracorpHubGreeting::salutation();
        $moduleCount = count($this->modules);
        $this->modulesLead = IntegracorpHubGreeting::modulesLead($moduleCount);
        $this->modulesCountLabel = IntegracorpHubGreeting::modulesCountLabel($moduleCount);
        $this->userInitial = Str::upper(Str::substr($this->userFirstName, 0, 1));

        if ($this->modules === []) {
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();
            $this->redirect(route('home'), navigate: true);

            return;
        }

        if (count($this->modules) === 1) {
            $this->redirect($this->modules[0]['url'], navigate: false);
        }
    }

    public function logout(): void
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        $this->redirect(route('home'), navigate: true);
    }
}; ?>

<div class="ic-hub-page ic-hub-page--modules ic-hall">
    <div class="ic-hall-shell">
        <header class="ic-hall-topbar">
            <div class="ic-hall-topbar__brand">
                @include('integracorp.partials.hub-brand-logo', ['placement' => 'header-bar', 'darkLogo' => 'gold'])
            </div>

            <div
                class="ic-hall-topbar__clock"
                x-data="icHubHallClock()"
                x-init="init()"
                wire:ignore
            >
                <span x-text="dateLabel"></span>
                <span class="ic-hall-topbar__clock-dot" aria-hidden="true"></span>
                <span class="ic-hall-topbar__clock-time" x-text="timeLabel"></span>
            </div>

            <div class="ic-hall-topbar__actions">
                <div class="ic-hall-topbar__user">
                    <div class="ic-hall-topbar__avatar" aria-hidden="true">{{ $userInitial }}</div>
                    <div class="ic-hall-topbar__user-copy">
                        <span class="ic-hall-topbar__user-name">{{ $userFirstName }}</span>
                        <span class="ic-hall-topbar__user-meta">{{ $modulesCountLabel }}</span>
                    </div>
                </div>

                @include('integracorp.partials.hub-theme-toggle')

                <button type="button" class="ic-hall-btn-logout" wire:click="logout" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="logout">Cerrar sesión</span>
                    <span wire:loading wire:target="logout">Saliendo…</span>
                </button>
            </div>
        </header>

        <section class="ic-hall-hero">
            <div class="ic-hall-hero__copy">
                <h1 class="ic-hall-hero__greeting">
                    {{ $salutation }},
                    <em class="ic-hall-hero__name">{{ $userFirstName }}.</em>
                </h1>
                <p class="ic-hall-hero__lead">{{ $modulesLead }}</p>
            </div>
        </section>

        @if ($modules === [])
            <div class="ic-empty">
                No hay módulos disponibles para tu usuario en este momento.
            </div>
        @else
            <main class="ic-hall-main">
                <div
                    class="ic-hall-stage"
                    style="--ic-module-count: {{ count($modules) }}"
                    x-data="icHubModulesCarousel()"
                    wire:ignore.self
                >
                    <div
                        class="ic-modules-grid ic-hall-accordion"
                        data-count="{{ count($modules) }}"
                    >
                        @foreach ($modules as $module)
                            <a
                                href="{{ $module['url'] }}"
                                wire:key="hub-module-{{ $module['id'] }}"
                                class="ic-module-card ic-module-card--enter ic-module-card--hall"
                                style="--ic-module-enter-delay: {{ $loop->index * 85 }}ms"
                                data-module-index="{{ $loop->index }}"
                                x-on:mouseenter="setActive({{ $loop->index }})"
                                x-on:focus="setActive({{ $loop->index }})"
                                x-on:animationend.once="$el.classList.remove('ic-module-card--enter')"
                                x-on:pointerdown="$el.classList.add('ic-module-card--pressed')"
                                x-on:pointerup="$el.classList.remove('ic-module-card--pressed')"
                                x-on:pointerleave="$el.classList.remove('ic-module-card--pressed')"
                                x-on:pointercancel="$el.classList.remove('ic-module-card--pressed')"
                            >
                                <div class="ic-module-card__media" aria-hidden="true">
                                    <img
                                        src="{{ $module['image_url'] }}"
                                        alt=""
                                        class="ic-module-card__photo"
                                        loading="lazy"
                                        decoding="async"
                                    />

                                    <div class="ic-module-card__veil"></div>
                                </div>

                                <div class="ic-module-card__title-vertical" aria-hidden="true">
                                    {{ $module['name'] }}
                                </div>

                                <div class="ic-module-card__panel">
                                    <div class="ic-module-card__panel-content">
                                        <span class="ic-module-card__badge">{{ $module['badge'] }}</span>
                                        <h2 class="ic-module-card__name">{{ $module['name'] }}</h2>
                                        <p class="ic-module-card__objective">{{ $module['objective'] }}</p>
                                        <div class="ic-module-card__footer-row">
                                            @if ($module['tags'] !== [])
                                                <ul class="ic-module-card__tags">
                                                    @foreach ($module['tags'] as $tag)
                                                        <li wire:key="hub-module-tag-{{ $module['id'] }}-{{ $tag }}" class="ic-module-card__tag">
                                                            {{ $tag }}
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                            <span class="ic-module-card__enter">
                                                Entrar
                                                <span class="ic-module-card__enter-line" aria-hidden="true"></span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </main>
        @endif
    </div>
</div>
