<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Storefront\StorefrontAuth;

it('cualquier usuario activo puede usar la pwa', function (): void {
    $colaborador = new User([
        'name' => 'María',
        'status' => 'ACTIVO',
        'is_agent' => false,
    ]);

    $inactivo = new User([
        'name' => 'Luis',
        'status' => 'INACTIVO',
        'is_agent' => true,
    ]);
    $inactivo->agent_id = 9;

    expect(StorefrontAuth::canAccessPwa($colaborador))->toBeTrue()
        ->and(StorefrontAuth::canAccessPwa($inactivo))->toBeFalse()
        ->and(StorefrontAuth::canAccessPwa(null))->toBeFalse();
});

it('el middleware y las rutas exigen sesion en toda la app', function (): void {
    $routes = file_get_contents(dirname(__DIR__, 2).'/routes/storefront.php');
    $bootstrap = file_get_contents(dirname(__DIR__, 2).'/bootstrap/app.php');
    $middleware = file_get_contents(dirname(__DIR__, 2).'/app/Http/Middleware/EnsureStorefrontAuthenticated.php');
    $account = file_get_contents(dirname(__DIR__, 2).'/app/Support/Storefront/StorefrontAccount.php');
    $google = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Storefront/StorefrontGoogleAuthController.php');
    $login = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/volt/app/login.blade.php');
    $register = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/volt/app/register.blade.php');
    $profile = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/volt/app/profile.blade.php');
    $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_09_03_192644_add_nro_identification_to_users_table.php');

    expect($bootstrap)
        ->toContain('storefront.auth')
        ->toContain('EnsureStorefrontAuthenticated')
        ->and($routes)->toContain("middleware('storefront.auth')")
        ->and($routes)->toContain("middleware('storefront.guest')")
        ->and($routes)->toContain('volt.app.register')
        ->and($routes)->toContain('volt.app.profile')
        ->and($routes)->toContain('volt.app.quotes')
        ->and($middleware)->toContain('mustCompleteProfile')
        ->and($account)->toContain('function findByLoginIdentifier')
        ->and($account)->toContain('function register')
        ->and($account)->toContain('function createFromGoogle')
        ->and($account)->toContain('nro_identification')
        ->and($google)->toContain('createFromGoogle')
        ->and($google)->not->toContain('canLoginAsAgent')
        ->and($login)->toContain('identifier')
        ->and($login)->toContain('Correo, teléfono o cédula')
        ->and($register)->toContain('nro_identification')
        ->and($register)->toContain('min:4')
        ->and($register)->not->toContain('wire:model="email"')
        ->and($register)->not->toContain('wire:model="phone"')
        ->and($register)->toContain('Mi perfil')
        ->and($profile)->toContain('updateProfile')
        ->and($profile)->toContain('min:4')
        ->and($migration)->toContain('nro_identification')
        ->and($migration)->toContain('MODIFY email VARCHAR(255) NULL');
});
