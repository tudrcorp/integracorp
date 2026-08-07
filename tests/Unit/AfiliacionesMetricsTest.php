<?php

declare(strict_types=1);

use App\Filament\Metrics\Pages\Afiliaciones;
use App\Services\IntegracorpApi\AfiliacionesMetricsClient;
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

it('consume el endpoint de afiliados individuales y corporativos', function (): void {
    Http::fake([
        'http://127.0.0.1:4000/api/metrics/afiliaciones/status-comparison' => Http::response([
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
                'individual' => [
                    'current' => 80,
                    'previous' => 100,
                    'delta' => -20,
                    'percent_change' => -20.0,
                    'trend' => 'down',
                    'previous_was_zero' => false,
                ],
                'corporate' => [
                    'current' => 12,
                    'previous' => 8,
                    'delta' => 4,
                    'percent_change' => 50.0,
                    'trend' => 'up',
                    'previous_was_zero' => false,
                ],
                'year_series' => [
                    'year' => 2026,
                    'labels' => ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul'],
                    'individual' => [10, 12, 15, 18, 20, 100, 80],
                    'corporate' => [1, 2, 2, 3, 4, 8, 12],
                ],
            ],
        ], 200),
    ]);

    $comparison = app(AfiliacionesMetricsClient::class)->statusComparison();

    expect($comparison['individual']['current'])->toBe(80)
        ->and($comparison['individual']['percent_change'])->toBe(-20.0)
        ->and($comparison['individual']['trend'])->toBe('down')
        ->and($comparison['corporate']['trend'])->toBe('up')
        ->and($comparison['corporate']['delta'])->toBe(4)
        ->and($comparison['year_series']['year'])->toBe(2026)
        ->and($comparison['year_series']['labels'])->toHaveCount(7)
        ->and($comparison['year_series']['individual'][6])->toBe(80)
        ->and($comparison['year_series']['corporate'][5])->toBe(8);

    Http::assertSent(fn ($request): bool => str_ends_with(
        $request->url(),
        '/api/metrics/afiliaciones/status-comparison'
    ));
});

it('consume los endpoints mensuales y diarios de afiliaciones', function (): void {
    Http::fake([
        'http://127.0.0.1:4000/api/metrics/afiliaciones/by-month*' => Http::response([
            'success' => true,
            'data' => [
                'kind' => 'individual',
                'year' => 2026,
                'through_month' => 7,
                'labels' => ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul'],
                'values' => [10, 12, 15, 18, 20, 100, 80],
                'total' => 255,
                'peak_month' => 6,
                'peak_label' => 'Jun',
                'peak_total' => 100,
            ],
        ], 200),
        'http://127.0.0.1:4000/api/metrics/afiliaciones/by-day*' => Http::response([
            'success' => true,
            'data' => [
                'kind' => 'corporate',
                'year' => 2026,
                'month' => 6,
                'month_label' => 'Jun',
                'labels' => ['01', '02', '03'],
                'values' => [1, 0, 2],
                'total' => 3,
                'peak_day' => 3,
                'peak_label' => '03',
                'peak_total' => 2,
            ],
        ], 200),
    ]);

    $byMonth = app(AfiliacionesMetricsClient::class)->byMonth('individual');
    $byDay = app(AfiliacionesMetricsClient::class)->byDay('corporate', 2026, 6);

    expect($byMonth['total'])->toBe(255)
        ->and($byMonth['peak_label'])->toBe('Jun')
        ->and($byMonth['values'][5])->toBe(100)
        ->and($byDay['month'])->toBe(6)
        ->and($byDay['total'])->toBe(3)
        ->and($byDay['peak_day'])->toBe(3)
        ->and($byDay['values'][2])->toBe(2);

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/api/metrics/afiliaciones/by-month'));
    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/api/metrics/afiliaciones/by-day'));
});

