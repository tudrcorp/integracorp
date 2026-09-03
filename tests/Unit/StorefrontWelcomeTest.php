<?php

declare(strict_types=1);

function storefrontWelcomePath(string $path): string
{
    return dirname(__DIR__, 2).'/'.ltrim($path, '/');
}

it('la bienvenida es una pantalla de app sin hamburguesa y con tres acciones', function (): void {
    $welcome = file_get_contents(storefrontWelcomePath('resources/views/livewire/volt/app/welcome.blade.php'));
    $layout = file_get_contents(storefrontWelcomePath('resources/views/components/layouts/storefront-welcome.blade.php'));
    $css = file_get_contents(storefrontWelcomePath('resources/css/storefront.css'));

    expect($welcome)
        ->toContain("Layout('components.layouts.storefront-welcome')")
        ->toContain('storefront.partials.google-login-button')
        ->toContain('sf-welcome__photo')
        ->toContain('image/storefront/welcome.jpg')
        ->toContain('Iniciar sesión')
        ->toContain('storefront.login')
        ->toContain('Ver planes')
        ->toContain('storefront.home')
        ->and($welcome)->not->toContain('storefront-menu-btn')
        ->and($welcome)->not->toContain('sf-welcome__tile');

    $googleButton = file_get_contents(storefrontWelcomePath('resources/views/storefront/partials/google-login-button.blade.php'));

    expect($googleButton)
        ->toContain('Continuar con Google')
        ->toContain('storefront.login.google')
        ->toContain('sf-welcome__google-icon')
        ->toContain('sf-welcome__google-label')
        ->and($googleButton)->not->toContain('x-show="! going"')
        ->and($googleButton)->not->toContain('display: inline-flex');

    expect($layout)
        ->toContain('sf-welcome-shell')
        ->toContain('is-welcome')
        ->and($layout)->not->toContain('storefront-menu-btn')
        ->and($layout)->not->toContain('storefront-header');

    expect($css)
        ->toContain('.sf-welcome__btn--google')
        ->toContain('.sf-welcome__google-icon')
        ->toContain('flex-direction: row')
        ->toContain('white-space: nowrap')
        ->toContain('.sf-welcome__btn--login')
        ->toContain('.sf-welcome__btn--plans')
        ->toContain('.sf-welcome__photo')
        ->toContain('border-radius: 999px')
        ->toContain('Instrument Serif');

    expect(file_exists(storefrontWelcomePath('public/image/storefront/welcome.jpg')))->toBeTrue();
});
