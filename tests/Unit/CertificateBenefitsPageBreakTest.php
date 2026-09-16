<?php

declare(strict_types=1);

use App\Support\Affiliations\CertificateBenefitsBlockFit;

uses(Tests\TestCase::class);

/**
 * @return array<int, array{text: string, show_cobertura: bool}>
 */
function filasDeBeneficios(int $cantidad, string $text = 'BENEFICIO DE PRUEBA'): array
{
    return array_map(
        fn (int $i): array => ['text' => $text.' '.$i, 'show_cobertura' => false],
        range(1, $cantidad),
    );
}

/**
 * @param  array<int, array<string, mixed>>  $sections
 */
function renderizarCertificado(array $sections, bool $conFirma = true): string
{
    return view('documents.certificate', [
        'pagador' => [
            'name' => 'EMPRESA DE PRUEBA',
            'code' => 'TDEC-COR-99999',
            'tarifa_anual' => 100.0,
            'plan' => 'PLAN ESPECIAL',
            'plan_id' => 3,
            'frecuencia_pago' => 'ANUAL',
            'fecha_afiliacion' => '01/01/2026',
            'tarifa_periodo' => 100.0,
            'fecha_vigencia' => '01/01/2026',
            'fecha_vigencia_final' => '01/01/2027',
            'periodo_facturado_hasta' => '01/01/2027',
            'agente_agencia' => 'AGENTE DE PRUEBA',
        ],
        'affiliateTableRows' => [[
            'full_name' => 'JUAN PEREZ',
            'nro_identificacion' => '11111111',
            'birth_date' => '01/01/1980',
            'relationship' => 'TITULAR',
            'plan_label' => '',
        ]],
        'coberturaFormatted' => '0,00',
        'beneficiosRows' => $sections[0]['rows'] ?? [],
        'benefitSections' => $sections,
        'showPlanColumn' => count($sections) > 1,
        'brandColor' => '#26b2ca',
        'logoDataUri' => '',
        'signatureDataUri' => $conFirma ? 'data:image/png;base64,AAAA' : '',
        'isAlliedCertificate' => ! $conFirma,
        'companyName' => $conFirma ? '' : 'EMPRESA ALIADA',
    ])->render();
}

it('mantiene en una página un bloque de beneficios de tamaño normal junto con la firma', function () {
    expect(CertificateBenefitsBlockFit::fitsInOnePage(filasDeBeneficios(11), true, true))->toBeTrue()
        ->and(CertificateBenefitsBlockFit::fitsInOnePage(filasDeBeneficios(12), true, true))->toBeTrue();
});

it('no agrupa un bloque de beneficios que no cabe en una página', function () {
    expect(CertificateBenefitsBlockFit::fitsInOnePage(filasDeBeneficios(60), false, false))->toBeFalse();
});

it('cuenta más alto un beneficio cuyo texto ocupa varias líneas', function () {
    $corto = CertificateBenefitsBlockFit::estimatedHeight(filasDeBeneficios(1, 'CORTO'), false, false);
    $largo = CertificateBenefitsBlockFit::estimatedHeight(
        [['text' => str_repeat('TEXTO MUY LARGO DE BENEFICIO ', 6), 'show_cobertura' => false]],
        false,
        false,
    );

    expect($largo)->toBeGreaterThan($corto);
});

it('envuelve el bloque de beneficios en una celda indivisible cuando cabe en la página', function () {
    $html = renderizarCertificado([[
        'plan_id' => 3,
        'plan_label' => '',
        'rows' => filasDeBeneficios(11),
        'note' => 'NOTA DE PRUEBA',
    ]]);

    expect($html)->toContain('class="keep-together-cell"')
        ->and($html)->toContain('NOTA DE PRUEBA')
        ->and(substr_count($html, 'class="signature"'))->toBe(1);
});

it('deja fluir el bloque y emite la firma aparte cuando los beneficios no caben en una página', function () {
    $html = renderizarCertificado([[
        'plan_id' => 3,
        'plan_label' => '',
        'rows' => filasDeBeneficios(60),
        'note' => null,
    ]]);

    expect($html)->not->toContain('class="keep-together-cell"')
        ->and(substr_count($html, 'class="signature"'))->toBe(1);
});

it('emite la firma una sola vez con varias secciones de beneficios', function () {
    $html = renderizarCertificado([
        ['plan_id' => 2, 'plan_label' => 'PLAN IDEAL', 'rows' => filasDeBeneficios(11), 'note' => null],
        ['plan_id' => 1, 'plan_label' => 'PLAN INICIAL', 'rows' => filasDeBeneficios(8), 'note' => null],
    ]);

    expect(substr_count($html, 'class="signature"'))->toBe(1)
        ->and(substr_count($html, 'class="keep-together-cell"'))->toBe(2)
        ->and($html)->toContain('Beneficios del PLAN IDEAL')
        ->and($html)->toContain('Beneficios del PLAN INICIAL');
});

it('no imprime firma en un certificado de empresa aliada sin firma cargada', function () {
    $html = renderizarCertificado([[
        'plan_id' => 3,
        'plan_label' => '',
        'rows' => filasDeBeneficios(11),
        'note' => null,
    ]], conFirma: false);

    expect($html)->not->toContain('class="signature"')
        ->and($html)->toContain('class="keep-together-cell"');
});

it('ignora las secciones de beneficios vacías', function () {
    $html = renderizarCertificado([
        ['plan_id' => 3, 'plan_label' => 'PLAN VACIO', 'rows' => [], 'note' => null],
        ['plan_id' => 3, 'plan_label' => 'PLAN CON BENEFICIOS', 'rows' => filasDeBeneficios(5), 'note' => null],
    ]);

    expect($html)->not->toContain('Beneficios del PLAN VACIO')
        ->and($html)->toContain('Beneficios del plan seleccionado')
        ->and(substr_count($html, 'class="signature"'))->toBe(1);
});
