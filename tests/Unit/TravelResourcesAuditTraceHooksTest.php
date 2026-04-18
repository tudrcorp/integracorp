<?php

declare(strict_types=1);

it('registra hooks de auditoría para agencias y agentes de viaje', function (): void {
    $providerPath = dirname(__DIR__, 2).'/app/Providers/AppServiceProvider.php';
    $contents = file_get_contents($providerPath);

    expect($contents)
        ->toContain('registerTravelResourcesSecurityAudits')
        ->and($contents)->toContain('TravelAgency::created')
        ->and($contents)->toContain('TravelAgency::updated')
        ->and($contents)->toContain('TravelAgency::deleted')
        ->and($contents)->toContain('TravelAgent::created')
        ->and($contents)->toContain('TravelAgent::updated')
        ->and($contents)->toContain('TravelAgent::deleted')
        ->and($contents)->toContain("travelAction('TRAVEL_AGENCY_CREATED')")
        ->and($contents)->toContain("travelAction('TRAVEL_AGENT_CREATED')")
        ->and($contents)->toContain("'BUSINESS', 'MARKETING'");
});

it('expone filtros de viajes en trazas de seguridad', function (): void {
    $tablePath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/SystemAuditTraces/Tables/SystemAuditTracesTable.php';
    $contents = file_get_contents($tablePath);

    expect($contents)
        ->toContain("'travel' => 'Viajes (Agencias y Agentes)'")
        ->and($contents)->toContain('AUDIT_%_TRAVEL_AGENCY_%')
        ->and($contents)->toContain('AUDIT_%_TRAVEL_AGENT_%')
        ->and($contents)->toContain("'marketing' => 'Marketing'")
        ->and($contents)->toContain("str_starts_with(\$action, 'AUDIT_MARKETING_')");
});
