<?php

declare(strict_types=1);

use App\Exceptions\QuoteServiceUnavailableException;
use App\Exceptions\TarifaNoDisponibleException;
use App\Services\TuDr\QuoteApiClient;
use App\Support\TuDrQuote\QuoteControlNumber;
use App\Support\TuDrQuote\QuoteDocumentLayout;
use App\Support\TuDrQuote\QuoteFeeMatrix;
use App\Support\TuDrQuote\QuoteRenderPayload;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    bootTuDrQuoteSqliteSchema();

    config([
        'cache.default' => 'array',
        'services.tudr_quote.url' => 'https://cotizador.tudrgroup.com',
        'services.tudr_quote.key' => 'clave-de-prueba-no-real',
        'services.tudr_quote.enabled' => true,
        'services.tudr_quote.timeout' => 10,
    ]);

    QuoteFeeMatrix::flush();
    QuoteDocumentLayout::flush();
});

it('envía la clave en la cabecera y nunca en el cuerpo', function (): void {
    Http::fake([
        '*/api/cotizar' => Http::response([
            'ok' => true,
            'control' => '0003981',
            'planes' => [],
            'ms' => 120.5,
            'fuente_tarifas' => 'payload',
        ]),
    ]);

    app(QuoteApiClient::class)->cotizar([
        'titular' => 'Prueba',
        'edades' => [25],
    ]);

    Http::assertSent(function (Illuminate\Http\Client\Request $request): bool {
        expect($request->header('X-Api-Key'))->toBe(['clave-de-prueba-no-real'])
            ->and(json_encode($request->data()))->not->toContain('clave-de-prueba-no-real');

        return true;
    });
});

it('traduce el 422 en un aviso con el motivo para el usuario', function (): void {
    Http::fake([
        '*/api/cotizar' => Http::response([
            'ok' => false,
            'error' => 'No hay tarifa para alguna edad.',
            'faltantes' => [
                ['plan' => 'ideal', 'motivo' => 'sin tarifa para 88 años'],
            ],
        ], 422),
    ]);

    $client = app(QuoteApiClient::class);

    try {
        $client->cotizar(['titular' => 'Prueba', 'edades' => [88]]);
        $this->fail('Debió lanzar TarifaNoDisponibleException.');
    } catch (TarifaNoDisponibleException $exception) {
        expect($exception->faltantes)->toHaveCount(1)
            ->and($exception->motivos())->toBe('IDEAL: sin tarifa para 88 años');
    }

    /** Un 422 es una respuesta de negocio: repetirla solo gasta tiempo. */
    Http::assertSentCount(1);
});

it('reintenta ante un 5xx y termina avisando que el servicio no está disponible', function (): void {
    Http::fake([
        '*/render' => Http::response('boom', 500),
    ]);

    expect(fn () => app(QuoteApiClient::class)->render(['control' => '0003981']))
        ->toThrow(QuoteServiceUnavailableException::class);

    /** Dos intentos: el original y el reintento. */
    Http::assertSentCount(2);
});

it('rechaza un documento que no sea PDF', function (): void {
    Http::fake([
        '*/render' => Http::response('<html>no soy un pdf</html>', 200),
    ]);

    expect(fn () => app(QuoteApiClient::class)->render(['control' => '0003981']))
        ->toThrow(QuoteServiceUnavailableException::class);
});

it('queda deshabilitado sin clave o con el interruptor apagado', function (): void {
    config(['services.tudr_quote.enabled' => false]);
    expect(app(QuoteApiClient::class)->enabled())->toBeFalse();

    config(['services.tudr_quote.enabled' => true, 'services.tudr_quote.key' => '']);
    expect(app(QuoteApiClient::class)->enabled())->toBeFalse();
});

it('arma la matriz de tarifas desde la tabla del portal', function (): void {
    $matriz = QuoteFeeMatrix::all();

    expect($matriz)->not->toBeEmpty();

    foreach ($matriz as $fila) {
        expect($fila['plan'])->toBeIn(['inicial', 'ideal', 'especial'])
            ->and($fila['cobertura'])->toBeInt()
            ->and($fila['edad_min'])->toBeLessThanOrEqual($fila['edad_max'])
            ->and($fila['tarifa_anual'])->toBeGreaterThan(0);
    }

    $inicial = array_values(array_filter($matriz, fn (array $fila): bool => $fila['plan'] === 'inicial'));

    /** El Inicial no tiene coberturas: el contrato exige cobertura 0. */
    expect($inicial)->not->toBeEmpty()
        ->and($inicial[0]['cobertura'])->toBe(0)
        ->and($inicial[0]['tarifa_anual'])->toBe(160.0);

    $especial = array_values(array_filter($matriz, fn (array $fila): bool => $fila['plan'] === 'especial'));

    expect($especial)->toHaveCount(12)
        ->and($especial[0])->toBe([
            'plan' => 'especial',
            'cobertura' => 5000,
            'edad_min' => 0,
            'edad_max' => 30,
            'tarifa_anual' => 311.0,
        ]);
});

it('lee los rangos de edad escritos de cualquier forma', function (): void {
    expect(QuoteFeeMatrix::parseAgeRange('0 A 30'))->toBe([0, 30])
        ->and(QuoteFeeMatrix::parseAgeRange('31 a 65'))->toBe([31, 65])
        ->and(QuoteFeeMatrix::parseAgeRange('76 a 85 años'))->toBe([76, 85])
        ->and(QuoteFeeMatrix::parseAgeRange(null))->toBe([0, 120]);
});

it('toma el número de control del consecutivo del portal', function (): void {
    expect(QuoteControlNumber::fromCode('COT-IND-0004011'))->toBe('0004011')
        ->and(QuoteControlNumber::fromCode('COT-CORP-0000123'))->toBe('0000123')
        ->and(QuoteControlNumber::fromCode('123'))->toBe('0000123')
        ->and(QuoteControlNumber::fromCode(null))->toBe('0000000');
});

it('etiqueta las coberturas y reparte anual en semestral y trimestral', function (): void {
    expect(QuoteRenderPayload::coverageLabel(5000))->toBe('5K')
        ->and(QuoteRenderPayload::coverageLabel(50000))->toBe('50K')
        ->and(QuoteRenderPayload::coverageLabel(0))->toBe('0')
        ->and(QuoteRenderPayload::split(320.0, 2))->toBe(160.0)
        ->and(QuoteRenderPayload::split(325.0, 2))->toBe(162.5)
        ->and(QuoteRenderPayload::split(650.0, 4))->toBe(162.5);
});

it('acota la configuración para que los cálculos nunca queden fuera del documento', function (): void {
    expect(QuoteDocumentLayout::sanitize(7, 3))->toBe(['total_pages' => 7, 'calculations_page' => 3])
        ->and(QuoteDocumentLayout::sanitize(3, 9))->toBe(['total_pages' => 3, 'calculations_page' => 3])
        ->and(QuoteDocumentLayout::sanitize(0, 0))->toBe(['total_pages' => 1, 'calculations_page' => 1])
        ->and(QuoteDocumentLayout::sanitize(99, 2))->toBe(['total_pages' => QuoteDocumentLayout::MAX_TOTAL_PAGES, 'calculations_page' => 2]);
});
