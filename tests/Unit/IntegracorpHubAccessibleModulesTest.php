<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Integracorp\IntegracorpHubAccessibleModules;
use App\Support\Integracorp\IntegracorpHubModuleRegistry;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

it('expone definiciones de módulos con nombre, objetivo e imagen', function (): void {
    $definitions = IntegracorpHubModuleRegistry::definitions();

    expect($definitions)->not->toBeEmpty();

    foreach ($definitions as $definition) {
        expect($definition)->toHaveKeys(['id', 'name', 'objective', 'image', 'route', 'sort', 'badge', 'tags'])
            ->and($definition['name'])->not->toBeEmpty()
            ->and($definition['objective'])->not->toBeEmpty();
    }
});

it('incluye negocios y operaciones para usuario interno con ambos departamentos', function (): void {
    $user = User::factory()->create([
        'email' => 'hub-multi-'.uniqid('', true).'@tudrencasa.com',
        'departament' => ['NEGOCIOS', 'OPERACIONES'],
        'status' => 'ACTIVO',
    ]);

    $ids = collect(IntegracorpHubAccessibleModules::forUser($user))->pluck('id')->all();

    expect($ids)->toContain('business')
        ->and($ids)->toContain('operations')
        ->and($ids)->not->toContain('marketing');
});

it('no incluye módulos internos para agente comercial', function (): void {
    $user = User::factory()->create([
        'email' => 'hub-agent-'.uniqid('', true).'@example.com',
        'is_agent' => true,
        'departament' => [],
        'status' => 'ACTIVO',
    ]);

    $modules = IntegracorpHubAccessibleModules::forUser($user);
    $ids = collect($modules)->pluck('id')->all();
    $agents = collect($modules)->firstWhere('id', 'agents');

    expect($ids)->toContain('agents')
        ->and($ids)->not->toContain('business')
        ->and($ids)->not->toContain('master')
        ->and($ids)->not->toContain('general')
        ->and($agents['url'])->toBe(route('filament.agents.pages.dashboard'));
});

it('resuelve módulos de agente aunque departament sea null', function (): void {
    $user = User::factory()->create([
        'email' => 'hub-agent-null-dept-'.uniqid('', true).'@example.com',
        'is_agent' => true,
        'departament' => null,
        'status' => 'ACTIVO',
    ]);

    $modules = IntegracorpHubAccessibleModules::forUser($user);

    expect($modules)->not->toBeEmpty()
        ->and(collect($modules)->pluck('id')->all())->toBe(['agents']);
});

it('agencia master ve la tarjeta unificada Agentes apuntando al panel master', function (): void {
    $user = User::factory()->create([
        'email' => 'hub-master-'.uniqid('', true).'@example.com',
        'is_agency' => true,
        'agency_type' => 'MASTER',
        'departament' => [],
        'status' => 'ACTIVO',
    ]);

    $modules = IntegracorpHubAccessibleModules::forUser($user);
    $ids = collect($modules)->pluck('id')->all();
    $agents = collect($modules)->firstWhere('id', 'agents');

    expect($ids)->toBe(['agents'])
        ->and($agents['name'])->toBe('Agentes')
        ->and($agents['url'])->toBe(route('filament.master.pages.dashboard'));
});

it('agencia general ve la tarjeta unificada Agentes apuntando al panel general', function (): void {
    $user = User::factory()->create([
        'email' => 'hub-general-'.uniqid('', true).'@example.com',
        'is_agency' => true,
        'agency_type' => 'GENERAL',
        'departament' => [],
        'status' => 'ACTIVO',
    ]);

    $agents = collect(IntegracorpHubAccessibleModules::forUser($user))->firstWhere('id', 'agents');

    expect($agents)->not->toBeNull()
        ->and($agents['url'])->toBe(route('filament.general.pages.dashboard'));
});

