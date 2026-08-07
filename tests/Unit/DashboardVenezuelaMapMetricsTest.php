<?php

declare(strict_types=1);

use App\Filament\Metrics\Widgets\VenezuelaActivityMapWidget;
use App\Services\IntegracorpApi\DashboardMetricsClient;
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

it('normaliza el payload venezuela-by-state del dashboard', function (): void {
    Http::fake([
        '*/api/metrics/dashboard/venezuela-by-state' => Http::response([
            'success' => true,
            'data' => [
                'years' => [
                    'current' => 2026,
                    'previous' => 2025,
                    'through_month' => 7,
                ],
                'totals' => [
                    'current' => [
                        'agents' => 12,
                        'agencies' => 4,
                        'affiliations_count' => 40,
                        'affiliations_amount' => 12500.5,
                    ],
                    'previous' => [
                        'agents' => 10,
                        'agencies' => 5,
                        'affiliations_count' => 30,
                        'affiliations_amount' => 10000,
                    ],
                    'delta' => [
                        'agents_pct' => 20,
                        'agencies_pct' => -20,
                        'affiliations_count_pct' => 33.3,
                        'affiliations_amount_pct' => 25,
                    ],
                    'providers' => [
                        'juridical' => 40,
                        'natural' => 5,
                        'total' => 45,
                    ],
                ],
                'states' => [
                    [
                        'state_id' => 12,
                        'state' => 'Miranda',
                        'geo_key' => 'Miranda',
                        'current' => [
                            'agents' => 5,
                            'agencies' => 2,
                            'affiliations_count' => 18,
                            'affiliations_amount' => 6200.25,
                        ],
                        'previous' => [
                            'agents' => 4,
                            'agencies' => 2,
                            'affiliations_count' => 12,
                            'affiliations_amount' => 5000,
                        ],
                        'delta' => [
                            'agents_pct' => 25,
                            'agencies_pct' => 0,
                            'affiliations_count_pct' => 50,
                            'affiliations_amount_pct' => 24,
                        ],
                        'providers' => [
                            'juridical' => 12,
                            'natural' => 2,
                            'total' => 14,
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $payload = app(DashboardMetricsClient::class)->venezuelaByState();

    expect($payload['years']['current'])->toBe(2026)
        ->and($payload['years']['previous'])->toBe(2025)
        ->and($payload['years']['through_month'])->toBe(7)
        ->and($payload['totals']['current']['agents'])->toBe(12)
        ->and($payload['totals']['current']['affiliations_amount'])->toBe(12500.5)
        ->and($payload['totals']['delta']['agencies_pct'])->toBe(-20.0)
        ->and($payload['totals']['providers']['juridical'])->toBe(40)
        ->and($payload['totals']['providers']['natural'])->toBe(5)
        ->and($payload['totals']['providers']['total'])->toBe(45)
        ->and($payload['states'])->toHaveCount(1)
        ->and($payload['states'][0]['geo_key'])->toBe('Miranda')
        ->and($payload['states'][0]['current']['affiliations_count'])->toBe(18)
        ->and($payload['states'][0]['providers']['juridical'])->toBe(12)
        ->and($payload['states'][0]['providers']['natural'])->toBe(2)
        ->and($payload['states'][0]['providers']['total'])->toBe(14);

    Http::assertSent(fn ($request): bool => str_contains(
        $request->url(),
        '/api/metrics/dashboard/venezuela-by-state'
    ));
});

it('registra el widget del mapa en el panel Metrics y expone la UI clave', function (): void {
    $provider = file_get_contents(dirname(__DIR__, 2).'/app/Providers/Filament/MetricsPanelProvider.php');
    $widget = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Metrics/Widgets/VenezuelaActivityMapWidget.php');
    $client = file_get_contents(dirname(__DIR__, 2).'/app/Services/IntegracorpApi/DashboardMetricsClient.php');
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/metrics/widgets/venezuela-activity-map.blade.php');
    $paths = file_get_contents(dirname(__DIR__, 2).'/resources/geo/venezuela-states-paths.php');
    $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/filament/admin/theme.css');
    $apiController = file_get_contents(dirname(__DIR__, 2).'/../../integracorp-api/src/controllers/metrics.dashboard.controller.js');
    $apiRoutes = file_get_contents(dirname(__DIR__, 2).'/../../integracorp-api/src/routes/metrics.routes.js');

    expect(VenezuelaActivityMapWidget::class)->toBeString()
        ->and($provider)->toContain('VenezuelaActivityMapWidget::class')
        ->and($provider)->not->toContain('WelcomeUserLiquidGlassWidget::class')
        ->and($widget)->toContain("protected string \$view = 'filament.metrics.widgets.venezuela-activity-map'")
        ->and($widget)->toContain("columnSpan = 'full'")
        ->and($widget)->toContain('DashboardMetricsClient')
        ->and($widget)->toContain('venezuela-states-paths.php')
        ->and($client)->toContain('/api/metrics/dashboard/venezuela-by-state')
        ->and($client)->toContain('affiliations_amount')
        ->and($view)->toContain('fi-metrics-ve-map')
        ->and($view)->toContain('Actividad nacional')
        ->and($view)->toContain('fi-metrics-ve-map__leader')
        ->and($view)->toContain('fi-metrics-ve-map__panel')
        ->and($view)->toContain('statesByKey')
        ->and($view)->toContain('pointerenter')
        ->and($view)->toContain('rowValue')
        ->and($view)->toContain('pinnedKey')
        ->and($paths)->toContain("'geo_key' => 'Miranda'")
        ->and($paths)->toContain("'geo_key' => 'Zulia'")
        ->and($paths)->toContain("'geo_key' => 'Distrito Capital'")
        ->and($paths)->toContain('M')
        ->and(strlen($paths))->toBeGreaterThan(50000)
        ->and($css)->toContain('fi-metrics-ve-map')
        ->and($css)->toContain('fi-metrics-ve-map__leader')
        ->and($css)->toContain('fi-metrics-ve-map__layout')
        ->and($css)->toContain('fi-metrics-ve-map-draw')
        ->and(file_exists(dirname(__DIR__, 2).'/resources/geo/venezuela-states.geojson'))->toBeTrue()
        ->and($view)->toContain('providers_juridical')
        ->and($view)->toContain('providers_natural')
        ->and($view)->toContain('Prov. jurídicos')
        ->and($client)->toContain('normalizeProviders')
        ->and($apiController)->toContain('countJuridicalProvidersByState')
        ->and($apiController)->toContain('countNaturalProvidersByStateLabel')
        ->and($apiController)->toContain('AFILIADO')
        ->and($apiController)->toContain('doctor_nurses')
        ->and($apiController)->toContain('getVenezuelaByState')
        ->and($apiController)->toContain('affiliations_amount')
        ->and($apiController)->toContain('years')
        ->and($apiController)->toContain('previous')
        ->and($apiController)->toContain('state_id_ti')
        ->and($apiController)->toContain('ytdBounds')
        ->and($apiRoutes)->toContain('/dashboard/venezuela-by-state')
        ->and($apiRoutes)->toContain('getVenezuelaByState');
});
