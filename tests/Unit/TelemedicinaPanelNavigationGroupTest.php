<?php

declare(strict_types=1);

it('panel telemedicina registra el grupo de navegacion gestion telemedica', function (): void {
    $path = dirname(__DIR__, 2).'/app/Providers/Filament/TelemedicinaPanelProvider.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("->label('GESTIÓN TELEMÉDICA')")
        ->toContain('navigationGroups')
        ->toContain('healthicons-f-call-centre')
        ->not->toContain('healthicons-f-i-telemedicine');
});

it('recursos principales del panel telemedicina pertenecen al grupo gestion telemedica', function (): void {
    $base = dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources';
    $resources = [
        $base.'/TelemedicineDoctors/TelemedicineDoctorResource.php',
        $base.'/TelemedicinePatients/TelemedicinePatientResource.php',
        $base.'/TelemedicineCases/TelemedicineCaseResource.php',
    ];

    foreach ($resources as $path) {
        expect(file_get_contents($path))
            ->toContain("navigationGroup = 'GESTIÓN TELEMÉDICA'");
    }
});
