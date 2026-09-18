<?php

declare(strict_types=1);

use App\Filament\Operations\Pages\Dashboard as OperationsEscritorio;
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
function actingAsOperationsUser(array $departments, array $permissionSlugs = []): User
{
    $user = new User([
        'name' => 'Analista Operaciones',
        'email' => 'analista@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => $departments,
    ]);

    $user->setRelation('permissions', collect($permissionSlugs)->map(
        fn (string $slug): Permission => new Permission(['slug' => $slug, 'module' => 'OPERACIONES', 'name' => $slug])
    ));

    Auth::setUser($user);

    return $user;
}

afterEach(fn () => Auth::forgetUser());

it('muestra el Escritorio al analista con el permiso asignado', function (): void {
    actingAsOperationsUser(['OPERACIONES'], ['escritorio']);

    expect(OperationsEscritorio::canAccess())->toBeTrue()
        ->and(OperationsEscritorio::shouldRegisterNavigation())->toBeTrue();
});

it('oculta el Escritorio al analista de operaciones sin ese permiso', function (): void {
    actingAsOperationsUser(['OPERACIONES'], ['servicios-medicos', 'ordenes-servicios']);

    expect(OperationsEscritorio::canAccess())->toBeFalse()
        ->and(OperationsEscritorio::shouldRegisterNavigation())->toBeFalse();
});

it('oculta el Escritorio al analista de operaciones sin permisos del módulo', function (): void {
    actingAsOperationsUser(['OPERACIONES']);

    expect(OperationsEscritorio::canAccess())->toBeFalse();
});

it('mantiene el Escritorio visible para SUPERADMIN sin asignación explícita', function (): void {
    actingAsOperationsUser(['OPERACIONES', 'SUPERADMIN']);

    expect(OperationsEscritorio::canAccess())->toBeTrue();
});

it('niega el Escritorio a quien no tiene el módulo de operaciones', function (): void {
    actingAsOperationsUser(['NEGOCIOS'], ['escritorio']);

    expect(OperationsEscritorio::canAccess())->toBeFalse();
});

it('niega el Escritorio a usuarios no autenticados', function (): void {
    Auth::forgetUser();

    expect(OperationsEscritorio::canAccess())->toBeFalse();
});

it('registra el Escritorio en el mapa de permisos del módulo OPERACIONES', function (): void {
    expect(DepartmentNavigationPermissionRegistry::slugsFor(OperationsEscritorio::class))->toBe(['escritorio'])
        ->and(DepartmentNavigationPermissionRegistry::moduleFor(OperationsEscritorio::class))->toBe('OPERACIONES')
        ->and(DepartmentNavigationPermissionRegistry::isSuperAdminOnly(OperationsEscritorio::class))->toBeFalse();
});

it('conserva la ruta y la etiqueta del Escritorio', function (): void {
    expect(Route::has('filament.operations.pages.dashboard'))->toBeTrue()
        ->and(Route::getRoutes()->getByName('filament.operations.pages.dashboard')->getAction('controller'))
        ->toContain(OperationsEscritorio::class)
        ->and(OperationsEscritorio::getNavigationLabel())->toBe('Escritorio');
});

it('evita el checkbox duplicado del permiso en el formulario de usuarios', function (): void {
    expect(App\Support\Filament\UserFormPermissionOptions::navToLegacySlugAliases())
        ->toHaveKey('dashboard')
        ->and(App\Support\Filament\UserFormPermissionOptions::navToLegacySlugAliases()['dashboard'])
        ->toBe(['escritorio']);
});

it('redirige al primer módulo disponible en lugar de responder 403', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Pages/Dashboard.php');

    expect($source)
        ->toContain('public function mountCanAuthorizeAccess(): void')
        ->toContain('OperationsPanelHomeFallback::url()')
        ->toContain('$this->redirect($fallbackUrl)');
});
