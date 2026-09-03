<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Storefront\StorefrontAuth;

it('un visitante no es agente', function (): void {
    expect(StorefrontAuth::isAgent(null))->toBeFalse();
});

it('solo un usuario de agente activo con ficha puede entrar a la pwa', function (): void {
    $agente = new User([
        'name' => 'Ana Pérez',
        'is_agent' => true,
        'status' => 'ACTIVO',
    ]);
    $agente->agent_id = 12;

    $inactivo = new User([
        'name' => 'Luis',
        'is_agent' => true,
        'status' => 'INACTIVO',
    ]);
    $inactivo->agent_id = 12;

    $sinFicha = new User([
        'name' => 'Carla',
        'is_agent' => true,
        'status' => 'ACTIVO',
    ]);
    $sinFicha->agent_id = null;

    $cliente = new User([
        'name' => 'Pedro',
        'is_agent' => false,
        'status' => 'ACTIVO',
    ]);
    $cliente->agent_id = 9;

    expect(StorefrontAuth::isAgent($agente))->toBeTrue()
        ->and(StorefrontAuth::canLoginAsAgent($agente))->toBeTrue()
        ->and(StorefrontAuth::isAgent($inactivo))->toBeFalse()
        ->and(StorefrontAuth::isAgent($sinFicha))->toBeFalse()
        ->and(StorefrontAuth::isAgent($cliente))->toBeFalse();
});
