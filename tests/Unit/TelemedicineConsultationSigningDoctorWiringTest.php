<?php

declare(strict_types=1);

/**
 * Los documentos de telemedicina se firman con el médico que atiende. El pool TDG
 * deja que un médico abra el caso de otro, así que tomar el firmante del caso
 * estampaba el sello equivocado en informe, receta y órdenes. Estos tests leen el
 * código para que nadie reintroduzca esa fuente.
 */
const FORM_CONSULTA = __DIR__.'/../../app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientForm.php';

const PAGINA_CREAR_CONSULTA = __DIR__.'/../../app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php';

const DEFAULTS_ASISTENTE_CONSULTA = __DIR__.'/../../app/Support/Telemedicine/ConsultationCreateWizardDefaults.php';

it('el formulario de consulta no toma el firmante del médico asignado al caso', function (): void {
    $contenido = file_get_contents(FORM_CONSULTA);

    expect($contenido)
        ->not->toContain("Hidden::make('telemedicine_doctor_id')->default(\$case->telemedicine_doctor_id)")
        ->not->toContain("'telemedicine_doctor_id' => \$case->telemedicine_doctor_id,")
        ->toContain('TelemedicineConsultationSigningDoctor::defaultIdForForm(Auth::user(), $case)');
});

it('la página de creación fija el firmante en el servidor antes de guardar', function (): void {
    $contenido = file_get_contents(PAGINA_CREAR_CONSULTA);

    expect($contenido)
        ->toContain('protected function mutateFormDataBeforeCreate(array $data): array')
        ->toContain('TelemedicineConsultationSigningDoctor::idForUser(Auth::user())')
        ->toContain("\$data['telemedicine_doctor_id'] = \$signingDoctorId;");
});

it('la página de creación bloquea la consulta si el usuario no tiene médico vinculado', function (): void {
    $contenido = file_get_contents(PAGINA_CREAR_CONSULTA);

    expect($contenido)
        ->toContain('failConsultationSigningDoctor')
        ->toContain('TelemedicineConsultationSigningDoctor::MISSING_DOCTOR_MESSAGE')
        ->toContain('throw ValidationException::withMessages');
});

it('los valores por defecto del asistente resuelven el firmante desde el usuario que atiende', function (): void {
    $contenido = file_get_contents(DEFAULTS_ASISTENTE_CONSULTA);

    expect($contenido)
        ->toContain('TelemedicineConsultationSigningDoctor::idForUserId($assignedByUserId)');
});
