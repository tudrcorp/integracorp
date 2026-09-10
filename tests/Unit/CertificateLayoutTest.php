<?php

declare(strict_types=1);

use App\Http\Controllers\AffiliationController;

uses(Tests\TestCase::class);

function certificateBladeSource(): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/certificate.blade.php');
}

/** La plantilla sin comentarios: los comentarios citan las técnicas descartadas y falsearían las aserciones. */
function certificateBladeCode(): string
{
    $src = certificateBladeSource();
    $src = (string) preg_replace('#/\*.*?\*/#s', '', $src);

    return (string) preg_replace('#\{\{--.*?--\}\}#s', '', $src);
}

it('el certificado pagina en vez de recortarse: nada del contenido va en posición absoluta', function (): void {
    $src = certificateBladeCode();

    // La firma vivía en `top: 930px` y se montaba sobre la tabla o sobre la nota legal.
    expect($src)->not->toContain('top: 930px')
        ->and($src)->not->toContain('position: absolute')
        // El body centrado con flex impedía que el contenido fluyera a la segunda página.
        ->and($src)->not->toContain('align-items: center')
        ->and($src)->not->toContain('min-height: 100vh');

    expect($src)
        ->toContain('@page')
        ->and($src)->toContain('.page-frame')
        ->and($src)->toContain('position: fixed');
});

it('el contenido termina antes de la línea azul del marco', function (): void {
    // El marco imprime una línea vertical en x≈713 de los 794px de ancho de la página A4.
    $lineaAzulEnPx = 713;
    $anchoPaginaEnPx = 794;

    preg_match('/margin:\s*(\d+)px\s+(\d+)px\s+(\d+)px\s+(\d+)px;/', certificateBladeCode(), $m);

    expect($m)->not->toBeEmpty('no se pudo leer el margen de @page');

    $margenDerecho = (int) $m[2];
    $finDelContenido = $anchoPaginaEnPx - $margenDerecho;

    expect($finDelContenido)->toBeLessThan($lineaAzulEnPx);
});

it('repite la cabecera de la tabla de afiliados en cada página y evita cortar filas', function (): void {
    $src = certificateBladeSource();

    expect($src)
        ->toContain('.table-people thead')
        ->and($src)->toContain('display: table-header-group')
        ->and($src)->toContain('page-break-inside: avoid')
        ->and($src)->toContain('table-layout: fixed');
});

it('numera las páginas con contadores CSS porque el motor corre sin PHP', function (): void {
    $src = certificateBladeCode();

    expect($src)
        ->toContain('counter(page)')
        ->and($src)->not->toContain('<script type="text/php">')
        ->and($src)->not->toContain('$PAGE_NUM');
});

it('imprime un bloque de beneficios por plan y la columna Plan cuando hay más de uno', function (): void {
    $src = certificateBladeCode();

    expect($src)
        ->toContain('@foreach ($benefitSections as $section)')
        ->and($src)->toContain("Beneficios del {{ \$section['plan_label'] }}")
        ->and($src)->toContain('@if ($showPlanColumn)')
        ->and($src)->toContain("{{ \$celda['plan_label'] ?? '' }}")
        // La nota legal ya no depende del número mágico del plan.
        ->and($src)->not->toContain("\$pagador['plan_id'] == 3");
});

it('el certificado individual sigue recibiendo una sola sección de beneficios', function (): void {
    $data = AffiliationController::dataForCertificatePdfView(
        ['name' => 'JUAN PEREZ', 'code' => 'TDEC-IND-0001', 'plan_id' => 1, 'cobertura' => 0],
        ['ATENCION MÉDICA TELEFONICA', 'EMERGENCIAS MÉDICAS POR PATOLOGIAS LISTADAS'],
        [['full_name' => 'JUAN PEREZ', 'nro_identificacion' => 'V-1', 'birth_date' => '01/01/1990', 'relationship' => 'TITULAR']],
    );

    expect($data['benefitSections'])->toHaveCount(1)
        ->and($data['showPlanColumn'])->toBeFalse()
        ->and($data['beneficiosRows'])->toHaveCount(2)
        ->and($data['affiliateTableRows'][0]['plan_label'])->toBe('')
        // Sin cobertura contratada no se imprime «US$ 0,00»: va el check como el resto.
        ->and(array_column($data['benefitSections'][0]['rows'], 'show_cobertura'))->toBe([false, false]);
});

it('con cobertura contratada el certificado individual sí muestra el monto', function (): void {
    $data = AffiliationController::dataForCertificatePdfView(
        ['name' => 'JUAN PEREZ', 'code' => 'TDEC-IND-0001', 'plan_id' => 1, 'cobertura' => 1500],
        ['EMERGENCIAS MÉDICAS POR PATOLOGIAS LISTADAS', 'LABORATORIOS A DOMICILIO'],
        [],
    );

    expect(array_column($data['benefitSections'][0]['rows'], 'show_cobertura'))->toBe([true, false])
        ->and($data['coberturaFormatted'])->toBe('1.500,00');
});

it('el servicio corporativo pasa las secciones por plan y limpia los apellidos de relleno', function (): void {
    $src = (string) file_get_contents(dirname(__DIR__, 2).'/app/Services/AffiliationCorporateBusinessDocumentsService.php');

    expect($src)
        ->toContain('CorporateCertificateBenefitSections::forAffiliation(')
        ->and($src)->toContain('$showPlanColumn = count($benefitSections) > 1;')
        ->and($src)->toContain('self::certificateFullName($affiliate->first_name, $affiliate->last_name)')
        ->and($src)->toContain("preg_match('/^[.\\-_\\/]+\$/', \$clean)");
});
