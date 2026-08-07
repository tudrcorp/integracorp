<?php

declare(strict_types=1);

use App\Filament\Metrics\Pages\Cotizaciones;
use App\Services\IntegracorpApi\CotizacionesMetricsClient;
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

it('consume el endpoint de cotizaciones creadas ejecutadas y anuladas', function (): void {
    Http::fake([
        'http://127.0.0.1:4000/api/metrics/cotizaciones/status-comparison' => Http::response([
            'success' => true,
            'data' => [
                'current_month' => [
                    'year' => 2026,
                    'month' => 7,
                    'start' => '2026-07-01',
                    'end_exclusive' => '2026-08-01',
                ],
                'previous_month' => [
                    'year' => 2026,
                    'month' => 6,
                    'start' => '2026-06-01',
                    'end_exclusive' => '2026-07-01',
                ],
                'created' => [
                    'current' => 120,
                    'previous' => 100,
                    'delta' => 20,
                    'percent_change' => 20.0,
                    'trend' => 'up',
                    'previous_was_zero' => false,
                ],
                'executed' => [
                    'current' => 45,
                    'previous' => 60,
                    'delta' => -15,
                    'percent_change' => -25.0,
                    'trend' => 'down',
                    'previous_was_zero' => false,
                ],
                'annulled' => [
                    'current' => 8,
                    'previous' => 8,
                    'delta' => 0,
                    'percent_change' => 0.0,
                    'trend' => 'flat',
                    'previous_was_zero' => false,
                ],
                'year_series' => [
                    'year' => 2026,
                    'labels' => ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul'],
                    'created' => [10, 12, 15, 18, 20, 100, 120],
                    'executed' => [4, 5, 6, 7, 8, 60, 45],
                    'annulled' => [1, 1, 2, 1, 2, 8, 8],
                ],
            ],
        ], 200),
    ]);

    $comparison = app(CotizacionesMetricsClient::class)->statusComparison();

    expect($comparison['created']['current'])->toBe(120)
        ->and($comparison['created']['percent_change'])->toBe(20.0)
        ->and($comparison['created']['trend'])->toBe('up')
        ->and($comparison['executed']['trend'])->toBe('down')
        ->and($comparison['executed']['delta'])->toBe(-15)
        ->and($comparison['annulled']['trend'])->toBe('flat')
        ->and($comparison['year_series']['year'])->toBe(2026)
        ->and($comparison['year_series']['labels'])->toHaveCount(7)
        ->and($comparison['year_series']['created'][6])->toBe(120)
        ->and($comparison['year_series']['executed'][5])->toBe(60)
        ->and($comparison['year_series']['annulled'][6])->toBe(8);

    Http::assertSent(fn ($request): bool => str_ends_with(
        $request->url(),
        '/api/metrics/cotizaciones/status-comparison'
    ));
});