it('consume el endpoint de demanda mensual por plan', function (): void {
    Http::fake([
        'http://127.0.0.1:4000/api/metrics/afiliaciones/by-plan-month' => Http::response([
            'success' => true,
            'data' => [
                'scope' => 'combined',
                'year' => 2026,
                'through_month' => 7,
                'labels' => ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul'],
                'plans' => [
                    [
                        'plan_id' => 1,
                        'code' => 'TDEC-PL-0001',
                        'label' => 'Plan Inicial',
                        'values' => [5, 6, 7, 8, 9, 10, 4],
                        'total' => 49,
                    ],
                    [
                        'plan_id' => 2,
                        'code' => 'TDEC-PL-0002',
                        'label' => 'Plan Ideal',
                        'values' => [2, 3, 4, 5, 6, 20, 8],
                        'total' => 48,
                    ],
                    [
                        'plan_id' => 3,
                        'code' => 'TDEC-PL-0003',
                        'label' => 'Plan Especial',
                        'values' => [1, 1, 1, 2, 2, 3, 1],
                        'total' => 11,
                    ],
                ],
                'total' => 108,
                'most_demanded' => [
                    'plan_id' => 1,
                    'label' => 'Plan Inicial',
                    'total' => 49,
                ],
                'least_demanded' => [
                    'plan_id' => 3,
                    'label' => 'Plan Especial',
                    'total' => 11,
                ],
            ],
        ], 200),
    ]);

    $payload = app(AfiliacionesMetricsClient::class)->byPlanMonth();

    expect($payload['plans'])->toHaveCount(3)
        ->and($payload['total'])->toBe(108)
        ->and($payload['most_demanded']['label'])->toBe('Plan Inicial')
        ->and($payload['least_demanded']['label'])->toBe('Plan Especial')
        ->and($payload['plans'][1]['values'][5])->toBe(20);

    Http::assertSent(fn ($request): bool => str_ends_with(
        $request->url(),
        '/api/metrics/afiliaciones/by-plan-month'
    ));
});

it('consume el endpoint de montos US$ por plan', function (): void {
    Http::fake([
        'http://127.0.0.1:4000/api/metrics/afiliaciones/by-plan-amount*' => Http::response([
            'success' => true,
            'data' => [
                'kind' => 'individual',
                'currency' => 'USD',
                'scope' => 'active_stock',
                'plans' => [
                    [
                        'plan_id' => 1,
                        'code' => 'TDEC-PL-0001',
                        'label' => 'Plan Inicial',
                        'amount' => 1200.5,
                        'count' => 10,
                    ],
                    [
                        'plan_id' => 2,
                        'code' => 'TDEC-PL-0002',
                        'label' => 'Plan Ideal',
                        'amount' => 3400.0,
                        'count' => 8,
                    ],
                    [
                        'plan_id' => 3,
                        'code' => 'TDEC-PL-0003',
                        'label' => 'Plan Especial',
                        'amount' => 890.25,
                        'count' => 3,
                    ],
                ],
                'total_amount' => 5490.75,
                'total_count' => 21,
                'top_plan' => [
                    'plan_id' => 2,
                    'label' => 'Plan Ideal',
                    'amount' => 3400.0,
                ],
            ],
        ], 200),
    ]);

    $payload = app(AfiliacionesMetricsClient::class)->byPlanAmount('individual');

    expect($payload['total_amount'])->toBe(5490.75)
        ->and($payload['top_plan']['label'])->toBe('Plan Ideal')
        ->and($payload['plans'][0]['amount'])->toBe(1200.5)
        ->and($payload['plans'][2]['count'])->toBe(3);

    Http::assertSent(fn ($request): bool => str_contains(
        $request->url(),
        '/api/metrics/afiliaciones/by-plan-amount'
    ));
});

it('consume el endpoint combinado de montos US$ por tipo y plan', function (): void {
    Http::fake([
        'http://127.0.0.1:4000/api/metrics/afiliaciones/by-plan-amount-combined*' => Http::response([
            'success' => true,
            'data' => [
                'currency' => 'USD',
                'scope' => 'active_stock',
                'segments' => [
                    [
                        'kind' => 'individual',
                        'kind_label' => 'Individual',
                        'plan_id' => 3,
                        'code' => 'TDEC-PL-0003',
                        'plan_label' => 'Plan Especial',
                        'label' => 'Ind. Especial',
                        'amount' => 40113.88,
                        'count' => 100,
                    ],
                    [
                        'kind' => 'corporate',
                        'kind_label' => 'Corporativa',
                        'plan_id' => 3,
                        'code' => 'TDEC-PL-0003',
                        'plan_label' => 'Plan Especial',
                        'label' => 'Corp. Especial',
                        'amount' => 9227.25,
                        'count' => 12,
                    ],
                ],
                'by_kind' => [
                    'individual' => [
                        'total_amount' => 49834.63,
                        'total_count' => 218,
                    ],
                    'corporate' => [
                        'total_amount' => 18154.5,
                        'total_count' => 36,
                    ],
                ],
                'total_amount' => 67989.13,
                'total_count' => 254,
                'top_segment' => [
                    'kind' => 'individual',
                    'kind_label' => 'Individual',
                    'plan_id' => 3,
                    'plan_label' => 'Plan Especial',
                    'label' => 'Ind. Especial',
                    'amount' => 40113.88,
                ],
            ],
        ], 200),
    ]);

    $payload = app(AfiliacionesMetricsClient::class)->byPlanAmountCombined();

    expect($payload['total_amount'])->toBe(67989.13)
        ->and($payload['by_kind']['individual']['total_amount'])->toBe(49834.63)
        ->and($payload['by_kind']['corporate']['total_count'])->toBe(36)
        ->and($payload['top_segment']['label'])->toBe('Ind. Especial')
        ->and($payload['segments'])->toHaveCount(2);

    Http::assertSent(fn ($request): bool => str_contains(
        $request->url(),
        '/api/metrics/afiliaciones/by-plan-amount-combined'
    ));
});

