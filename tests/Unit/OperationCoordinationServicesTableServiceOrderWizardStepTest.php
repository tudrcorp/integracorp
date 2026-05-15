<?php

declare(strict_types=1);

it('integra la creación de orden de servicio dentro del wizard de negociación y precios', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("Step::make('Orden de Servicio')")
        ->and($contents)->toContain('service_order_item_ids')
        ->and($contents)->toContain('create_service_order')
        ->and($contents)->toContain('create_associated_quote')
        ->and($contents)->toContain('Crear cotización asociada')
        ->and($contents)->toContain('createServiceOrderFromWizard')
        ->and($contents)->toContain('Cobertura')
        ->and($contents)->toContain('coverageValue')
        ->and($contents)->toContain('coverageLabel')
        ->and($contents)->toContain('OperationServiceOrderController::create')
        ->and($contents)->toContain("->where('status', '!=', 'EN GESTION')");
});
