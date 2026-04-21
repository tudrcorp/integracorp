<?php

declare(strict_types=1);

it('registra trazas de auditoria en acciones sensibles de afiliaciones corporativas business', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('SecurityAudit::log')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATION_CORPORATE_PAYMENT_UPLOAD')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATION_CORPORATE_PAYMENT_UPLOAD_FAILED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATION_CORPORATE_OBSERVATION_ADDED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATION_CORPORATE_STATUS_UPDATED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATION_CORPORATE_EXCLUDED');
});
