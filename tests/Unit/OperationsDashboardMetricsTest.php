<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Operations\OperationsDashboardMetrics;

it('identifica usuarios de proveedor con acceso al panel de operaciones', function (): void {
    $supplierUser = new User([
        'status' => 'ACTIVO',
        'supplier_id' => 15,
        'departament' => ['OPERACIONES'],
        'is_proveedor_amd' => false,
    ]);

    $amdUser = new User([
        'status' => 'ACTIVO',
        'supplier_id' => 20,
        'departament' => [],
        'is_proveedor_amd' => true,
    ]);

    $invalidUser = new User([
        'status' => 'ACTIVO',
        'supplier_id' => 21,
        'departament' => ['NEGOCIOS'],
        'is_proveedor_amd' => false,
    ]);

    expect(OperationsDashboardMetrics::userHasOperationsPortalAccess($supplierUser))->toBeTrue()
        ->and(OperationsDashboardMetrics::userHasOperationsPortalAccess($amdUser))->toBeTrue()
        ->and(OperationsDashboardMetrics::userHasOperationsPortalAccess($invalidUser))->toBeFalse();
});

it('expone consultas de métricas con scope de proveedor', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/OperationsDashboardMetrics.php');

    expect($contents)
        ->toContain('OperationsSupplierScope::applyToQuery(TelemedicinePatient::query())')
        ->toContain('OperationsSupplierScope::applyToQuery(TelemedicineCase::query())')
        ->toContain('OperationsSupplierScope::coordinationServiceQuery()')
        ->toContain("->where('status', 'ALTA MEDICA')")
        ->toContain("->where('status', 'EN SEGUIMIENTO')")
        ->toContain('whereNotNull(\'afilliation_id\')')
        ->toContain('orWhereNotNull(\'afilliation_corporate_id\')');
});