it('prioriza master sobre general y agentes en la tarjeta unificada', function (): void {
    $user = User::factory()->create([
        'email' => 'hub-priority-'.uniqid('', true).'@tudrencasa.com',
        'is_agent' => true,
        'is_agency' => true,
        'agency_type' => 'MASTER',
        'departament' => ['NEGOCIOS'],
        'status' => 'ACTIVO',
    ]);

    $modules = IntegracorpHubAccessibleModules::forUser($user);
    $agentsCards = collect($modules)->where('id', 'agents');

    expect($agentsCards)->toHaveCount(1)
        ->and($agentsCards->first()['url'])->toBe(route('filament.master.pages.dashboard'))
        ->and(collect($modules)->pluck('id')->all())->toContain('business');
});

it('el catálogo del hub no expone tarjetas separadas de master ni general', function (): void {
    $ids = collect(IntegracorpHubModuleRegistry::definitions())->pluck('id')->all();

    expect($ids)->toContain('agents')
        ->and($ids)->not->toContain('master')
        ->and($ids)->not->toContain('general');
});

it('la recuperación de contraseña replica el shell corporativo del login', function (): void {
    $forgot = file_get_contents(base_path('resources/views/livewire/volt/integracorp/hub-forgot-password.blade.php'));

    $reset = file_get_contents(base_path('resources/views/livewire/volt/integracorp/hub-reset-password.blade.php'));

    expect($forgot)
        ->toContain('x-integracorp.hub-auth-shell')
        ->toContain('ic-auth-form')
        ->toContain('¿Olvidaste tu')
        ->toContain('ic-auth-back')
        ->toContain('nombre@tudrencasa.com')
        ->not->toContain('ic-login-glass')
        ->not->toContain('integracorp.partials.hub-login-orbs');

    expect($reset)
        ->toContain('x-integracorp.hub-auth-shell')
        ->toContain('Restablecer')
        ->toContain('ic-auth-back');

    $web = file_get_contents(base_path('routes/web.php'));

    expect($web)
        ->toContain("->name('integracorp.hub.password.request')")
        ->toContain("->name('integracorp.hub.password.reset')");
});

it('la vista de login usa acceso corporativo con toggle de tema en el shell', function (): void {
    $login = file_get_contents(base_path('resources/views/livewire/volt/integracorp/hub-login.blade.php'));
    $shell = file_get_contents(base_path('resources/views/components/integracorp/hub-auth-shell.blade.php'));
    $layout = file_get_contents(base_path('resources/views/components/layouts/integracorp-hub.blade.php'));
    $authStyles = file_get_contents(base_path('resources/views/integracorp/partials/hub-auth-styles.blade.php'));

    expect($login)
        ->toContain('x-integracorp.hub-auth-shell')
        ->toContain('Bienvenido')
        ->toContain('Ingresa a tu cuenta de Integracorp.')
        ->toContain('ic-auth-form')
        ->toContain('ic-auth-field__label')
        ->toContain('nombre@tudrencasa.com')
        ->toContain('ic-auth-remember')
        ->toContain('hub-submit-button')
        ->toContain('integracorp.hub.password.request')
        ->toContain('hub-password-field')
        ->not->toContain('ic-login-glass');

    expect($shell)->toContain('integracorp.partials.hub-theme-toggle');

    expect(file_get_contents(base_path('resources/views/integracorp/partials/hub-submit-button.blade.php')))
        ->toContain('ic-auth-submit')
        ->toContain('ic-auth-submit__line');

    expect(file_get_contents(base_path('resources/views/integracorp/partials/hub-theme-toggle.blade.php')))
        ->toContain('ic-hub-theme-switch')
        ->toContain('role="switch"')
        ->toContain('icHubThemeToggle')
        ->toContain('is-jelly');

    expect(file_get_contents(base_path('resources/views/integracorp/partials/hub-theme-toggle-alpine.blade.php')))
        ->toContain('ic-hub-theme-switching')
        ->toContain('ic-hub-body--auth')
        ->toContain('1100')
        ->toContain('requestAnimationFrame');

    expect(file_get_contents(base_path('resources/views/integracorp/partials/hub-password-field.blade.php')))
        ->toContain('ic-auth-input__reveal');

    expect($layout)->toContain('integracorp.partials.hub-auth-styles')
        ->toContain('scroll-smooth')
        ->toContain('! $icHubAuthRoute');

    expect($authStyles)
        ->toContain('-webkit-autofill')
        ->toContain('#F0F4F8')
        ->toContain('#0B132B')
        ->toContain('#64748B')
        ->toContain('.ic-auth__panel')
        ->toContain('background-color: #F0F4F8')
        ->toContain('rgba(216, 184, 120, 0.22) 0%')
        ->toContain('ic-hub-theme-switching:has(body.ic-hub-body--auth)')
        ->toContain('transition: none !important')
        ->toContain('.ic-auth-submit')
        ->toContain('html:has(body.ic-hub-body--auth)')
        ->toContain('overflow: hidden')
        ->toContain('height: 100dvh')
        ->toContain('max-height: 100dvh')
        ->toContain('position: fixed')
        ->toContain('inset: 0')
        ->not->toContain('hub-bg-modules-light.jpg');

    expect($shell)->toContain('hub-auth-hero.png');
});

