<?php

declare(strict_types=1);

it('OperationCoordinationServicesTable resalta servicios con badge info/danger según derivado crítico', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('use App\Support\Telemedicine\TelemedicineDerivedServiceBadge;')
        ->and($contents)->toContain("TextColumn::make('servicie')")
        ->and($contents)->toContain("TextColumn::make('specific_service')");

    expect(substr_count($contents, 'TelemedicineDerivedServiceBadge::driftNameIsCritical'))->toBeGreaterThanOrEqual(4);
});

it('OperationCoordinationServicesTable define la acción modal de doctor TDG para traslado en ambulancia', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("Action::make('selectTdgDoctorForAmbulanceFollowUp')")
        ->and($contents)->toContain('Seleccionar Doctor TDG para seguimiento de caso')
        ->and($contents)->toContain('TelemedicineDerivedServiceBadge::specificServiceIsTrasladoEnAmbulancia')
        ->and($contents)->toContain('TelemedicineDoctor::query()')
        ->and($contents)->toContain("'managed_by', 'TDG'")
        ->and($contents)->toContain('TelemedicineCase::query()')
        ->and($contents)->toContain('telemedicine_case_id')
        ->and($contents)->toContain('Width::TwoExtraLarge')
        ->and($contents)->toContain('FilamentIosButton::extraClassForFilamentColor');
});
