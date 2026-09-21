<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Storefront\StorefrontAuth;
use App\Support\Storefront\StorefrontNav;

it('el menu publico ofrece login, registro y contacto', function (): void {
    $items = StorefrontNav::items(null);
    $keys = array_column($items, 'key');

    expect($keys)->toContain('home', 'quote', 'payments', 'downloads', 'login', 'register', 'business_whatsapp', 'quotes_whatsapp')
        ->and($keys)->not->toContain('logout', 'pending', 'profile', 'quotes')
        ->and($items[0]['icon'])->toBe('home')
        ->and($keys[array_search('payments', $keys, true) - 1])->toBe('quote')
        ->and($keys[array_search('downloads', $keys, true) - 1])->toBe('payments');

    $register = collect($items)->firstWhere('key', 'register');
    $payments = collect($items)->firstWhere('key', 'payments');
    $downloads = collect($items)->firstWhere('key', 'downloads');

    expect($register)->not->toBeNull()
        ->and($register['label'])->toBe('Crear cuenta')
        ->and($register['soon'])->toBeFalse()
        ->and($register['route'])->toBe('storefront.register')
        ->and($payments['label'])->toBe('Métodos de pago')
        ->and($payments['route'])->toBe('storefront.payment-methods')
        ->and($downloads['label'])->toBe('Zona de Descarga')
        ->and($downloads['route'])->toBe('storefront.download-zone')
        ->and($downloads['icon'])->toBe('downloads')
        ->and($downloads['soon'])->toBeFalse();
});

it('cada item del menu trae un icono para la hoja inferior', function (): void {
    $items = StorefrontNav::items(null);
    $icons = array_column($items, 'icon');

    expect($icons)->toBe(['home', 'quote', 'payments', 'downloads', 'login', 'affiliations', 'whatsapp', 'whatsapp'])
        ->and(file_get_contents(dirname(__DIR__, 2).'/resources/views/storefront/partials/nav-icon.blade.php'))
        ->toContain("'home'")
        ->toContain("'payments'")
        ->toContain("'downloads'")
        ->toContain("'affiliations'")
        ->toContain("'quotes'")
        ->toContain("'whatsapp'");
});

it('el menu abre whatsapp del cliente hacia negocios y cotizaciones', function (): void {
    $items = StorefrontNav::items(null);
    $contacts = array_values(array_filter(
        $items,
        fn (array $item): bool => ($item['external'] ?? false) === true,
    ));

    expect($contacts)->toHaveCount(2)
        ->and($contacts[0]['label'])->toBe('Equipo de negocios')
        ->and($contacts[1]['label'])->toBe('Equipo de cotizaciones')
        ->and($contacts[0]['url'])->toStartWith('https://wa.me/584127018390')
        ->and($contacts[1]['url'])->toStartWith('https://wa.me/')
        ->and($contacts[0]['url'])->toContain('equipo%20de%20negocios')
        ->and($contacts[1]['url'])->toContain('cotizaci')
        ->and($contacts[0]['hint'])->toContain('0412 701 8390');
});

it('el menu autenticado ofrece perfil y cerrar sesion', function (): void {
    $agente = new User([
        'name' => 'Ana Pérez',
        'is_agent' => true,
        'status' => 'ACTIVO',
    ]);
    $agente->agent_id = 4;

    $items = StorefrontNav::items($agente);
    $keys = array_column($items, 'key');

    expect($keys)->toContain('logout', 'profile', 'quotes')
        ->and($keys)->not->toContain('login')
        ->and($keys)->not->toContain('register')
        ->and($keys)->toContain('business_whatsapp', 'quotes_whatsapp')
        ->and($keys[array_search('quotes', $keys, true) - 1])->toBe('downloads')
        ->and(StorefrontAuth::isAgent($agente))->toBeTrue()
        ->and(StorefrontNav::subtitle($agente))->toStartWith('Hola,');

    $quotes = collect($items)->firstWhere('key', 'quotes');

    expect($quotes)->not->toBeNull()
        ->and($quotes['label'])->toBe('Mis cotizaciones')
        ->and($quotes['route'])->toBe('storefront.quotes')
        ->and($quotes['icon'])->toBe('quotes')
        ->and($quotes['accent'])->toBeTrue();

    expect(collect($items)->firstWhere('key', 'home')['accent'])->toBeFalse()
        ->and(collect($items)->firstWhere('key', 'payments')['accent'])->toBeFalse()
        ->and(collect($items)->firstWhere('key', 'downloads')['accent'])->toBeFalse();
});

