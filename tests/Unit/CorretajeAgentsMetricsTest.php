<?php

declare(strict_types=1);

use App\Filament\Metrics\Pages\Negocios\Corretaje\CorretajeAgents;
use App\Services\IntegracorpApi\CorretajeAgentsMetricsClient;
use App\Services\IntegracorpApi\IntegracorpApiClient;
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

it('consume el endpoint de metricas de agentes de corretaje desde integracorp-api', function (): void {
    Http::fake([
        'http://127.0.0.1:4000/api/metrics/corretaje/agents' => Http::response([
            'success' => true,
            'data' => [
                'total_registered' => 423,
                'total_active' => 405,
                'total_superiors' => 396,
                'total_subagents' => 22,
            ],
        ], 200),
    ]);

    $summary = app(CorretajeAgentsMetricsClient::class)->summary();

    expect($summary)->toBe([
        'total_registered' => 423,
        'total_active' => 405,
        'total_superiors' => 396,
        'total_subagents' => 22,
    ]);

    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/api/metrics/corretaje/agents'));
});

it('consume el endpoint de agentes activos por estado', function (): void {
    Http::fake([
        'http://127.0.0.1:4000/api/metrics/corretaje/agents/by-state' => Http::response([
            'success' => true,
            'data' => [
                'items' => [
                    ['state_id' => 10, 'state' => 'DISTRITO CAPITAL', 'total' => 64],
                    ['state_id' => null, 'state' => 'Sin estado', 'total' => 12],
                ],
                'total_active' => 76,
            ],
        ], 200),
    ]);

    $byState = app(CorretajeAgentsMetricsClient::class)->byState();

    expect($byState['total_active'])->toBe(76)
        ->and($byState['items'])->toHaveCount(2)
        ->and($byState['items'][0])->toBe([
            'state_id' => 10,
            'state' => 'DISTRITO CAPITAL',
            'total' => 64,
        ])
        ->and($byState['items'][1]['state_id'])->toBeNull();

    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/api/metrics/corretaje/agents/by-state'));
});

it('cachea las metricas de agentes para evitar roundtrips repetidos', function (): void {
    Http::fake([
        'http://127.0.0.1:4000/api/metrics/corretaje/agents' => Http::response([
            'success' => true,
            'data' => [
                'total_registered' => 10,
                'total_active' => 8,
                'total_superiors' => 7,
                'total_subagents' => 1,
            ],
        ], 200),
        'http://127.0.0.1:4000/api/metrics/corretaje/agents/by-state' => Http::response([
            'success' => true,
            'data' => [
                'items' => [
                    ['state_id' => 1, 'state' => 'ZULIA', 'total' => 8],
                ],
                'total_active' => 8,
            ],
        ], 200),
    ]);

    $client = app(CorretajeAgentsMetricsClient::class);

    $client->summary();
    $client->summary();
    $client->byState();
    $client->byState();

    Http::assertSentCount(2);
});

it('consume el endpoint de agentes por afiliaciones activas', function (): void {
    Http::fake([
        'http://127.0.0.1:4000/api/metrics/corretaje/agents/by-active-affiliations*' => Http::response([
            'success' => true,
            'data' => [
                'items' => [
                    [
                        'agent_id' => 234,
                        'agent_name' => 'IN-HOUSE',
                        'code_agent' => null,
                        'total_individual' => 17,
                        'total_corporate' => 0,
                        'total' => 17,
                    ],
                    [
                        'agent_id' => 87,
                        'agent_name' => 'NUMIDIA JOSEFINA LOZADA SUAREZ',
                        'code_agent' => 'AGT-087',
                        'total_individual' => 1,
                        'total_corporate' => 2,
                        'total' => 3,
                    ],
                ],
                'total_affiliations' => 178,
                'total_individual_affiliations' => 172,
                'total_corporate_affiliations' => 6,
                'total_agents' => 65,
                'limit' => 20,
            ],
        ], 200),
    ]);

    $payload = app(CorretajeAgentsMetricsClient::class)->byActiveAffiliations(20);

    expect($payload['total_affiliations'])->toBe(178)
        ->and($payload['total_individual_affiliations'])->toBe(172)
        ->and($payload['total_corporate_affiliations'])->toBe(6)
        ->and($payload['total_agents'])->toBe(65)
        ->and($payload['items'])->toHaveCount(2)
        ->and($payload['items'][0]['total'])->toBe(17)
        ->and($payload['items'][0]['total_corporate'])->toBe(0)
        ->and($payload['items'][1]['total_individual'])->toBe(1)
        ->and($payload['items'][1]['total_corporate'])->toBe(2)
        ->and($payload['items'][1]['code_agent'])->toBe('AGT-087');

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/api/metrics/corretaje/agents/by-active-affiliations'));
});

