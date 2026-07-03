<?php

declare(strict_types=1);

use App\Support\Exports\CorporateAffiliationsExportService;

uses(Tests\TestCase::class);

it('define encabezados combinados de afiliacion corporativa plan y afiliado', function (): void {
    $headers = CorporateAffiliationsExportService::headers();

    expect($headers)
        ->toContain('ID Afiliación Corporativa')
        ->toContain('Plan Contrato')
        ->toContain('ID Afiliado Corporativo')
        ->toContain('Nombres Afiliado')
        ->toContain('Estatus Afiliado')
        ->and(count($headers))->toBe(64);
});

it('expone filtros y descarga para reporte de afiliaciones corporativas', function (): void {
    $service = file_get_contents(dirname(__DIR__, 2).'/app/Support/Exports/CorporateAffiliationsExportService.php');
    $action = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Actions/CorporateAffiliationsExportAction.php');
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php');

    expect($service)
        ->toContain('affiliationQuery')
        ->toContain("whereHas('affiliationCorporatePlans'")
        ->toContain("whereHas('corporateAffiliates'")
        ->toContain('streamCsv')
        ->toContain('downloadXlsx')
        ->toContain('affiliateStatusOptions');

    expect($action)
        ->toContain('CorporateAffiliationsReportExportAction::make');

    expect($table)
        ->toContain('CorporateAffiliationsExportAction::make')
        ->toContain('AffiliationExportIosPresentation::apply');
});

it('tablas de operaciones exponen exportacion de afiliados', function (): void {
    $affiliatesTable = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Affiliates/Tables/AffiliatesTable.php');
    $corporateTable = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/AffiliateCorporates/Tables/AffiliateCorporatesTable.php');

    expect($affiliatesTable)
        ->toContain('IndividualAffiliationsReportExportAction::make')
        ->toContain('AffiliationExportIosPresentation::apply')
        ->toContain('AUDIT_OPERATIONS_AFFILIATES_EXPORT')
        ->toContain('operations.affiliates.export-report');

    expect($corporateTable)
        ->toContain('CorporateAffiliationsReportExportAction::make')
        ->toContain('AffiliationExportIosPresentation::apply')
        ->toContain('AUDIT_OPERATIONS_AFFILIATE_CORPORATES_EXPORT')
        ->toContain('operations.affiliate-corporates.export-report');

    $administrationTable = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php');

    expect($administrationTable)
        ->toContain('CorporateAffiliationsReportExportAction::make')
        ->toContain('AffiliationExportIosPresentation::apply')
        ->toContain('AUDIT_ADMINISTRATION_CORPORATE_AFFILIATIONS_EXPORT')
        ->toContain('administration.affiliation-corporates.export-report');
});

it('expone el job y comando de exportacion de afiliaciones corporativas', function (): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Jobs/ExportCorporateAffiliations.php'))
        ->toContain('ReportsScheduledExecution')
        ->toContain('CorporateAffiliationsExportService')
        ->toContain('setDocumentAttachment');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Console/Commands/ExportCorporateAffiliationsCommand.php'))
        ->toContain('export:corporate-affiliations')
        ->toContain('--sync');

    expect(file_get_contents(dirname(__DIR__, 2).'/routes/console.php'))
        ->toContain('ExportCorporateAffiliations');
});

it('genera excel cuando existen tablas de afiliaciones corporativas', function (): void {
    if (! \Illuminate\Support\Facades\Schema::hasTable('affiliation_corporates')) {
        $this->markTestSkipped('La tabla affiliation_corporates no está disponible en este entorno de prueba.');
    }

    $result = app(CorporateAffiliationsExportService::class)->create();

    expect($result->filename)->toEndWith('.xlsx')
        ->and($result->bytes)->toBeGreaterThan(0)
        ->and($result->rowCount)->toBeGreaterThanOrEqual(0)
        ->and(\Illuminate\Support\Facades\Storage::disk('public')->exists($result->publicRelativePath))->toBeTrue();

    \Illuminate\Support\Facades\Storage::disk('public')->delete($result->publicRelativePath);
});
