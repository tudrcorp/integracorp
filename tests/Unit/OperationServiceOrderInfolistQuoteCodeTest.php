<?php

declare(strict_types=1);

it('OperationServiceOrderInfolist muestra el código de cotización origen', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Schemas/OperationServiceOrderInfolist.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("TextEntry::make('approvedOperationQuote.id')")
        ->toContain('Código cotización origen')
        ->toContain('COT-');
});
