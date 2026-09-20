<?php

declare(strict_types=1);

use App\Services\TuDr\QuoteProposalPdfService;
use App\Support\TuDrQuote\QuoteDocumentLayout;
use App\Support\TuDrQuote\QuoteFeeMatrix;
use App\Support\TuDrQuote\QuoteRenderPayload;
use App\Support\TuDrQuote\QuoteServiceAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

/**
 * Corre sobre un esquema sqlite propio: la base de desarrollo no se toca, y el
 * PDF de prueba se borra al terminar.
 */
beforeEach(function (): void {
    bootTuDrQuoteSqliteSchema();

    config([
        'cache.default' => 'array',
        'services.tudr_quote.url' => 'https://cotizador.tudrgroup.com',
        'services.tudr_quote.key' => 'clave-de-prueba-no-real',
        'services.tudr_quote.enabled' => true,
    ]);

    QuoteFeeMatrix::flush();
    QuoteDocumentLayout::flush();
});

afterEach(function (): void {
    foreach (glob(public_path('storage/quotes/COT-IND-TEST-*.pdf')) ?: [] as $archivo) {
        @unlink($archivo);
    }
});

/**
 * Cotización de prueba con dos rangos de edad del Plan Especial,
 * reutilizando rangos y coberturas reales del catálogo.
 *
 * @return array{id: int, code: string}
 */