it('consume el endpoint de cotizaciones por agencia master y general', function (): void {
    Http::fake([
        'http://127.0.0.1:4000/api/metrics/cotizaciones/by-agency*' => Http::response([
            'success' => true,
            'data' => [
                'current_month' => [
                    'year' => 2026,
                    'month' => 7,
                    'start' => '2026-07-01',
                    'end_exclusive' => '2026-08-01',
                ],
                'previous_month' => [
                    'year' => 2026,
                    'month' => 6,
                    'start' => '2026-06-01',
                    'end_exclusive' => '2026-07-01',
                ],
                'items' => [
                    [
                        'agency_id' => 7,
                        'agency_code' => 'M-01',
                        'agency_name' => 'Agencia Norte',
                        'agency_type' => 'MASTER',
                        'agency_type_id' => 1,
                        'quotes_total' => 90,
                        'executed_with_affiliation' => 30,
                        'remaining' => 60,
                        'quotes_total_previous' => 60,
                        'executed_with_affiliation_previous' => 20,
                        'quotes_mom' => [
                            'current' => 90,
                            'previous' => 60,
                            'delta' => 30,
                            'percent_change' => 50.0,
                            'trend' => 'up',
                            'previous_was_zero' => false,
                        ],
                        'executed_mom' => [
                            'current' => 30,
                            'previous' => 20,
                            'delta' => 10,
                            'percent_change' => 50.0,
                            'trend' => 'up',
                            'previous_was_zero' => false,
                        ],
                    ],
                    [
                        'agency_id' => 8,
                        'agency_code' => 'G-02',
                        'agency_name' => 'Agencia Sur',
                        'agency_type' => 'GENERAL',
                        'agency_type_id' => 3,
                        'quotes_total' => 40,
                        'executed_with_affiliation' => 10,
                        'remaining' => 30,
                        'quotes_total_previous' => 50,
                        'executed_with_affiliation_previous' => 15,
                        'quotes_mom' => [
                            'current' => 40,
                            'previous' => 50,
                            'delta' => -10,
                            'percent_change' => -20.0,
                            'trend' => 'down',
                            'previous_was_zero' => false,
                        ],
                        'executed_mom' => [
                            'current' => 10,
                            'previous' => 15,
                            'delta' => -5,
                            'percent_change' => -33.3,
                            'trend' => 'down',
                            'previous_was_zero' => false,
                        ],
                    ],
                ],
                'total_quotes' => 300,
                'total_executed_with_affiliation' => 80,
                'total_agencies' => 12,
                'conversion_rate' => 26.7,
                'mom' => [
                    'quotes' => [
                        'current' => 300,
                        'previous' => 250,
                        'delta' => 50,
                        'percent_change' => 20.0,
                        'trend' => 'up',
                        'previous_was_zero' => false,
                    ],
                    'executed' => [
                        'current' => 80,
                        'previous' => 70,
                        'delta' => 10,
                        'percent_change' => 14.3,
                        'trend' => 'up',
                        'previous_was_zero' => false,
                    ],
                ],
                'limit' => 25,
            ],
        ], 200),
    ]);

    $payload = app(CotizacionesMetricsClient::class)->byAgency(25);

    expect($payload['items'])->toHaveCount(2)
        ->and($payload['current_month']['month'])->toBe(7)
        ->and($payload['previous_month']['month'])->toBe(6)
        ->and($payload['items'][0]['agency_type'])->toBe('MASTER')
        ->and($payload['items'][0]['quotes_total'])->toBe(90)
        ->and($payload['items'][0]['quotes_mom']['percent_change'])->toBe(50.0)
        ->and($payload['items'][1]['agency_type'])->toBe('GENERAL')
        ->and($payload['total_agencies'])->toBe(12)
        ->and($payload['mom']['quotes']['trend'])->toBe('up')
        ->and($payload['conversion_rate'])->toBe(26.7);

    Http::assertSent(fn ($request): bool => str_contains(
        $request->url(),
        '/api/metrics/cotizaciones/by-agency'
    ));
});

