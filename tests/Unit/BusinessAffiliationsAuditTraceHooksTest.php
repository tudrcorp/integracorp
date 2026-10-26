<?php

declare(strict_types=1);

it('registra trazas de auditoria en acciones sensibles de afiliaciones business', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Tables/AffiliationsTable.php';
    $contents = file_get_contents($path);

    // Los cambios de frecuencia (fila y masivo) pasan por el servicio de cambio de plan, que audita,
    // deja bitácora e historial dentro de la misma transacción.
    $changer = file_get_contents(dirname(__DIR__, 2).'/app/Support/Affiliations/PlanChange/IndividualAffiliationPlanChanger.php');

    expect(substr_count($contents, '->changeFrequencyMany('))->toBe(2)
        ->and($changer)->toContain("SecurityAudit::log('AUDIT_BUSINESS_AFFILIATION_PLAN_CHANGED'")
        ->and($changer)->toContain('status_log_affiliations()->create(');

    expect($contents)
        ->toContain('SecurityAudit::log')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATION_PAYMENT_UPLOAD')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATION_PAYMENT_UPLOAD_FAILED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATION_CERTIFICATE_DOWNLOADED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATION_CERTIFICATE_DOWNLOAD_FAILED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATION_CARD_DOWNLOADED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATION_CARD_DOWNLOAD_FAILED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATION_DOCUMENTS_REGENERATE_OPENED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATION_DOCUMENTS_REGENERATE_OPEN_FAILED')
        ->and($contents)->toContain('business.affiliations.regenerate-documents')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATION_WELCOME_KIT_DOWNLOADED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATION_WELCOME_KIT_RESENT')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATION_WELCOME_KIT_FAILED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATION_OBSERVATION_ADDED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATION_STATUS_UPDATED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATION_EXCLUDED')
        // El borrado masivo sin validaciones se reemplazó por la eliminación segura (26/10/2026); su
        // auditoría la deja AffiliationDeletionService (AUDIT_AFFILIATION_DELETED).
        ->and($contents)->toContain('AffiliationDeletionActions::deleteBulkAction()')
        ->and($contents)->not->toContain('DeleteBulkAction::make()')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATIONS_BULK_PAYMENT_UPLOAD')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATIONS_BULK_PAYMENT_UPLOAD_FAILED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATIONS_BULK_REASSIGNED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATIONS_BULK_REASSIGN_FAILED');
});
