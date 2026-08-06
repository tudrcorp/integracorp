<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\TelemedicineGeneralServices\Actions\DeleteTelemedicineGeneralServiceAction;
use App\Filament\Operations\Resources\TelemedicineGeneralServices\TelemedicineGeneralServiceResource;
use App\Models\TelemedicineGeneralService;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Support\Filament\UserFormPermissionOptions;

it('registra el recurso de servicios consulta general en permisos de operaciones', function (): void {
    expect(DepartmentNavigationPermissionRegistry::slugsFor(TelemedicineGeneralServiceResource::class))
        ->toBe(['servicios-consulta-general'])
        ->and(UserFormPermissionOptions::navToLegacySlugAliases()['telemedicinegeneralserviceresource'] ?? null)
        ->toBe(['servicios-consulta-general']);
});

it('exige motivo y registra auditoría al eliminar un servicio general', function (): void {
    $actionPath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineGeneralServices/Actions/DeleteTelemedicineGeneralServiceAction.php';
    $contents = file_get_contents($actionPath);

    expect($contents)
        ->toContain('deletion_reason')
        ->toContain('SecurityAudit::log')
        ->toContain('AUDIT_OPERATIONS_TELEMEDICINE_GENERAL_SERVICE_DELETED')
        ->toContain('operations.telemedicine-general-services.delete')
        ->toContain('minLength(10)');
});

it('expone el catálogo inicial de servicios generales en la migración', function (): void {
    $migrationPath = dirname(__DIR__, 2).'/database/migrations/2026_08_06_003534_create_telemedicine_general_services_table.php';
    $contents = file_get_contents($migrationPath);

    expect($contents)
        ->toContain('NEBULIZACIÓN')
        ->toContain('OXIGENOTERAPIA')
        ->toContain('SUTURA DE HERIDA')
        ->toContain('CURA DE HERIDA')
        ->toContain('ADMINISTRACION DE TRATAMIENTO')
        ->toContain('LAVADO OTICO-OCULAR')
        ->toContain('INMOBILIZACION')
        ->toContain('telemedicine_general_services');
});

it('define el modelo de servicio general con scope activo', function (): void {
    expect(class_exists(TelemedicineGeneralService::class))->toBeTrue()
        ->and(method_exists(TelemedicineGeneralService::class, 'scopeActive'))->toBeTrue()
        ->and((new TelemedicineGeneralService)->getFillable())
        ->toContain('name')
        ->toContain('status');
});

it('usa delete action con motivo en tabla y edición', function (): void {
    $tablePath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineGeneralServices/Tables/TelemedicineGeneralServicesTable.php';
    $editPath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineGeneralServices/Pages/EditTelemedicineGeneralService.php';

    expect(file_get_contents($tablePath))
        ->toContain('DeleteTelemedicineGeneralServiceAction::make()')
        ->and(file_get_contents($editPath))
        ->toContain('DeleteTelemedicineGeneralServiceAction::make()')
        ->and(class_exists(DeleteTelemedicineGeneralServiceAction::class))->toBeTrue();
});
