<?php

declare(strict_types=1);

use App\Filament\Metrics\Pages\Negocios\Corretaje\CorretajeAgencies;
use App\Services\IntegracorpApi\CorretajeAgenciesMetricsClient;
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

it('consume el endpoint de metricas de agencias de corretaje desde integracorp-api', function (): void {
    Http::fake([
        'http://127.0.0.1:4000/api/metrics/corretaje/agencies' => Http::response([
            'success' => true,
            'data' => [
                'total_registered' => 63,
                'total_active' => 61,
                'total_masters' => 24,
                'total_generals' => 39,
            ],
        ], 200),
    ]);

    $summary = app(CorretajeAgenciesMetricsClient::class)->summary();

    expect($summary)->toBe([
        'total_registered' => 63,
        'total_active' => 61,
        'total_masters' => 24,
        'total_generals' => 39,
    ]);

    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/api/metrics/corretaje/agencies'));
});

it('cachea las metricas de agencias para evitar roundtrips repetidos', function (): void {
    Http::fake([
        'http://127.0.0.1:4000/api/metrics/corretaje/agencies' => Http::response([
            'success' => true,
            'data' => [
                'total_registered' => 10,
                'total_active' => 8,
                'total_masters' => 3,
                'total_generals' => 5,
            ],
        ], 200),
    ]);

    $client = app(CorretajeAgenciesMetricsClient::class);
    $client->summary();
    $client->summary();

    Http::assertSentCount(1);
});

it('consume el endpoint de captacion mensual de agencias master y general', function (): void {
    Http::fake([
        'http://127.0.0.1:4000/api/metrics/corretaje/agencies/registration-comparison' => Http::response([
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
                'master' => [
                    'current' => 5,
                    'previous' => 3,
                    'delta' => 2,
                    'percent_change' => 66.7,
                    'trend' => 'up',
                    'previous_was_zero' => false,
                ],
                'general' => [
                    'current' => 2,
                    'previous' => 4,
                    'delta' => -2,
                    'percent_change' => -50.0,
                    'trend' => 'down',
                    'previous_was_zero' => false,
                ],
                'year_series' => [
                    'year' => 2026,
                    'labels' => ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul'],
                    'master' => [1, 0, 2, 1, 0, 3, 5],
                    'general' => [0, 1, 1, 2, 0, 4, 2],
                ],
            ],
        ], 200),
    ]);

    $comparison = app(CorretajeAgenciesMetricsClient::class)->registrationComparison();

    expect($comparison['master']['current'])->toBe(5)
        ->and($comparison['master']['percent_change'])->toBe(66.7)
        ->and($comparison['master']['trend'])->toBe('up')
        ->and($comparison['general']['trend'])->toBe('down')
        ->and($comparison['general']['delta'])->toBe(-2)
        ->and($comparison['year_series']['year'])->toBe(2026)
        ->and($comparison['year_series']['master'][6])->toBe(5)
        ->and($comparison['year_series']['general'][5])->toBe(4);

    Http::assertSent(fn ($request): bool => str_ends_with(
        $request->url(),
        '/api/metrics/corretaje/agencies/registration-comparison'
    ));
});

it('consume el endpoint de agencias activas master y general por estado', function (): void {
    Http::fake([
        'http://127.0.0.1:4000/api/metrics/corretaje/agencies/by-state' => Http::response([
            'success' => true,
            'data' => [
                'items' => [
                    [
                        'state_id' => 10,
                        'state' => 'DISTRITO CAPITAL',
                        'total_masters' => 4,
                        'total_generals' => 9,
                        'total' => 13,
                    ],
                    [
                        'state_id' => null,
                        'state' => 'Sin estado',
                        'total_masters' => 1,
                        'total_generals' => 0,
                        'total' => 1,
                    ],
                ],
                'total_active' => 14,
                'total_masters' => 5,
                'total_generals' => 9,
            ],
        ], 200),
    ]);

    $byState = app(CorretajeAgenciesMetricsClient::class)->byState();

    expect($byState['total_active'])->toBe(14)
        ->and($byState['total_masters'])->toBe(5)
        ->and($byState['items'])->toHaveCount(2)
        ->and($byState['items'][0]['total_generals'])->toBe(9)
        ->and($byState['items'][1]['state_id'])->toBeNull();

    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/api/metrics/corretaje/agencies/by-state'));
});

