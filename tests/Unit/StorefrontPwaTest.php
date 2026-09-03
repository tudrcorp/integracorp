<?php

declare(strict_types=1);

function storefrontBasePath(string $path): string
{
    return dirname(__DIR__, 2).'/'.ltrim($path, '/');
}

it('el layout storefront es una app mobile con menu hamburguesa en el header', function (): void {
    $layout = file_get_contents(storefrontBasePath('resources/views/components/layouts/storefront.blade.php'));
    $css = file_get_contents(storefrontBasePath('resources/css/storefront.css'));
    $head = file_get_contents(storefrontBasePath('resources/views/partials/storefront-head.blade.php'));

    expect($layout)
        ->toContain('storefront-app')
        ->toContain('storefront-header')
        ->toContain('storefront-menu-btn')
        ->toContain('storefront-burger')
        ->toContain('menuOpen = ! menuOpen')
        ->toContain("menuOpen && 'is-open'")
        ->toContain('storefront-sheet')
        ->toContain('storefront-sheet__handle')
        ->toContain('storefront-quote-success')
        ->toContain('quote-success')
        ->toContain('target="_blank"')
        ->toContain('rel="noopener noreferrer"')
        ->toContain('is-whatsapp')
        ->toContain("\$item['url']")
        ->and($layout)->not->toContain('x-show="menuOpen"')
        ->and($layout)->not->toContain('x-transition.opacity')
        ->and($layout)->not->toContain('storefront-tabbar')
        ->and($layout)->not->toContain('position: fixed; bottom: 0');

    expect($css)
        ->toContain('storefront-header')
        ->toContain('.storefront-menu-btn')
        ->toContain('background: transparent')
        ->toContain('backdrop-filter: blur')
        ->toContain('safe-area-inset-top')
        ->toContain('100dvh')
        ->toContain('100lvh')
        ->toContain('.storefront-sheet')
        ->toContain('.storefront-overlay.is-open')
        ->toContain('visibility: hidden')
        ->toContain('translateY(110%)')
        ->toContain('align-items: flex-end')
        ->toContain('justify-content: stretch')
        ->toContain('border-radius: 1.45rem 1.45rem 0 0')
        ->toContain('.sf-quote')
        ->toContain('.sf-review')
        ->toContain('.sf-ticket')
        ->toContain('.sf-proposal__choice')
        ->toContain('margin-top: auto')
        ->toContain('.sf-success')
        ->toContain('sf-success__handle')
        ->toContain('sf-check-draw')
        ->toContain('--sf-paper: #f3eee6')
        ->toContain('max-height: min(58dvh, 34rem)')
        ->toContain('max-height: 70dvh')
        ->toContain('height: 70dvh')
        ->toContain('.sf-quote-sheet')
        ->toContain('.sf-product-sheet__includes')
        ->toContain('body.is-benefits-open .sf-product__photo')
        ->toContain('filter: blur(22px)')
        ->toContain('body:has(.sf-product) .storefront-header')
        ->toContain('body:has(.sf-product) .storefront-atmosphere')
        ->toContain('.sf-product__photo')
        ->toContain('.storefront-sheet__icon.is-whatsapp')
        ->toContain('.sf-seg[data-channel="whatsapp"] .sf-seg__btn--whatsapp')
        ->toContain('.sf-seg[data-channel="email"] .sf-seg__btn--email')
        ->toContain('.sf-seg[data-channel="email"] .sf-seg__btn--whatsapp')
        ->toContain('#25d366')
        ->toContain('.sf-spinner')
        ->toContain('width: 100vw')
        ->and($css)->not->toContain('max-height: min(88dvh, 46rem)')
        ->and($css)->not->toContain('height: -webkit-fill-available')
        ->and($css)->not->toContain('.storefront-tabbar')
        ->and($css)->not->toContain('@keyframes sf-enter')
        ->and($css)->not->toContain('.sf-seg__btn--whatsapp.is-on')
        ->and($css)->not->toContain('.sf-seg__btn--email.is-on');

    expect($head)
        ->toContain('apple-mobile-web-app-capable')
        ->toContain('black-translucent')
        ->toContain('storefront.webmanifest')
        ->toContain('viewport-fit=cover')
        ->toContain('resources/css/storefront.css')
        ->toContain('theme-color" content="#020914');
});

it('existe manifest pwa y service worker de la app de planes', function (): void {
    $manifest = file_get_contents(storefrontBasePath('public/pwa/storefront.webmanifest'));
    $sw = file_get_contents(storefrontBasePath('public/app/sw.js'));
    $install = file_get_contents(storefrontBasePath('resources/views/storefront/partials/install.blade.php'));
    $htaccess = file_get_contents(storefrontBasePath('public/.htaccess'));

    expect($manifest)
        ->toContain('"short_name": "Tu Dr En Casa"')
        ->toContain('"start_url": "/app"')
        ->toContain('"scope": "/app"')
        ->toContain('"display": "standalone"')
        ->toContain('"theme_color": "#020914"');

    expect($sw)
        ->toContain("self.addEventListener('install'")
        ->toContain('/app')
        ->toContain("request.mode === 'navigate'");

    expect($install)
        ->toContain("register('/app/sw.js")
        ->toContain("scope: '/app/'")
        ->toContain('Agregar a pantalla de inicio');

    expect($htaccess)->toContain('RewriteRule ^app/?$ index.php [L]');
});

