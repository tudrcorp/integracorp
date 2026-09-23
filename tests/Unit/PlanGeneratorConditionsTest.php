<?php

declare(strict_types=1);

use App\Support\PlanGenerators\PlanGeneratorConditions;

it('normaliza una o varias condiciones y descarta las vacías', function (): void {
    expect(PlanGeneratorConditions::normalize([
        '  Vigencia de 15 días.  ',
        '',
        ['text' => 'Las tarifas no incluyen IVA.'],
        '   ',
        ['uuid' => ['text' => 'Pago contra factura.']],
    ]))->toBe([
        'Vigencia de 15 días.',
        'Las tarifas no incluyen IVA.',
        'Pago contra factura.',
    ]);
});

it('recorta cada condición al largo máximo y a la cantidad máxima', function (): void {
    $larga = str_repeat('a', PlanGeneratorConditions::MAX_LENGTH + 40);
    $muchas = array_fill(0, PlanGeneratorConditions::MAX_ITEMS + 5, 'Condición');

    expect(PlanGeneratorConditions::normalize([$larga]))->toBe([
        str_repeat('a', PlanGeneratorConditions::MAX_LENGTH),
    ])->and(PlanGeneratorConditions::normalize($muchas))->toHaveCount(PlanGeneratorConditions::MAX_ITEMS);
});

it('abre el formulario con una fila en blanco cuando todavía no hay condiciones', function (): void {
    expect(PlanGeneratorConditions::formState([]))->toBe([''])
        ->and(PlanGeneratorConditions::formState(['Ya escrita.']))->toBe(['Ya escrita.']);
});

it('la derivada pide las condiciones debajo del total grupal y el pdf las imprime', function (): void {
    $accion = (string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/PlanGenerators/Tables/Actions/DeriveQuotationBulkAction.php');
    $formulario = (string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/PlanGenerators/Schemas/PlanGeneratorForm.php');
    $vista = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/plan-generators/stacked-matrices-preview.blade.php');
    $pdf = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/partials/plan-generator-plan-body.blade.php');

    expect($accion)
        ->toContain('PlanGeneratorConditions::field()')
        ->toContain('assertConditionsArePresent');

    expect(strpos($accion, 'group-total') === false || strpos($accion, 'stacked-matrices-editor') < strpos($accion, 'PlanGeneratorConditions::field()'))
        ->toBeTrue();

    expect($formulario)->toContain('PlanGeneratorConditions::field(onlyOnDerivedRecord: true)');

    expect($vista)
        ->toContain('group-total-matrix')
        ->toContain('conditions-list')
        ->and(strpos($vista, 'group-total-matrix'))->toBeLessThan(strpos($vista, 'conditions-list'));

    expect($pdf)
        ->toContain('Total grupal')
        ->toContain('Condiciones')
        ->toContain('condition-line')
        ->and(strpos($pdf, 'Total grupal'))->toBeLessThan(strpos($pdf, 'Condiciones'));
});
