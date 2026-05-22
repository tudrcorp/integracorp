<?php

declare(strict_types=1);

it('expone la exportación de comisiones por jerarquía en header actions de la vista listado', function (): void {
    $listPath = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Agents/Pages/ListAgents.php';
    $tablePath = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Agents/Tables/AgentsTable.php';

    $listSource = file_get_contents($listPath);
    $tableSource = file_get_contents($tablePath);

    expect($listSource)
        ->toContain("Action::make('export_commission_hierarchy')")
        ->toContain('REPORT_COMMISSION_HIERARCHY')
        ->toContain('AdministrationAgentReportsExportService::toCsv');

    expect($tableSource)->not->toContain("Action::make('export_commission_hierarchy')");
});
