<?php

declare(strict_types=1);

it('filtra casos por managed_by ATENMEDI en el dashboard de telemedicina para usuarios ATENMEDI', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Telemedicina/Widgets/TelemedicineCaseTableDash.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('userDepartmentsIncludeAtenmedi')
        ->toContain("->where('managed_by', 'ATENMEDI')")
        ->toContain('telemedicine_doctor_follow_up_id')
        ->toContain('excludeCasesWhereLatestConsultationDriftIsTrasladoAmbulanciaForAtenmediDoctor')
        ->and($contents)->toContain('atenmediUserBlockedFromUpdatingConsultation')
        ->and($contents)->toContain('ATENMEDI_BLOCK_UPDATE_DRIFT_SERVICE_LIST_ID')
        ->and($contents)->toContain('userIsInAtenmediTelemedicinaContext')
        ->and($contents)->toContain('driftServiceNameIndicatesTrasladoAmbulancia')
        ->and($contents)->toContain('última consulta tenga derivado');
});
