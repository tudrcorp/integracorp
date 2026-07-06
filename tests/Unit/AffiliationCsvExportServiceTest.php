<?php

declare(strict_types=1);

use App\Support\Exports\AffiliationCsvExportService;

uses(Tests\TestCase::class);

it('define encabezados csv de afiliaciones individuales', function (): void {
    expect(AffiliationCsvExportService::headers())
        ->toContain('Código afiliación')
        ->toContain('Nombre titular')
        ->toContain('Estatus')
        ->and(count(AffiliationCsvExportService::headers()))->toBe(23);
});

it('expone exportacion csv de afiliaciones individuales en la tabla', function (): void {
    $service = file_get_contents(dirname(__DIR__, 2).'/app/Support/Exports/AffiliationCsvExportService.php');
    $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/AffiliationExportCsvController.php');
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Tables/AffiliationsTable.php');

    expect($service)
        ->toContain('streamCsv')
        ->toContain("whereIn('id'")
        ->toContain('ownerAccountManagers');

    expect($controller)
        ->toContain('storeFiltersAndGetToken')
        ->toContain('affiliation_ids')
        ->toContain('Cache::pull');

    expect($table)
        ->toContain("BulkAction::make('exportAffiliationsCsv')")
        ->toContain('Exportar Afiliaciones')
        ->toContain('AffiliationExportCsvController::storeFiltersAndGetToken')
        ->toContain('redirect()->route')
        ->toContain('business.affiliations.export-csv');
});

it('descarga csv de afiliaciones individuales mediante controlador con token', function (): void {
    if (! \Illuminate\Support\Facades\Schema::hasTable('affiliations')) {
        $this->markTestSkipped('La tabla affiliations no está disponible en este entorno de prueba.');
    }

    $token = \App\Http\Controllers\AffiliationExportCsvController::storeFiltersAndGetToken([
        'affiliation_ids' => [1, 2, 3],
    ], 'business');

    $request = \Illuminate\Http\Request::create('/business/export-affiliations-csv', 'GET', [
        'token' => $token,
    ]);

    $route = new \Illuminate\Routing\Route('GET', 'business/export-affiliations-csv', []);
    $route->name('business.affiliations.export-csv');
    $request->setRouteResolver(fn (): \Illuminate\Routing\Route => $route);

    $response = app(\App\Http\Controllers\AffiliationExportCsvController::class)($request);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment')
        ->and($response->headers->get('Content-Disposition'))->toContain('.csv')
        ->and($response->headers->get('Content-Type'))->toContain('text/csv');
});

it('expone ruta csv de afiliaciones individuales en business', function (): void {
    expect(route('business.affiliations.export-csv'))->toBeString()
        ->and(route('administration.affiliations.export-csv'))->toBeString();
});

it('expone exportacion csv de afiliaciones individuales en la tabla de administracion', function (): void {
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Affiliations/Tables/AffiliationsTable.php');

    expect($table)
        ->toContain("BulkAction::make('exportAffiliationsCsv')")
        ->toContain('Exportar Afiliaciones')
        ->toContain('AffiliationExportCsvController::storeFiltersAndGetToken')
        ->toContain('administration.affiliations.export-csv');
});
