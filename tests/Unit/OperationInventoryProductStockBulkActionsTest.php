<?php

declare(strict_types=1);

use App\Services\OperationInventoryProductStockAdjuster;

it('expone bulk actions de aumentar y restar existencia en la tabla de productos', function (): void {
    $table = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationInventoryProducts/Tables/OperationInventoryProductsTable.php'
    );
    $actions = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationInventoryProducts/Actions/BulkAdjustProductExistenceActions.php'
    );

    expect($table)
        ->toContain('BulkAdjustProductExistenceActions::increase()')
        ->toContain('BulkAdjustProductExistenceActions::decrease()');

    expect($actions)
        ->toContain("BulkAction::make('increase_product_existence')")
        ->toContain("BulkAction::make('decrease_product_existence')")
        ->toContain("->label('Aumentar existencia')")
        ->toContain("->label('Restar existencia')")
        ->toContain("Textarea::make('note')")
        ->toContain('OperationInventoryProductStockAdjuster');
});

it('registra reposición y ajuste de inventario con tipos canónicos', function (): void {
    expect(OperationInventoryProductStockAdjuster::TYPE_REPOSITION)->toBe('REPOSICIÓN DE INVENTARIO')
        ->and(OperationInventoryProductStockAdjuster::TYPE_ADJUSTMENT)->toBe('AJUSTE DE INVENTARIO');

    $service = file_get_contents(
        dirname(__DIR__, 2).'/app/Services/OperationInventoryProductStockAdjuster.php'
    );

    expect($service)
        ->toContain('OperationInventoryEntry::query()->create')
        ->toContain('OperationInventoryOutflow::query()->create')
        ->toContain("'type_entry' => self::TYPE_REPOSITION")
        ->toContain("'type_entry' => self::TYPE_ADJUSTMENT")
        ->toContain("'observations' => \$note")
        ->toContain('lockForUpdate()');
});

it('exige motivo al restar existencia', function (): void {
    $adjuster = new OperationInventoryProductStockAdjuster;

    expect(fn () => $adjuster->decrease(collect(), 1, 1, '   '))
        ->toThrow(InvalidArgumentException::class, 'Debe indicar el motivo del ajuste de existencia.');
});

it('persiste observations en salidas y lo muestra en la tabla', function (): void {
    $model = file_get_contents(dirname(__DIR__, 2).'/app/Models/OperationInventoryOutflow.php');
    $table = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationInventoryOutflows/Tables/OperationInventoryOutflowsTable.php'
    );
    $migration = collect(glob(
        dirname(__DIR__, 2).'/database/migrations/*add_observations_to_operation_inventory_outflows_table.php'
    ))->first();

    expect($model)->toContain("'observations'")
        ->and($table)->toContain("TextColumn::make('observations')")
        ->and($table)->toContain("'AJUSTE DE INVENTARIO'")
        ->and($migration)->not->toBeNull()
        ->and(file_get_contents($migration))->toContain('observations');
});
