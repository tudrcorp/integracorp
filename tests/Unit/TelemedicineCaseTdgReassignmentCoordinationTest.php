<?php

declare(strict_types=1);

use App\Models\OperationCoordinationService;
use App\Models\TelemedicineConsultationPatient;
use App\Support\Telemedicine\TelemedicineCaseTdgReassignmentCoordination;

it('detecta coordinacion por reasignacion atenmedi a tdg', function (): void {
    $record = new OperationCoordinationService([
        'observations' => TelemedicineCaseTdgReassignmentCoordination::OBSERVATION_PREFIX."\n".'Motivo: prueba',
    ]);

    expect(TelemedicineCaseTdgReassignmentCoordination::isReassignmentCoordination($record))->toBeTrue();
});

it('detecta amd en consulta por servicio principal o derivado', function (): void {
    $amdMain = new TelemedicineConsultationPatient([
        'telemedicine_service_list_id' => TelemedicineCaseTdgReassignmentCoordination::AMD_SERVICE_LIST_ID,
        'telemedicine_service_list_drift_id' => 5,
    ]);

    $amdDrift = new TelemedicineConsultationPatient([
        'telemedicine_service_list_id' => 1,
        'telemedicine_service_list_drift_id' => TelemedicineCaseTdgReassignmentCoordination::AMD_SERVICE_LIST_ID,
    ]);

    $other = new TelemedicineConsultationPatient([
        'telemedicine_service_list_id' => 1,
        'telemedicine_service_list_drift_id' => 3,
    ]);

    expect(TelemedicineCaseTdgReassignmentCoordination::consultationInvolvesAmd($amdMain))->toBeTrue()
        ->and(TelemedicineCaseTdgReassignmentCoordination::consultationInvolvesAmd($amdDrift))->toBeTrue()
        ->and(TelemedicineCaseTdgReassignmentCoordination::consultationInvolvesAmd($other))->toBeFalse();
});

it('expone prefijo de observacion usado en reasignacion', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineCases/Tables/TelemedicineCasesTable.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('TelemedicineCaseTdgReassignmentCoordination::OBSERVATION_PREFIX')
        ->toContain('seedAmdManagementItemFromCaseReassignment');
});

it('habilita gestion amd en tabla de coordinacion', function (): void {
    $manager = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/CoordinationServiceItemsManager.php');

    expect($manager)
        ->toContain('manageServiceActionIsDisabled')
        ->toContain('TelemedicineCaseTdgReassignmentCoordination::ensureAmdManagementItem');
});
