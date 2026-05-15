<?php

declare(strict_types=1);

it('formulario de historia clínica en Operaciones usa pestañas por bloque', function (): void {
    $c = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineHistoryPatients/Schemas/TelemedicineHistoryPatientForm.php');

    expect($c)
        ->toContain('Tabs::make')
        ->toContain('Tab::make')
        ->toContain('persistTab')
        ->toContain('telemedicineHistoryPatientFormTabs')
        ->toContain('Información general')
        ->toContain('Familiares')
        ->toContain('Patológicos')
        ->toContain('Hábitos y social')
        ->toContain('Quirúrgicos')
        ->toContain('Alergias')
        ->toContain('Medicamentos')
        ->toContain('Ginecológicos');
});
