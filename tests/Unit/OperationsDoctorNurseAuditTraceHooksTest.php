<?php

declare(strict_types=1);

it('registra auditoría en edición de proveedores naturales en operaciones', function (): void {
    $editPagePath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/DoctorNurses/Pages/EditDoctorNurse.php';

    $editContents = file_get_contents($editPagePath);

    expect($editContents)
        ->toContain('AUDIT_OPERATIONS_DOCTOR_NURSE_UPDATED')
        ->and($editContents)->toContain('SecurityAudit::log')
        ->and($editContents)->toContain('resolveChangedFields')
        ->and($editContents)->toContain('changed_fields');
});

it('registra auditoría para carga de documentos de proveedor natural', function (): void {
    $viewPagePath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/DoctorNurses/Pages/ViewDoctorNurse.php';

    $viewContents = file_get_contents($viewPagePath);

    expect($viewContents)
        ->toContain('AUDIT_OPERATIONS_DOCTOR_NURSE_DOCUMENT_UPLOADED')
        ->and($viewContents)->toContain('operations.doctor-nurses.carta-acceptance.upload')
        ->and($viewContents)->toContain('operations.doctor-nurses.documents.upload');
});
