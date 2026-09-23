<?php

declare(strict_types=1);

use App\Support\PlanGenerators\PlanGeneratorConditions;

it('conserva los saltos de línea y las viñetas del texto pegado', function (): void {
    $pegado = "  * BENEFICIO DE ORIENTACIÓN MÉDICA.\n* BENEFICIOS DOMICILIARIOS TIENEN PERÍODO DE ESPERA.\n\n* SE EXCLUYEN PATOLOGÍAS PREEXISTENTES.  \n";

    expect(PlanGeneratorConditions::normalize($pegado))->toBe(
        "* BENEFICIO DE ORIENTACIÓN MÉDICA.\n* BENEFICIOS DOMICILIARIOS TIENEN PERÍODO DE ESPERA.\n\n* SE EXCLUYEN PATOLOGÍAS PREEXISTENTES.",
    );
});

it('acepta un texto más largo que el tope viejo de 500 caracteres', function (): void {
    $larga = str_repeat("* Condición de la cotización.\n", 40);

    expect(mb_strlen(PlanGeneratorConditions::normalize($larga)))->toBeGreaterThan(500)
        ->and(substr_count(PlanGeneratorConditions::normalize($larga), "\n"))->toBe(39);
});

it('convierte la lista vieja en renglones sin recortar el texto', function (): void {
    $json = json_encode([
        'Vigencia de 15 días.',
        'Las tarifas no incluyen IVA.',
    ], JSON_UNESCAPED_UNICODE);

    expect(PlanGeneratorConditions::fromLegacyStorage($json))->toBe(
        "Vigencia de 15 días.\nLas tarifas no incluyen IVA.",
    )->and(PlanGeneratorConditions::formState(null))->toBe('')
        ->and(PlanGeneratorConditions::normalize("  \n"))->toBe('');
});

it('la derivada pide las condiciones en un cuadro de texto debajo del total grupal', function (): void {
    $campo = (string) file_get_contents(dirname(__DIR__, 2).'/app/Support/PlanGenerators/PlanGeneratorConditions.php');
    $accion = (string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/PlanGenerators/Tables/Actions/DeriveQuotationBulkAction.php');
    $formulario = (string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/PlanGenerators/Schemas/PlanGeneratorForm.php');
    $vista = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/plan-generators/stacked-matrices-preview.blade.php');
    $pdf = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/partials/plan-generator-plan-body.blade.php');
    $migracion = (string) file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_09_23_140000_change_plan_generator_conditions_to_long_text.php');

    expect($campo)
        ->toContain("Textarea::make('conditions')")
        ->not->toContain('Repeater::make');

    expect($accion)
        ->toContain('PlanGeneratorConditions::field()')
        ->toContain('assertConditionsArePresent');

    expect(strpos($accion, 'stacked-matrices-editor'))->toBeLessThan(strpos($accion, 'PlanGeneratorConditions::field()'));

    expect($formulario)->toContain('PlanGeneratorConditions::field(onlyOnDerivedRecord: true)');

    expect($vista)
        ->toContain('group-total-matrix')
        ->toContain('conditions-list')
        ->and(strpos($vista, 'group-total-matrix'))->toBeLessThan(strpos($vista, 'conditions-list'));

    expect($pdf)
        ->toContain('Total grupal')
        ->toContain('conditions-block')
        ->and(strpos($pdf, 'Total grupal'))->toBeLessThan(strpos($pdf, 'conditions-block'));

    expect($migracion)->toContain("longText('conditions')");
});
