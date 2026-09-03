<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Storefront\StorefrontAuth;
use App\Support\Storefront\StorefrontNav;

it('el menu publico ofrece login de agente y contacto, sin placeholders de pronto', function (): void {
    $items = StorefrontNav::items(null);
    $keys = array_column($items, 'key');

    expect($keys)->toContain('home', 'quote', 'login', 'business_whatsapp', 'quotes_whatsapp')
        ->and($keys)->not->toContain('logout', 'affiliations', 'payments', 'pending')
        ->and($items[0]['icon'])->toBe('home');

    $soon = array_values(array_filter($items, fn (array $item): bool => $item['soon']));

    expect($soon)->toBe([]);
});

it('cada item del menu trae un icono para la hoja inferior', function (): void {
    $items = StorefrontNav::items(null);
    $icons = array_column($items, 'icon');

    expect($icons)->toBe(['home', 'quote', 'login', 'whatsapp', 'whatsapp'])
        ->and(file_get_contents(dirname(__DIR__, 2).'/resources/views/storefront/partials/nav-icon.blade.php'))
        ->toContain("'home'")
        ->toContain("'install'")
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

it('el menu de agente cierra sesion en vez de pedir login', function (): void {
    $agente = new User([
        'name' => 'Ana Pérez',
        'is_agent' => true,
        'status' => 'ACTIVO',
    ]);
    $agente->agent_id = 4;

    $items = StorefrontNav::items($agente);
    $keys = array_column($items, 'key');

    expect($keys)->toContain('logout')
        ->and($keys)->not->toContain('login')
        ->and($keys)->toContain('business_whatsapp', 'quotes_whatsapp')
        ->and(StorefrontAuth::isAgent($agente))->toBeTrue()
        ->and(StorefrontNav::subtitle($agente))->toStartWith('Hola,');
});

it('la ficha del plan no usa El plan y ofrece volver al catalogo', function (): void {
    $nav = file_get_contents(dirname(__DIR__, 2).'/app/Support/Storefront/StorefrontNav.php');

    expect($nav)
        ->not->toContain("'storefront.plan' => 'El plan'")
        ->toContain("'storefront.plan' => ''")
        ->toContain('function back')
        ->toContain("'label' => 'Volver a planes'")
        ->and(StorefrontNav::back())->toBeNull();
});
