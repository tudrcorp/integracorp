<?php

declare(strict_types=1);

use App\Support\Exports\AffiliateCorporateCsvExportService;

uses(Tests\TestCase::class);

it('define encabezados csv de afiliados corporativos', function (): void {
    expect(AffiliateCorporateCsvExportService::headers())
        ->toContain('Código afiliación')
        ->toContain('Plan')
        ->toContain('Estatus')
        ->and(count(AffiliateCorporateCsvExportService::headers()))->toBe(19);
});

it('expone exportacion csv directa para afiliados corporativos', function (): void {
    $service = file_get_contents(dirname(__DIR__, 2).'/app/Support/Exports/AffiliateCorporateCsvExportService.php');
    $exportAction = file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/AffiliateCorporateCsvExportAction.php');
    $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/AffiliateCorporateExportCsvController.php');
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php');

    expect($service)
        ->toContain('streamCsv')
        ->toContain('exportAndAudit')
        ->toContain("where('plan_id'")
        ->toContain("where('status'")
        ->toContain('whereIn(\'affiliation_corporate_id\'')
        ->toContain("whereIn('id'")
        ->toContain('affiliate_corporate_ids')
        ->toContain('ownerAccountManagers');

    expect($exportAction)
        ->toContain('CsvExportDownloadTrigger::fromAction')
        ->toContain('storeFiltersAndGetToken')
        ->toContain('successNotification(null)');

    expect($controller)
        ->toContain('storeFiltersAndGetToken')
        ->toContain('affiliation_corporate_ids')
        ->toContain('Cache::pull');

    expect($table)
        ->toContain("BulkAction::make('exportAffiliateCorporatesCsv')")
        ->toContain('storeFiltersAndGetToken')
        ->toContain('affiliation_corporate_ids')
        ->toContain('redirect()->route')
        ->toContain('business.affiliate-corporates.export-csv');
});

it('descarga csv de afiliados corporativos mediante controlador con token', function (): void {
    if (! \Illuminate\Support\Facades\Schema::hasTable('affiliate_corporates')) {
        $this->markTestSkipped('La tabla affiliate_corporates no está disponible en este entorno de prueba.');
    }

    $token = \App\Http\Controllers\AffiliateCorporateExportCsvController::storeFiltersAndGetToken([
        'affiliation_corporate_ids' => [1, 2, 3],
    ], 'business');

    $request = \Illuminate\Http\Request::create('/business/export-affiliate-corporates-csv', 'GET', [
        'token' => $token,
    ]);

    $route = new \Illuminate\Routing\Route('GET', 'business/export-affiliate-corporates-csv', []);
    $route->name('business.affiliate-corporates.export-csv');
    $request->setRouteResolver(fn (): \Illuminate\Routing\Route => $route);

    $response = app(\App\Http\Controllers\AffiliateCorporateExportCsvController::class)($request);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment')
        ->and($response->headers->get('Content-Disposition'))->toContain('.csv')
        ->and($response->headers->get('Content-Type'))->toContain('text/csv');
});

it('expone rutas csv directas de afiliados corporativos', function (): void {
    expect(route('business.affiliate-corporates.export-csv'))->toBeString()
        ->and(route('administration.affiliate-corporates.export-csv'))->toBeString()
        ->and(route('operations.affiliate-corporates.export-csv'))->toBeString();
});

it('expone exportacion csv de afiliados corporativos en la tabla de operaciones', function (): void {
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/AffiliateCorporates/Tables/AffiliateCorporatesTable.php');

    expect($table)
        ->toContain("BulkAction::make('exportAffiliateCorporatesCsv')")
        ->toContain('Exportar CSV')
        ->toContain('affiliate_corporate_ids')
        ->toContain('operations.affiliate-corporates.export-csv')
        ->not->toContain('ExportBulkAction::make')
        ->not->toContain('AffiliateCorporateExporter::class');
});
