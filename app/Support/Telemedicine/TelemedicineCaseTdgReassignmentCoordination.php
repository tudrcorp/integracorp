<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\OperationCoordinationService;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicinePatientSpecialty;
use App\Models\TelemedicineServiceList;
use Illuminate\Support\Facades\Auth;

/**
 * Coordinación creada al reasignar un caso de ATENMEDI a TDG (AMD u otros servicios).
 */
final class TelemedicineCaseTdgReassignmentCoordination
{
    public const OBSERVATION_PREFIX = 'Reasignación de gestión ATENMEDI → TDG.';

    public const AMD_SERVICE_LIST_ID = 2;

    public static function isReassignmentCoordination(OperationCoordinationService $record): bool
    {
        return str_starts_with(trim((string) $record->observations), self::OBSERVATION_PREFIX);
    }

    public static function consultationInvolvesAmd(?TelemedicineConsultationPatient $consultation): bool
    {
        if ($consultation === null) {
            return false;
        }

        return (int) $consultation->telemedicine_service_list_id === self::AMD_SERVICE_LIST_ID
            || (int) $consultation->telemedicine_service_list_drift_id === self::AMD_SERVICE_LIST_ID;
    }

    public static function isAmdReassignmentCoordination(OperationCoordinationService $record): bool
    {
        if (! self::isReassignmentCoordination($record)) {
            return false;
        }

        $record->loadMissing('telemedicineConsultationPatient');

        return self::consultationInvolvesAmd($record->telemedicineConsultationPatient);
    }

    public static function ensureAmdManagementItem(OperationCoordinationService $record): void
    {
        if (! self::isAmdReassignmentCoordination($record)) {
            return;
        }

        if ($record->telemedicinePatientSpecialties()->exists()) {
            return;
        }

        $record->loadMissing([
            'telemedicineConsultationPatient.telemedicineServiceList',
            'telemedicineConsultationPatient.telemedicineServiceListDrift',
        ]);

        $consultation = $record->telemedicineConsultationPatient;

        if ($consultation === null) {
            return;
        }

        $amdLabel = self::amdServiceLabel($consultation, $record);

        TelemedicinePatientSpecialty::query()->create([
            'telemedicine_patient_id' => $record->telemedicine_patient_id,
            'telemedicine_case_id' => $record->telemedicine_case_id,
            'telemedicine_doctor_id' => $record->telemedicine_doctor_id,
            'telemedicine_consultation_patient_id' => $consultation->id,
            'type' => 'CUBIERTO',
            'specialty' => $amdLabel,
            'assigned_by' => (string) (Auth::user()?->name ?? 'SISTEMA'),
            'status' => 'PENDIENTE',
            'operation_coordination_service_id' => $record->id,
        ]);
    }

    public static function seedAmdManagementItemFromCaseReassignment(
        OperationCoordinationService $coordination,
        TelemedicineCase $case,
        ?TelemedicineConsultationPatient $consultation
    ): void {
        if ($consultation === null || ! self::consultationInvolvesAmd($consultation)) {
            return;
        }

        self::ensureAmdManagementItem($coordination);
    }

    private static function amdServiceLabel(
        TelemedicineConsultationPatient $consultation,
        OperationCoordinationService $record
    ): string {
        if ((int) $consultation->telemedicine_service_list_drift_id === self::AMD_SERVICE_LIST_ID) {
            return (string) (
                $consultation->telemedicineServiceListDrift?->name
                ?? $record->specific_service
                ?? 'Asistencia médica domiciliaria'
            );
        }

        if ((int) $consultation->telemedicine_service_list_id === self::AMD_SERVICE_LIST_ID) {
            return (string) (
                $consultation->telemedicineServiceList?->name
                ?? $record->servicie
                ?? 'Asistencia médica domiciliaria'
            );
        }

        $service = TelemedicineServiceList::query()->find(self::AMD_SERVICE_LIST_ID);

        return (string) ($service?->name ?? 'Asistencia médica domiciliaria');
    }
}
