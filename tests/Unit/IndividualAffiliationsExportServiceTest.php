<?php

declare(strict_types=1);

use App\Support\Exports\IndividualAffiliationsExportService;

uses(Tests\TestCase::class);

it('define encabezados combinados de afiliacion y afiliado', function (): void {
    $headers = IndividualAffiliationsExportService::headers();

    expect($headers)
        ->toContain('ID Afiliación')
        ->toContain('Código Afiliación')
        ->toContain('Nombre Afiliado')
        ->toContain('Relación Afiliado')
        ->toContain('Estatus Afiliado')
        ->and(count($headers))->toBe(47);
});

it('expone filtros y descarga para reporte de afiliaciones individuales', function (): void {
    $service = file_get_contents(dirname(__DIR__, 2).'/app/Support/Exports/IndividualAffiliationsExportService.php');
    $reportAction = file_get_contents(dirname(__DIR__, 2).'/app/Support/Exports/IndividualAffiliationsReportExportAction.php');
    $action = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Actions/IndividualAffiliationsExportAction.php');
    $businessTable = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Tables/AffiliationsTable.php');
    $operationsTable = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Affiliates/Tables/AffiliatesTable.php');

    expect($service)
        ->toContain('affiliationQuery')
        ->toContain("where('plan_id'")
        ->toContain("orWhereHas('affiliates'")
        ->toContain("whereHas('affiliates'")
        ->toContain('streamCsv')
        ->toContain('downloadXlsx')
        ->toContain('affiliateStatusOptions');

    expect($reportAction)
        ->toContain('AUDIT_BUSINESS_INDIVIDUAL_AFFILIATIONS_EXPORT')
        ->toContain("Select::make('plan_id')")
        ->toContain("modalSubmitActionLabel('Descargar')")
        ->toContain("modalCancelActionLabel('Cancelar')")
        ->toContain('redirect()->route($auditRoute')
        ->toContain('successNotification(null)');

    expect($action)
        ->toContain('IndividualAffiliationsReportExportAction::make');

    expect($businessTable)
        ->toContain("BulkAction::make('exportAffiliationsCsv')")
        ->toContain('business.affiliations.export-csv')
        ->not->toContain('AffiliationExportIosPresentation::apply');

    expect($operationsTable)
        ->toContain("BulkAction::make('exportAffiliatesCsv')")
        ->toContain('operations.affiliates.export-csv')
        ->toContain('affiliate_ids');

    $administrationTable = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Affiliations/Tables/AffiliationsTable.php');

    expect($administrationTable)
        ->toContain('IndividualAffiliationsReportExportAction::make')
        ->toContain('AUDIT_ADMINISTRATION_INDIVIDUAL_AFFILIATIONS_EXPORT')
        ->toContain('administration.affiliations.export-report');
});

it('expone el job y comando de exportacion de afiliaciones individuales', function (): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Jobs/ExportIndividualAffiliations.php'))
        ->toContain('ReportsScheduledExecution')
        ->toContain('IndividualAffiliationsExportService')
        ->toContain('setDocumentAttachment');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Console/Commands/ExportIndividualAffiliationsCommand.php'))
        ->toContain('export:individual-affiliations')
        ->toContain('--sync');

    expect(file_get_contents(dirname(__DIR__, 2).'/routes/console.php'))
        ->toContain('ExportIndividualAffiliations');
});

it('genera excel cuando existen tablas de afiliaciones', function (): void {
    if (! \Illuminate\Support\Facades\Schema::hasTable('affiliations')) {
        $this->markTestSkipped('La tabla affiliations no está disponible en este entorno de prueba.');
    }

    $result = app(IndividualAffiliationsExportService::class)->create();

    expect($result->filename)->toEndWith('.xlsx')
        ->and($result->bytes)->toBeGreaterThan(0)
        ->and($result->rowCount)->toBeGreaterThanOrEqual(0)
        ->and(\Illuminate\Support\Facades\Storage::disk('public')->exists($result->publicRelativePath))->toBeTrue();

    \Illuminate\Support\Facades\Storage::disk('public')->delete($result->publicRelativePath);
});