it('el layout del hub coloca el footer fuera del contenedor de vidrio', function (): void {
    $layout = file_get_contents(base_path('resources/views/components/layouts/integracorp-hub.blade.php'));

    expect($layout)
        ->toContain('ic-hub-footer')
        ->toContain('integracorp.partials.hub-footer-copy')
        ->toContain('integracorp.partials.hub-auth-styles')
        ->toContain('ic-hub-body--auth')
        ->toContain('image/i2.jpg')
        ->toContain('image/hub-bg-light.jpg')
        ->toContain('image/hub-bg-modules-light.jpg')
        ->toContain('ic-hub-bg__photo--auth')
        ->toContain('ic-hub-bg__photo--modules')
        ->toContain('ic-hub-bg__photo--modules-dark')
        ->toContain('ic-hub-bg__photo--modules-light')
        ->toContain('ic-hub-bg__vignette')
        ->toContain('integracorp.partials.hub-modules-sparkles')
        ->toContain('integracorp.partials.hub-modules-ribbons')
        ->toContain('integracorp.partials.hub-brand-logo')
        ->toContain("routeIs('integracorp.hub.modules')")
        ->toContain('integracorp.partials.hub-modules-carousel-alpine')
        ->toContain('integracorp.partials.hub-hall-clock-alpine')
        ->toContain('integracorp.partials.hub-theme-toggle-alpine')
        ->not->toContain('ic-hub-bg__photo--dark')
        ->not->toContain('ic-hub-bg__photo--light')
        ->not->toContain('ic-hub-bg__healthtech')
        ->not->toContain('hub-healthtech-bg');

    expect(file_get_contents(base_path('resources/views/integracorp/partials/hub-modules-sparkles.blade.php')))
        ->toContain('ic-hub-bg__glow')
        ->toContain('ic-hub-bg__spark--14');

    expect(file_exists(public_path('image/hub-bg-light.jpg')))->toBeTrue();
    expect(file_exists(public_path('image/hub-bg-modules-light.jpg')))->toBeTrue();
    expect(file_exists(public_path('image/i2.jpg')))->toBeTrue();

    expect(file_get_contents(base_path('resources/views/integracorp/partials/hub-styles.blade.php')))
        ->toContain('ic-hub-bg__photo--auth')
        ->toContain('ic-hub-bg__photo--modules')
        ->toContain('ic-hub-bg__photo--modules-dark')
        ->toContain('ic-hub-bg__photo--modules-light')
        ->toContain('brightness(0.42)')
        ->toContain('.ic-hub-bg__vignette')
        ->toContain('.ic-hub-bg__sparkles')
        ->toContain('.ic-hub-bg__glow')
        ->toContain('@keyframes ic-hub-sparkle-drift')
        ->toContain('@keyframes ic-hub-glow-pulse')
        ->toContain('@keyframes ic-hub-ribbon-drift')
        ->toContain('.ic-hub-bg__ribbons')
        ->toContain('blur(78px)')
        ->toContain('inset 0 0 150px 60px')
        ->toContain('body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__photo--modules-dark')
        ->toContain('html.ic-hub-theme-light body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__photo--modules-dark')
        ->toContain('html.ic-hub-theme-light body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__vignette');

    expect(file_get_contents(base_path('resources/views/integracorp/partials/hub-brand-logo.blade.php')))
        ->toContain('ic-hub-brand-mark')
        ->toContain('ic-hub-brand-mark--header-center')
        ->toContain('ic-hub-brand-mark--login-center')
        ->toContain('logoTDG.png')
        ->toContain('logoNewTDG.png');

    $login = file_get_contents(base_path('resources/views/livewire/volt/integracorp/hub-login.blade.php'));
    $authStyles = file_get_contents(base_path('resources/views/integracorp/partials/hub-auth-styles.blade.php'));

    expect($login)
        ->toContain('hub-auth-form-logo')
        ->toContain('x-integracorp.hub-auth-shell');

    expect($authStyles)
        ->toContain('.ic-auth')
        ->toContain('grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr)')
        ->toContain('--ic-hub-logo-gold')
        ->toContain('.ic-auth__hero-kicker')
        ->toContain('.ic-auth-input__reveal')
        ->toContain('.ic-auth-options .ic-auth-link')
        ->toContain('background: var(--ic-hub-logo-gold)');

    expect(file_get_contents(base_path('resources/views/components/integracorp/hub-auth-shell.blade.php')))
        ->toContain('Acceso corporativo')
        ->toContain('integracorp.partials.hub-theme-toggle');
});

