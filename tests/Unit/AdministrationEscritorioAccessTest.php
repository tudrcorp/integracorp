<?php

declare(strict_types=1);

use App\Filament\Administration\Pages\Dashboard as AdministrationEscritorio;
use App\Filament\Administration\Resources\AffiliationCorporatePaymentFrequencyChanges\AffiliationCorporatePaymentFrequencyChangeResource;
use App\Models\Permission;
use App\Models\User;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

uses(Tests\TestCase::class);

/**
 * @param  list<string>  $departments
 * @param  list<string>  $permissionSlugs
 */
function actingAsAdministrationUser(array $departments, array $permissionSlugs = []): User
{
    $user = new User([
        'name' => 'Analista Administración',
        'email' => 'analista@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => $departments,
    ]);

    $user->setRelation('permissions', collect($permissionSlugs)->map(
        fn (string $slug): Permission => new Permission(['slug' => $slug, 'module' => 'ADMINISTRACION', 'name' => $slug])
    ));

    Auth::setUser($user);

    return $user;
}

afterEach(fn () => Auth::forgetUser());

it('muestra el Escritorio al analista con el permiso asignado', function (): void {
    actingAsAdministrationUser(['ADMINISTRACION'], ['escritorio']);

    expect(AdministrationEscritorio::canAccess())->toBeTrue()
        ->and(AdministrationEscritorio::shouldRegisterNavigation())->toBeTrue();
});

it('oculta el Escritorio al analista de administración sin ese permiso', function (): void {
    actingAsAdministrationUser(['ADMINISTRACION'], ['gestion-de-cobranza']);

    expect(AdministrationEscritorio::canAccess())->toBeFalse()
        ->and(AdministrationEscritorio::shouldRegisterNavigation())->toBeFalse();
});

it('oculta el Escritorio al analista de administración sin permisos del módulo', function (): void {
    actingAsAdministrationUser(['ADMINISTRACION']);

    expect(AdministrationEscritorio::canAccess())->toBeFalse();
});

it('mantiene el Escritorio visible para SUPERADMIN sin asignación explícita', function (): void {
    actingAsAdministrationUser(['ADMINISTRACION', 'SUPERADMIN']);

    expect(AdministrationEscritorio::canAccess())->toBeTrue();
});

it('niega el Escritorio a quien no tiene el módulo de administración', function (): void {
    actingAsAdministrationUser(['NEGOCIOS'], ['escritorio']);

    expect(AdministrationEscritorio::canAccess())->toBeFalse();
});

it('niega el Escritorio a usuarios no autenticados', function (): void {
    Auth::forgetUser();

    expect(AdministrationEscritorio::canAccess())->toBeFalse();
});

it('registra el Escritorio en el mapa de permisos del módulo ADMINISTRACION', function (): void {
    expect(DepartmentNavigationPermissionRegistry::slugsFor(AdministrationEscritorio::class))->toBe(['escritorio'])
        ->and(DepartmentNavigationPermissionRegistry::moduleFor(AdministrationEscritorio::class))->toBe('ADMINISTRACION')
        ->and(DepartmentNavigationPermissionRegistry::isSuperAdminOnly(AdministrationEscritorio::class))->toBeFalse();
});

it('conserva la ruta y la etiqueta del Escritorio de administración', function (): void {
    expect(Route::has('filament.administration.pages.dashboard'))->toBeTrue()
        ->and(Route::getRoutes()->getByName('filament.administration.pages.dashboard')->getAction('controller'))
        ->toContain(AdministrationEscritorio::class)
        ->and(AdministrationEscritorio::getNavigationLabel())->toBe('Escritorio');
});

it('muestra los cambios de frecuencia a quien tiene el permiso asignado', function (): void {
    actingAsAdministrationUser(['ADMINISTRACION'], ['cambios-de-frecuencia-de-pago']);

    expect(AffiliationCorporatePaymentFrequencyChangeResource::canAccess())->toBeTrue()
        ->and(AffiliationCorporatePaymentFrequencyChangeResource::shouldRegisterNavigation())->toBeTrue();
});

it('oculta los cambios de frecuencia al analista sin ese permiso', function (): void {
    actingAsAdministrationUser(['ADMINISTRACION'], ['gestion-de-cobranza']);

    expect(AffiliationCorporatePaymentFrequencyChangeResource::canAccess())->toBeFalse()
        ->and(AffiliationCorporatePaymentFrequencyChangeResource::shouldRegisterNavigation())->toBeFalse();
});

it('mantiene los cambios de frecuencia visibles para SUPERADMIN', function (): void {
    actingAsAdministrationUser(['SUPERADMIN']);

    expect(AffiliationCorporatePaymentFrequencyChangeResource::canAccess())->toBeTrue();
});

it('registra los cambios de frecuencia en el mapa de permisos de ADMINISTRACION', function (): void {
    expect(DepartmentNavigationPermissionRegistry::slugsFor(AffiliationCorporatePaymentFrequencyChangeResource::class))
        ->toBe(['cambios-de-frecuencia-de-pago'])
        ->and(DepartmentNavigationPermissionRegistry::moduleFor(AffiliationCorporatePaymentFrequencyChangeResource::class))
        ->toBe('ADMINISTRACION');
});

it('redirige al primer módulo disponible en lugar de responder 403', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Pages/Dashboard.php');

    expect($source)
        ->toContain('public function mountCanAuthorizeAccess(): void')
        ->toContain('AdministrationPanelHomeFallback::url()')
        ->toContain('$this->redirect($fallbackUrl)');
});
