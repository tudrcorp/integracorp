<?php

declare(strict_types=1);

it('registra auditoría para envío de documentos de afiliación individual por correo', function (): void {
    $controllerPath = dirname(__DIR__, 2).'/app/Http/Controllers/AffiliationBusinessDocumentsController.php';
    $contents = file_get_contents($controllerPath);

    expect($contents)
        ->toContain('AUDIT_AFFILIATION_DOCUMENTS_EMAIL_SENT')
        ->and($contents)->toContain('AUDIT_AFFILIATION_DOCUMENTS_EMAIL_FAILED')
        ->and($contents)->toContain('business.affiliation-documents.send-email')
        ->and($contents)->toContain("->cc('afiliaciones@tudrencasa.com')")
        ->and($contents)->toContain("->bcc('solrodriguez@tudrencasa.com')");
});

it('registra auditoría para envío de documentos de afiliación corporativa por correo', function (): void {
    $controllerPath = dirname(__DIR__, 2).'/app/Http/Controllers/AffiliationCorporateBusinessDocumentsController.php';
    $contents = file_get_contents($controllerPath);

    expect($contents)
        ->toContain('AUDIT_AFFILIATION_CORPORATE_DOCUMENTS_EMAIL_SENT')
        ->and($contents)->toContain('AUDIT_AFFILIATION_CORPORATE_DOCUMENTS_EMAIL_FAILED')
        ->and($contents)->toContain('business.affiliation-corporate-documents.send-email')
        ->and($contents)->toContain("->cc('afiliaciones@tudrencasa.com')")
        ->and($contents)->toContain("->bcc('solrodriguez@tudrencasa.com')");
});