it('consume los endpoints de afiliaciones activas individuales y corporativas por tipo', function (): void {
    Http::fake([
        'http://127.0.0.1:4000/api/metrics/corretaje/agencies/by-active-affiliations' => Http::response([
            'success' => true,
            'data' => [
                'items' => [
                    ['agency_type_id' => 1, 'agency_type' => 'MASTER', 'total' => 120],
                    ['agency_type_id' => 3, 'agency_type' => 'GENERAL', 'total' => 85],
                ],
                'total_masters' => 120,
                'total_generals' => 85,
                'total_affiliations' => 205,
            ],
        ], 200),
        'http://127.0.0.1:4000/api/metrics/corretaje/agencies/by-active-corporate-affiliations' => Http::response([
            'success' => true,
            'data' => [
                'items' => [
                    ['agency_type_id' => 1, 'agency_type' => 'MASTER', 'total' => 40],
                    ['agency_type_id' => 3, 'agency_type' => 'GENERAL', 'total' => 22],
                ],
                'total_masters' => 40,
                'total_generals' => 22,
                'total_affiliations' => 62,
            ],
        ], 200),
    ]);

    $client = app(CorretajeAgenciesMetricsClient::class);
    $individual = $client->byActiveAffiliations();
    $corporate = $client->byActiveCorporateAffiliations();

    expect($individual['total_affiliations'])->toBe(205)
        ->and($individual['total_masters'])->toBe(120)
        ->and($corporate['total_affiliations'])->toBe(62)
        ->and($corporate['total_generals'])->toBe(22);

    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/api/metrics/corretaje/agencies/by-active-affiliations'));
    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/api/metrics/corretaje/agencies/by-active-corporate-affiliations'));
});

it('consume el detalle de afiliaciones por agencia al hacer drill-down', function (): void {
    Http::fake([
        'http://127.0.0.1:4000/api/metrics/corretaje/agencies/by-active-affiliations/by-agency*' => Http::response([
            'success' => true,
            'data' => [
                'agency_type_id' => 1,
                'agency_type' => 'MASTER',
                'items' => [
                    ['agency_code' => 'TDG-100', 'agency_name' => 'MASTER CENTRAL', 'total' => 18],
                    ['agency_code' => 'AG-200', 'agency_name' => 'AGENCIA NORTE', 'total' => 10],
                ],
                'total_affiliations' => 28,
                'agencies_count' => 2,
                'limit' => 30,
            ],
        ], 200),
        'http://127.0.0.1:4000/api/metrics/corretaje/agencies/by-active-corporate-affiliations/by-agency*' => Http::response([
            'success' => true,
            'data' => [
                'agency_type_id' => 3,
                'agency_type' => 'GENERAL',
                'items' => [
                    ['agency_code' => 'AG-310', 'agency_name' => 'GENERAL SUR', 'total' => 4],
                ],
                'total_affiliations' => 4,
                'agencies_count' => 1,
                'limit' => 30,
            ],
        ], 200),
    ]);

    $client = app(CorretajeAgenciesMetricsClient::class);
    $masterDetail = $client->byActiveAffiliationsByAgency(1);
    $generalCorporate = $client->byActiveCorporateAffiliationsByAgency(3);

    expect($masterDetail['agency_type'])->toBe('MASTER')
        ->and($masterDetail['items'])->toHaveCount(2)
        ->and($masterDetail['items'][0]['agency_name'])->toBe('MASTER CENTRAL')
        ->and($generalCorporate['total_affiliations'])->toBe(4);

    Http::assertSent(fn ($request): bool => str_contains(
        $request->url(),
        '/api/metrics/corretaje/agencies/by-active-affiliations/by-agency'
    ) && str_contains($request->url(), 'agency_type_id=1'));
});

