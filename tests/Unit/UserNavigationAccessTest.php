<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\User;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Support\Filament\UserFormPermissionOptions;
use App\Support\Filament\UserNavigationAccess;
use Illuminate\Foundation\Testing\WithFaker;

uses(WithFaker::class, Tests\TestCase::class);

function makeNavigationUser(array $departments, array $permissionSlugsByModule = []): User
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

    foreach ($permissionSlugsByModule as $module => $slugs) {
        foreach ($slugs as $slug) {
            $permissions->push(
                tap(new Permission, fn (Permission $permission) => $permission->forceFill([
                    'id' => fake()->unique()->randomNumber(5),
                    'name' => $slug,
                    'slug' => $slug,
                    'module' => $module,
                ]))
            );
        }
    }

    $user->setRelation('permissions', $permissions);

    return $user;
}

it('permite acceso total a superadmin', function (): void {
    $user = makeNavigationUser(['SUPERADMIN', 'NEGOCIOS']);

    expect(UserNavigationAccess::canAccessMenuItem($user, 'NEGOCIOS', ['cotizador-individual']))->toBeTrue();
});

it('deniega acceso si el usuario no tiene el modulo en departament', function (): void {
    $user = makeNavigationUser(['ADMINISTRACION'], [
        'NEGOCIOS' => ['cotizador-individual'],
    ]);

    expect(UserNavigationAccess::canAccessMenuItem($user, 'NEGOCIOS', ['cotizador-individual']))->toBeFalse();
});

it('permite acceso completo al modulo cuando no hay permisos granulares asignados', function (): void {
    $user = makeNavigationUser(['NEGOCIOS', 'ADMINISTRACION']);

    expect(UserNavigationAccess::canAccessMenuItem($user, 'NEGOCIOS', ['cotizador-individual']))->toBeTrue()
        ->and(UserNavigationAccess::canAccessMenuItem($user, 'ADMINISTRACION', ['afiliaciones-individuales']))->toBeTrue();
});

it('restringe acceso granular por modulo cuando hay permisos asignados', function (): void {
    $user = makeNavigationUser(['NEGOCIOS', 'ADMINISTRACION'], [
        'NEGOCIOS' => ['cotizador-individual'],
        'ADMINISTRACION' => ['afiliaciones-individuales'],
    ]);

    expect(UserNavigationAccess::canAccessMenuItem($user, 'NEGOCIOS', ['cotizador-individual']))->toBeTrue()
        ->and(UserNavigationAccess::canAccessMenuItem($user, 'NEGOCIOS', ['afiliaciones-individuales']))->toBeFalse()
        ->and(UserNavigationAccess::canAccessMenuItem($user, 'ADMINISTRACION', ['afiliaciones-individuales']))->toBeTrue()
        ->and(UserNavigationAccess::canAccessMenuItem($user, 'ADMINISTRACION', ['ventas']))->toBeFalse();
});

it('expone helpers en el modelo user', function (): void {
    $user = makeNavigationUser(['NEGOCIOS'], [
        'NEGOCIOS' => ['cotizador-individual'],
    ]);

    expect($user->hasDepartamentModule('NEGOCIOS'))->toBeTrue()
        ->and($user->canAccessNavigationItem('NEGOCIOS', ['cotizador-individual']))->toBeTrue()
        ->and($user->canAccessNavigationItem('NEGOCIOS', ['afiliaciones-individuales']))->toBeFalse();
});

it('registra mapeos para cotizaciones y afiliaciones individuales de negocios', function (): void {
    expect(\App\Support\Filament\DepartmentNavigationPermissionRegistry::slugsFor(
        \App\Filament\Business\Resources\IndividualQuotes\IndividualQuoteResource::class
    ))->toBe(['cotizador-individual'])
        ->and(\App\Support\Filament\DepartmentNavigationPermissionRegistry::slugsFor(
            \App\Filament\Business\Resources\Affiliations\AffiliationResource::class
        ))->toBe(['afiliaciones-individuales']);
});

it('aplica el trait de navegacion en recursos business clave', function (): void {
    $individualQuote = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/IndividualQuotes/IndividualQuoteResource.php');
    $adminAffiliation = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Affiliations/AffiliationResource.php');

    expect($individualQuote)->toContain('AuthorizesDepartmentNavigation')
        ->and($adminAffiliation)->toContain('AuthorizesDepartmentNavigation');
});

it('concede agenda corporativa y calendarios tdg por defecto a analistas de negocios', function (): void {
    $user = makeNavigationUser(['NEGOCIOS'], [
        'NEGOCIOS' => ['cotizador-individual'],
    ]);

    expect(UserNavigationAccess::canAccessMenuItem($user, 'NEGOCIOS', ['agenda-corporativa']))->toBeTrue()
        ->and(UserNavigationAccess::canAccessMenuItem($user, 'NEGOCIOS', ['calendarios-tdg']))->toBeTrue()
        ->and(UserNavigationAccess::canAccessMenuItem($user, 'NEGOCIOS', ['afiliaciones-individuales']))->toBeFalse();
});

it('expone los permisos por defecto del modulo negocios', function (): void {
    expect(UserNavigationAccess::defaultPermissionSlugsForModule('NEGOCIOS'))
        ->toBe(['agenda-corporativa', 'calendarios-tdg']);
});

it('expone permisos asignables para operaciones marketing y proyectos', function (): void {
    expect(UserFormPermissionOptions::countForModule('MARKETING'))->toBeGreaterThan(0)
        ->and(UserFormPermissionOptions::countForModule('OPERACIONES'))->toBeGreaterThan(0)
        ->and(UserFormPermissionOptions::countForModule('PROYECTOS'))->toBeGreaterThan(0)
        ->and(DepartmentNavigationPermissionRegistry::slugsFor(
            \App\Filament\Marketing\Resources\Affiliations\AffiliationResource::class
        ))->toBe(['afiliaciones-individuales'])
        ->and(DepartmentNavigationPermissionRegistry::slugsFor(
            \App\Filament\Operations\Resources\Affiliates\AffiliateResource::class
        ))->toBe(['afiliados-individuales'])
        ->and(DepartmentNavigationPermissionRegistry::slugsFor(
            \App\Filament\Projects\Resources\ProjectManagement\Projects\ProjectResource::class
        ))->toBe(['proyectos']);
});
