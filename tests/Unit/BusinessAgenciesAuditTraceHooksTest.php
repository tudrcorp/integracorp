<?php

declare(strict_types=1);

it('registra auditoría en acciones de tabla de agencias en business', function (): void {
    $tablePath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agencies/Tables/AgenciesTable.php';
    $contents = file_get_contents($tablePath);

    expect($contents)
        ->toContain('AUDIT_BUSINESS_AGENCY_ACTIVATED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AGENCY_ACTIVATE_FAILED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AGENCY_HIERARCHY_UPDATED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AGENCY_HIERARCHY_UPDATE_FAILED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AGENCY_INACTIVATED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AGENCY_INACTIVATE_FAILED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AGENCY_DELETED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AGENCY_DELETE_FAILED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AGENCY_WELCOME_LETTER_RESENT')
        ->and($contents)->toContain('AUDIT_BUSINESS_AGENCY_WELCOME_LETTER_RESEND_FAILED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AGENCIES_ACCOUNT_MANAGER_ASSIGNED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AGENCIES_ACCOUNT_MANAGER_ASSIGN_FAILED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AGENCIES_BULK_DELETED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AGENCIES_BULK_DELETE_FAILED');
});

it('registra auditoría en creación y edición de agencias en business', function (): void {
    $createPath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agencies/Pages/CreateAgency.php';
    $editPath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agencies/Pages/EditAgency.php';

    $createContents = file_get_contents($createPath);
    $editContents = file_get_contents($editPath);

    expect($createContents)
        ->toContain('AUDIT_BUSINESS_AGENCY_CREATED')
        ->and($createContents)->toContain('AUDIT_BUSINESS_AGENCY_CREATE_FAILED')
        ->and($createContents)->toContain('business.agencies.create');

    expect($editContents)
        ->toContain('AUDIT_BUSINESS_AGENCY_UPDATED')
        ->and($editContents)->toContain('business.agencies.edit')
        ->and($editContents)->toContain('changed_fields');
});

it('registra auditoría en envío de link de registro de agencias', function (): void {
    $listPath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agencies/Pages/ListAgencies.php';
    $contents = file_get_contents($listPath);

    expect($contents)
        ->toContain('AUDIT_BUSINESS_AGENCY_REGISTER_LINK_EMAIL_SENT')
        ->and($contents)->toContain('AUDIT_BUSINESS_AGENCY_REGISTER_LINK_EMAIL_FAILED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AGENCY_REGISTER_LINK_WHATSAPP_SENT')
        ->and($contents)->toContain('AUDIT_BUSINESS_AGENCY_REGISTER_LINK_WHATSAPP_FAILED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AGENCY_REGISTER_LINK_SEND_FAILED')
        ->and($contents)->toContain('business.agencies.send-register-link');
});
