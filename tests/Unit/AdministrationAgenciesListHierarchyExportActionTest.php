<?php

declare(strict_types=1);

it('expone la exportación de comisiones por jerarquía en header actions de listado de agencias', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Agencies/Pages/ListAgencies.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain("Action::make('export_commission_hierarchy')")
        ->toContain('REPORT_COMMISSION_HIERARCHY')
        ->toContain('AdministrationAgencyReportsExportService::toCsv')
        ->toContain('registerModalActions')
        ->toContain('agencyReportCsvModalActions');
});

it('el modal de reportes de agencias dispara descargas csv con acciones filament', function (): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Agencies/Pages/ListAgencies.php');
    $modal = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/administration/agencies/agency-reports-export-modal.blade.php');

    expect($page)
        ->toContain('->registerModalActions($this->agencyReportCsvModalActions())')
        ->toContain("'csvAction' => 'download_agency_report_csv_'");

    expect($modal)
        ->toContain('getModalAction($report[\'csvAction\'])')
        ->toContain('Descarga inmediata en CSV');
});
