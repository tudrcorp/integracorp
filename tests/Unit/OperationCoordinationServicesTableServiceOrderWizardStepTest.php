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
        ->and($contents)->toContain('quote_price_usd')
        ->and($contents)->toContain('quote_price_ves')
        ->and($contents)->toContain('quote_bcv_rate')
        ->and($contents)->toContain('quote_profit_percentage')
        ->and($contents)->toContain('quote_total_profit_usd')
        ->and($contents)->toContain('Porcentaje de utilidad (%)')
        ->and($contents)->toContain('Ganancia total')
        ->and($contents)->toContain('ApiBcvController::getTasaBcv()')
        ->and($contents)->toContain('persistGeneratedOrderDocuments')
        ->and($contents)->toContain('service_order_pdf_path')
        ->and($contents)->toContain('associated_quote_pdf_path')
        ->and($contents)->toContain('createServiceOrderFromWizard')
        ->and($contents)->toContain('Cobertura')
        ->and($contents)->toContain('coverageValue')
        ->and($contents)->toContain('coverageLabel')
        ->and($contents)->toContain('OperationServiceOrderController::create')
        ->and($contents)->toContain("->where('status', '!=', 'EN GESTION')");
});

it('muestra primero la cotización y luego la orden de servicio en la modal de negociación', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php';
    $contents = file_get_contents($path);

    $quoteTogglePosition = strpos($contents, "Toggle::make('create_associated_quote')");
    $serviceOrderTogglePosition = strpos($contents, "Toggle::make('create_service_order')");

    expect($quoteTogglePosition)
        ->not->toBeFalse()
        ->and($serviceOrderTogglePosition)->not->toBeFalse()
        ->and($quoteTogglePosition)->toBeLessThan($serviceOrderTogglePosition);
});
