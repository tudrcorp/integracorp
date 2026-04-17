<?php

declare(strict_types=1);

it('abre modal de perfil al hacer click en nombre de agente y agencia', function (): void {
    $agentsTablePath = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Agents/Tables/AgentsTable.php';
    $agenciesTablePath = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Agencies/Tables/AgenciesTable.php';
    $agentViewPath = dirname(__DIR__, 2).'/resources/views/filament/administration/agents/agent-quick-profile.blade.php';
    $agencyViewPath = dirname(__DIR__, 2).'/resources/views/filament/administration/agencies/agency-quick-profile.blade.php';

    $agentsTable = file_get_contents($agentsTablePath);
    $agenciesTable = file_get_contents($agenciesTablePath);

    expect(file_exists($agentViewPath))->toBeTrue()
        ->and(file_exists($agencyViewPath))->toBeTrue();

    expect($agentsTable)
        ->toContain("TextColumn::make('name')")
        ->toContain("Action::make('view_agent_profile')")
        ->toContain('modalHeading(\'Perfil del Agente\')')
        ->toContain('modalWidth(\'4xl\')')
        ->toContain('iosStatusPill')
        ->toContain('filament.administration.agents.agent-quick-profile');

    expect($agenciesTable)
        ->toContain("TextColumn::make('name_corporative')")
        ->toContain("Action::make('view_agency_profile')")
        ->toContain('modalHeading(\'Perfil de la Agencia\')')
        ->toContain('modalWidth(\'4xl\')')
        ->toContain('iosStatusPill')
        ->toContain('filament.administration.agencies.agency-quick-profile');
});
