<?php

declare(strict_types=1);

it('filtra casos por managed_by ATENMEDI en el dashboard de telemedicina para usuarios ATENMEDI', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineCaseFilamentListQuery.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('userIsInAtenmediTelemedicinaContext($user)')
        ->toContain("->where('managed_by', 'ATENMEDI')")
        ->toContain('excludeCasesWhereLatestConsultationDriftIsTrasladoAmbulanciaForAtenmediDoctor')
        ->toContain('atenmediUserBlockedFromUpdatingConsultation')
        ->toContain('driftServiceNameIndicatesTrasladoAmbulancia');
});