function crearCotizacionIndividualDePrueba(): array
{
    $code = 'COT-IND-TEST-'.substr((string) microtime(true), -6);

    $quoteId = (int) DB::table('individual_quotes')->insertGetId([
        'code' => $code,
        'full_name' => 'TITULAR DE PRUEBA',
        'created_by' => 'TEST',
        'plan' => '3',
        'status' => 'PRE-APROBADA',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $tarifas = DB::table('fees')
        ->join('age_ranges', 'age_ranges.id', '=', 'fees.age_range_id')
        ->join('coverages', 'coverages.id', '=', 'fees.coverage_id')
        ->where('fees.plan_id', 3)
        ->orderBy('age_ranges.id')
        ->orderBy('coverages.price')
        ->get(['fees.age_range_id', 'fees.coverage_id', 'fees.price']);

    foreach ($tarifas as $indice => $tarifa) {
        DB::table('detail_individual_quotes')->insert([
            'individual_quote_id' => $quoteId,
            'plan_id' => 3,
            'age_range_id' => $tarifa->age_range_id,
            'coverage_id' => $tarifa->coverage_id,
            'total_persons' => $indice < 6 ? 2 : 1,
            'fee' => $tarifa->price,
            'subtotal_anual' => $tarifa->price * ($indice < 6 ? 2 : 1),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    return ['id' => $quoteId, 'code' => $code];
}

it('agrupa la cotización por rango de edad con su población', function (): void {
    ['id' => $quoteId, 'code' => $code] = crearCotizacionIndividualDePrueba();

    $payload = QuoteRenderPayload::forIndividualQuote($quoteId, $code, 'TITULAR DE PRUEBA', 'Agente', '20/09/2026');

    expect($payload)->not->toBeNull()
        ->and($payload['control'])->toHaveLength(7)
        ->and($payload['planes'])->toHaveCount(1);

    $plan = $payload['planes'][0];

    expect($plan['plan'])->toBe('especial')
        ->and($plan['nombre'])->toBe('Paquete Especial')
        ->and($plan['filas'])->toHaveCount(2)
        ->and($plan['coberturas'])->toBe(['5K', '10K', '20K', '30K', '40K', '50K']);

    /** grupal_anual[i] = Σ tarifa_rango[i] × población del rango. */
    foreach ($plan['grupal_anual'] as $indice => $grupal) {
        $esperado = 0.0;

        foreach ($plan['filas'] as $fila) {
            $esperado += $fila['tarifas'][$indice] * $fila['poblacion'];
        }

        expect($grupal)->toBe(round($esperado, 2))
            ->and($plan['grupal_semestral'][$indice])->toBe(round($grupal / 2, 2))
            ->and($plan['grupal_trimestral'][$indice])->toBe(round($grupal / 4, 2));
    }
});

it('manda al servicio las tarifas del portal y guarda el PDF devuelto', function (): void {
    ['id' => $quoteId, 'code' => $code] = crearCotizacionIndividualDePrueba();

    Http::fake([
        '*/render' => Http::response('%PDF-1.4 propuesta de prueba', 200, ['Content-Type' => 'application/pdf']),
    ]);

    $generado = app(QuoteProposalPdfService::class)->generate($quoteId, QuoteDocumentLayout::SCOPE_INDIVIDUAL, [
        'plan' => 3,
        'code' => $code,
        'name' => 'TITULAR DE PRUEBA',
        'agent_name' => 'Agente de prueba',
        'date' => '20-09-2026',
    ]);

    expect($generado)->toBeTrue()
        ->and(file_exists(public_path('storage/quotes/'.$code.'.pdf')))->toBeTrue();

    Http::assertSent(function (Illuminate\Http\Client\Request $request): bool {
        $data = $request->data();

        expect($data['tarifas'] ?? [])->not->toBeEmpty()
            ->and($data['control'])->toHaveLength(7)
            ->and($data['fecha'])->toBe('20/09/2026')
            ->and($data['planes'][0]['filas'][0])->toHaveKeys(['rango', 'poblacion', 'tarifas']);

        return true;
    });
});

it('cae al generador local cuando el servicio no responde', function (): void {
    ['id' => $quoteId, 'code' => $code] = crearCotizacionIndividualDePrueba();

    Http::fake([
        '*/render' => Http::response('sin servicio', 503),
    ]);

    $generado = QuoteServiceAttempt::generate($quoteId, QuoteDocumentLayout::SCOPE_INDIVIDUAL, [
        'plan' => 3,
        'code' => $code,
        'name' => 'TITULAR DE PRUEBA',
        'agent_name' => 'Agente de prueba',
        'date' => '20-09-2026',
    ]);

    expect($generado)->toBeFalse()
        ->and(file_exists(public_path('storage/quotes/'.$code.'.pdf')))->toBeFalse();
});

it('avisa del motivo cuando faltan tarifas y deja seguir el flujo manual', function (): void {
    ['id' => $quoteId, 'code' => $code] = crearCotizacionIndividualDePrueba();

    Http::fake([
        '*/render' => Http::response([
            'ok' => false,
            'error' => 'Sin tarifa',
            'faltantes' => [['plan' => 'especial', 'motivo' => 'sin tarifa para 95 años']],
        ], 422),
    ]);

    $generado = QuoteServiceAttempt::generate($quoteId, QuoteDocumentLayout::SCOPE_INDIVIDUAL, [
        'plan' => 3,
        'code' => $code,
        'name' => 'TITULAR DE PRUEBA',
        'agent_name' => 'Agente de prueba',
        'date' => '20-09-2026',
    ]);

    expect($generado)->toBeFalse();
});

it('no llama al servicio con planes que no sabe dibujar ni con el interruptor apagado', function (): void {
    Http::fake();

    expect(QuoteProposalPdfService::supportsPlan('CM'))->toBeFalse()
        ->and(QuoteProposalPdfService::supportsPlan(11))->toBeFalse()
        ->and(QuoteProposalPdfService::supportsPlan(3))->toBeTrue();

    ['id' => $quoteId, 'code' => $code] = crearCotizacionIndividualDePrueba();

    $details = [
        'plan' => 11,
        'code' => $code,
        'name' => 'TITULAR DE PRUEBA',
        'agent_name' => 'Agente',
        'date' => '20-09-2026',
    ];

    expect(app(QuoteProposalPdfService::class)->generate($quoteId, QuoteDocumentLayout::SCOPE_INDIVIDUAL, $details))->toBeFalse();

    config(['services.tudr_quote.enabled' => false]);
    $details['plan'] = 3;

    expect(app(QuoteProposalPdfService::class)->generate($quoteId, QuoteDocumentLayout::SCOPE_INDIVIDUAL, $details))->toBeFalse();

    Http::assertNothingSent();
});

/**
 * Cotización multiplan: Inicial y Especial en el mismo documento.
 *
 * @return array{id: int, code: string}
 */
function crearCotizacionMultiplanDePrueba(): array
{
    $code = 'COT-IND-TEST-'.substr((string) microtime(true), -6);

    $quoteId = (int) DB::table('individual_quotes')->insertGetId([
        'code' => $code,
        'full_name' => 'TITULAR MULTIPLAN',
        'created_by' => 'TEST',
        'plan' => 'CM',
        'status' => 'PRE-APROBADA',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    foreach ([1, 3] as $planId) {
        $tarifas = DB::table('fees')->where('plan_id', $planId)->get(['age_range_id', 'coverage_id', 'price']);

        foreach ($tarifas as $tarifa) {
            DB::table('detail_individual_quotes')->insert([
                'individual_quote_id' => $quoteId,
                'plan_id' => $planId,
                'age_range_id' => $tarifa->age_range_id,
                'coverage_id' => $tarifa->coverage_id,
                'total_persons' => 2,
                'fee' => $tarifa->price,
                'subtotal_anual' => $tarifa->price * 2,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    return ['id' => $quoteId, 'code' => $code];
}

/**
 * @return list<array<string, mixed>>
 */
function detallesMultiplanDePrueba(string $code, array $planIds = [1, 3]): array
{
    return array_map(static fn (int $planId): array => [
        'plan' => $planId,
        'code' => $code,
        'name' => 'TITULAR MULTIPLAN',
        'agent_name' => 'Agente de prueba',
        'date' => '20-09-2026',
    ], $planIds);
}

it('manda los dos planes de una cotización multiplan en un solo documento', function (): void {
    ['id' => $quoteId, 'code' => $code] = crearCotizacionMultiplanDePrueba();

    Http::fake([
        '*/render' => Http::response('%PDF-1.4 propuesta multiplan', 200, ['Content-Type' => 'application/pdf']),
    ]);

    $generado = app(QuoteProposalPdfService::class)->generateMultiple(
        $quoteId,
        QuoteDocumentLayout::SCOPE_INDIVIDUAL,
        detallesMultiplanDePrueba($code),
    );

    expect($generado)->toBeTrue()
        ->and(file_exists(public_path('storage/quotes/'.$code.'.pdf')))->toBeTrue();

    Http::assertSent(function (Illuminate\Http\Client\Request $request): bool {
        $planes = $request->data()['planes'] ?? [];

        /** Un plan por página de cálculos, en orden ascendente. */
        expect($planes)->toHaveCount(2)
            ->and($planes[0]['plan'])->toBe('inicial')
            ->and($planes[1]['plan'])->toBe('especial')
            ->and($planes[1]['filas'])->toHaveCount(2);

        return true;
    });
});

it('no dibuja ceros cuando a la cotización le falta una tarifa', function (): void {
    ['id' => $quoteId, 'code' => $code] = crearCotizacionMultiplanDePrueba();

    Http::fake([
        '*/render' => Http::response('%PDF-1.4 no debería llegar aquí', 200),
    ]);

    /** Una cobertura del Especial desaparece: el rango queda incompleto. */
    DB::table('detail_individual_quotes')
        ->where('individual_quote_id', $quoteId)
        ->where('plan_id', 3)
        ->limit(1)
        ->delete();

    $generado = app(QuoteProposalPdfService::class)->generateMultiple(
        $quoteId,
        QuoteDocumentLayout::SCOPE_INDIVIDUAL,
        detallesMultiplanDePrueba($code),
    );

    expect($generado)->toBeFalse()
        ->and(file_exists(public_path('storage/quotes/'.$code.'.pdf')))->toBeFalse();

    Http::assertNothingSent();
});

it('devuelve el documento entero al generador local si un plan no es dibujable', function (): void {
    ['id' => $quoteId, 'code' => $code] = crearCotizacionMultiplanDePrueba();

    Http::fake();

    /** El plan 11 no está entre los que el servicio sabe dibujar. */
    $generado = app(QuoteProposalPdfService::class)->generateMultiple(
        $quoteId,
        QuoteDocumentLayout::SCOPE_INDIVIDUAL,
        detallesMultiplanDePrueba($code, [1, 3, 11]),
    );

    expect($generado)->toBeFalse();

    Http::assertNothingSent();
});

it('ordena los planes aunque lleguen desordenados', function (): void {
    ['id' => $quoteId, 'code' => $code] = crearCotizacionMultiplanDePrueba();

    Http::fake([
        '*/render' => Http::response('%PDF-1.4 propuesta multiplan', 200),
    ]);

    app(QuoteProposalPdfService::class)->generateMultiple(
        $quoteId,
        QuoteDocumentLayout::SCOPE_INDIVIDUAL,
        detallesMultiplanDePrueba($code, [3, 1]),
    );

    Http::assertSent(function (Illuminate\Http\Client\Request $request): bool {
        $planes = $request->data()['planes'] ?? [];

        expect($planes[0]['plan'])->toBe('inicial')
            ->and($planes[1]['plan'])->toBe('especial');

        return true;
    });
});
