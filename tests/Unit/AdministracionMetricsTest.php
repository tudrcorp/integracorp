<?php

declare(strict_types=1);

use App\Filament\Metrics\Pages\Administracion;
use App\Filament\Metrics\Widgets\AdministracionSalesMomStats;
use App\Services\IntegracorpApi\AdministracionMetricsClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    Cache::flush();

    config([
        'services.integracorp_api.base_url' => 'http://127.0.0.1:4000',
        'services.integracorp_api.timeout' => 3,
    ]);
});

it('consume el endpoint de ventas administracion usd y ves', function (): void {
    Http::fake([
        'http://127.0.0.1:4000/api/metrics/administracion/sales-comparison' => Http::response([
            'success' => true,
            'data' => [
                'current_month' => [
                    'year' => 2026,
                    'month' => 8,
                    'start' => '2026-08-01',
                    'end_exclusive' => '2026-09-01',
                ],
                'previous_month' => [
                    'year' => 2026,
                    'month' => 7,
                    'start' => '2026-07-01',
                    'end_exclusive' => '2026-08-01',
                ],
                'usd' => [
                    'current' => 12500.5,
                    'previous' => 10000.0,
                    'delta' => 2500.5,
                    'percent_change' => 25.0,
                    'trend' => 'up',
                    'previous_was_zero' => false,
                ],
                'ves' => [
                    'current' => 450000.75,
                    'previous' => 500000.0,
                    'delta' => -49999.25,
                    'percent_change' => -10.0,
                    'trend' => 'down',
                    'previous_was_zero' => false,
                ],
                'year_series' => [
                    'year' => 2026,
                    'labels' => ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago'],
                    'usd' => [1000.0, 1200.0, 1500.0, 1800.0, 2000.0, 2200.0, 10000.0, 12500.5],
                    'ves' => [40000.0, 45000.0, 50000.0, 55000.0, 60000.0, 65000.0, 500000.0, 450000.75],
                ],
            ],
        ], 200),
    ]);

    $comparison = app(AdministracionMetricsClient::class)->salesComparison();

    expect($comparison['usd']['current'])->toBe(12500.5)
        ->and($comparison['usd']['percent_change'])->toBe(25.0)
        ->and($comparison['usd']['trend'])->toBe('up')
        ->and($comparison['ves']['trend'])->toBe('down')
        ->and($comparison['ves']['delta'])->toBe(-49999.25)
        ->and($comparison['year_series']['year'])->toBe(2026)
        ->and($comparison['year_series']['labels'])->toHaveCount(8)
        ->and($comparison['year_series']['usd'][7])->toBe(12500.5)
        ->and($comparison['year_series']['ves'][6])->toBe(500000.0);

    Http::assertSent(fn ($request): bool => str_ends_with(
        $request->url(),
        '/api/metrics/administracion/sales-comparison'
    ));
});

it('arma las cards de ventas con formato de dinero y chart anual', function (): void {
    Http::fake([
        'http://127.0.0.1:4000/api/metrics/administracion/sales-comparison' => Http::response([
            'success' => true,
            'data' => [
                'current_month' => [
                    'year' => 2026,
                    'month' => 8,
                    'start' => '2026-08-01',
                    'end_exclusive' => '2026-09-01',
                ],
                'previous_month' => [
                    'year' => 2026,
                    'month' => 7,
                    'start' => '2026-07-01',
                    'end_exclusive' => '2026-08-01',
                ],
                'usd' => [
                    'current' => 100.0,
                    'previous' => 50.0,
                    'delta' => 50.0,
                    'percent_change' => 100.0,
                    'trend' => 'up',
                    'previous_was_zero' => false,
                ],
                'ves' => [
                    'current' => 0.0,
                    'previous' => 0.0,
                    'delta' => 0.0,
                    'percent_change' => 0.0,
                    'trend' => 'flat',
                    'previous_was_zero' => true,
                ],
                'year_series' => [
                    'year' => 2026,
                    'labels' => ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago'],
                    'usd' => [10, 20, 30, 40, 50, 60, 50, 100],
                    'ves' => [0, 0, 0, 0, 0, 0, 0, 0],
                ],
            ],
        ], 200),
    ]);

    $data = app(AdministracionSalesMomStats::class)->getComparisonViewData();

    expect($data['cards'])->toHaveCount(2)
        ->and($data['cards'][0]['key'])->toBe('usd')
        ->and($data['cards'][0]['title'])->toBe('Total de Ventas General')
        ->and($data['cards'][0]['value_prefix'])->toBe('US$ ')
        ->and($data['cards'][0]['decimals'])->toBe(2)
        ->and($data['cards'][0]['current'])->toBe(100.0)
        ->and($data['cards'][0]['chart']['data']['datasets'][0]['data'][7])->toBe(100.0)
        ->and($data['cards'][1]['key'])->toBe('ves')
        ->and($data['cards'][1]['title'])->toBe('Total de ventas en VES')
        ->and($data['cards'][1]['value_prefix'])->toBe('Bs. ')
        ->and($data['cards'][1]['percent_label'])->toBe('Sin cambios');
});

it('registra el widget de ventas en la pagina de administracion', function (): void {
    $root = dirname(__DIR__, 2);
    $page = file_get_contents($root.'/app/Filament/Metrics/Pages/Administracion.php');
    $pageView = file_get_contents($root.'/resources/views/filament/metrics/pages/administracion.blade.php');
    $widget = file_get_contents($root.'/app/Filament/Metrics/Widgets/AdministracionSalesMomStats.php');
    $apiController = file_get_contents($root.'/../../integracorp-api/src/controllers/metrics.administracion.controller.js');
    $routes = file_get_contents($root.'/../../integracorp-api/src/routes/metrics.routes.js');

    expect(Administracion::class)->toBeString()
        ->and($page)->toContain('AdministracionSalesMomStats::class')
        ->and($page)->toContain("return 'filament.metrics.pages.administracion'")
        ->and($page)->toMatch('/getHeaderWidgets\(\): array\s*\{\s*return \[\];/s')
        ->and($pageView)->toContain('fi-metrics-module')
        ->and($widget)->toContain('Total de Ventas General')
        ->and($widget)->toContain('Total de ventas en VES')
        ->and($widget)->toContain('salesComparison')
        ->and($apiController)->toContain('getAdministracionSalesComparison')
        ->and($apiController)->toContain('total_amount')
        ->and($apiController)->toContain('pay_amount_ves')
        ->and($apiController)->toContain('is_payment_link = 0')
        ->and($routes)->toContain('/administracion/sales-comparison');
});
