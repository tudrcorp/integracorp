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
        ->toContain('Heroicon::OutlinedBuildingOffice2');
});
