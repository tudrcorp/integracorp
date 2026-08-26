<?php

declare(strict_types=1);

it('agrega historia de la enfermedad actual y evolución solo en el cuestionario de seguimiento', function (): void {
    $form = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientForm.php'
    );

    $followUpStep = strpos($form, "Step::make('Cuestionario de Seguimiento')");
    $motivoStep = strpos($form, "Step::make('Motivo de la Consulta')");
    $historyField = strpos($form, "Textarea::make('current_illness_history')");
    $evolutionField = strpos($form, "Textarea::make('patient_evolution')");

    expect($followUpStep)->not->toBeFalse()
        ->and($motivoStep)->not->toBeFalse()
        ->and($historyField)->not->toBeFalse()
        ->and($evolutionField)->not->toBeFalse()
        ->and($historyField)->toBeGreaterThan($followUpStep)
        ->and($evolutionField)->toBeGreaterThan($followUpStep)
        ->and($historyField)->toBeGreaterThan($motivoStep);

    $motivoSection = substr($form, (int) $motivoStep, (int) $followUpStep - (int) $motivoStep);

    expect($motivoSection)
        ->not->toContain("Textarea::make('current_illness_history')")
        ->not->toContain("Textarea::make('patient_evolution')");

    expect($form)
        ->toContain("->label('Historia de la enfermedad actual')")
        ->toContain("->label('Evolución del paciente')")
        ->toContain('isFollowUpConsultationContext')
        ->toContain("Fieldset::make('Historia clínica de seguimiento')");
});

it('no exige las cinco preguntas de seguimiento', function (): void {
    $form = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientForm.php'
    );

    $start = strpos($form, "Fieldset::make('Preguntas de Seguimiento')");
    $end = strpos($form, "Section::make('Estatus del caso')");

    expect($start)->not->toBeFalse()
        ->and($end)->not->toBeFalse()
        ->and($end)->toBeGreaterThan($start);

    $questions = substr($form, (int) $start, (int) $end - (int) $start);

    expect($questions)
        ->toContain("Textarea::make('cuestion_1')")
        ->toContain("Textarea::make('cuestion_2')")
        ->toContain("Textarea::make('cuestion_3')")
        ->toContain("Textarea::make('cuestion_4')")
        ->toContain("Textarea::make('cuestion_5')")
        ->not->toContain('->required()');
});

it('persiste las notas clínicas de seguimiento en el modelo de consulta', function (): void {
    $model = file_get_contents(dirname(__DIR__, 2).'/app/Models/TelemedicineConsultationPatient.php');

    expect($model)
        ->toContain("'current_illness_history'")
        ->toContain("'patient_evolution'");
});

it('agrega columnas longtext nullable para las notas clínicas de seguimiento', function (): void {
    $migration = file_get_contents(
        dirname(__DIR__, 2).'/database/migrations/2026_08_25_224900_add_follow_up_clinical_notes_to_telemedicine_consultation_patients_table.php'
    );

    expect($migration)
        ->toContain("Schema::hasTable('telemedicine_consultation_patients')")
        ->toContain("hasColumn('telemedicine_consultation_patients', 'current_illness_history')")
        ->toContain("hasColumn('telemedicine_consultation_patients', 'patient_evolution')")
        ->toContain("\$table->longText('current_illness_history')->nullable()")
        ->toContain("\$table->longText('patient_evolution')->nullable()");
});

it('muestra las notas clínicas de seguimiento en el infolist de telemedicina', function (): void {
    $infolist = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientInfolist.php'
    );

    $tab = strpos($infolist, "Tab::make('Cuestionario de seguimiento')");
    $history = strpos($infolist, "TextEntry::make('current_illness_history')");
    $evolution = strpos($infolist, "TextEntry::make('patient_evolution')");

    expect($tab)->not->toBeFalse()
        ->and($history)->toBeGreaterThan($tab)
        ->and($evolution)->toBeGreaterThan($tab)
        ->and($infolist)->toContain("->label('Historia de la enfermedad actual')")
        ->and($infolist)->toContain("->label('Evolución del paciente')");
});
