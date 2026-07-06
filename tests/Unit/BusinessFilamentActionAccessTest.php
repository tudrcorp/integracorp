<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\User;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\PermissionNavigationGroupResolver;
use App\Support\Filament\UserNavigationAccess;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Auth;

uses(WithFaker::class, Tests\TestCase::class);

function makeActionUser(array $departments, array $permissionSlugs = []): User
{
    $user = new User;
    $user->forceFill([
        'id' => fake()->unique()->randomNumber(5),
        'name' => 'Test User',
        'email' => 'test@tudrencasa.com',
        'departament' => $departments,
        'status' => 'ACTIVO',
    ]);

    $permissions = collect();

    foreach ($permissionSlugs as $slug) {
        $permissions->push(
            tap(new Permission, fn (Permission $permission) => $permission->forceFill([
                'id' => fake()->unique()->randomNumber(5),
                'name' => $slug,
                'slug' => $slug,
                'module' => 'NEGOCIOS',
            ]))
        );
    }

    $user->setRelation('permissions', $permissions);

    return $user;
}

it('permite crear afiliado corporativo a superadmin', function (): void {
    $user = makeActionUser(['SUPERADMIN', 'NEGOCIOS']);

    expect(UserNavigationAccess::canPerformModuleAction(
        $user,
        'NEGOCIOS',
        BusinessFilamentActionPermissionRegistry::CREATE_CORPORATE_AFFILIATE,
    ))->toBeTrue();
});

it('permite crear afiliado corporativo con el permiso asignado', function (): void {
    $user = makeActionUser(
        ['NEGOCIOS'],
        [BusinessFilamentActionPermissionRegistry::CREATE_CORPORATE_AFFILIATE],
    );

    expect(UserNavigationAccess::canPerformModuleAction(
        $user,
        'NEGOCIOS',
        BusinessFilamentActionPermissionRegistry::CREATE_CORPORATE_AFFILIATE,
    ))->toBeTrue();
});

it('niega crear afiliado corporativo sin permiso asignado', function (): void {
    $user = makeActionUser(['NEGOCIOS'], ['cotizador-individual']);

    expect(UserNavigationAccess::canPerformModuleAction(
        $user,
        'NEGOCIOS',
        BusinessFilamentActionPermissionRegistry::CREATE_CORPORATE_AFFILIATE,
    ))->toBeFalse();
});

it('resuelve el grupo de navegacion del permiso de crear afiliado corporativo', function (): void {
    $permission = new Permission;
    $permission->forceFill([
        'slug' => BusinessFilamentActionPermissionRegistry::CREATE_CORPORATE_AFFILIATE,
        'module' => 'NEGOCIOS',
        'name' => 'Crear afiliado corporativo',
    ]);

    expect(PermissionNavigationGroupResolver::groupForPermission($permission))->toBe('AFILIACIONES');
});

it('expone la accion de crear afiliado corporativo segun el usuario autenticado', function (): void {
    $user = makeActionUser(
        ['NEGOCIOS'],
        [BusinessFilamentActionPermissionRegistry::CREATE_CORPORATE_AFFILIATE],
    );

    Auth::login($user);

    expect(BusinessFilamentActionAccess::userCan(
        BusinessFilamentActionPermissionRegistry::CREATE_CORPORATE_AFFILIATE,
    ))->toBeTrue();
});
