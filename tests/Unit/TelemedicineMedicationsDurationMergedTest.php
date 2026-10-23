<?php

declare(strict_types=1);

/**
 * En la consulta de Telemedicina la duración va dentro de las indicaciones: no hay campo
 * «Duración(en días)» y las indicaciones son obligatorias. Operaciones no cambia.
 */
function telemedicineConsultationSource(string $relative): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/'.$relative);
}

it('quita la columna y el campo de duración del paso de medicamentos', function (): void {
    $form = telemedicineConsultationSource('app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientForm.php');

    expect($form)->not->toContain("TableColumn::make('Duración(en días)')")
        ->and($form)->not->toContain("TextInput::make('duration')")
        ->and($form)->toContain("TableColumn::make('Indicaciones y Duración en días')");
});

it('exige las indicaciones, que ahora llevan la duración', function (): void {
    $form = telemedicineConsultationSource('app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientForm.php');
    $field = substr($form, (int) strpos($form, "TextInput::make('indications')"), 400);

    expect($field)->toContain('->required()');
});

it('guarda el medicamento aunque la fila no traiga duración', function (): void {
    $page = telemedicineConsultationSource('app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php');

    expect($page)->toContain("filled(\$medicationsArr[\$i]['duration'] ?? null)")
        ->and($page)->not->toContain("\$medications->duration = \$medicationsArr[\$i]['duration'];");
});

it('no toca el formulario de consulta de Operaciones', function (): void {
    expect(telemedicineConsultationSource('app/Filament/Operations/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientForm.php'))
        ->toContain("TextInput::make('duration')");
});
