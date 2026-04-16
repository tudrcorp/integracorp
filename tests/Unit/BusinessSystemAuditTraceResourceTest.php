<?php

declare(strict_types=1);

it('expone recurso de trazas de seguridad en panel business', function (): void {
    $resourcePath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/SystemAuditTraces/SystemAuditTraceResource.php';
    $tablePath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/SystemAuditTraces/Tables/SystemAuditTracesTable.php';
    $viewPath = dirname(__DIR__, 2).'/resources/views/filament/business/system-audit-traces/trace-details.blade.php';

    expect(file_exists($resourcePath))->toBeTrue()
        ->and(file_exists($tablePath))->toBeTrue()
        ->and(file_exists($viewPath))->toBeTrue();

    $resourceContents = file_get_contents($resourcePath);
    $tableContents = file_get_contents($tablePath);

    expect($resourceContents)
        ->toContain("protected static ?string \$navigationLabel = 'Trazas de Seguridad';")
        ->toContain("protected static string|UnitEnum|null \$navigationGroup = 'CONFIGURACIÓN';")
        ->toContain("->where('action', 'like', 'AUDIT_%')")
        ->toContain("->orWhere('action', 'like', 'TDEV_COMPENSACION_%')");

    expect($tableContents)
        ->toContain("->heading('Trazabilidad de Seguridad')")
        ->toContain("Action::make('view_trace')")
        ->toContain("SelectFilter::make('category')")
        ->toContain("'Registro de ventas'");
});
