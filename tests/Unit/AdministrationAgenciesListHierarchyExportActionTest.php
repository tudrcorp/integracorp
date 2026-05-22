<?php

declare(strict_types=1);

it('expone la exportación de comisiones por jerarquía en header actions de listado de agencias', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Agencies/Pages/ListAgencies.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain("Action::make('export_commission_hierarchy')")
        ->toContain('REPORT_COMMISSION_HIERARCHY')
        ->toContain('AdministrationAgencyReportsExportService::toCsv');
});