it('consume el endpoint de monto US$ de afiliaciones individuales y corporativas por agencia', function (): void {
    Http::fake([
        'http://127.0.0.1:4000/api/metrics/corretaje/agencies/by-active-affiliation-amount*' => Http::response([
            'success' => true,
            'data' => [
                'items' => [
                    [
                        'agency_code' => 'AG-001',
                        'agency_name' => 'MASTER CENTRAL',
                        'amount_individual' => 1200.5,
                        'amount_corporate' => 800.25,
                        'total_amount' => 2000.75,
                        'individual_count' => 4,
                        'corporate_count' => 2,
                    ],
                    [
                        'agency_code' => 'AG-002',
                        'agency_name' => 'GENERAL NORTE',
                        'amount_individual' => 500,
                        'amount_corporate' => 0,
                        'total_amount' => 500,
                        'individual_count' => 1,
                        'corporate_count' => 0,
                    ],
                ],
                'total_amount' => 2500.75,
                'total_individual_amount' => 1700.5,
                'total_corporate_amount' => 800.25,
                'total_agencies' => 2,
                'limit' => 20,
            ],
        ], 200),
    ]);

    $payload = app(CorretajeAgenciesMetricsClient::class)->byActiveAffiliationAmount(20);

    expect($payload['total_amount'])->toBe(2500.75)
        ->and($payload['total_individual_amount'])->toBe(1700.5)
        ->and($payload['total_corporate_amount'])->toBe(800.25)
        ->and($payload['items'])->toHaveCount(2)
        ->and($payload['items'][0]['amount_corporate'])->toBe(800.25)
        ->and($payload['items'][1]['amount_corporate'])->toBe(0.0);

    Http::assertSent(fn ($request): bool => str_contains(
        $request->url(),
        '/api/metrics/corretaje/agencies/by-active-affiliation-amount'
    ));
});

