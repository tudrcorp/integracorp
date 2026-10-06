<?php

declare(strict_types=1);

use App\Support\PlanGenerators\PlanGeneratorCompanyRates;
use App\Support\PlanGenerators\PlanGeneratorGroupTotalCalculator as Calculator;

/**
 * El analista puede quitar filas del total grupal porque la cotización solo se
 * paga de ciertas maneras. Lo quitado no sale en el PDF ni se ofrece como forma
 * de pago al registrar la empresa.
 */
it('muestra todas las filas cuando no se quitó ninguna', function (): void {
    expect(array_column(Calculator::groupTotalRows(false), 'key'))->toBe(['annual', 'semestral', 'trimestral'])
        ->and(array_column(Calculator::groupTotalRows(true, null), 'key'))->toBe(['annual', 'semestral', 'trimestral', 'mensual']);
});

it('oculta las filas quitadas y conserva el orden', function (): void {
    expect(array_column(Calculator::groupTotalRows(true, ['semestral']), 'key'))->toBe(['annual', 'trimestral', 'mensual'])
        ->and(array_column(Calculator::groupTotalRows(false, ['annual', 'semestral']), 'key'))->toBe(['trimestral']);
});

it('nunca deja el total grupal vacío: vuelve a la anual', function (): void {
    expect(array_column(Calculator::groupTotalRows(false, ['annual', 'semestral', 'trimestral']), 'key'))->toBe(['annual'])
        // Con la mensual encendida, esa basta.
        ->and(array_column(Calculator::groupTotalRows(true, ['annual', 'semestral', 'trimestral']), 'key'))->toBe(['mensual']);
});

it('descarta claves desconocidas, repetidas o de otro tipo', function (mixed $entrada, array $esperado): void {
    expect(Calculator::normalizeHiddenRows($entrada))->toBe($esperado);
})->with([
    'nulo' => [null, []],
    'texto suelto' => ['semestral', []],
    'json de la columna' => ['["trimestral","semestral"]', ['semestral', 'trimestral']],
    'basura y repetidos' => [['semestral', 'semestral', 'DROP', 3, 'mensual'], ['semestral']],
]);

it('lista para restaurar solo lo quitado, sin la mensual', function (): void {
    expect(Calculator::removedRows(false, ['semestral']))->toBe([['key' => 'semestral', 'label' => 'Tarifa Semestral']])
        ->and(Calculator::removedRows(false, []))->toBe([]);
});

it('ofrece como forma de pago solo las filas que quedaron', function (): void {
    expect(PlanGeneratorCompanyRates::paymentFrequencyOptions(['plan' => ['include_monthly_total' => false, 'group_total_hidden_rows' => ['semestral', 'trimestral']]]))
        ->toBe(['ANUAL' => 'ANUAL'])
        ->and(PlanGeneratorCompanyRates::paymentFrequencyOptions(['plan' => ['include_monthly_total' => true, 'group_total_hidden_rows' => ['annual']]]))
        ->toBe(['SEMESTRAL' => 'SEMESTRAL', 'TRIMESTRAL' => 'TRIMESTRAL', 'MENSUAL' => 'MENSUAL'])
        // Una sesión guardada antes del cambio no trae la clave: se ofrecen todas.
        ->and(PlanGeneratorCompanyRates::paymentFrequencyOptions(['plan' => []]))
        ->toBe(['ANUAL' => 'ANUAL', 'SEMESTRAL' => 'SEMESTRAL', 'TRIMESTRAL' => 'TRIMESTRAL']);
});

it('el editor pinta el botón de quitar y el de restaurar; la vista de lectura no', function (): void {
    $partial = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/plan-generators/partials/group-total-matrix.blade.php');
    $editor = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/plan-generators/stacked-matrices-editor.blade.php');
    $preview = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/plan-generators/stacked-matrices-preview.blade.php');

    expect($partial)->toContain('removeGroupTotalRow(')->toContain('restoreGroupTotalRow(')->toContain('Debe quedar al menos una forma de pago')
        ->and($editor)->toContain("'editable' => true")
        ->and($preview)->not->toContain("'editable' => true");
});
