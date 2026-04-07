<?php

declare(strict_types=1);

it('no oculta columnas por defecto en las tablas de agentes y agencias (business)', function (): void {
    $agentsPath = __DIR__.'/../../app/Filament/Business/Resources/Agents/Tables/AgentsTable.php';
    $agenciesPath = __DIR__.'/../../app/Filament/Business/Resources/Agencies/Tables/AgenciesTable.php';

    $agents = file_get_contents($agentsPath);
    $agencies = file_get_contents($agenciesPath);

    expect($agents)->not->toBeFalse()->not->toContain('isToggledHiddenByDefault: true');
    expect($agencies)->not->toBeFalse()->not->toContain('isToggledHiddenByDefault: true');
});
