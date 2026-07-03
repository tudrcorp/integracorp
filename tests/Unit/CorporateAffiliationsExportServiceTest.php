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

it('expone exportacion csv directa en listado de afiliaciones corporativas', function (): void {
    $listPage = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Pages/ListAffiliationCorporates.php');
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php');

    expect($listPage)
        ->not->toContain('getHeaderActions')
        ->not->toContain('AffiliateCorporateCsvExportAction::make');

    expect($table)
        ->toContain("BulkAction::make('exportAffiliationCorporatesCsv')")
        ->toContain('Exportar Afiliaciones')
        ->toContain('AffiliationCorporateExportCsvController::storeFiltersAndGetToken')
        ->toContain('business.affiliation-corporates.export-csv')
        ->toContain("BulkAction::make('exportAffiliateCorporatesCsv')")
        ->toContain('storeFiltersAndGetToken')
        ->toContain('affiliation_corporate_ids')
        ->toContain('redirect()->route')
        ->toContain('business.affiliate-corporates.export-csv')
        ->not->toContain('CorporateAffiliationsExportAction::make')
        ->not->toContain('AffiliationExportIosPresentation::apply');
});

it('tablas de operaciones exponen exportacion de afiliados', function (): void {
    $affiliatesTable = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Affiliates/Tables/AffiliatesTable.php');
    $corporateTable = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/AffiliateCorporates/Tables/AffiliateCorporatesTable.php');

    expect($affiliatesTable)
        ->toContain("BulkAction::make('exportAffiliatesCsv')")
        ->toContain('Exportar CSV')
        ->toContain('AffiliateExportCsvController::storeFiltersAndGetToken')
        ->toContain('affiliate_ids')
        ->toContain('operations.affiliates.export-csv');

    expect($corporateTable)
        ->toContain("BulkAction::make('exportAffiliateCorporatesCsv')")
        ->toContain('Exportar CSV')
        ->toContain('affiliate_corporate_ids')
        ->toContain('operations.affiliate-corporates.export-csv');

    $administrationTable = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php');

    expect($administrationTable)
        ->toContain('exportAffiliationCorporatesCsv')
        ->toContain('exportAffiliateCorporatesCsv')
        ->toContain('administration.affiliation-corporates.export-csv')
        ->toContain('administration.affiliate-corporates.export-csv')
        ->not->toContain('AffiliateCorporateCsvExportAction::make');
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

it('expone rutas de descarga para reportes de afiliaciones corporativas', function (): void {
    expect(route('business.affiliation-corporates.export-report'))->toBeString()
        ->and(route('administration.affiliation-corporates.export-report'))->toBeString()
        ->and(route('operations.affiliate-corporates.export-report'))->toBeString();
});

it('descarga reporte corporativo mediante controlador con parametros de consulta', function (): void {
    if (! \Illuminate\Support\Facades\Schema::hasTable('affiliation_corporates')) {
        $this->markTestSkipped('La tabla affiliation_corporates no está disponible en este entorno de prueba.');
    }

    $request = \Illuminate\Http\Request::create('/business/export-affiliation-corporates-report', 'GET', [
        'format' => 'xlsx',
    ]);

    $route = new \Illuminate\Routing\Route('GET', 'business/export-affiliation-corporates-report', []);
    $route->name('business.affiliation-corporates.export-report');
    $request->setRouteResolver(fn (): \Illuminate\Routing\Route => $route);

    $response = app(\App\Http\Controllers\CorporateAffiliationsExportController::class)($request);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Disposition'))->toContain('.xlsx');
});
