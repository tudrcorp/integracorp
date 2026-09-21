<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Support\Storefront\StorefrontPlanView;
use Tests\TestCase;

uses(TestCase::class);

it('ordena los beneficios como el plan inicial y deja los extra al final', function (): void {
    $inicial = [
        'ATENCION MÉDICA TELEFONICA (TELEMEDICINA)',
        'ENTREGA DE TRATAMIENTO MÉDICO A DOMICILIO',
        'MONITOREO TELEFÓNICO EVOLUTIVO',
        'ATENCIÓN MÉDICA DOMICILIARIA CON TRATAMIENTO DE UNIDOSIS INCLUIDA',
    ];

    $rows = [
        ['benefit_label' => 'URGENCIAS MENORES EN DOMICILIO'],
        ['benefit_label' => 'ATENCIÓN MÉDICA DOMICILIARIA CON TRATAMIENTO DE UNIDOSIS INCLUIDA'],
        ['benefit_label' => 'ATENCION MÉDICA TELEFONICA (TELEMEDICINA)'],
        ['benefit_label' => 'ENTREGA DE TRATAMIENTO MÉDICO A DOMICILIO'],
        ['benefit_label' => 'MONITOREO TELEFÓNICO EVOLUTIVO'],
        ['benefit_label' => 'CONSULTA ONLINE O PRESENCIAL CON MÉDICOS ESPECIALISTAS'],
    ];

    $sorted = array_column(StorefrontPlanView::sortBenefitRows($rows, $inicial), 'benefit_label');

    expect($sorted)->toBe([
        'ATENCION MÉDICA TELEFONICA (TELEMEDICINA)',
        'ENTREGA DE TRATAMIENTO MÉDICO A DOMICILIO',
        'MONITOREO TELEFÓNICO EVOLUTIVO',
        'ATENCIÓN MÉDICA DOMICILIARIA CON TRATAMIENTO DE UNIDOSIS INCLUIDA',
        'URGENCIAS MENORES EN DOMICILIO',
        'CONSULTA ONLINE O PRESENCIAL CON MÉDICOS ESPECIALISTAS',
    ]);
});

it('ideal y especial reutilizan el orden de beneficios del plan inicial', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Support/Storefront/StorefrontPlanView.php');

    expect($source)
        ->toContain('orderedBenefitRows')
        ->toContain("orderBy('benefits.id')")
        ->and($source)->not->toContain("orderBy('description')");
});

it('en el catalogo real ideal y especial empiezan como el plan inicial', function (): void {
    $inicial = Plan::query()->find(1);
    $ideal = Plan::query()->find(2);
    $especial = Plan::query()->find(3);

    if ($inicial === null || $ideal === null || $especial === null) {
        test()->markTestSkipped('Faltan los planes básicos 1, 2 o 3 en esta base.');
    }

    $labels = static fn (Plan $plan): array => array_values(array_column(
        StorefrontPlanView::make($plan)['benefits']['rows'],
        'benefit_label',
    ));

    $inicialLabels = $labels($inicial);

    expect($inicialLabels)->not->toBeEmpty()
        ->and(array_slice($labels($ideal), 0, count($inicialLabels)))->toBe($inicialLabels)
        ->and(array_slice($labels($especial), 0, count($inicialLabels)))->toBe($inicialLabels);
});
