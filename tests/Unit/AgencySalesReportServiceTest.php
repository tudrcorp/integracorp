<?php

declare(strict_types=1);

use App\Models\Agency;
use App\Services\AgencySalesReportService;
use App\Support\Charts\SvgDualLineChartRenderer;
use Carbon\Carbon;
use Tests\TestCase;

uses(TestCase::class);

it('resuelve periodo año en curso por defecto', function (): void {
    $asOf = Carbon::create(2026, 8, 6, 12);

    $period = AgencySalesReportService::resolvePeriod(['period' => 'current_year'], $asOf);

    expect($period['from']->toDateString())->toBe('2026-01-01')
        ->and($period['to']->toDateString())->toBe('2026-08-06')
        ->and($period['period_label'])->toContain('2026');
});

it('resuelve rango de fechas personalizado', function (): void {
    $period = AgencySalesReportService::resolvePeriod([
        'period' => 'range',
        'date_from' => '2026-02-01',
        'date_to' => '2026-03-15',
    ]);

    expect($period['from']->toDateString())->toBe('2026-02-01')
        ->and($period['to']->toDateString())->toBe('2026-03-15')
        ->and($period['period_label'])->toBe('01/02/2026 — 15/03/2026');
});

it('arma serie mensual individual y corporativa del año en curso', function (): void {
    $individuals = collect([
        (object) ['total_amount' => 100, 'created_at' => Carbon::create(2026, 1, 10)],
        (object) ['total_amount' => 50, 'created_at' => Carbon::create(2026, 1, 20)],
        (object) ['total_amount' => 200, 'created_at' => Carbon::create(2026, 3, 5)],
    ]);

    $corporates = collect([
        (object) ['total_amount' => 1000, 'created_at' => Carbon::create(2026, 2, 1)],
        (object) ['total_amount' => 500, 'created_at' => Carbon::create(2025, 12, 1)],
    ]);

    $series = AgencySalesReportService::buildYearSeriesFromCollections($individuals, $corporates, 2026, 3);

    expect($series['labels'])->toBe(['Ene', 'Feb', 'Mar'])
        ->and($series['individual'])->toBe([150.0, 0.0, 200.0])
        ->and($series['corporate'])->toBe([0.0, 1000.0, 0.0]);
});

it('detecta ventas corporativas con o sin tilde en el tipo', function (): void {
    expect(AgencySalesReportService::isCorporateSale('AFILIACION INDIVIDUAL'))->toBeFalse()
        ->and(AgencySalesReportService::isCorporateSale('AFILIACIÓN CORPORATIVA'))->toBeTrue()
        ->and(AgencySalesReportService::isCorporateSale('AFILIACION CORPORATIVA'))->toBeTrue();
});

it('genera data uri de grafico dual png o svg', function (): void {
    $dataUri = SvgDualLineChartRenderer::toPngDataUri(
        ['Ene', 'Feb', 'Mar'],
        [150, 0, 200],
        [0, 1000, 0],
        'Prueba dual',
        'Individual',
        'Corporativo',
        400,
        200,
    );

    expect($dataUri)->toStartWith('data:image/')
        ->and(strlen($dataUri))->toBeGreaterThan(50);
});

it('expone accion descargar reporte de ventas en tabla de agencias', function (): void {
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Agencies/Tables/AgenciesTable.php');
    $action = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Agencies/Actions/DownloadAgencySalesReportAction.php');
    $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/AgencySalesReportService.php');
    $blade = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/agency-sales-report.blade.php');
    $routes = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');
    $listSales = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Sales/Pages/ListSales.php');

    expect($table)
        ->toContain('DownloadAgencySalesReportAction::make()')
        ->and($action)->toContain("->label('Descargar')")
        ->toContain('download_sales_report')
        ->toContain('makeHeader')
        ->toContain('download_agency_sales_report_header')
        ->toContain("Select::make('agency_id')")
        ->toContain('storeParamsAndGetToken')
        ->toContain('administration.agencies.sales-report.download')
        ->toContain("'current_year'")
        ->toContain("'range'")
        ->toContain("'pdf'")
        ->toContain("'csv'")
        ->and($listSales)->toContain('DownloadAgencySalesReportAction::makeHeader()')
        ->and($service)->toContain('SvgDualLineChartRenderer::toPngDataUri')
        ->toContain('total_amount')
        ->toContain('Sale::query()')
        ->toContain('activeIndividualAffiliationStats')
        ->toContain('activeCorporateAffiliationStats')
        ->toContain('storeParamsAndGetToken')
        ->and($blade)->toContain('chart_data_uri')
        ->toContain('Total venta US$')
        ->toContain('afiliaciones activas')
        ->toContain('individual_population')
        ->toContain('corporate_population')
        ->toContain('afiliados')
        ->not->toContain('Observaciones')
        ->and($routes)->toContain('administration.agencies.sales-report.download');
});

it('cuenta afiliaciones activas con status ACTIVA', function (): void {
    $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/AgencySalesReportService.php');

    expect($service)
        ->toContain("->where('status', 'ACTIVA')")
        ->toContain('Affiliation::query()')
        ->toContain('AffiliationCorporate::query()')
        ->toContain('family_members')
        ->toContain('poblation');
});

it('suma poblaciones numericas con defaults seguros', function (): void {
    $method = new ReflectionMethod(AgencySalesReportService::class, 'numericPopulation');
    $method->setAccessible(true);

    expect($method->invoke(null, null, 1))->toBe(1)
        ->and($method->invoke(null, '', 0))->toBe(0)
        ->and($method->invoke(null, 25, 0))->toBe(25)
        ->and($method->invoke(null, '100', 0))->toBe(100);
});

it('guarda y consume token de descarga de reporte de ventas', function (): void {
    $token = AgencySalesReportService::storeParamsAndGetToken([
        'agency_id' => 89,
        'period' => 'current_year',
        'format' => 'pdf',
    ]);

    $params = AgencySalesReportService::pullParamsFromToken($token);

    expect($params)->toBe([
        'agency_id' => 89,
        'period' => 'current_year',
        'date_from' => null,
        'date_to' => null,
        'format' => 'pdf',
    ]);

    expect(AgencySalesReportService::pullParamsFromToken($token))->toBeNull();
});

it('registra la ruta nombrada del reporte de ventas de agencia', function (): void {
    expect(route('administration.agencies.sales-report.download', ['token' => 'x']))->toBeString();
});

it('genera nombre de archivo con codigo de agencia', function (): void {
    $agency = new Agency(['code' => 'TDG-151']);
    $agency->id = 51;

    expect(AgencySalesReportService::filename($agency, 'pdf'))
        ->toStartWith('reporte-ventas-agencia-TDG-151-')
        ->toEndWith('.pdf');
});
