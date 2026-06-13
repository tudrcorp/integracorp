<?php

declare(strict_types=1);

it('OperationCoordinationServicesTable define acción gestionar cotización con estatus y orden automática', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("Action::make('manage_service_quote')")
        ->toContain('Gestionar Cotización')
        ->toContain('ManageCoordinationServiceQuotes::getUrl')
        ->toContain('CoordinationServiceQuoteManager::coordinationQuotes');
});

it('OperationQuoteGenerator define estatus por defecto pendiente por aprobar', function (): void {
    $model = file_get_contents(dirname(__DIR__, 2).'/app/Models/OperationQuoteGenerator.php');
    $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_05_18_235213_add_status_to_operation_quote_generators_table.php');

    expect($model)
        ->toContain("public const STATUS_PENDING = 'PENDIENTE POR APROBAR'")
        ->toContain("public const STATUS_APPROVED = 'APROBADA'")
        ->and($migration)->toContain("->default('PENDIENTE POR APROBAR')");
});
