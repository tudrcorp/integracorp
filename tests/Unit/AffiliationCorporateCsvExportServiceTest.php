<?php

declare(strict_types=1);

use App\Support\Exports\AffiliationCorporateCsvExportService;

uses(Tests\TestCase::class);

it('define encabezados csv de afiliaciones corporativas', function (): void {
    expect(AffiliationCorporateCsvExportService::headers())
        ->toContain('Código afiliación')
        ->toContain('Cliente corporativo')
        ->toContain('Estatus')
        ->and(count(AffiliationCorporateCsvExportService::headers()))->toBe(26);
});

it('expone exportacion csv de afiliaciones corporativas en la tabla', function (): void {
    $service = file_get_contents(dirname(__DIR__, 2).'/app/Support/Exports/AffiliationCorporateCsvExportService.php');
    $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/AffiliationCorporateExportCsvController.php');
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php');

    expect($service)
        ->toContain('streamCsv')
        ->toContain("whereIn('id'")
        ->toContain('ownerAccountManagers');

    expect($controller)
        ->toContain('storeFiltersAndGetToken')
        ->toContain('affiliation_corporate_ids')
        ->toContain('Cache::pull');

    expect($table)
        ->toContain("BulkAction::make('exportAffiliationCorporatesCsv')")
        ->toContain('Exportar Afiliaciones')
        ->toContain('AffiliationCorporateExportCsvController::storeFiltersAndGetToken')
        ->toContain('redirect()->route')
        ->toContain('business.affiliation-corporates.export-csv')
        ->toContain("BulkAction::make('exportAffiliateCorporatesCsv')")
        ->toContain('business.affiliate-corporates.export-csv');
});

it('descarga csv de afiliaciones corporativas mediante controlador con token', function (): void {
    if (! \Illuminate\Support\Facades\Schema::hasTable('affiliation_corporates')) {
        $this->markTestSkipped('La tabla affiliation_corporates no está disponible en este entorno de prueba.');
    }

    $token = \App\Http\Controllers\AffiliationCorporateExportCsvController::storeFiltersAndGetToken([
        'affiliation_corporate_ids' => [1, 2, 3],
    ], 'business');

    $request = \Illuminate\Http\Request::create('/business/export-affiliation-corporates-csv', 'GET', [
        'token' => $token,
    ]);

    $route = new \Illuminate\Routing\Route('GET', 'business/export-affiliation-corporates-csv', []);
    $route->name('business.affiliation-corporates.export-csv');
    $request->setRouteResolver(fn (): \Illuminate\Routing\Route => $route);

    $response = app(\App\Http\Controllers\AffiliationCorporateExportCsvController::class)($request);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment')
        ->and($response->headers->get('Content-Disposition'))->toContain('.csv')
        ->and($response->headers->get('Content-Type'))->toContain('text/csv');
});

it('expone ruta csv de afiliaciones corporativas en business', function (): void {
    expect(route('business.affiliation-corporates.export-csv'))->toBeString();
});
