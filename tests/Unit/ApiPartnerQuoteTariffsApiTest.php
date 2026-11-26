<?php

declare(strict_types=1);

use App\Enums\ApiPartnerAbility;
use App\Models\ApiPartner;
use App\Support\ApiPartners\ApiPartnerToken;
use App\Support\ApiPartners\QuoteTariffFeed;
use App\Support\TuDrQuote\QuoteFeeMatrix;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

uses(Tests\TestCase::class);

/**
 * Tarifas para el agente de ventas por WhatsApp: misma matriz que la propuesta del portal,
 * con permiso propio y 304 cuando el cliente ya tiene la versión vigente. Escribe (aliado y
 * bitácora de acceso) dentro de una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    RateLimiter::clear('api-partners:failed:'.sha1('127.0.0.1'));
});

afterEach(function (): void {
    DB::rollBack();
});

function quoteTariffPartner(array $abilities): string
{
    $generated = ApiPartnerToken::generate();

    $partner = new ApiPartner;
    $partner->forceFill([
        'name' => 'QA AGENTE DE VENTAS',
        'token_prefix' => $generated['prefix'],
        'token_hash' => $generated['hash'],
        'token_issued_at' => now(),
        'abilities' => $abilities,
        'rate_limit_per_minute' => 120,
        'is_active' => true,
    ])->save();

    return $generated['token'];
}

function quoteTariffRequest(?string $token, array $headers = []): Illuminate\Http\Response|Symfony\Component\HttpFoundation\Response
{
    $server = ['HTTPS' => 'on', 'REMOTE_ADDR' => '127.0.0.1'];

    if ($token !== null) {
        $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
    }

    foreach ($headers as $name => $value) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
    }

    return app()->handle(Request::create('https://localhost/api/v1/aliados/tarifas', 'GET', server: $server));
}

it('resume los planes y conserva el formato de la matriz del portal', function (): void {
    $matrix = [
        ['plan' => 'inicial', 'cobertura' => 0, 'edad_min' => 0, 'edad_max' => 99, 'tarifa_anual' => 160.0],
        ['plan' => 'ideal', 'cobertura' => 3000, 'edad_min' => 0, 'edad_max' => 45, 'tarifa_anual' => 231.0],
        ['plan' => 'ideal', 'cobertura' => 1000, 'edad_min' => 46, 'edad_max' => 75, 'tarifa_anual' => 243.0],
        ['plan' => 'ideal', 'cobertura' => 1000, 'edad_min' => 0, 'edad_max' => 45, 'tarifa_anual' => 189.0],
    ];

    $payload = QuoteTariffFeed::payload($matrix);

    expect($payload['moneda'])->toBe('USD')
        ->and($payload['tarifas'])->toBe($matrix)
        ->and($payload['planes'])->toBe([
            ['plan' => 'inicial', 'coberturas' => [], 'edad_min' => 0, 'edad_max' => 99],
            ['plan' => 'ideal', 'coberturas' => [1000, 3000], 'edad_min' => 0, 'edad_max' => 75],
        ]);
});

it('la versión cambia solo si cambia un precio', function (): void {
    $matrix = [['plan' => 'inicial', 'cobertura' => 0, 'edad_min' => 0, 'edad_max' => 99, 'tarifa_anual' => 160.0]];
    $changed = [['plan' => 'inicial', 'cobertura' => 0, 'edad_min' => 0, 'edad_max' => 99, 'tarifa_anual' => 165.0]];

    expect(QuoteTariffFeed::version($matrix))->toBe(QuoteTariffFeed::version($matrix))
        ->and(QuoteTariffFeed::version($matrix))->not->toBe(QuoteTariffFeed::version($changed))
        ->and(strlen(QuoteTariffFeed::version($matrix)))->toBe(16);
});

it('sin token responde 401 y sin el permiso de tarifas 403', function (): void {
    expect(quoteTariffRequest(null)->getStatusCode())->toBe(401);

    $token = quoteTariffPartner([ApiPartnerAbility::NonCoveredMedicationsRead->value]);

    expect(quoteTariffRequest($token)->getStatusCode())->toBe(403);
});

it('con el permiso devuelve las tarifas vigentes de la base y 304 si ya tiene la versión', function (): void {
    $token = quoteTariffPartner([ApiPartnerAbility::QuoteTariffsRead->value]);
    $response = quoteTariffRequest($token);
    $body = json_decode((string) $response->getContent(), true);
    $matrix = QuoteFeeMatrix::all();

    if ($matrix === []) {
        expect($response->getStatusCode())->toBe(503);

        return;
    }

    expect($response->getStatusCode())->toBe(200)
        ->and($body['data']['tarifas'])->toBe(json_decode((string) json_encode($matrix), true))
        ->and($body['meta']['version'])->toBe(QuoteTariffFeed::version($matrix))
        ->and($response->headers->get('ETag'))->toBe('"'.QuoteTariffFeed::version($matrix).'"')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');

    $notModified = quoteTariffRequest($token, ['If-None-Match' => '"'.$body['meta']['version'].'"']);

    expect($notModified->getStatusCode())->toBe(304)
        ->and((string) $notModified->getContent())->toBe('');

    expect(quoteTariffRequest($token, ['If-None-Match' => '"version-vieja"'])->getStatusCode())->toBe(200);
});

it('la ruta exige su propio permiso', function (): void {
    $routes = (string) file_get_contents(dirname(__DIR__, 2).'/routes/partners-api.php');

    expect($routes)->toContain("Route::get('tarifas', [QuoteTariffController::class, 'index'])")
        ->and($routes)->toContain("'api.partner:'.ApiPartnerAbility::QuoteTariffsRead->value")
        ->and(ApiPartnerAbility::options())->toHaveKey('quotes.tariffs.read');
});