it('el layout del hub declara favicon institucional y título IntegraCorp', function (): void {
    $layout = file_get_contents(base_path('resources/views/components/layouts/integracorp-hub.blade.php'));
    $expectedTitle = 'IntegraCorp - Sistema Integral de Gestión para Empresas';

    expect($layout)
        ->toContain("<title>{$expectedTitle}</title>")
        ->toContain('application-name" content="IntegraCorp"')
        ->toContain('apple-mobile-web-app-title" content="IntegraCorp"')
        ->toContain("asset('image/ico_Android_IOS.png')")
        ->toContain('rel="icon"')
        ->toContain('rel="apple-touch-icon"')
        ->not->toContain('partials.head');

    $login = file_get_contents(base_path('resources/views/livewire/volt/integracorp/hub-login.blade.php'));

    expect($login)->toContain("Title('{$expectedTitle}')");
});

it('la pantalla de módulos define hall, acordeón y layout responsive', function (): void {
    $modules = file_get_contents(base_path('resources/views/livewire/volt/integracorp/hub-modules.blade.php'));
    $styles = file_get_contents(base_path('resources/views/integracorp/partials/hub-styles.blade.php'));

    expect($modules)
        ->toContain('ic-hall-topbar')
        ->toContain("'placement' => 'header-bar', 'darkLogo' => 'gold'")
        ->toContain('ic-hall-hero')
        ->toContain('ic-hall-hero__greeting')
        ->toContain('ic-hall-hero__name')
        ->not->toContain('Integracorp · Hall')
        ->not->toContain('activeNum')
        ->not->toContain('Módulos')
        ->toContain('$modulesLead')
        ->toContain('$modulesCountLabel')
        ->toContain('hub-theme-toggle')
        ->toContain('ic-modules-grid')
        ->toContain('ic-hall-accordion')
        ->toContain('data-count="{{ count($modules) }}"')
        ->toContain('--ic-module-count')
        ->toContain('ic-module-card--pressed')
        ->toContain('ic-module-card--enter')
        ->toContain('ic-module-card--hall')
        ->toContain('--ic-module-enter-delay')
        ->toContain('$loop->index * 85')
        ->toContain('pointerdown')
        ->toContain('ic-module-card__panel')
        ->toContain('ic-module-card__name')
        ->toContain('ic-module-card__objective')
        ->toContain('ic-module-card__media')
        ->toContain('ic-module-card__photo')
        ->toContain('ic-module-card__enter')
        ->toContain('icHubModulesCarousel')
        ->toContain('icHubHallClock')
        ->not->toContain('hub-modules-carousel-alpine')
        ->not->toContain('Entrar al módulo');

    expect(file_get_contents(base_path('resources/views/integracorp/partials/hub-modules-carousel-alpine.blade.php')))
        ->toContain('3000')
        ->toContain('ic-module-card--spotlight')
        ->toContain('ic-module-card--hall-collapsed')
        ->toContain('setActive')
        ->toContain('prefers-reduced-motion')
        ->toContain('(min-width: 900px)')
        ->toContain('window.Alpine')
        ->toContain("addEventListener('alpine:init'");

    expect(file_get_contents(base_path('resources/views/integracorp/partials/hub-hall-clock-alpine.blade.php')))
        ->toContain('icHubHallClock')
        ->toContain('es-VE');

    expect(file_get_contents(base_path('resources/views/integracorp/partials/hub-brand-logo.blade.php')))
        ->toContain('ic-hub-brand-mark--header-bar')
        ->toContain('ic-hub-brand-mark--dark-logo-gold')
        ->toContain('ic-hub-brand-mark__logo--on-dark')
        ->toContain('ic-hub-brand-mark__logo--on-dark-gold')
        ->toContain('ic-hub-brand-mark__logo--on-light')
        ->toContain('logoTDG.png')
        ->toContain('hub-auth-logo-gold.png')
        ->toContain('logoNewTDG.png');

    expect(file_get_contents(base_path('resources/views/integracorp/partials/hub-styles.blade.php')))
        ->toContain('ic-hub-brand-mark--dark-logo-gold')
        ->toContain('ic-hub-brand-mark__logo--on-dark-gold');

    expect(file_get_contents(base_path('app/Support/Integracorp/IntegracorpHubModuleRegistry.php')))
        ->toContain('image/hub-modules/business.png')
        ->toContain('image/hub-modules/metrics.png')
        ->toContain('image/hub-modules/admin.png');

    expect($styles)
        ->toContain('--ic-hub-logo-gold: #d29d45')
        ->toContain('.ic-hall-hero__name')
        ->toContain('color: var(--ic-hub-logo-gold)')
        ->toContain('.ic-hall-topbar__avatar')
        ->toContain('.ic-hall-btn-logout')
        ->toContain('.ic-hall-stage')
        ->toContain('body.ic-hub-body:has(.ic-hub-page--modules) .ic-hall-main')
        ->toContain('body.ic-hub-body:has(.ic-hub-page--modules) .ic-hall-stage')
        ->toContain('#0f2233')
        ->toContain('html:has(.ic-hub-page--modules)')
        ->toContain('body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg')
        ->toContain('html.ic-hub-theme-switching')
        ->toContain('transition: background-color 1s ease')
        ->toContain('body.ic-hub-body:has(.ic-hub-page--modules) .ic-hall-topbar')
        ->toContain('padding-top: max(0.5rem, env(safe-area-inset-top, 0px))')
        ->toContain('body.ic-hub-body:has(.ic-hub-page--modules) .ic-hall-shell')
        ->toContain('body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg::after')
        ->toContain('#F0F4F8')
        ->toContain('#0B132B')
        ->toContain('#64748B')
        ->toContain('border-bottom: 1px solid rgba(239, 233, 223, 0.08)')
        ->toContain('html.ic-hub-theme-light body.ic-hub-body:has(.ic-hub-page--modules) .ic-hall-topbar')
        ->toContain('.ic-hub-page.ic-hub-page--modules')
        ->toContain('body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-footer')
        ->toContain('border-top: 1px solid rgba(239, 233, 223, 0.08)')
        ->toContain('ic-hub-brand-mark__logo--on-light')
        ->toContain('grid-column: 2')
        ->toContain('width: min(100%, 76rem)')
        ->toContain('.ic-hall-accordion')
        ->toContain('body.ic-hub-body:has(.ic-hub-page--modules) .ic-hall-hero')
        ->toContain('white-space: nowrap')
        ->toContain('padding-bottom: 0')
        ->toContain('justify-content: center')
        ->toContain('padding-bottom: clamp(0.65rem, 1.8vh, 1.25rem)')
        ->toContain('--ic-hall-accordion-spotlight-w')
        ->toContain('width: fit-content')
        ->toContain('--ic-hall-accordion-collapsed-w')
        ->toContain('gap: var(--ic-modules-gap)')
        ->toContain('--ic-module-card-ink: #efe9df')
        ->toContain('--ic-module-card-muted: rgba(239, 233, 223, 0.78)')
        ->toContain('color: var(--ic-module-card-muted)')
        ->toContain('color: var(--ic-module-card-subtle)')
        ->toContain('--ic-module-card-max-h: 18.25rem')
        ->not->toContain('html.ic-hub-theme-light .ic-module-card__name')
        ->toContain('.ic-hall-accordion > .ic-module-card--hall')
        ->toContain('min-height: 100%')
        ->toContain('box-shadow: none')
        ->toContain('.ic-module-card__media')
        ->toContain('clip-path: inset(0 round var(--ic-hall-radius))')
        ->toContain('inset: -3px')
        ->toContain('scale(1.06)')
        ->toContain('--ic-modules-card-gap')
        ->toContain('body.ic-hub-body:has(.ic-hub-page--modules)')
        ->toContain('.ic-module-card--hall.ic-module-card--pressed')
        ->not->toContain('translate3d(0, -0.4rem, 0)')
        ->toContain('.ic-module-card--spotlight')
        ->toContain('0 10px 24px rgba(15, 23, 42, 0.12)')
        ->toContain('scale(0.982)')
        ->toContain('width 0.8s cubic-bezier(0.2, 0.8, 0.2, 1)')
        ->toContain('@keyframes ic-module-card-enter')
        ->toContain('--ic-module-enter-delay')
        ->toContain('transform: translate3d(0, 0, 0)')
        ->toContain('inset: 0')
        ->toContain('--ic-module-title-air: 0.7rem')
        ->toContain('-webkit-line-clamp: 2')
        ->toContain('.ic-module-card__badge')
        ->toContain('border-radius: 999px')
        ->toContain('.ic-module-card__objective')
        ->toContain('.ic-module-card__tag')
        ->toContain('transform: translateY(0.55rem)')
        ->toContain('brightness(0.42)')
        ->toContain('html.ic-hub-theme-light .ic-hall')
        ->toContain('html.ic-hub-theme-light body.ic-hub-body:has(.ic-hub-page--modules) .ic-hub-bg__photo--modules-dark')
        ->toContain('.ic-hub-bg__photo--modules-light')
        ->toContain('--ic-hub-modules-photo-filter')
        ->toContain('.ic-hub-bg__vignette')
        ->toContain('.ic-hub-bg__sparkles')
        ->toContain('z-index: 5')
        ->toContain('.ic-hall-topbar__actions .ic-hub-theme-toggle-wrap')
        ->toContain('@media (max-width: 899px)')
        ->toContain('body.ic-hub-body:has(.ic-hub-page--modules) .ic-hall-hero__lead')
        ->toContain('body.ic-hub-body:has(.ic-hub-page--modules) .ic-module-card--hall .ic-module-card__objective')
        ->toContain('text-align: center')
        ->toContain('ic-hub-brand-mark--header-bar')
        ->toContain('aspect-ratio: 1 / 1')
        ->toContain('object-fit: cover')
        ->toContain('overflow-y: auto')
        ->toContain('margin-top: auto')
        ->not->toContain('#f4f8fa')
        ->not->toContain('ic-hub-bg__healthtech')
        ->not->toContain('ic-hub-ecg-sweep');
});
