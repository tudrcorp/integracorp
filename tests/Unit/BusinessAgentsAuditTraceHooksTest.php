<?php

declare(strict_types=1);

it('registra auditoría en acciones de tabla de agentes en business', function (): void {
    $tablePath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agents/Tables/AgentsTable.php';
    $contents = file_get_contents($tablePath);

    expect($contents)
        ->toContain('AUDIT_BUSINESS_AGENT_ACTIVATED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AGENT_ACTIVATE_FAILED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AGENT_WELCOME_EMAIL_SENT')
        ->and($contents)->toContain('AUDIT_BUSINESS_AGENT_WELCOME_EMAIL_FAILED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AGENT_HIERARCHY_UPDATED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AGENT_HIERARCHY_UPDATE_FAILED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AGENT_INACTIVATED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AGENT_INACTIVATE_FAILED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AGENT_DELETED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AGENT_DELETE_FAILED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AGENTS_ACCOUNT_MANAGER_ASSIGNED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AGENTS_ACCOUNT_MANAGER_ASSIGN_FAILED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AGENTS_BULK_DELETED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AGENTS_BULK_DELETE_FAILED');
});

it('registra auditoría en creación y edición de agentes en business', function (): void {
    $createPath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agents/Pages/CreateAgent.php';
    $editPath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agents/Pages/EditAgent.php';

    $createContents = file_get_contents($createPath);
    $editContents = file_get_contents($editPath);

    expect($createContents)
        ->toContain('AUDIT_BUSINESS_AGENT_CREATED')
        ->and($createContents)->toContain('AUDIT_BUSINESS_AGENT_CREATE_FAILED')
        ->and($createContents)->toContain('business.agents.create');

    expect($editContents)
        ->toContain('AUDIT_BUSINESS_AGENT_UPDATED')
        ->and($editContents)->toContain('business.agents.edit')
        ->and($editContents)->toContain('changed_fields');
});
