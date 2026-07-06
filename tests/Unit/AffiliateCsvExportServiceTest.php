<?php

declare(strict_types=1);

use App\Support\Exports\AffiliateCsvExportService;

uses(Tests\TestCase::class);

it('define encabezados csv de afiliados individuales', function (): void {
    expect(AffiliateCsvExportService::headers())
        ->toContain('Código afiliación')
        ->toContain('Nombre afiliado')
        ->toContain('Estatus')
        ->and(count(AffiliateCsvExportService::headers()))->toBe(19);
});

it('expone exportacion csv directa para afiliados individuales', function (): void {
    $service = file_get_contents(dirname(__DIR__, 2).'/app/Support/Exports/AffiliateCsvExportService.php');
    $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/AffiliateExportCsvController.php');
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Tables/AffiliationsTable.php');

    expect($service)
        ->toContain('streamCsv')
        ->toContain("whereIn('affiliation_id'")
        ->toContain("whereIn('id'")
        ->toContain('affiliate_ids')
        ->toContain('ownerAccountManagers');

    expect($controller)
        ->toContain('storeFiltersAndGetToken')
        ->toContain('affiliation_ids')
        ->toContain('Cache::pull');

    expect($table)
        ->toContain("BulkAction::make('exportAffiliatesCsv')")
        ->toContain('Exportar Afiliados')
        ->toContain('storeFiltersAndGetToken')
        ->toContain('affiliation_ids')
        ->toContain('redirect()->route')
        ->toContain('business.affiliates.export-csv');
});

it('descarga csv de afiliados individuales mediante controlador con token', function (): void {
    if (! \Illuminate\Support\Facades\Schema::hasTable('affiliates')) {
        $this->markTestSkipped('La tabla affiliates no está disponible en este entorno de prueba.');
    }

    $token = \App\Http\Controllers\AffiliateExportCsvController::storeFiltersAndGetToken([
        'affiliation_ids' => [1, 2, 3],
    ], 'business');

    $request = \Illuminate\Http\Request::create('/business/export-affiliates-csv', 'GET', [
        'token' => $token,
    ]);

    $route = new \Illuminate\Routing\Route('GET', 'business/export-affiliates-csv', []);
    $route->name('business.affiliates.export-csv');
    $request->setRouteResolver(fn (): \Illuminate\Routing\Route => $route);

    $response = app(\App\Http\Controllers\AffiliateExportCsvController::class)($request);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment')
        ->and($response->headers->get('Content-Disposition'))->toContain('.csv')
        ->and($response->headers->get('Content-Type'))->toContain('text/csv');
});

it('expone ruta csv directa de afiliados individuales en business', function (): void {
    expect(route('business.affiliates.export-csv'))->toBeString()
        ->and(route('administration.affiliates.export-csv'))->toBeString()
        ->and(route('operations.affiliates.export-csv'))->toBeString();
});

it('expone exportacion csv de afiliados individuales en la tabla de administracion', function (): void {
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Affiliations/Tables/AffiliationsTable.php');

    expect($table)
        ->toContain("BulkAction::make('exportAffiliatesCsv')")
        ->toContain('Exportar Afiliados')
        ->toContain('AffiliateExportCsvController::storeFiltersAndGetToken')
        ->toContain('administration.affiliates.export-csv');
});

it('expone exportacion csv de afiliados individuales en la tabla de operaciones', function (): void {
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Affiliates/Tables/AffiliatesTable.php');

    expect($table)
        ->toContain("BulkAction::make('exportAffiliatesCsv')")
        ->toContain('Exportar CSV')
        ->toContain('affiliate_ids')
        ->toContain('operations.affiliates.export-csv')
        ->not->toContain('ExportBulkAction::make')
        ->not->toContain('AffiliateExporter::class');
});