it('consume el endpoint de monto US$ de afiliaciones activas por agente', function (): void {
    Http::fake([
        'http://127.0.0.1:4000/api/metrics/corretaje/agents/by-active-affiliation-amount*' => Http::response([
            'success' => true,
            'data' => [
                'items' => [
                    [
                        'agent_id' => 247,
                        'agent_name' => 'Maira Cedeño',
                        'code_agent' => null,
                        'affiliations_count' => 9,
                        'total_amount' => 4288.75,
                    ],
                ],
                'total_affiliations' => 172,
                'total_agents' => 63,
                'total_amount' => 55000.5,
                'limit' => 20,
            ],
        ], 200),
    ]);

    $payload = app(CorretajeAgentsMetricsClient::class)->byActiveAffiliationAmount(20);

    expect($payload['total_amount'])->toBe(55000.5)
        ->and($payload['items'][0]['affiliations_count'])->toBe(9)
        ->and($payload['items'][0]['total_amount'])->toBe(4288.75);

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/api/metrics/corretaje/agents/by-active-affiliation-amount'));
});

it('consume el endpoint de tendencia de ventas US$ por estado', function (): void {
    Http::fake([
        'http://127.0.0.1:4000/api/metrics/corretaje/agents/sales-by-state' => Http::response([
            'success' => true,
            'data' => [
                'items' => [
                    [
                        'state_id' => 10,
                        'state' => 'DISTRITO CAPITAL',
                        'affiliations_count' => 72,
                        'total_amount' => 20346.04,
                    ],
                    [
                        'state_id' => 7,
                        'state' => 'CARABOBO',
                        'affiliations_count' => 17,
                        'total_amount' => 3739.46,
                    ],
                ],
                'total_affiliations' => 170,
                'total_agents' => 55,
                'total_amount' => 35000.0,
                'states_count' => 24,
                'top_state' => [
                    'state_id' => 10,
                    'state' => 'DISTRITO CAPITAL',
                    'affiliations_count' => 72,
                    'total_amount' => 20346.04,
                ],
            ],
        ], 200),
    ]);

    $payload = app(CorretajeAgentsMetricsClient::class)->salesByState();

    expect($payload['states_count'])->toBe(24)
        ->and($payload['total_amount'])->toBe(35000.0)
        ->and($payload['top_state']['state'])->toBe('DISTRITO CAPITAL')
        ->and($payload['items'][0]['total_amount'])->toBe(20346.04);

    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/api/metrics/corretaje/agents/sales-by-state'));
});

