<?php

declare(strict_types=1);

use App\Services\IntegracorpApi\IntegracorpApiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

it('envia el header X-API-Key cuando esta configurada', function (): void {
    config([
        'services.integracorp_api.base_url' => 'http://127.0.0.1:4000',
        'services.integracorp_api.timeout' => 3,
        'services.integracorp_api.api_key' => 'test-metrics-api-key',
    ]);

    Http::fake([
        'http://127.0.0.1:4000/api/metrics/dashboard/venezuela-by-state' => Http::response([
            'success' => true,
            'data' => ['ok' => true],
        ], 200),
    ]);

    app(IntegracorpApiClient::class)->getJson('/api/metrics/dashboard/venezuela-by-state');

    Http::assertSent(function ($request): bool {
        return $request->url() === 'http://127.0.0.1:4000/api/metrics/dashboard/venezuela-by-state'
            && $request->hasHeader('X-API-Key', 'test-metrics-api-key');
    });
});

it('no fuerza X-API-Key cuando la clave esta vacia', function (): void {
    config([
        'services.integracorp_api.base_url' => 'http://127.0.0.1:4000',
        'services.integracorp_api.timeout' => 3,
        'services.integracorp_api.api_key' => '',
    ]);

    Http::fake([
        'http://127.0.0.1:4000/api/health' => Http::response([
            'success' => true,
            'data' => ['ok' => true],
        ], 200),
    ]);

    app(IntegracorpApiClient::class)->getJson('/api/health');

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), '/api/health')
            && $request->header('X-API-Key') === [];
    });
});
