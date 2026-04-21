<?php

declare(strict_types=1);

it('registra trazas de auditoria en acciones sensibles de afiliaciones business', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Tables/AffiliationsTable.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('SecurityAudit::log')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATION_PAYMENT_UPLOAD')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATION_PAYMENT_UPLOAD_FAILED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATION_CERTIFICATE_DOWNLOADED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATION_CERTIFICATE_DOWNLOAD_FAILED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATION_CARD_DOWNLOADED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATION_CARD_DOWNLOAD_FAILED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATION_WELCOME_KIT_DOWNLOADED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATION_WELCOME_KIT_RESENT')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATION_WELCOME_KIT_FAILED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATION_PAYMENT_FREQUENCY_UPDATED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATION_PAYMENT_FREQUENCY_UPDATE_FAILED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATION_OBSERVATION_ADDED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATION_STATUS_UPDATED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATION_EXCLUDED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATIONS_BULK_DELETED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATIONS_BULK_DELETE_FAILED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATIONS_BULK_PAYMENT_UPLOAD')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATIONS_BULK_PAYMENT_UPLOAD_FAILED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATIONS_BULK_FREQUENCY_UPDATED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATIONS_BULK_FREQUENCY_UPDATE_FAILED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATIONS_BULK_REASSIGNED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATIONS_BULK_REASSIGN_FAILED');
});