it('consume el endpoint de afiliaciones por estado', function (): void {
    Http::fake([
        'http://127.0.0.1:4000/api/metrics/afiliaciones/by-state*' => Http::response([
            'success' => true,
            'data' => [
                'scope' => 'active_stock',
                'metric' => 'count',
                'states' => [
                    [
                        'state_id' => 1,
                        'label' => 'Miranda',
                        'count' => 120,
                        'is_other' => false,
                    ],
                    [
                        'state_id' => 2,
                        'label' => 'Distrito Capital',
                        'count' => 80,
                        'is_other' => false,
                    ],
                    [
                        'state_id' => null,
                        'label' => 'Otros',
                        'count' => 25,
                        'is_other' => true,
                    ],
                ],
                'total_count' => 225,
                'states_count' => 10,
                'states_shown' => 2,
                'others_count' => 25,
                'top_state' => [
                    'state_id' => 1,
                    'label' => 'Miranda',
                    'count' => 120,
                ],
            ],
        ], 200),
    ]);

    $payload = app(AfiliacionesMetricsClient::class)->byState();

    expect($payload['total_count'])->toBe(225)
        ->and($payload['top_state']['label'])->toBe('Miranda')
        ->and($payload['states'])->toHaveCount(3)
        ->and($payload['states'][2]['is_other'])->toBeTrue()
        ->and($payload['others_count'])->toBe(25);

    Http::assertSent(fn ($request): bool => str_contains(
        $request->url(),
        '/api/metrics/afiliaciones/by-state'
    ));
});

