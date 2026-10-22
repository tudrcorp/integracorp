<?php

declare(strict_types=1);

it('amplía symptoms_diagnosis de la coordinación a text para no recortar el diagnóstico', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_10_21_182929_change_symptoms_diagnosis_to_text_on_operation_coordination_services_table.php');

    expect($contents)
        ->toContain("private const COLUMN = 'symptoms_diagnosis'")
        ->toContain('->text(self::COLUMN)')
        ->toContain('->string(self::COLUMN)')
        ->toContain('->change()')
        ->toContain('Síntomas y diagnóstico')
        ->toContain('operation_coordination_services')
        ->toContain('isTextColumn');
});

it('copia el diagnóstico de la consulta completo, sin recortarlo a 255', function (): void {
    $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/OperationCoordinationServiceController.php');

    expect($controller)
        ->toContain("'symptoms_diagnosis' => \$record['diagnostic_impression'] ?? '...'")
        ->not->toContain('mb_substr')
        ->not->toContain('Str::limit');
});
