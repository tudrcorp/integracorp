<?php

declare(strict_types=1);

it('registra auditoría en creación y edición de proveedores en operaciones', function (): void {
    $createPagePath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Suppliers/Pages/CreateSupplier.php';
    $editPagePath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Suppliers/Pages/EditSupplier.php';

    $createContents = file_get_contents($createPagePath);
    $editContents = file_get_contents($editPagePath);

    expect($createContents)
        ->toContain('AUDIT_OPERATIONS_SUPPLIER_CREATED')
        ->and($createContents)->toContain('SecurityAudit::log');

    expect($editContents)
        ->toContain('AUDIT_OPERATIONS_SUPPLIER_UPDATED')
        ->and($editContents)->toContain('resolveChangedFields')
        ->and($editContents)->toContain('changed_fields');
});

it('registra auditoría para carga y descarga de documentos de proveedor', function (): void {
    $viewPagePath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Suppliers/Pages/ViewSupplier.php';
    $listPagePath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Suppliers/Pages/ListSuppliers.php';
    $controllerPath = dirname(__DIR__, 2).'/app/Http/Controllers/Operations/SupplierDocumentAuditController.php';
    $fichaControllerPath = dirname(__DIR__, 2).'/app/Http/Controllers/SupplierFichaPdfController.php';
    $routesPath = dirname(__DIR__, 2).'/routes/web.php';

    $viewContents = file_get_contents($viewPagePath);
    $listContents = file_get_contents($listPagePath);
    $controllerContents = file_get_contents($controllerPath);
    $fichaControllerContents = file_get_contents($fichaControllerPath);
    $routesContents = file_get_contents($routesPath);

    expect($viewContents)
        ->toContain('AUDIT_OPERATIONS_SUPPLIER_DOCUMENT_UPLOADED')
        ->and($viewContents)->toContain('operations.suppliers.documents.download')
        ->and($viewContents)->toContain('operations.suppliers.carta-acceptance.preview')
        ->and($viewContents)->toContain('operations.suppliers.carta-acceptance.download');

    expect($controllerContents)
        ->toContain('AUDIT_OPERATIONS_SUPPLIER_DOCUMENT_DOWNLOADED')
        ->and($controllerContents)->toContain('AUDIT_OPERATIONS_SUPPLIER_DOCUMENT_VIEWED')
        ->and($controllerContents)->toContain('downloadAffiliationDocument')
        ->and($controllerContents)->toContain('previewCartaAcceptance')
        ->and($controllerContents)->toContain('downloadCartaAcceptance');

    expect($fichaControllerContents)
        ->toContain('AUDIT_OPERATIONS_SUPPLIER_FICHA_VIEWED')
        ->and($fichaControllerContents)->toContain('AUDIT_OPERATIONS_SUPPLIER_FICHA_DOWNLOADED')
        ->and($fichaControllerContents)->toContain('operations.suppliers.ficha.preview')
        ->and($fichaControllerContents)->toContain('operations.suppliers.ficha.download');

    expect($listContents)
        ->toContain('sendSupplierReportPdf')
        ->and($listContents)->toContain('AUDIT_OPERATIONS_SUPPLIER_REPORT_EMAIL_SENT')
        ->and($listContents)->toContain('AUDIT_OPERATIONS_SUPPLIER_REPORT_EMAIL_FAILED')
        ->and($listContents)->toContain('operations.suppliers.report.send-email');

    expect($routesContents)
        ->toContain('operations.suppliers.ficha.preview')
        ->and($routesContents)->toContain('operations.suppliers.ficha.download')
        ->toContain('operations.suppliers.documents.download')
        ->and($routesContents)->toContain('operations.suppliers.carta-acceptance.preview')
        ->and($routesContents)->toContain('operations.suppliers.carta-acceptance.download');
});