it('registra los stats y graficos en la pagina Corretaje Agencias', function (): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Metrics/Pages/Negocios/Corretaje/CorretajeAgencies.php');
    $widget = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Metrics/Widgets/CorretajeAgenciesStatsOverview.php');
    $momWidget = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Metrics/Widgets/CorretajeAgenciesRegistrationMomStats.php');
    $byStateChart = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Metrics/Widgets/CorretajeAgenciesByStateChart.php');
    $affiliationsChart = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Metrics/Widgets/CorretajeAgenciesByActiveAffiliationsChart.php');
    $corporateChart = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Metrics/Widgets/CorretajeAgenciesByActiveCorporateAffiliationsChart.php');
    $amountChart = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Metrics/Widgets/CorretajeAgenciesByActiveAffiliationAmountChart.php');
    $amountView = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/widgets/corretaje-agencies-by-active-affiliation-amount-chart.blade.php');
    $drillConcern = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Metrics/Widgets/Concerns/CorretajeAgenciesAffiliationsByTypeChart.php');
    $drillView = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/widgets/corretaje-agencies-affiliations-by-type-chart.blade.php');
    $apiController = file_get_contents(dirname(__DIR__, 2).'/../../integracorp-api/src/controllers/metrics.corretajeAgencies.controller.js');
    $apiRoutes = file_get_contents(dirname(__DIR__, 2).'/../../integracorp-api/src/routes/metrics.routes.js');

    $pageView = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/pages/corretaje-agencies.blade.php');

    expect(CorretajeAgencies::class)->toBeString()
        ->and($page)->toContain('CorretajeAgenciesStatsOverview::class')
        ->and($page)->toContain('CorretajeAgenciesRegistrationMomStats::class')
        ->and($page)->toContain('CorretajeAgenciesByStateChart::class')
        ->and($page)->toContain('CorretajeAgenciesByActiveAffiliationsChart::class')
        ->and($page)->toContain('CorretajeAgenciesByActiveCorporateAffiliationsChart::class')
        ->and($page)->toContain('CorretajeAgenciesByActiveAffiliationAmountChart::class')
        ->and($page)->toContain("'lg' => 2")
        ->and($page)->toContain("return 'filament.metrics.pages.corretaje-agencies'")
        ->and($page)->toMatch('/getHeaderWidgets\(\): array\s*\{\s*return \[\];/s')
        ->and(strpos($page, 'CorretajeAgenciesStatsOverview::class'))
        ->toBeLessThan(strpos($page, 'CorretajeAgenciesRegistrationMomStats::class'))
        ->and(strpos($page, 'CorretajeAgenciesRegistrationMomStats::class'))
        ->toBeLessThan(strpos($page, 'CorretajeAgenciesByStateChart::class'))
        ->and($pageView)->toContain('fi-metrics-module')
        ->and($widget)->toContain('TOTAL REGISTRADAS')
        ->and($widget)->toContain('AGENCIAS MASTER')
        ->and($widget)->toContain('CorretajeAgenciesMetricsClient')
        ->and($momWidget)->toContain('registrationComparison')
        ->and($momWidget)->toContain('year_series')
        ->and($momWidget)->toContain('buildRegistrationMomYearChart')
        ->and($momWidget)->toContain('filament.metrics.widgets.corretaje-registration-mom')
        ->and(file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/widgets/corretaje-registration-mom.blade.php'))
        ->toContain('fi-metrics-registration-mom__chart')
        ->and(file_get_contents(dirname(__DIR__, 2).'/../../integracorp-api/src/controllers/metrics.corretajeAgencies.controller.js'))
        ->toContain('monthlyAgencyRegistrationsForYear')
        ->and($apiController)->toContain('DATE(created_at)')
        ->and($apiController)->toContain("date_field: 'created_at'")
        ->and($byStateChart)->toContain("columnSpan = 'full'")
        ->and($byStateChart)->toContain('total_masters')
        ->and($byStateChart)->toContain('total_generals')
        ->and($byStateChart)->toContain("'label' => 'MASTER'")
        ->and($byStateChart)->toContain("'label' => 'GENERAL'")
        ->and($byStateChart)->toContain('categoryPercentage: 0.86')
        ->and($byStateChart)->toContain('barPercentage: 0.95')
        ->and($byStateChart)->toContain('padding: { top: 28')
        ->and($affiliationsChart)->toContain('byActiveAffiliations')
        ->and($affiliationsChart)->toContain('byActiveAffiliationsByAgency')
        ->and($corporateChart)->toContain('byActiveCorporateAffiliations')
        ->and($corporateChart)->toContain('byActiveCorporateAffiliationsByAgency')
        ->and($amountChart)->toContain('byActiveAffiliationAmount')
        ->and($amountChart)->toContain("columnSpan = 'full'")
        ->and($amountChart)->toContain("'label' => 'Individuales'")
        ->and($amountChart)->toContain("'label' => 'Corporativas'")
        ->and($amountChart)->toContain("'skipNull' => true")
        ->and($amountChart)->toContain("'valueLabelFormat' => 'usd'")
        ->and($amountView)->not->toContain('fi-metrics-agents-by-state-chart__chips')
        ->and($drillConcern)->toContain('handleChartClick')
        ->and($drillConcern)->toContain('resetDrillDown')
        ->and($drillConcern)->toContain('drillAgencyType')
        ->and($drillConcern)->toContain('categoryPercentage: {$isDetail} ? 0.86 : 0.72')
        ->and($drillConcern)->toContain('barPercentage: {$isDetail} ? 0.94 : 0.9')
        ->and($drillConcern)->toContain('padding: { top: 28')
        ->and($drillView)->toContain('fi-metrics-agencies-affiliations-drill__stage')
        ->and($drillView)->toContain('wire:click="resetDrillDown"')
        ->and($drillView)->not->toContain('fi-metrics-agents-by-state-chart__chips')
        ->and(file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/pages/corretaje-agencies.blade.php'))
        ->toContain('filament.metrics.partials.bar-value-labels-plugin')
        ->and($apiController)->toContain('getCorretajeAgenciesByState')
        ->and($apiController)->toContain('getCorretajeAgenciesByActiveAffiliations')
        ->and($apiController)->toContain('getCorretajeAgenciesByActiveCorporateAffiliations')
        ->and($apiController)->toContain('getCorretajeAgenciesByActiveAffiliationsByAgency')
        ->and($apiController)->toContain('getCorretajeAgenciesByActiveAffiliationAmount')
        ->and($apiController)->toContain('listActiveAffiliationsByAgency')
        ->and($apiController)->toContain('af.code_agency = af.owner_code')
        ->and($apiController)->toContain('agent_id IS NULL')
        ->and($apiController)->toContain('affiliation_corporates')
        ->and($apiRoutes)->toContain('/corretaje/agencies/by-state')
        ->and($apiRoutes)->toContain('/corretaje/agencies/by-active-affiliations')
        ->and($apiRoutes)->toContain('/corretaje/agencies/by-active-affiliations/by-agency')
        ->and($apiRoutes)->toContain('/corretaje/agencies/by-active-corporate-affiliations')
        ->and($apiRoutes)->toContain('/corretaje/agencies/by-active-corporate-affiliations/by-agency')
        ->and($apiRoutes)->toContain('/corretaje/agencies/by-active-affiliation-amount');
});