it('la ficha del plan no usa El plan y ofrece volver al catalogo', function (): void {
    $nav = file_get_contents(dirname(__DIR__, 2).'/app/Support/Storefront/StorefrontNav.php');

    expect($nav)
        ->not->toContain("'storefront.plan' => 'El plan'")
        ->toContain("'storefront.plan' => ''")
        ->toContain('function back')
        ->toContain("'label' => 'Volver al catálogo'")
        ->and(StorefrontNav::back())->toBeNull();
});

it('el catalogo no muestra Planes junto al logo', function (): void {
    $nav = file_get_contents(dirname(__DIR__, 2).'/app/Support/Storefront/StorefrontNav.php');

    expect($nav)
        ->toContain('private static function homeSubtitle')
        ->toContain("return '';")
        ->not->toContain("return 'Planes';");
});

it('la pantalla de confirmar no muestra Confirmar junto al logo', function (): void {
    $nav = file_get_contents(dirname(__DIR__, 2).'/app/Support/Storefront/StorefrontNav.php');

    expect($nav)
        ->toContain("'storefront.quote.confirm' => ''")
        ->not->toContain("'storefront.quote.confirm' => 'Confirmar'");
});

it('la pantalla de datos no muestra Tus datos junto al logo', function (): void {
    $nav = file_get_contents(dirname(__DIR__, 2).'/app/Support/Storefront/StorefrontNav.php');

    expect($nav)
        ->toContain("'storefront.quote.details' => ''")
        ->not->toContain("'storefront.quote.details' => 'Tus datos'");
});

it('la pantalla de personas no muestra Cotizar junto al logo', function (): void {
    $nav = file_get_contents(dirname(__DIR__, 2).'/app/Support/Storefront/StorefrontNav.php');

    expect($nav)
        ->toContain("'storefront.quote.people' => ''")
        ->not->toContain("'storefront.quote.people' => 'Cotizar'");
});

it('la pantalla de propuesta no muestra Propuesta junto al logo', function (): void {
    $nav = file_get_contents(dirname(__DIR__, 2).'/app/Support/Storefront/StorefrontNav.php');

    expect($nav)
        ->toContain("'storefront.quote.proposal' => ''")
        ->not->toContain("'storefront.quote.proposal' => 'Propuesta'");
});

it('la pantalla de pagar no muestra titulo junto al logo', function (): void {
    $nav = file_get_contents(dirname(__DIR__, 2).'/app/Support/Storefront/StorefrontNav.php');

    expect($nav)
        ->toContain("'storefront.quote.coverages' => ''")
        ->toContain("'storefront.quote.frequency' => ''")
        ->toContain("'storefront.quote.pay' => ''")
        ->toContain("'storefront.quote.receipt' => ''")
        ->toContain("'storefront.quote.receipt.success' => ''")
        ->toContain("'label' => 'Volver a cotizaciones'");
});

it('la pantalla de zona de descarga no muestra titulo junto al logo', function (): void {
    $nav = file_get_contents(dirname(__DIR__, 2).'/app/Support/Storefront/StorefrontNav.php');

    expect($nav)
        ->toContain("'storefront.download-zone' => ''")
        ->toContain("'storefront.download-zone.feed' => ''")
        ->toContain("'storefront.download-zone' => [")
        ->not->toContain("'storefront.download-zone' => 'Zona de Descarga'");
});

it('la pantalla de cotizaciones no muestra Mis cotizaciones junto al logo', function (): void {
    $nav = file_get_contents(dirname(__DIR__, 2).'/app/Support/Storefront/StorefrontNav.php');

    expect($nav)
        ->toContain("'storefront.quotes' => ''")
        ->toContain("'storefront.quotes' => [")
        ->not->toContain("'storefront.quotes' => 'Mis cotizaciones'");
});

it('la pantalla de resultado no muestra Cotización lista junto al logo', function (): void {
    $nav = file_get_contents(dirname(__DIR__, 2).'/app/Support/Storefront/StorefrontNav.php');

    expect($nav)
        ->toContain("'storefront.quote.result' => ''")
        ->not->toContain("'storefront.quote.result' => 'Cotización lista'");
});