it('consume el endpoint de captacion mensual de agentes superiores y subagentes', function (): void {
    Http::fake([
        'http://127.0.0.1:4000/api/metrics/corretaje/agents/registration-comparison' => Http::response([
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
                'superiors' => [
                    'current' => 12,
                    'previous' => 8,
                    'delta' => 4,
                    'percent_change' => 50.0,
                    'trend' => 'up',
                    'previous_was_zero' => false,
                ],
                'subagents' => [
                    'current' => 1,
                    'previous' => 3,
                    'delta' => -2,
                    'percent_change' => -66.7,
                    'trend' => 'down',
                    'previous_was_zero' => false,
                ],
                'year_series' => [
                    'year' => 2026,
                    'labels' => ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul'],
                    'superiors' => [2, 1, 0, 3, 4, 8, 12],
                    'subagents' => [1, 0, 2, 1, 0, 3, 1],
                ],
            ],
        ], 200),
    ]);

    $comparison = app(CorretajeAgentsMetricsClient::class)->registrationComparison();

    expect($comparison['superiors']['current'])->toBe(12)
        ->and($comparison['superiors']['percent_change'])->toBe(50.0)
        ->and($comparison['superiors']['trend'])->toBe('up')
        ->and($comparison['subagents']['trend'])->toBe('down')
        ->and($comparison['subagents']['delta'])->toBe(-2)
        ->and($comparison['year_series']['year'])->toBe(2026)
        ->and($comparison['year_series']['labels'])->toHaveCount(7)
        ->and($comparison['year_series']['superiors'][6])->toBe(12)
        ->and($comparison['year_series']['subagents'][5])->toBe(3);

    Http::assertSent(fn ($request): bool => str_ends_with(
        $request->url(),
        '/api/metrics/corretaje/agents/registration-comparison'
    ));
});