it('las rutas de la pwa viven bajo /app y el flujo de cotizacion es por paginas', function (): void {
    $routes = file_get_contents(storefrontBasePath('routes/storefront.php'));
    $bootstrap = file_get_contents(storefrontBasePath('bootstrap/app.php'));
    $vite = file_get_contents(storefrontBasePath('vite.config.js'));

    expect($routes)
        ->toContain("->prefix('app')")
        ->toContain('volt.app.welcome')
        ->toContain('volt.app.home')
        ->toContain('volt.app.plan')
        ->toContain('volt.app.quote-people')
        ->toContain('volt.app.quote-details')
        ->toContain('volt.app.quote-confirm')
        ->toContain('volt.app.quote-proposal')
        ->toContain('volt.app.quote-result')
        ->toContain('volt.app.login')
        ->toContain('storefront.login.google')
        ->toContain('storefront.logout');

    expect($bootstrap)->toContain('routes/storefront.php')
        ->and($vite)->toContain('resources/css/storefront.css');
});

it('el catalogo y la ficha venden el plan como producto', function (): void {
    $home = file_get_contents(storefrontBasePath('resources/views/livewire/volt/app/home.blade.php'));
    $plan = file_get_contents(storefrontBasePath('resources/views/livewire/volt/app/plan.blade.php'));
    $people = file_get_contents(storefrontBasePath('resources/views/livewire/volt/app/quote-people.blade.php'));
    $details = file_get_contents(storefrontBasePath('resources/views/livewire/volt/app/quote-details.blade.php'));
    $confirm = file_get_contents(storefrontBasePath('resources/views/livewire/volt/app/quote-confirm.blade.php'));
    $result = file_get_contents(storefrontBasePath('resources/views/livewire/volt/app/quote-result.blade.php'));
    $proposal = file_get_contents(storefrontBasePath('resources/views/livewire/volt/app/quote-proposal.blade.php'));
    $login = file_get_contents(storefrontBasePath('resources/views/livewire/volt/app/login.blade.php'));

    expect($home)
        ->toContain('sf-plan-card')
        ->toContain('sf-plan-card__photo')
        ->toContain('sf-plan-card__media')
        ->toContain('storefront.plan')
        ->and($home)->not->toContain('sf-plan-card__glow')
        ->and($plan)->toContain('sf-product')
        ->and($plan)->toContain('Ver Beneficios')
        ->and($plan)->toContain('Cotizar')
        ->and($plan)->toContain('Qué incluye')
        ->and($plan)->toContain('Rangos de edad y tarifas')
        ->and($plan)->toContain('sf-product-sheet__rate-group')
        ->and($plan)->toContain('sf-product-sheet__includes')
        ->and($plan)->not->toContain('sf-chips')
        ->and($plan)->toContain('startQuote')
        ->and($plan)->toContain('sf-product-sheet')
        ->and($plan)->toContain('benefitsOpen')
        ->and($plan)->toContain("benefitsOpen && 'is-open'")
        ->and($plan)->not->toContain('x-show="benefitsOpen"')
        ->and($people)->toContain('asAgent')
        ->and($people)->toContain('sf-quote')
        ->and($people)->toContain('¿Quiénes se cubren?')
        ->and($people)->toContain('Grupo familiar del cliente')
        ->and($details)->toContain('sf-quote')
        ->and($confirm)->toContain('sf-quote')
        ->and($confirm)->toContain('storefront-quote-success')
        ->and($confirm)->toContain('sf-review')
        ->and($confirm)->toContain('Estimado')
        ->and($confirm)->toContain('Correo')
        ->and($confirm)->toContain('Teléfono')
        ->and($result)->toContain('sf-quote')
        ->and($result)->toContain('sf-ticket')
        ->and($result)->toContain('Código de cotización')
        ->and($result)->toContain('Copiar código')
        ->and($result)->toContain('A nombre de')
        ->and($result)->toContain('storefront.quote.proposal')
        ->and($result)->toContain('wire:navigate')
        ->and($result)->not->toContain('/in/')
        ->and($proposal)->toContain('components.layouts.storefront')
        ->and($proposal)->toContain('sf-quote')
        ->and($proposal)->toContain('sf-quote-sheet')
        ->and($proposal)->toContain('sf-back')
        ->and($proposal)->toContain('Rangos de edad')
        ->and($proposal)->toContain('Formas de pago')
        ->and($proposal)->toContain('Descargar cotización')
        ->and($proposal)->toContain('Enviar ahora')
        ->and($proposal)->toContain('Destinatarios')
        ->and($proposal)->toContain('sf-seg__btn--whatsapp')
        ->and($proposal)->toContain('data-channel')
        ->and($proposal)->toContain('role="radio"')
        ->and($proposal)->toContain('setRecipientChannel')
        ->and($proposal)->toContain('addEmailRecipient')
        ->and($proposal)->toContain('Agregar correo')
        ->and($proposal)->not->toContain('x-data="{ channel:')
        ->and($proposal)->not->toContain('is-on')
        ->and($proposal)->toContain('Hablar con negocios')
        ->and($proposal)->toContain('storefront.quote.result')
        ->and($proposal)->toContain('storefront.partials.btn-loading')
        ->and($proposal)->not->toContain('sf-sticky-cta')
        ->and($proposal)->not->toContain('components.layouts.interactive')
        ->and($confirm)->toContain('storefront.partials.btn-loading')
        ->and($login)->toContain('Entra con tu cuenta')
        ->and($login)->toContain('no inicia sesión')
        ->and($login)->toContain('google-login-button')
        ->and($login)->toContain('o con tu correo de agente');
});
