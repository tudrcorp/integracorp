<?php

declare(strict_types=1);

it('formulario de paciente en Operaciones usa pestañas con estilos ios', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicinePatients/Schemas/TelemedicinePatientForm.php');

    expect($contents)
        ->toContain('Tabs::make')
        ->toContain('Tab::make')
        ->toContain('persistTab')
        ->toContain('telemedicinePatientFormTabs')
        ->toContain('TABS_CONTAINER')
        ->toContain('SECTION_CARD')
        ->toContain('Información principal')
        ->toContain('Representante o Contacto')
        ->toContain('Unidades de Negocio')
        ->toContain('Heroicon::OutlinedIdentification')
        ->toContain('Heroicon::OutlinedUsers')
        ->toContain('Heroicon::OutlinedBuildingOffice2')
        ->toContain("TextInput::make('phone_contact')")
        ->toContain("TextInput::make('email_contact')")
        ->toContain('Teléfono de contacto')
        ->toContain('Correo de contacto');
});

it('formulario de paciente en Operaciones expone portal solo a analistas TDG con defaults', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicinePatients/Schemas/TelemedicinePatientForm.php');

    expect($contents)
        ->toContain('Portal del paciente')
        ->toContain("TextInput::make('patient_portal_password')")
        ->toContain("Toggle::make('patient_portal_authorized')")
        ->toContain('Clave del portal del paciente')
        ->toContain('Autorizar uso del portal del paciente')
        ->toContain('OperationsSupplierScope::authenticatedUserIsTdgAnalyst()')
        ->toContain('TelemedicinePatient::DEFAULT_PATIENT_PORTAL_PASSWORD')
        ->toContain('->default(true)');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Models/TelemedicinePatient.php'))
        ->toContain("public const DEFAULT_PATIENT_PORTAL_PASSWORD = '12345678'")
        ->toContain("'patient_portal_password'")
        ->toContain("'patient_portal_authorized'")
        ->toContain("'patient_portal_authorized' => 'boolean'");

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicinePatients/Pages/CreateTelemedicinePatient.php'))
        ->toContain("\$data['patient_portal_password'] ??= TelemedicinePatient::DEFAULT_PATIENT_PORTAL_PASSWORD")
        ->toContain("\$data['patient_portal_authorized'] ??= true");

    expect(file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_07_23_083101_add_patient_portal_fields_to_telemedicine_patients_table.php'))
        ->toContain("string('patient_portal_password')")
        ->toContain("->default('12345678')")
        ->toContain("boolean('patient_portal_authorized')")
        ->toContain('->default(true)');
});

it('formulario de paciente en Telemedicina incluye información de contacto', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicinePatients/Schemas/TelemedicinePatientForm.php');

    expect($contents)
        ->toContain("TextInput::make('phone_contact')")
        ->toContain("TextInput::make('email_contact')")
        ->toContain('Teléfono de contacto')
        ->toContain('Correo de contacto');
});
