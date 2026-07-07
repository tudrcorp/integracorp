<?php

declare(strict_types=1);

use App\Support\Exports\CorporateQuoteCsvExportService;
use App\Support\Exports\CorporateQuoteDataCsvExportService;

uses(Tests\TestCase::class);

it('define encabezados csv de cotizaciones corporativas', function (): void {
    expect(CorporateQuoteCsvExportService::headers())
        ->toContain('Código')
        ->toContain('Cliente corporativo')
        ->toContain('Estatus')
        ->and(count(CorporateQuoteCsvExportService::headers()))->toBe(14);
});

it('define encabezados csv de cotizados corporativos', function (): void {
    expect(CorporateQuoteDataCsvExportService::headers())
        ->toContain('Código cotización')
        ->toContain('Población (personas)')
        ->toContain('Rango edad')
        ->toContain('Nombres')
        ->and(count(CorporateQuoteDataCsvExportService::headers()))->toBe(26);
});

it('expone exportacion csv de cotizaciones en la tabla', function (): void {
    $quoteService = file_get_contents(dirname(__DIR__, 2).'/app/Support/Exports/CorporateQuoteCsvExportService.php');
    $quotedService = file_get_contents(dirname(__DIR__, 2).'/app/Support/Exports/CorporateQuoteDataCsvExportService.php');
    $quoteController = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/CorporateQuoteExportCsvController.php');
    $quotedController = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/CorporateQuoteDataExportCsvController.php');
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CorporateQuotes/Tables/CorporateQuotesTable.php');

    expect($quoteService)
        ->toContain('streamCsv')
        ->toContain("whereIn('id'")
        ->toContain('ownerAccountManagers');

    expect($quotedService)
        ->toContain('streamCsv')
        ->toContain('DetailCorporateQuote::query()')
        ->toContain('writeQuotePopulation')
        ->toContain('corporate_quote_data_ids')
        ->toContain('ownerAccountManagers');

    expect($quoteController)
        ->toContain('storeFiltersAndGetToken')
        ->toContain('corporate_quote_ids')
        ->toContain('Cache::pull');

    expect($quotedController)
        ->toContain('storeFiltersAndGetToken')
        ->toContain('corporate_quote_ids')
        ->toContain('Cache::pull');

    expect($table)
        ->toContain("BulkAction::make('exportCorporateQuotesCsv')")
        ->toContain('Exportar Cotizaciones')
        ->toContain('CorporateQuoteExportCsvController::storeFiltersAndGetToken')
        ->toContain('business.corporate-quotes.export-csv')
        ->not->toContain("BulkAction::make('exportCorporateQuoteDataCsv')")
        ->not->toContain('Exportar Cotizados')
        ->not->toContain('business.corporate-quote-data.export-csv')
        ->not->toContain("->label('Exportar CSV')")
        ->not->toContain('exportPopulationCsv')
        ->not->toContain('export-csv-with-population')
        ->not->toContain('export-population-csv');
});

it('descarga csv de cotizaciones corporativas mediante controlador con token', function (): void {
    if (! \Illuminate\Support\Facades\Schema::hasTable('corporate_quotes')) {
        $this->markTestSkipped('La tabla corporate_quotes no está disponible en este entorno de prueba.');
    }

    $token = \App\Http\Controllers\CorporateQuoteExportCsvController::storeFiltersAndGetToken([
        'corporate_quote_ids' => [1, 2, 3],
    ], 'business');

    $request = \Illuminate\Http\Request::create('/business/export-corporate-quotes-csv', 'GET', [
        'token' => $token,
    ]);

    $route = new \Illuminate\Routing\Route('GET', 'business/export-corporate-quotes-csv', []);
    $route->name('business.corporate-quotes.export-csv');
    $request->setRouteResolver(fn (): \Illuminate\Routing\Route => $route);

    $response = app(\App\Http\Controllers\CorporateQuoteExportCsvController::class)($request);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment')
        ->and($response->headers->get('Content-Disposition'))->toContain('.csv')
        ->and($response->headers->get('Content-Type'))->toContain('text/csv');
});

it('descarga csv de cotizados corporativos mediante controlador con token', function (): void {
    if (! \Illuminate\Support\Facades\Schema::hasTable('corporate_quote_data')) {
        $this->markTestSkipped('La tabla corporate_quote_data no está disponible en este entorno de prueba.');
    }

    $token = \App\Http\Controllers\CorporateQuoteDataExportCsvController::storeFiltersAndGetToken([
        'corporate_quote_ids' => [1, 2, 3],
    ], 'business');

    $request = \Illuminate\Http\Request::create('/business/export-corporate-quote-data-csv', 'GET', [
        'token' => $token,
    ]);

    $route = new \Illuminate\Routing\Route('GET', 'business/export-corporate-quote-data-csv', []);
    $route->name('business.corporate-quote-data.export-csv');
    $request->setRouteResolver(fn (): \Illuminate\Routing\Route => $route);

    $response = app(\App\Http\Controllers\CorporateQuoteDataExportCsvController::class)($request);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment')
        ->and($response->headers->get('Content-Disposition'))->toContain('.csv')
        ->and($response->headers->get('Content-Type'))->toContain('text/csv');
});

it('exporta poblacion por rango etario cuando no hay personas importadas', function (): void {
    if (! \Illuminate\Support\Facades\Schema::hasTable('detail_corporate_quotes')) {
        $this->markTestSkipped('La tabla detail_corporate_quotes no está disponible en este entorno de prueba.');
    }

    $quoteId = \App\Models\CorporateQuote::query()
        ->whereHas('detailCoporateQuotes')
        ->whereDoesntHave('corporateQuoteData')
        ->value('id');

    if ($quoteId === null) {
        $this->markTestSkipped('No hay cotizaciones con población por rango etario disponible.');
    }

    $detailsCount = \App\Models\DetailCorporateQuote::query()
        ->where('corporate_quote_id', $quoteId)
        ->count();

    expect($detailsCount)->toBeGreaterThan(0);

    $token = \App\Http\Controllers\CorporateQuoteDataExportCsvController::storeFiltersAndGetToken([
        'corporate_quote_ids' => [$quoteId],
    ], 'business');

    $request = \Illuminate\Http\Request::create('/business/export-corporate-quote-data-csv', 'GET', [
        'token' => $token,
    ]);

    $route = new \Illuminate\Routing\Route('GET', 'business/export-corporate-quote-data-csv', []);
    $route->name('business.corporate-quote-data.export-csv');
    $request->setRouteResolver(fn (): \Illuminate\Routing\Route => $route);

    ob_start();
    app(\App\Http\Controllers\CorporateQuoteDataExportCsvController::class)($request)->sendContent();
    $csv = (string) ob_get_clean();

    expect(substr_count($csv, 'Rango etario'))->toBeGreaterThanOrEqual($detailsCount);
});

it('expone rutas csv de cotizaciones y cotizados en business', function (): void {
    expect(route('business.corporate-quotes.export-csv'))->toBeString()
        ->and(route('business.corporate-quote-data.export-csv'))->toBeString();
});
