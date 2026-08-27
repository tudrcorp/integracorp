<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\User;
use App\Support\CommercialStructure\ReferidorAccess;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\PermissionNavigationGroupResolver;
use App\Support\Filament\UserNavigationAccess;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Auth;

uses(WithFaker::class, Tests\TestCase::class);

function makeActionUser(array $departments, array $permissionSlugs = [], string $permissionModule = 'NEGOCIOS'): User
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
                'module' => $permissionModule,
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

it('resuelve el grupo de navegacion del permiso de matriz de negociacion', function (): void {
    $permission = new Permission;
    $permission->forceFill([
        'slug' => BusinessFilamentActionPermissionRegistry::MANAGE_WHITE_COMPANY_NEGOTIATED_FEES,
        'module' => 'NEGOCIOS',
        'name' => 'Matriz de negociación',
    ]);

    expect(PermissionNavigationGroupResolver::groupForPermission($permission))->toBe('ESTRUCTURA COMERCIAL');
});

it('permite la matriz de negociacion con el subpermiso asignado', function (): void {
    $user = makeActionUser(
        ['NEGOCIOS'],
        [BusinessFilamentActionPermissionRegistry::MANAGE_WHITE_COMPANY_NEGOTIATED_FEES],
    );

    expect(UserNavigationAccess::canPerformModuleAction(
        $user,
        'NEGOCIOS',
        BusinessFilamentActionPermissionRegistry::MANAGE_WHITE_COMPANY_NEGOTIATED_FEES,
    ))->toBeTrue();
});

it('niega la matriz de negociacion sin el subpermiso aunque tenga empresas aliadas', function (): void {
    $user = makeActionUser(['NEGOCIOS'], ['empresas-aliadas']);

    expect(UserNavigationAccess::canPerformModuleAction(
        $user,
        'NEGOCIOS',
        BusinessFilamentActionPermissionRegistry::MANAGE_WHITE_COMPANY_NEGOTIATED_FEES,
    ))->toBeFalse();
});

it('resuelve el grupo de navegacion del permiso de documentos de marca', function (): void {
    $permission = new Permission;
    $permission->forceFill([
        'slug' => BusinessFilamentActionPermissionRegistry::MANAGE_WHITE_COMPANY_DOCUMENT_BRAND,
        'module' => 'NEGOCIOS',
        'name' => 'Documentos de marca',
    ]);

    expect(PermissionNavigationGroupResolver::groupForPermission($permission))->toBe('ESTRUCTURA COMERCIAL');
});

it('permite documentos de marca con el subpermiso asignado', function (): void {
    $user = makeActionUser(
        ['NEGOCIOS'],
        [BusinessFilamentActionPermissionRegistry::MANAGE_WHITE_COMPANY_DOCUMENT_BRAND],
    );

    expect(UserNavigationAccess::canPerformModuleAction(
        $user,
        'NEGOCIOS',
        BusinessFilamentActionPermissionRegistry::MANAGE_WHITE_COMPANY_DOCUMENT_BRAND,
    ))->toBeTrue();
});

it('niega documentos de marca sin el subpermiso aunque tenga empresas aliadas', function (): void {
    $user = makeActionUser(['NEGOCIOS'], ['empresas-aliadas']);

    expect(UserNavigationAccess::canPerformModuleAction(
        $user,
        'NEGOCIOS',
        BusinessFilamentActionPermissionRegistry::MANAGE_WHITE_COMPANY_DOCUMENT_BRAND,
    ))->toBeFalse();
});

it('permite gestionar referidor a superadmin', function (): void {
    $user = makeActionUser(['SUPERADMIN', 'NEGOCIOS']);

    expect(UserNavigationAccess::canPerformModuleAction(
        $user,
        'NEGOCIOS',
        BusinessFilamentActionPermissionRegistry::MANAGE_REFERIDOR,
    ))->toBeTrue();
});

it('permite gestionar referidor con el permiso asignado', function (): void {
    $user = makeActionUser(
        ['NEGOCIOS'],
        [BusinessFilamentActionPermissionRegistry::MANAGE_REFERIDOR],
    );

    expect(UserNavigationAccess::canPerformModuleAction(
        $user,
        'NEGOCIOS',
        BusinessFilamentActionPermissionRegistry::MANAGE_REFERIDOR,
    ))->toBeTrue();
});

it('niega gestionar referidor sin el permiso asignado', function (): void {
    $user = makeActionUser(['NEGOCIOS'], ['agencias-de-corretaje']);

    expect(UserNavigationAccess::canPerformModuleAction(
        $user,
        'NEGOCIOS',
        BusinessFilamentActionPermissionRegistry::MANAGE_REFERIDOR,
    ))->toBeFalse();
});

it('resuelve el grupo de navegacion del permiso de gestionar referidor', function (string $module): void {
    $permission = new Permission;
    $permission->forceFill([
        'slug' => BusinessFilamentActionPermissionRegistry::MANAGE_REFERIDOR,
        'module' => $module,
        'name' => 'Asignación de referidor',
    ]);

    expect(PermissionNavigationGroupResolver::groupForPermission($permission))->toBe('ESTRUCTURA COMERCIAL');
})->with(['NEGOCIOS', 'ADMINISTRACION']);

it('fuera de un panel concede el reporte de ventas al analista de administracion', function (): void {
    $user = makeActionUser(
        ['ADMINISTRACION'],
        [BusinessFilamentActionPermissionRegistry::WHITE_COMPANY_SALES_REPORT],
        'ADMINISTRACION',
    );

    Auth::login($user);

    expect(BusinessFilamentActionAccess::userCan(
        BusinessFilamentActionPermissionRegistry::WHITE_COMPANY_SALES_REPORT,
    ))->toBeTrue();
});

it('fuera de un panel niega el reporte de ventas a quien solo es de negocios', function (): void {
    $user = makeActionUser(
        ['NEGOCIOS'],
        [BusinessFilamentActionPermissionRegistry::WHITE_COMPANY_SALES_REPORT],
        'ADMINISTRACION',
    );

    Auth::login($user);

    expect(BusinessFilamentActionAccess::userCan(
        BusinessFilamentActionPermissionRegistry::WHITE_COMPANY_SALES_REPORT,
    ))->toBeFalse();
});

it('fuera de un panel niega el reporte de ventas sin el subpermiso aunque vea empresas aliadas', function (): void {
    $user = makeActionUser(['ADMINISTRACION'], ['empresas-aliadas'], 'ADMINISTRACION');

    Auth::login($user);

    expect(BusinessFilamentActionAccess::userCan(
        BusinessFilamentActionPermissionRegistry::WHITE_COMPANY_SALES_REPORT,
    ))->toBeFalse();
});

it('permite gestionar referidor al analista de administracion en su panel', function (): void {
    $user = makeActionUser(
        ['ADMINISTRACION'],
        [ReferidorAccess::PERMISSION_SLUG],
        'ADMINISTRACION',
    );

    Auth::login($user);
    Filament::setCurrentPanel(Filament::getPanel('administration'));

    expect(ReferidorAccess::userCanManage())->toBeTrue();
});
