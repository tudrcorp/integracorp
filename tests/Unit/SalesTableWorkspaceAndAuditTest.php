<?php

declare(strict_types=1);

it('agrega modal workspace en recibo y trazas de seguridad en acciones de ventas', function (): void {
    $tablePath = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Sales/Tables/SalesTable.php';
    $modalPath = dirname(__DIR__, 2).'/resources/views/filament/administration/sales/modals/sale-workspace-modal.blade.php';

    $tableContents = file_get_contents($tablePath);
    $modalContents = file_get_contents($modalPath);

    expect($tableContents)
        ->toContain('view_sale_workspace')
        ->toContain('sale-workspace-modal')
        ->toContain('extraModalFooterActions')
        ->toContain('AUDIT_ADMIN_SALES_PDF_DOWNLOADED')
        ->toContain('AUDIT_ADMIN_SALES_PDF_REGENERATED')
        ->toContain('AUDIT_ADMIN_SALES_INVOICE_GENERATED')
        ->toContain('AUDIT_ADMIN_SALES_BULK_DELETED')
        ->toContain('AUDIT_ADMIN_SALES_BULK_EXPORTED')
        ->toContain('SecurityAudit::log');

    expect($modalContents)
        ->toContain('Resumen de venta')
        ->toContain('Información principal')
        ->toContain('Acciones principales');
});
