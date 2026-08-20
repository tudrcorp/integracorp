<?php

declare(strict_types=1);

use App\Filament\Business\Resources\WhiteCompanies\RelationManagers\NegotiatedFeesRelationManager;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\PermissionNavigationGroupResolver;

uses(Tests\TestCase::class);

it('habilita la matriz de negociacion en negocios y en administracion', function (): void {
    $slug = BusinessFilamentActionPermissionRegistry::MANAGE_WHITE_COMPANY_NEGOTIATED_FEES;

    expect(BusinessFilamentActionPermissionRegistry::modulesForSlug($slug))
        ->toBe(['NEGOCIOS', 'ADMINISTRACION'])
        ->and(BusinessFilamentActionPermissionRegistry::slugIsAvailableInModule($slug, 'ADMINISTRACION'))->toBeTrue()
        ->and(BusinessFilamentActionPermissionRegistry::slugIsAvailableInModule($slug, 'NEGOCIOS'))->toBeTrue();
});

it('mantiene las demas acciones solo en negocios', function (string $slug): void {
    expect(BusinessFilamentActionPermissionRegistry::modulesForSlug($slug))->toBe(['NEGOCIOS'])
        ->and(BusinessFilamentActionPermissionRegistry::slugIsAvailableInModule($slug, 'ADMINISTRACION'))->toBeFalse();
})->with([
    'crear afiliado corporativo' => [BusinessFilamentActionPermissionRegistry::CREATE_CORPORATE_AFFILIATE],
    'documentos de marca' => [BusinessFilamentActionPermissionRegistry::MANAGE_WHITE_COMPANY_DOCUMENT_BRAND],
    'asignar plan' => [BusinessFilamentActionPermissionRegistry::ASSIGN_WHITE_COMPANY_PLAN],
]);

it('agrupa la matriz en ESTRUCTURA COMERCIAL en ambos modulos', function (string $module): void {
    $permission = new App\Models\Permission([
        'slug' => BusinessFilamentActionPermissionRegistry::MANAGE_WHITE_COMPANY_NEGOTIATED_FEES,
        'module' => $module,
        'name' => 'Matriz de negociación',
    ]);

    expect(PermissionNavigationGroupResolver::groupForPermission($permission))->toBe('ESTRUCTURA COMERCIAL');
})->with(['NEGOCIOS', 'ADMINISTRACION']);

it('no agrupa una accion en un modulo donde no esta habilitada', function (): void {
    $permission = new App\Models\Permission([
        'slug' => BusinessFilamentActionPermissionRegistry::ASSIGN_WHITE_COMPANY_PLAN,
        'module' => 'ADMINISTRACION',
        'name' => 'Asignar plan a empresa aliada',
    ]);

    expect(PermissionNavigationGroupResolver::groupForPermission($permission))->toBe('Otros');
});

it('el relation manager sigue protegido por el permiso de la matriz', function (): void {
    $source = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Business/Resources/WhiteCompanies/RelationManagers/NegotiatedFeesRelationManager.php'
    );

    expect(method_exists(NegotiatedFeesRelationManager::class, 'canViewForRecord'))->toBeTrue()
        ->and($source)->toContain('BusinessFilamentActionAccess::userCan(')
        ->and($source)->toContain('BusinessFilamentActionPermissionRegistry::MANAGE_WHITE_COMPANY_NEGOTIATED_FEES');
});

it('evalua la accion contra el modulo del panel activo con respaldo en negocios', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/BusinessFilamentActionAccess.php');

    expect($source)
        ->toContain('Filament::getCurrentPanel()?->getId()')
        ->toContain('InternalPanelDepartmentMap::moduleForPanel($panelId)')
        ->toContain('BusinessFilamentActionPermissionRegistry::slugIsAvailableInModule($actionSlug, $module)')
        ->toContain('BusinessFilamentActionPermissionRegistry::OWNER_MODULE');
});

it('el comando sincroniza la accion en cada modulo habilitado', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Console/Commands/SyncFilamentNavigationPermissionsCommand.php');

    expect($source)
        ->toContain("foreach (\$definition['modules'] as \$module) {")
        ->toContain("'module' => \$module,")
        ->not->toContain("'module' => 'NEGOCIOS',");
});
