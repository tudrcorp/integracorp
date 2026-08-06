<?php

declare(strict_types=1);

it('persiste general_service al crear coordinaciones desde consulta', function (): void {
    $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/OperationCoordinationServiceController.php');

    expect($controller)
        ->toContain("'general_service' => \$generalServiceName")
        ->toContain('resolveGeneralServiceName')
        ->toContain('TelemedicineGeneralService::query()')
        ->toContain('telemedicine_general_service_id');
});

it('copia servicio general al reasignar caso a TDG', function (): void {
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineCases/Tables/TelemedicineCasesTable.php');

    expect($table)
        ->toContain('telemedicineGeneralService:id,name')
        ->toContain("'general_service' => \$generalServiceName");
});

it('muestra servicio general en infolist de coordinación', function (): void {
    $infolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/OperationCoordinationServiceInfolist.php');

    expect($infolist)
        ->toContain("TextEntry::make('general_service')")
        ->toContain("->label('Servicio general')");
});

it('incluye migración de general_service en operation_coordination_services', function (): void {
    $path = dirname(__DIR__, 2).'/database/migrations/2026_08_06_005130_add_general_service_to_operation_coordination_services_table.php';

    expect(file_get_contents($path))
        ->toContain('general_service')
        ->toContain('operation_coordination_services');
});
