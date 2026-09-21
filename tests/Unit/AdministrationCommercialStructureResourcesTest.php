<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\TravelAgencies\TravelAgencyResource as AdministrationTravelAgencyResource;
use App\Filament\Administration\Resources\WhiteCompanies\WhiteCompanyResource as AdministrationWhiteCompanyResource;
use App\Filament\Business\Resources\TravelAgencies\TravelAgencyResource as BusinessTravelAgencyResource;
use App\Filament\Business\Resources\WhiteCompanies\WhiteCompanyResource as BusinessWhiteCompanyResource;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Support\Filament\AdministrationPanelNavigationGroups;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Support\Filament\InternalPanelDepartmentMap;

uses(Tests\TestCase::class);

it('resuelve los recursos nuevos al modulo ADMINISTRACION y no a NEGOCIOS', function (string $resource): void {
    expect(InternalPanelDepartmentMap::moduleForClass($resource))->toBe('ADMINISTRACION')
        ->and(DepartmentNavigationPermissionRegistry::moduleFor($resource))->toBe('ADMINISTRACION');
})->with([
    'agencias de viaje' => [AdministrationTravelAgencyResource::class],
    'empresas aliadas' => [AdministrationWhiteCompanyResource::class],
]);

it('conserva el modulo NEGOCIOS en los recursos originales', function (string $resource): void {
    expect(DepartmentNavigationPermissionRegistry::moduleFor($resource))->toBe('NEGOCIOS');
})->with([
    'agencias de viaje' => [BusinessTravelAgencyResource::class],
    'empresas aliadas' => [BusinessWhiteCompanyResource::class],
]);

it('registra los slugs de navegacion para que sean asignables desde el usuario', function (string $resource, string $slug): void {
    expect(DepartmentNavigationPermissionRegistry::slugsFor($resource))->toBe([$slug]);
})->with([
    'agencias de viaje' => [AdministrationTravelAgencyResource::class, 'agencias-de-viaje'],
    'empresas aliadas' => [AdministrationWhiteCompanyResource::class, 'empresas-aliadas'],
]);

it('aplica el control de acceso por departamento', function (string $resource): void {
    expect(in_array(AuthorizesDepartmentNavigation::class, class_uses_recursive($resource), true))->toBeTrue()
        ->and(DepartmentNavigationPermissionRegistry::isSuperAdminOnly($resource))->toBeFalse();
})->with([
    'agencias de viaje' => [AdministrationTravelAgencyResource::class],
    'empresas aliadas' => [AdministrationWhiteCompanyResource::class],
]);

it('coloca ambos recursos en ESTRUCTURA COMERCIAL, un grupo que el panel ya define', function (string $resource): void {
    $navigationGroup = (new ReflectionClass($resource))
        ->getProperty('navigationGroup')
        ->getValue();

    expect($navigationGroup)->toBe('ESTRUCTURA COMERCIAL')
        ->and(AdministrationPanelNavigationGroups::labels())->toContain('ESTRUCTURA COMERCIAL');
})->with([
    'agencias de viaje' => [AdministrationTravelAgencyResource::class],
    'empresas aliadas' => [AdministrationWhiteCompanyResource::class],
]);

it('reutiliza formulario y tabla de negocios en lugar de duplicarlos', function (): void {
    $root = dirname(__DIR__, 2);

    $travel = file_get_contents($root.'/app/Filament/Administration/Resources/TravelAgencies/TravelAgencyResource.php');
    $white = file_get_contents($root.'/app/Filament/Administration/Resources/WhiteCompanies/WhiteCompanyResource.php');

    expect($travel)
        ->toContain('use App\Filament\Business\Resources\TravelAgencies\Schemas\TravelAgencyForm;')
        ->toContain('use App\Filament\Business\Resources\TravelAgencies\Tables\TravelAgenciesTable;');

    expect($white)
        ->toContain('use App\Filament\Business\Resources\WhiteCompanies\Schemas\WhiteCompanyForm;')
        ->toContain('use App\Filament\Business\Resources\WhiteCompanies\Tables\WhiteCompaniesTable;')
        ->toContain('AssignedPlansRelationManager::class')
        ->toContain('NegotiatedFeesRelationManager::class');
});

it('no altera la pantalla de permisos: sigue siendo generica por modulo', function (): void {
    $root = dirname(__DIR__, 2);
    $userForm = file_get_contents($root.'/app/Filament/Business/Resources/Users/Schemas/UserForm.php');

    /** La asignación se mantiene en Negocios y se alimenta sola de la tabla permissions. */
    expect($userForm)
        ->toContain('public static function getPermissionAssignableModules(): array')
        ->toContain('self::getDepartamentModules()')
        ->not->toContain('ADMINISTRACION');
});