it('consume el endpoint de cotizaciones por agente', function (): void {
    Http::fake([
        'http://127.0.0.1:4000/api/metrics/cotizaciones/by-agent*' => Http::response([
            'success' => true,
            'data' => [
                'current_month' => [
                    'year' => 2026,
                    'month' => 7,
                    'start' => '2026-07-01',
                    'end_exclusive' => '2026-08-01',
                ],
                'previous_month' => [
                    'year' => 2026,
                    'month' => 6,
                    'start' => '2026-06-01',
                    'end_exclusive' => '2026-07-01',
                ],
                'items' => [
                    [
                        'agent_id' => 10,
                        'agent_name' => 'Ana Pérez',
                        'code_agent' => 'AG-10',
                        'quotes_total' => 40,
                        'executed_with_affiliation' => 12,
                        'remaining' => 28,
                        'quotes_total_previous' => 20,
                        'executed_with_affiliation_previous' => 8,
                        'quotes_mom' => [
                            'current' => 40,
                            'previous' => 20,
                            'delta' => 20,
                            'percent_change' => 100.0,
                            'trend' => 'up',
                            'previous_was_zero' => false,
                        ],
                        'executed_mom' => [
                            'current' => 12,
                            'previous' => 8,
                            'delta' => 4,
                            'percent_change' => 50.0,
                            'trend' => 'up',
                            'previous_was_zero' => false,
                        ],
                    ],
                    [
                        'agent_id' => 11,
                        'agent_name' => 'Luis Gómez',
                        'code_agent' => null,
                        'quotes_total' => 15,
                        'executed_with_affiliation' => 15,
                        'remaining' => 0,
                        'quotes_total_previous' => 15,
                        'executed_with_affiliation_previous' => 10,
                        'quotes_mom' => [
                            'current' => 15,
                            'previous' => 15,
                            'delta' => 0,
                            'percent_change' => 0.0,
                            'trend' => 'flat',
                            'previous_was_zero' => false,
                        ],
                        'executed_mom' => [
                            'current' => 15,
                            'previous' => 10,
                            'delta' => 5,
                            'percent_change' => 50.0,
                            'trend' => 'up',
                            'previous_was_zero' => false,
                        ],
                    ],
                ],
                'total_quotes' => 120,
                'total_executed_with_affiliation' => 35,
                'total_agents' => 18,
                'conversion_rate' => 29.2,
                'mom' => [
                    'quotes' => [
                        'current' => 120,
                        'previous' => 100,
                        'delta' => 20,
                        'percent_change' => 20.0,
                        'trend' => 'up',
                        'previous_was_zero' => false,
                    ],
                    'executed' => [
                        'current' => 35,
                        'previous' => 40,
                        'delta' => -5,
                        'percent_change' => -12.5,
                        'trend' => 'down',
                        'previous_was_zero' => false,
                    ],
                ],
                'limit' => 25,
            ],
        ], 200),
    ]);

    $payload = app(CotizacionesMetricsClient::class)->byAgent(25);

    expect($payload['items'])->toHaveCount(2)
        ->and($payload['current_month']['month'])->toBe(7)
        ->and($payload['items'][0]['quotes_total'])->toBe(40)
        ->and($payload['items'][0]['executed_with_affiliation'])->toBe(12)
        ->and($payload['items'][0]['remaining'])->toBe(28)
        ->and($payload['items'][0]['quotes_mom']['percent_change'])->toBe(100.0)
        ->and($payload['items'][1]['code_agent'])->toBeNull()
        ->and($payload['total_quotes'])->toBe(120)
        ->and($payload['total_executed_with_affiliation'])->toBe(35)
        ->and($payload['mom']['executed']['trend'])->toBe('down')
        ->and($payload['conversion_rate'])->toBe(29.2);

    Http::assertSent(fn ($request): bool => str_contains(
        $request->url(),
        '/api/metrics/cotizaciones/by-agent'
    ));
});

