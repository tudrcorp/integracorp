<?php

declare(strict_types=1);

it('mejora la tabla de agencias en administración con grupos y pestañas', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Agencies/Tables/AgenciesTable.php');

    expect($source)
        ->toContain('getTabs')
        ->toContain('ColumnGroup::make')
        ->toContain('recordRowClasses')
        ->toContain('emptyStateIcon')
        ->toContain('deferFilters(false)');
});

it('mejora la tabla de agentes en administración con grupos y pestañas', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Agents/Tables/AgentsTable.php');

    expect($source)
        ->toContain('getTabs')
        ->toContain('ColumnGroup::make')
        ->toContain('commissionColor($record->commission_tdev)')
        ->toContain('hierarchyPrefix');
});

it('conecta pestañas en listados de agencias y agentes', function (): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Agencies/Pages/ListAgencies.php'))
        ->toContain('AgenciesTable::getTabs')
        ->and(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Agents/Pages/ListAgents.php'))
        ->toContain('AgentsTable::getTabs');
});
