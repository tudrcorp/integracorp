<?php

declare(strict_types=1);

it('incluye migración de telemedicine_case_id en salidas de inventario', function (): void {
    $path = dirname(__DIR__, 2).'/database/migrations/2026_08_04_193944_add_telemedicine_case_id_to_operation_inventory_outflows_table.php';
    $src = file_get_contents($path);

    expect(is_string($src))->toBeTrue()
        ->and($src)->toContain('telemedicine_case_id')
        ->and($src)->toContain('operation_inventory_outflows')
        ->and($src)->toContain('SALIDA TELEMEDICINA');
});

it('el modelo de salidas relaciona el caso de telemedicina', function (): void {
    $model = file_get_contents(dirname(__DIR__, 2).'/app/Models/OperationInventoryOutflow.php');

    expect($model)
        ->toContain("'telemedicine_case_id'")
        ->toContain('function telemedicineCase')
        ->toContain('TelemedicineCase::class');
});

it('las tablas e infolist de inventario muestran el número de caso', function (): void {
    $movements = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationInventoryMovements/Tables/OperationInventoryMovementsTable.php'
    );
    $outflows = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationInventoryOutflows/Tables/OperationInventoryOutflowsTable.php'
    );
    $infolist = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationInventoryOutflows/Schemas/OperationInventoryOutflowInfolist.php'
    );
    $csv = file_get_contents(
        dirname(__DIR__, 2).'/app/Http/Controllers/OperationInventoryOutflowExportCsvController.php'
    );
    $list = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationInventoryOutflows/Pages/ListOperationInventoryOutflows.php'
    );

    expect($movements)
        ->toContain("TextColumn::make('telemedicineCase.code')")
        ->toContain("->label('Nº caso')")
        ->and($outflows)
        ->toContain("TextColumn::make('telemedicineCase.code')")
        ->toContain("->label('Nº caso')")
        ->and($infolist)
        ->toContain("TextEntry::make('telemedicineCase.code')")
        ->toContain("->label('Nº caso')")
        ->and($csv)
        ->toContain("'Nº caso'")
        ->toContain('telemedicineCase:id,code')
        ->and($list)
        ->toContain("protected static ?string \$title = 'Salidas de Inventario'")
        ->toContain('getSubheading')
        ->toContain('FilamentIosButton::extraClassForFilamentColor');

    $movementsList = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationInventoryMovements/Pages/ListOperationInventoryMovements.php'
    );

    expect($movementsList)
        ->toContain("protected static ?string \$title = 'Movimientos de Inventario'")
        ->toContain('getSubheading')
        ->toContain('FilamentIosButton::extraClassForFilamentColor')
        ->toContain('Volver al inventario');
});
