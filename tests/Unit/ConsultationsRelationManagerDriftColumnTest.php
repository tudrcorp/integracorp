<?php

declare(strict_types=1);

use App\Support\Telemedicine\TelemedicineDerivedServiceBadge;

it('usa TelemedicineDerivedServiceBadge en el relation manager de consultas', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineCases/RelationManagers/ConsultationsRelationManager.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('TelemedicineDerivedServiceBadge::driftNameIsCritical')
        ->and($contents)->toContain("'danger'")
        ->and($contents)->toContain("'info'");
});

it('importa TelemedicineDerivedServiceBadge en Operations relation manager de consultas del caso', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineCases/RelationManagers/ConsultationsRelationManager.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('use App\Support\Telemedicine\TelemedicineDerivedServiceBadge;')
        ->and($contents)->toContain('TelemedicineDerivedServiceBadge::driftNameIsCritical');
});

it('usa TelemedicineDerivedServiceBadge en el infolist de consulta telemedicina', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientInfolist.php';
    expect(file_get_contents($path))->toContain('TelemedicineDerivedServiceBadge::driftNameIsCritical');
});

it('Operations importa TelemedicineDerivedServiceBadge en TelemedicineConsultationPatientInfolist', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientInfolist.php';
    expect(file_get_contents($path))
        ->toContain('use App\Support\Telemedicine\TelemedicineDerivedServiceBadge;')
        ->and(file_get_contents($path))->toContain('TelemedicineDerivedServiceBadge::driftNameIsCritical');
});

it('detecta derivados críticos insensible a mayúsculas y acentos', function (): void {
    expect(TelemedicineDerivedServiceBadge::driftNameIsCritical('  traslado en ambulancia  '))->toBeTrue()
        ->and(TelemedicineDerivedServiceBadge::driftNameIsCritical('Ingreso a clínica'))->toBeTrue()
        ->and(TelemedicineDerivedServiceBadge::driftNameIsCritical('Consulta general'))->toBeFalse()
        ->and(TelemedicineDerivedServiceBadge::driftNameIsCritical(null))->toBeFalse();
});

it('specificServiceIsTrasladoEnAmbulancia coincide solo con el texto exacto normalizado', function (): void {
    expect(TelemedicineDerivedServiceBadge::specificServiceIsTrasladoEnAmbulancia('TRASLADO EN AMBULANCIA'))->toBeTrue()
        ->and(TelemedicineDerivedServiceBadge::specificServiceIsTrasladoEnAmbulancia('  traslado en ambulancia  '))->toBeTrue()
        ->and(TelemedicineDerivedServiceBadge::specificServiceIsTrasladoEnAmbulancia('INGRESO A CLINICA'))->toBeFalse()
        ->and(TelemedicineDerivedServiceBadge::specificServiceIsTrasladoEnAmbulancia('TRASLADO EN AMBULANCIA — URGENTE'))->toBeFalse()
        ->and(TelemedicineDerivedServiceBadge::specificServiceIsTrasladoEnAmbulancia(null))->toBeFalse();
});
