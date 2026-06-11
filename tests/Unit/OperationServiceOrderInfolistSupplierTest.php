<?php

declare(strict_types=1);

it('OperationServiceOrderInfolist muestra proveedor y dirección en el tab Resumen', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Schemas/OperationServiceOrderInfolist.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("Fieldset::make('Proveedor')")
        ->toContain("TextEntry::make('supplier_summary')")
        ->toContain("->label('Proveedor')")
        ->toContain("TextEntry::make('supplier_address_summary')")
        ->toContain("->label('Dirección')")
        ->toContain('resolveSupplierName')
        ->toContain('resolveSupplierAddress')
        ->toContain('approvedOperationQuote?->supplier_address');
});

it('OperationServiceOrderInfolist resalta la vigencia con un bloque visual', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Schemas/OperationServiceOrderInfolist.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("TextEntry::make('validity_highlight')")
        ->toContain('renderValidityHighlight')
        ->toContain('shouldHighlightVigencia')
        ->toContain('Vigencia de la orden');
});