it('registra el widget MoM y el grafico por agente en la pagina Cotizaciones', function (): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Metrics/Pages/Cotizaciones.php');
    $momWidget = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Metrics/Widgets/CotizacionesStatusMomStats.php');
    $byAgentWidget = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Metrics/Widgets/CotizacionesByAgentChart.php');
    $byAgencyWidget = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Metrics/Widgets/CotizacionesByAgencyChart.php');
    $byAgentView = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/widgets/cotizaciones-by-agent-chart.blade.php');
    $byAgencyView = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/widgets/cotizaciones-by-agency-chart.blade.php');
    $momSummaryView = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/partials/cotizaciones-chart-mom-summary.blade.php');
    $apiController = file_get_contents(dirname(__DIR__, 2).'/../../integracorp-api/src/controllers/metrics.cotizaciones.controller.js');
    $apiRoutes = file_get_contents(dirname(__DIR__, 2).'/../../integracorp-api/src/routes/metrics.routes.js');
    $blade = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/widgets/corretaje-registration-mom.blade.php');
    $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/filament/admin/theme.css');
    $chartConcern = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Metrics/Widgets/Concerns/BuildsRegistrationMomYearChart.php');

    $pageView = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/pages/cotizaciones.blade.php');

    expect(Cotizaciones::class)->toBeString()
        ->and($page)->toContain('CotizacionesStatusMomStats::class')
        ->and($page)->toContain('CotizacionesByAgentChart::class')
        ->and($page)->toContain('CotizacionesByAgencyChart::class')
        ->and($page)->toContain("return 'filament.metrics.pages.cotizaciones'")
        ->and($page)->toMatch('/getHeaderWidgets\(\): array\s*\{\s*return \[\];/s')
        ->and(strpos($page, 'CotizacionesStatusMomStats::class'))
        ->toBeLessThan(strpos($page, 'CotizacionesByAgentChart::class'))
        ->and($pageView)->toContain('fi-metrics-module')
        ->and($momWidget)->toContain('statusComparison')
        ->and($momWidget)->toContain('Cotizaciones creadas')
        ->and($momWidget)->toContain('Cotizaciones convertidas en afiliación')
        ->and($momWidget)->toContain('Cómo vamos este mes, comparado con el mes pasado')
        ->and($momWidget)->toContain('Vamos mejor que el mes pasado')
        ->and($momWidget)->not->toContain('Cotizaciones anuladas')
        ->and($momWidget)->toContain('year_series')
        ->and($momWidget)->toContain('buildRegistrationMomYearChart')
        ->and($momWidget)->toContain("'grid_cols' => 2")
        ->and($momWidget)->toContain('filament.metrics.widgets.corretaje-registration-mom')
        ->and($byAgentWidget)->toContain('byAgent(25)')
        ->and($byAgentWidget)->toContain('stacked: false')
        ->and($byAgentWidget)->toContain('max: {$yAxisMax}')
        ->and($byAgentWidget)->toContain('resolveYAxisMax')
        ->and($byAgentWidget)->toContain('1.18')
        ->and($byAgentWidget)->toContain('Total de cotizaciones')
        ->and($byAgentWidget)->toContain('Convertidas en afiliación')
        ->and($byAgentWidget)->toContain('getMomSummaryViewData')
        ->and($byAgentWidget)->toContain('Comparamos el mes actual con el mes pasado')
        ->and($byAgentWidget)->toContain('formatCotizacionesMomDeltaSentence')
        ->and($byAgentWidget)->not->toContain('Resto del total')
        ->and($byAgentWidget)->not->toContain("'stack' => 'quotes'")
        ->and($byAgentWidget)->toContain("columnSpan = 'full'")
        ->and($byAgentView)->toContain('fi-metrics-cotizaciones-by-agent-chart')
        ->and($byAgentView)->toContain('cotizaciones-chart-mom-summary')
        ->and($byAgentView)->toContain('getMomSummaryViewData')
        ->and($byAgencyWidget)->toContain('byAgency(25)')
        ->and($byAgencyWidget)->toContain('MASTER o GENERAL')
        ->and($byAgencyWidget)->toContain('stacked: false')
        ->and($byAgencyWidget)->toContain('Total de cotizaciones')
        ->and($byAgencyWidget)->toContain('Convertidas en afiliación')
        ->and($byAgencyWidget)->toContain('getMomSummaryViewData')
        ->and($byAgencyWidget)->toContain("columnSpan = 'full'")
        ->and($byAgencyView)->toContain('fi-metrics-cotizaciones-by-agency-chart')
        ->and($byAgencyView)->toContain('cotizaciones-chart-mom-summary')
        ->and($momSummaryView)->toContain('Para entenderlo fácil')
        ->and($momSummaryView)->toContain('frente a')
        ->and($momSummaryView)->toContain('Convertidas en afiliación')
        ->and($momSummaryView)->toContain('Diferencia:')
        ->and($momSummaryView)->toContain('¿Cuántas se convierten?')
        ->and($blade)->toContain("type: @js('line')")
        ->and($blade)->toContain('fi-metrics-registration-mom__chart')
        ->and($blade)->toContain('frente al mes pasado')
        ->and($blade)->toContain('Diferencia:')
        ->and($blade)->toContain('fi-metrics-registration-mom__verdict')
        ->and($css)->toContain('fi-metrics-registration-mom__card--emerald')
        ->and($css)->toContain('fi-metrics-yoy--up')
        ->and($css)->toContain('fi-metrics-mom-summary')
        ->and($css)->toContain('fi-metrics-mom-summary__intro')
        ->and($chartConcern)->toContain("'emerald'")
        ->and($chartConcern)->toContain('datasetLabel')
        ->and($apiController)->toContain('individual_quotes')
        ->and($apiController)->toContain('corporate_quotes')
        ->and($apiController)->toContain('affiliations')
        ->and($apiController)->toContain('affiliation_corporates')
        ->and($apiController)->toContain('getCotizacionesByAgent')
        ->and($apiController)->toContain('getCotizacionesByAgency')
        ->and($apiController)->toContain('monthBounds')
        ->and($apiController)->not->toContain('yearToDateWindows')
        ->and($apiController)->toContain('AGENCY_TYPE_MASTER')
        ->and($apiController)->toContain('AGENCY_TYPE_GENERAL')
        ->and($apiController)->toContain('EJECUTADA')
        ->and($apiController)->toContain('year_series')
        ->and($apiController)->toContain('quotes_mom')
        ->and($apiRoutes)->toContain('/cotizaciones/status-comparison')
        ->and($apiRoutes)->toContain('/cotizaciones/by-agent')
        ->and($apiRoutes)->toContain('/cotizaciones/by-agency')
        ->and($apiRoutes)->toContain('getCotizacionesByAgency');
});
