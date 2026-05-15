<?php

declare(strict_types=1);

it('infolist de historia clínica usa pestañas por bloque', function (): void {
    $c = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineHistoryPatients/Schemas/TelemedicineHistoryPatientInfolist.php');

    expect($c)
        ->toContain('Tabs::make')
        ->toContain('Tab::make')
        ->toContain('persistTab')
        ->toContain('Información general')
        ->toContain('Familiares')
        ->toContain('Patológicos')
        ->toContain('Hábitos y social')
        ->toContain('Quirúrgicos')
        ->toContain('Alergias')
        ->toContain('Medicamentos')
        ->toContain('Ginecológicos');
});

it('infolist de historia clínica en panel Telemedicina usa pestañas por bloque', function (): void {
    $c = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineHistoryPatients/Schemas/TelemedicineHistoryPatientInfolist.php');

    expect($c)
        ->toContain('Tabs::make')
        ->toContain('Tab::make')
        ->toContain('persistTab')
        ->toContain('telemedicineHistoryPatientTelemedicinaInfolistTabs')
        ->toContain('Información general')
        ->toContain('Ginecológicos');
});
