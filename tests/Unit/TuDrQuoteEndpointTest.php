<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\TuDrQuote\QuoteFeeMatrix;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    bootTuDrQuoteSqliteSchema();

    config([
        'cache.default' => 'array',
        'services.tudr_quote.url' => 'https://cotizador.tudrgroup.com',
        'services.tudr_quote.key' => 'clave-de-prueba-no-real',
        'services.tudr_quote.enabled' => true,
    ]);

    Storage::fake('local');
    QuoteFeeMatrix::flush();

    /** El endpoint vive en `web`: el navegador manda su token CSRF, el test no. */
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

function usuarioAutenticadoDePrueba(): User
{
    return makeTuDrQuoteUser('Analista de cotizaciones');
}

it('cotiza desde el panel sin exponer la clave al navegador', function (): void {
    Http::fake([
        '*/api/cotizar' => Http::response([
            'ok' => true,
            'control' => '0004011',
            'planes' => [['plan' => 'inicial', 'total_anual' => 480]],
            'resumen' => 'Titular: Prueba · 3 afiliados',
            'pdf_base64' => base64_encode('%PDF-1.4 propuesta'),
        ]),
    ]);

    $response = $this->actingAs(usuarioAutenticadoDePrueba())
        ->postJson(route('propuestas.cotizar'), [
            'titular' => 'Prueba',
            'afiliados' => [
                ['nombre' => 'Ana', 'edad' => 25],
                ['edad' => 50],
                ['edad' => 70],
            ],
            'planes' => ['todos'],
        ]);

    $response->assertSuccessful()
        ->assertJsonStructure(['ok', 'control', 'planes', 'resumen', 'pdf_url']);

    expect($response->getContent())->not->toContain('clave-de-prueba-no-real')
        ->and($response->json('pdf_url'))->toContain('/propuestas/');
});

it('devuelve el motivo cuando faltan tarifas', function (): void {
    Http::fake([
        '*/api/cotizar' => Http::response([
            'ok' => false,
            'error' => 'No hay tarifa para alguna edad.',
            'faltantes' => [['plan' => 'ideal', 'motivo' => 'sin tarifa para 95 años']],
        ], 422),
    ]);

    $this->actingAs(usuarioAutenticadoDePrueba())
        ->postJson(route('propuestas.cotizar'), [
            'titular' => 'Prueba',
            'afiliados' => [['edad' => 95]],
        ])
        ->assertStatus(422)
        ->assertJsonPath('faltantes.0.motivo', 'sin tarifa para 95 años');
});

it('responde no disponible con el interruptor apagado y no llama al servicio', function (): void {
    Http::fake();
    config(['services.tudr_quote.enabled' => false]);

    $this->actingAs(usuarioAutenticadoDePrueba())
        ->postJson(route('propuestas.cotizar'), [
            'titular' => 'Prueba',
            'afiliados' => [['edad' => 30]],
        ])
        ->assertStatus(503);

    Http::assertNothingSent();
});

it('valida titular, afiliados y edades', function (array $payload, string $campo): void {
    Http::fake();

    $this->actingAs(usuarioAutenticadoDePrueba())
        ->postJson(route('propuestas.cotizar'), $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors($campo);

    Http::assertNothingSent();
})->with([
    'sin titular' => [['afiliados' => [['edad' => 30]]], 'titular'],
    'sin afiliados' => [['titular' => 'Prueba', 'afiliados' => []], 'afiliados'],
    'edad no numérica' => [['titular' => 'Prueba', 'afiliados' => [['edad' => 'treinta']]], 'afiliados.0.edad'],
    'edad fuera de rango' => [['titular' => 'Prueba', 'afiliados' => [['edad' => 130]]], 'afiliados.0.edad'],
    'plan inexistente' => [['titular' => 'Prueba', 'afiliados' => [['edad' => 30]], 'planes' => ['premium']], 'planes.0'],
]);

it('rechaza más de veinte afiliados y remite a la cotización corporativa', function (): void {
    Http::fake();

    $afiliados = array_map(fn (int $i): array => ['edad' => 30], range(1, 21));

    $this->actingAs(usuarioAutenticadoDePrueba())
        ->postJson(route('propuestas.cotizar'), [
            'titular' => 'Prueba',
            'afiliados' => $afiliados,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('afiliados');
});

it('exige sesión para cotizar y para descargar', function (): void {
    $this->postJson(route('propuestas.cotizar'), [
        'titular' => 'Prueba',
        'afiliados' => [['edad' => 30]],
    ])->assertUnauthorized();
});

it('no entrega el PDF de otro usuario', function (): void {
    $this->actingAs(usuarioAutenticadoDePrueba())
        ->get(route('propuestas.pdf', ['control' => '9999999']))
        ->assertForbidden();
});

it('no deja abrir la propuesta de otra cotización con un control parecido', function (): void {
    $user = usuarioAutenticadoDePrueba();

    $ajena = 'COT-IND-'.'0004011';
    $propia = 'COT-IND-'.'0014011';

    DB::table('individual_quotes')->insert([
        'code' => $propia,
        'full_name' => 'TITULAR DE PRUEBA',
        'created_by' => (string) $user->name,
        'status' => 'PRE-APROBADA',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Storage::disk('local')->put('propuestas/0004011.pdf', '%PDF-1.4 ajena');

    /** El usuario es dueño de la 0014011, no de la 0004011. */
    $this->actingAs($user)
        ->get(route('propuestas.pdf', ['control' => '0004011']))
        ->assertForbidden();

    expect($ajena)->not->toBe($propia);
});