it('registra el widget MoM y los graficos mensuales en la pagina Afiliaciones', function (): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Metrics/Pages/Afiliaciones.php');
    $momWidget = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Metrics/Widgets/AfiliacionesStatusMomStats.php');
    $plansChart = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Metrics/Widgets/AfiliacionesPlansDemandChart.php');
    $plansView = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/widgets/afiliaciones-plans-demand-chart.blade.php');
    $combinedPie = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Metrics/Widgets/AfiliacionesPlanAmountCombinedPieChart.php');
    $statePie = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Metrics/Widgets/AfiliacionesByStatePieChart.php');
    $combinedPieView = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/widgets/afiliaciones-plan-amount-combined-pie-chart.blade.php');
    $statePieView = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/widgets/afiliaciones-by-state-pie-chart.blade.php');
    $drillConcern = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Metrics/Widgets/Concerns/AfiliacionesByMonthDrillChart.php');
    $individualChart = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Metrics/Widgets/AfiliacionesIndividualesByMonthChart.php');
    $corporateChart = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Metrics/Widgets/AfiliacionesCorporativasByMonthChart.php');
    $drillView = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/widgets/afiliaciones-by-month-drill-chart.blade.php');
    $client = file_get_contents(dirname(__DIR__, 2).'/app/Services/IntegracorpApi/AfiliacionesMetricsClient.php');
    $apiController = file_get_contents(dirname(__DIR__, 2).'/../../integracorp-api/src/controllers/metrics.afiliaciones.controller.js');
    $apiRoutes = file_get_contents(dirname(__DIR__, 2).'/../../integracorp-api/src/routes/metrics.routes.js');
    $blade = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/widgets/corretaje-registration-mom.blade.php');

    $pageView = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/pages/afiliaciones.blade.php');

    expect(Afiliaciones::class)->toBeString()
        ->and($page)->toContain('AfiliacionesStatusMomStats::class')
        ->and($page)->toContain('AfiliacionesStatusByStatePieChart::class')
        ->and($page)->toContain('AfiliacionesPlansDemandChart::class')
        ->and($page)->toContain('AfiliacionesPlanAmountCombinedPieChart::class')
        ->and($page)->not->toContain('AfiliacionesByStatePieChart::class')
        ->and($page)->toContain('AfiliacionesIndividualesByMonthChart::class')
        ->and($page)->toContain('AfiliacionesCorporativasByMonthChart::class')
        ->and($page)->toContain("'xl' => 2")
        ->and($page)->toContain("return 'filament.metrics.pages.afiliaciones'")
        ->and($page)->toContain('protected function getHeaderWidgets(): array')
        ->and($page)->toMatch('/getHeaderWidgets\(\): array\s*\{\s*return \[\];/s')
        ->and(strpos($page, 'AfiliacionesStatusMomStats::class'))
        ->toBeLessThan(strpos($page, 'AfiliacionesStatusByStatePieChart::class'))
        ->and(strpos($page, 'AfiliacionesStatusByStatePieChart::class'))
        ->toBeLessThan(strpos($page, 'AfiliacionesPlansDemandChart::class'))
        ->and($pageView)->toContain('fi-metrics-module')
        ->and($pageView)->toContain('fi-metrics-module__eyebrow')
        ->and($pageView)->toContain('fi-metrics-module__title')
        ->and($pageView)->toContain('fi-metrics-module__subtitle')
        ->and($momWidget)->toContain('statusComparison')
        ->and($momWidget)->toContain('Afiliados individuales')
        ->and($momWidget)->toContain('Afiliados corporativos')
        ->and($momWidget)->toContain('year_series')
        ->and($momWidget)->toContain('buildRegistrationMomYearChart')
        ->and($momWidget)->toContain("'grid_cols' => 2")
        ->and($momWidget)->toContain('filament.metrics.widgets.corretaje-registration-mom')
        ->and($momWidget)->toContain('Cómo vamos este mes, comparado con el mes pasado')
        ->and($plansChart)->toContain('byPlanMonth')
        ->and($plansChart)->toContain("return 'line'")
        ->and($plansChart)->toContain('columnSpan = 1')
        ->and($plansChart)->toContain('Plan Inicial')
        ->and($plansChart)->toContain('Plan Ideal')
        ->and($plansChart)->toContain('Plan Especial')
        ->and($plansChart)->toContain('most_demanded')
        ->and($plansChart)->toContain('least_demanded')
        ->and(strpos($page, 'AfiliacionesPlansDemandChart::class'))
        ->toBeLessThan(strpos($page, 'AfiliacionesPlanAmountCombinedPieChart::class'))
        ->and($plansView)->not->toContain('fi-metrics-agents-by-state-chart__chips')
        ->and($combinedPie)->toContain("return 'pie'")
        ->and($combinedPie)->toContain('cutout: 0')
        ->and($combinedPie)->toContain('display: false')
        ->and($combinedPie)->toContain('#2F6BFF')
        ->and($combinedPie)->toContain('#7C3AED')
        ->and($combinedPie)->toContain('byPlanAmountCombined')
        ->and($combinedPie)->toContain("'valueFormat' => 'usd'")
        ->and($combinedPieView)->not->toContain('fi-metrics-agents-by-state-chart__chips')
        ->and($combinedPieView)->not->toContain('afterHeader')
        ->and(file_get_contents(dirname(__DIR__, 2).'/resources/css/filament/admin/theme.css'))
        ->toContain('fi-section-header-text-ctn')
        ->and(file_get_contents(dirname(__DIR__, 2).'/resources/css/filament/admin/theme.css'))
        ->toContain('min-w-[14rem]')
        ->and($statePie)->toContain("return 'pie'")
        ->and($statePie)->toContain('cutout: 0')
        ->and($statePie)->toContain('byState')
        ->and($statePie)->toContain("'valueFormat' => 'count'")
        ->and($statePie)->toContain('Afiliaciones por estado')
        ->and($statePie)->toContain('520px')
        ->and(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Metrics/Widgets/AfiliacionesStatusByStatePieChart.php'))
        ->toContain("columnSpan = 'full'")
        ->and(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Metrics/Widgets/AfiliacionesStatusByStatePieChart.php'))
        ->toContain('Total de afiliaciones por estado')
        ->and($statePieView)->not->toContain('fi-metrics-agents-by-state-chart__chips')
        ->and($pageView)
        ->toContain('pie-outlabels-plugin')
        ->and(file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/partials/pie-outlabels-plugin.blade.php'))
        ->toContain('fiMetricsPieOutLabels')
        ->and(file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/partials/pie-outlabels-plugin.blade.php'))
        ->toContain('lineTo')
        ->and(file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/partials/pie-outlabels-plugin.blade.php'))
        ->toContain('valueFormat')
        ->and(file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/partials/pie-outlabels-plugin.blade.php'))
        ->toContain('stemBase')
        ->and(file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/partials/pie-outlabels-plugin.blade.php'))
        ->toContain('railGap')
        ->and(file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/partials/pie-outlabels-plugin.blade.php'))
        ->toContain('leftRailX')
        ->and(file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/partials/pie-outlabels-plugin.blade.php'))
        ->toContain('packLabelsWithoutOverlap')
        ->and(file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/partials/pie-outlabels-plugin.blade.php'))
        ->toContain('PLUGIN_VERSION = 9')
        ->and(file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/partials/pie-outlabels-plugin.blade.php'))
        ->toContain('responsiveMetrics')
        ->and(file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/partials/pie-outlabels-plugin.blade.php'))
        ->toContain('textWidth')
        ->and($statePie)->toContain('right: 180')
        ->and($drillConcern)->toContain('selectedMonth')
        ->and($drillConcern)->toContain('handleChartClick')
        ->and($drillConcern)->toContain('resetDrillDown')
        ->and($drillConcern)->toContain('easeOutCubic')
        ->and($individualChart)->toContain("'individual'")
        ->and($corporateChart)->toContain("'corporate'")
        ->and($drillView)->toContain('fi-metrics-agencies-affiliations-drill__stage')
        ->and($drillView)->toContain('resetDrillDown')
        ->and($drillView)->toContain('is-ready')
        ->and($client)->toContain('/api/metrics/afiliaciones/status-comparison')
        ->and($client)->toContain('/api/metrics/afiliaciones/by-month')
        ->and($client)->toContain('/api/metrics/afiliaciones/by-day')
        ->and($client)->toContain('/api/metrics/afiliaciones/by-plan-month')
        ->and($client)->toContain('/api/metrics/afiliaciones/by-plan-amount')
        ->and($client)->toContain('/api/metrics/afiliaciones/by-plan-amount-combined')
        ->and($client)->toContain('/api/metrics/afiliaciones/by-state')
        ->and($client)->toContain("'individual'")
        ->and($client)->toContain("'corporate'")
        ->and($apiController)->toContain('affiliations')
        ->and($apiController)->toContain('affiliation_corporates')
        ->and($apiController)->toContain('afilliation_corporate_plans')
        ->and($apiController)->toContain('getAfiliacionesStatusComparison')
        ->and($apiController)->toContain('getAfiliacionesByMonth')
        ->and($apiController)->toContain('getAfiliacionesByDay')
        ->and($apiController)->toContain('getAfiliacionesByPlanMonth')
        ->and($apiController)->toContain('getAfiliacionesByPlanAmount')
        ->and($apiController)->toContain('getAfiliacionesByPlanAmountCombined')
        ->and($apiController)->toContain('getAfiliacionesByState')
        ->and($apiController)->toContain('state_id_ti')
        ->and($apiController)->toContain('monthBounds')
        ->and($apiController)->toContain('year_series')
        ->and($apiController)->toContain('individual')
        ->and($apiController)->toContain('corporate')
        ->and($apiRoutes)->toContain('/afiliaciones/status-comparison')
        ->and($apiRoutes)->toContain('/afiliaciones/by-month')
        ->and($apiRoutes)->toContain('/afiliaciones/by-day')
        ->and($apiRoutes)->toContain('/afiliaciones/by-plan-month')
        ->and($apiRoutes)->toContain('/afiliaciones/by-plan-amount')
        ->and($apiRoutes)->toContain('/afiliaciones/by-plan-amount-combined')
        ->and($apiRoutes)->toContain('/afiliaciones/by-state')
        ->and($apiRoutes)->toContain('getAfiliacionesStatusComparison')
        ->and($blade)->toContain('fi-metrics-registration-mom__chart');
});
