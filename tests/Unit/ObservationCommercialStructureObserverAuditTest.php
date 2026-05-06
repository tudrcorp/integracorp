<?php

declare(strict_types=1);

it('registra acciones de auditoría para observaciones comerciales de agencia y agente', function (): void {
    $path = dirname(__DIR__, 2).'/app/Observers/ObservationCommercialStructureObserver.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('AUDIT_BUSINESS_AGENCY_COMMERCIAL_OBSERVATION_CREATED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AGENCY_COMMERCIAL_OBSERVATION_UPDATED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AGENT_COMMERCIAL_OBSERVATION_CREATED')
        ->and($contents)->toContain('AUDIT_BUSINESS_AGENT_COMMERCIAL_OBSERVATION_UPDATED')
        ->and($contents)->toContain('business.agencies.edit')
        ->and($contents)->toContain('business.agents.edit');
});