it('registra los stats y los graficos en la pagina Corretaje Agentes', function (): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Metrics/Pages/Negocios/Corretaje/CorretajeAgents.php');
    $widget = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Metrics/Widgets/CorretajeAgentsStatsOverview.php');
    $momWidget = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Metrics/Widgets/CorretajeAgentsRegistrationMomStats.php');
    $chart = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Metrics/Widgets/CorretajeAgentsByStateChart.php');
    $affiliationsChart = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Metrics/Widgets/CorretajeAgentsByActiveAffiliationsChart.php');
    $amountChart = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Metrics/Widgets/CorretajeAgentsByActiveAffiliationAmountChart.php');
    $salesRadarChart = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Metrics/Widgets/CorretajeAgentsSalesByStateRadarChart.php');
    $chartView = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/widgets/corretaje-agents-by-state-chart.blade.php');
    $affiliationsView = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/widgets/corretaje-agents-by-active-affiliations-chart.blade.php');
    $amountView = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/widgets/corretaje-agents-by-active-affiliation-amount-chart.blade.php');
    $salesRadarView = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/widgets/corretaje-agents-sales-by-state-radar-chart.blade.php');
    $apiController = file_get_contents(dirname(__DIR__, 2).'/../../integracorp-api/src/controllers/metrics.corretajeAgents.controller.js');
    $apiRoutes = file_get_contents(dirname(__DIR__, 2).'/../../integracorp-api/src/routes/metrics.routes.js');

    $pageView = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/pages/corretaje-agents.blade.php');

    expect(CorretajeAgents::class)->toBeString()
        ->and($page)->toContain('CorretajeAgentsStatsOverview::class')
        ->and($page)->toContain('CorretajeAgentsRegistrationMomStats::class')
        ->and($page)->toContain('CorretajeAgentsByStateChart::class')
        ->and($page)->toContain('CorretajeAgentsByActiveAffiliationsChart::class')
        ->and($page)->toContain('CorretajeAgentsByActiveAffiliationAmountChart::class')
        ->and($page)->toContain('CorretajeAgentsSalesByStateRadarChart::class')
        ->and($page)->not->toContain('CorretajeAgentsByStateRadarChart')
        ->and($page)->toContain("'lg' => 2")
        ->and($page)->toContain("return 'filament.metrics.pages.corretaje-agents'")
        ->and($page)->toMatch('/getHeaderWidgets\(\): array\s*\{\s*return \[\];/s')
        ->and(strpos($page, 'CorretajeAgentsStatsOverview::class'))
        ->toBeLessThan(strpos($page, 'CorretajeAgentsRegistrationMomStats::class'))
        ->and(strpos($page, 'CorretajeAgentsRegistrationMomStats::class'))
        ->toBeLessThan(strpos($page, 'CorretajeAgentsByStateChart::class'))
        ->and($pageView)->toContain('fi-metrics-module')
        ->and($widget)->toContain('TOTAL REGISTRADOS')
        ->and($widget)->toContain('protected static bool $isLazy = false')
        ->and($widget)->toContain('CorretajeAgentsMetricsClient')
        ->and($momWidget)->toContain('Superó captación')
        ->and($momWidget)->toContain('Bajó captación')
        ->and($momWidget)->toContain('registrationComparison')
        ->and($momWidget)->toContain('Agentes SUPERIORES')
        ->and($momWidget)->toContain('SUBAGENTES')
        ->and($momWidget)->toContain('year_series')
        ->and($momWidget)->toContain('buildRegistrationMomYearChart')
        ->and($momWidget)->toContain('filament.metrics.widgets.corretaje-registration-mom')
        ->and(file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/widgets/corretaje-registration-mom.blade.php'))
        ->toContain("type: @js('line')")
        ->and(file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/widgets/corretaje-registration-mom.blade.php'))
        ->toContain('fi-metrics-registration-mom__chart')
        ->and(file_get_contents(dirname(__DIR__, 2).'/../../integracorp-api/src/controllers/metrics.corretajeAgents.controller.js'))
        ->toContain('year_series')
        ->and(file_get_contents(dirname(__DIR__, 2).'/../../integracorp-api/src/controllers/metrics.corretajeAgents.controller.js'))
        ->toContain('monthlyAgentRegistrationsForYear')
        ->and($chart)->toContain('Agentes activos por estado')
        ->and($chart)->toContain("indexAxis: 'x'")
        ->and($chart)->toContain("'topLeft' => 10")
        ->and($chart)->toContain("'bottomLeft' => 0")
        ->and($chart)->toContain('categoryPercentage: 0.88')
        ->and($chart)->toContain('barPercentage: 0.94')
        ->and($chart)->toContain('padding: { top: 28')
        ->and($chart)->toContain('byState()')
        ->and($affiliationsChart)->toContain('Agentes por afiliaciones activas')
        ->and($affiliationsChart)->toContain('byActiveAffiliations')
        ->and($affiliationsChart)->toContain("'label' => 'Individuales'")
        ->and($affiliationsChart)->toContain("'label' => 'Corporativas'")
        ->and($affiliationsChart)->toContain("'skipNull' => true")
        ->and($affiliationsChart)->toContain('total_corporate')
        ->and($apiController)->toContain('affiliation_corporates')
        ->and($apiController)->toContain('total_corporate')
        ->and($affiliationsChart)->toContain('categoryPercentage: 0.78')
        ->and($amountChart)->toContain('Monto US$ de afiliaciones activas por agente')
        ->and($amountChart)->toContain('byActiveAffiliationAmount')
        ->and($amountChart)->toContain('total_amount')
        ->and($amountChart)->toContain("columnSpan = 'full'")
        ->and($amountChart)->toContain('categoryPercentage: 0.88')
        ->and($salesRadarChart)->toContain('Radar · tendencia de ventas US$ por estado')
        ->and($salesRadarChart)->toContain('salesByState')
        ->and($salesRadarChart)->toContain('POINT_COLORS')
        ->and($salesRadarChart)->not->toContain('categoryPercentage:')
        ->and($chartView)->toContain('fi-metrics-agents-by-state-chart')
        ->and($chartView)->not->toContain('fi-metrics-agents-by-state-chart__chips')
        ->and($affiliationsView)->not->toContain('fi-metrics-agents-by-state-chart__chips')
        ->and($amountView)->not->toContain('fi-metrics-agents-by-state-chart__chips')
        ->and($amountChart)->toContain("'valueLabelFormat' => 'usd'")
        ->and($salesRadarView)->not->toContain('fi-metrics-agents-by-state-chart__chips')
        ->and(file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/pages/corretaje-agents.blade.php'))
        ->toContain('filament.metrics.partials.bar-value-labels-plugin')
        ->and(file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/partials/bar-value-labels-plugin.blade.php'))
        ->toContain('fiMetricsBarValueLabels')
        ->and(file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/partials/bar-value-labels-plugin.blade.php'))
        ->toContain("format === 'usd'")
        ->and(file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/partials/bar-value-labels-plugin.blade.php'))
        ->toContain('US$ ${amount}')
        ->and(file_exists(dirname(__DIR__, 2).'/app/Filament/Metrics/Widgets/CorretajeAgentsByStateRadarChart.php'))->toBeFalse()
        ->and($apiController)->toContain("status = 'ACTIVO'")
        ->and($apiController)->toContain("status = 'ACTIVA'")
        ->and($apiController)->toContain('getCorretajeAgentsByActiveAffiliations')
        ->and($apiController)->toContain('getCorretajeAgentsByActiveAffiliationAmount')
        ->and($apiController)->toContain('getCorretajeAgentsSalesByState')
        ->and($apiController)->toContain('getCorretajeAgentsRegistrationComparison')
        ->and($apiController)->toContain('DATE(created_at)')
        ->and($apiController)->toContain("date_field: 'created_at'")
        ->and($apiController)->toContain('SUM(af.total_amount)')
        ->and($apiController)->toContain('agent_id IS NOT NULL')
        ->and($apiRoutes)->toContain('/corretaje/agents/by-state')
        ->and($apiRoutes)->toContain('/corretaje/agents/by-active-affiliations')
        ->and($apiRoutes)->toContain('/corretaje/agents/by-active-affiliation-amount')
        ->and($apiRoutes)->toContain('/corretaje/agents/sales-by-state')
        ->and($apiRoutes)->toContain('/corretaje/agents/registration-comparison');
});

it('usa el cliente HTTP base de integracorp-api', function (): void {
    config([
        'services.integracorp_api.base_url' => 'http://example.test:4000/',
        'services.integracorp_api.timeout' => 5,
    ]);

    $client = app(IntegracorpApiClient::class);

    expect($client->baseUrl())->toBe('http://example.test:4000')
        ->and($client->timeout())->toBe(5);
});

it('unifica el fondo liquid glass del panel metrics para evitar franjas', function (): void {
    $theme = file_get_contents(dirname(__DIR__, 2).'/resources/css/filament/admin/theme.css');

    expect($theme)->toContain('--metrics-bg-dark')
        ->and($theme)->toContain('background-attachment: fixed')
        ->and($theme)->toContain('.fi-body.fi-panel-metrics')
        ->and($theme)->toContain('.fi-panel-metrics .fi-main-ctn')
        ->and($theme)->toContain('background: transparent !important');
});

it('declara skeleton de carga y optimizaciones de render para graficos metrics', function (): void {
    $theme = file_get_contents(dirname(__DIR__, 2).'/resources/css/filament/admin/theme.css');
    $placeholder = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/partials/chart-loading-placeholder.blade.php');
    $trait = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Metrics/Widgets/Concerns/HasMetricsChartPerformance.php');
    $stateChart = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Metrics/Widgets/CorretajeAgentsByStateChart.php');

    expect($theme)->toContain('.fi-metrics-chart-loading')
        ->and($theme)->toContain('fi-metrics-bar-bounce')
        ->and($theme)->toContain('fi-metrics-chart-reveal')
        ->and($theme)->toContain('prefers-reduced-motion')
        ->and($theme)->toContain('content-visibility: auto')
        ->and($placeholder)->toContain('fi-metrics-chart-loading__bars')
        ->and($placeholder)->toContain('aria-busy="true"')
        ->and($trait)->toContain('chart-loading-placeholder')
        ->and($stateChart)->toContain('HasMetricsChartPerformance')
        ->and($stateChart)->toContain('devicePixelRatio: Math.min(window.devicePixelRatio || 1, 1.5)')
        ->and($stateChart)->toContain('duration: 260');
});
