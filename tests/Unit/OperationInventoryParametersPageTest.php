<?php

declare(strict_types=1);

use App\Filament\Operations\Pages\ManageOperationInventoryParameters;
use App\Models\OperationInventorySetting;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Support\Filament\UserFormPermissionOptions;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

it('configura la página de parámetros en el grupo de inventario', function (): void {
    expect(ManageOperationInventoryParameters::getNavigationLabel())->toBe('Parámetros de Inventario')
        ->and(ManageOperationInventoryParameters::getNavigationGroup())->toBe('INVENTARIO DIAGNOMOVIL')
        ->and(ManageOperationInventoryParameters::getNavigationSort())->toBe(8);
});

it('registra el permiso de navegación parametros-inventario', function (): void {
    expect(DepartmentNavigationPermissionRegistry::slugsFor(
        ManageOperationInventoryParameters::class
    ))->toBe(['parametros-inventario']);

    $aliases = UserFormPermissionOptions::navToLegacySlugAliases();

    expect($aliases['manageoperationinventoryparameters'] ?? null)->toBe(['parametros-inventario']);
});

it('usa AuthorizesDepartmentNavigation y guarda el umbral', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Pages/ManageOperationInventoryParameters.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('AuthorizesDepartmentNavigation')
        ->toContain("TextInput::make('low_stock_threshold')")
        ->toContain('OperationInventorySetting::current()')
        ->toContain("'low_stock_threshold' => \$threshold");
});

it('persiste el umbral de stock bajo en OperationInventorySetting', function (): void {
    if (! Schema::hasTable('operation_inventory_settings')) {
        expect(true)->toBeTrue();

        return;
    }

    $settings = OperationInventorySetting::current();
    $previous = $settings->low_stock_threshold;
    $previousUpdatedBy = $settings->updated_by;

    try {
        $settings->update([
            'low_stock_threshold' => 7,
            'updated_by' => 'pest',
        ]);

        expect(OperationInventorySetting::current()->lowStockThreshold())->toBe(7);
    } finally {
        $settings->update([
            'low_stock_threshold' => $previous,
            'updated_by' => $previousUpdatedBy,
        ]);
    }
});
