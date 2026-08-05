<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\ObservationCase;
use App\Models\OperationCoordinationService;
use App\Models\OperationServiceOrder;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineDoctor;
use App\Models\User;
use App\Support\Telemedicine\TelemedicineDerivedServiceBadge;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Reasignación de una coordinación «TRASLADO EN AMBULANCIA» a un médico TDG:
 * status REASIGNADO A TDG, managed_by TDG, bitácora del caso y CxC.
 * Si el caso estaba en ALTA MÉDICA, se reabre a EN SEGUIMIENTO para que sea visible en telemedicina.
 */
final class ReassignAmbulanceCoordinationToTdgDoctor
{
    public const STATUS_REASSIGNED_TO_TDG = 'REASIGNADO A TDG';

    public const CASE_STATUS_ALTA_MEDICA = 'ALTA MEDICA';

    public const CASE_STATUS_REOPENED = 'EN SEGUIMIENTO';

    public const OBSERVATION_PREFIX = 'Reasignación a TDG por TRASLADO EN AMBULANCIA.';

    /**
     * @return list<string>
     */
    public static function blockedStatuses(): array
    {
        return ['CANCELADA', 'CANCELADO'];
    }

    public static function isTrasladoEnAmbulancia(OperationCoordinationService $coordination): bool
    {
        return TelemedicineDerivedServiceBadge::specificServiceIsTrasladoEnAmbulancia($coordination->specific_service);
    }

    public static function isAlreadyReassigned(OperationCoordinationService $coordination): bool
    {
        return mb_strtoupper(trim((string) $coordination->status)) === self::STATUS_REASSIGNED_TO_TDG;
    }

    public static function isEligible(OperationCoordinationService $coordination): bool
    {
        if (! self::isTrasladoEnAmbulancia($coordination)) {
            return false;
        }

        $status = mb_strtoupper(trim((string) $coordination->status));

        return ! in_array($status, self::blockedStatuses(), true);
    }

    public static function buildBitacoraDescription(string $doctorName, string $reason): string
    {
        return self::OBSERVATION_PREFIX
            ."\n".'Médico TDG: '.$doctorName
            ."\n".'Motivo: '.trim($reason);
    }

    /**
     * @return array{
     *     coordination: OperationCoordinationService,
     *     doctor: TelemedicineDoctor,
     *     created_receivable: bool,
     *     doctor_changed: bool,
     *     first_reassignment: bool,
     *     case_reopened: bool
     * }
     */
    public static function execute(
        OperationCoordinationService $coordination,
        int $doctorId,
        string $reason,
        ?User $user = null,
    ): array {
        $user ??= Auth::user();
        $reason = trim($reason);

        if (mb_strlen($reason) < 10) {
            throw new InvalidArgumentException('El motivo de la reasignación debe tener al menos 10 caracteres.');
        }

        if (! self::isEligible($coordination)) {
            throw new InvalidArgumentException(
                'Solo se pueden reasignar a TDG coordinaciones de TRASLADO EN AMBULANCIA que no estén canceladas.'
            );
        }

        if (! filled($coordination->telemedicine_case_id)) {
            throw new InvalidArgumentException(
                'La coordinación no tiene un caso de telemedicina vinculado; no se puede reasignar al panel médico.'
            );
        }

        $doctor = TelemedicineDoctor::query()->find($doctorId);

        if ($doctor === null) {
            throw new InvalidArgumentException('El médico seleccionado no existe.');
        }

        if (mb_strtoupper(trim((string) $doctor->status)) !== 'ACTIVO') {
            throw new InvalidArgumentException('El médico seleccionado no está activo.');
        }

        if (mb_strtoupper(trim((string) $doctor->managed_by)) !== 'TDG') {
            throw new InvalidArgumentException('El médico seleccionado debe pertenecer a TDG.');
        }

        $doctorName = filled($doctor->full_name) ? (string) $doctor->full_name : 'Médico #'.$doctor->id;
        $bitacoraDescription = self::buildBitacoraDescription($doctorName, $reason);
        $userId = $user?->id;
        $userName = filled($user?->name) ? (string) $user->name : 'SISTEMA';
        $previousDoctorId = $coordination->telemedicine_doctor_id !== null
            ? (int) $coordination->telemedicine_doctor_id
            : null;
        $doctorChanged = $previousDoctorId !== $doctorId;
        $firstReassignment = ! self::isAlreadyReassigned($coordination);
        $createdReceivable = false;
        $caseReopened = false;

        DB::transaction(function () use (
            $coordination,
            $doctorId,
            &$bitacoraDescription,
            $reason,
            $user,
            $userId,
            $userName,
            $firstReassignment,
            &$createdReceivable,
            &$caseReopened,
        ): void {
            $case = TelemedicineCase::query()->find($coordination->telemedicine_case_id);
            $caseUpdates = [
                'telemedicine_doctor_id' => $doctorId,
                'managed_by' => 'TDG',
            ];

            if ($case !== null && mb_strtoupper(trim((string) $case->status)) === self::CASE_STATUS_ALTA_MEDICA) {
                $caseUpdates['status'] = self::CASE_STATUS_REOPENED;
                $caseReopened = true;
                $bitacoraDescription .= "\n".'Caso reabierto desde ALTA MEDICA → '.self::CASE_STATUS_REOPENED.' para gestión TDG.';

                TelemedicineConsultationPatient::query()
                    ->where('telemedicine_case_id', $case->id)
                    ->whereRaw('UPPER(TRIM(status)) = ?', [self::CASE_STATUS_ALTA_MEDICA])
                    ->update(['status' => self::CASE_STATUS_REOPENED]);
            }

            $previousObservations = trim((string) ($coordination->observations ?? ''));

            $coordination->telemedicine_doctor_id = $doctorId;
            $coordination->managed_by = 'TDG';
            $coordination->status = self::STATUS_REASSIGNED_TO_TDG;
            $coordination->observations = $previousObservations !== ''
                ? $previousObservations."\n\n".$bitacoraDescription
                : $bitacoraDescription;
            $coordination->updated_by = $userName;
            $coordination->save();

            TelemedicineCase::query()
                ->whereKey($coordination->telemedicine_case_id)
                ->update($caseUpdates);

            ObservationCase::query()->create([
                'telemedicine_case_id' => $coordination->telemedicine_case_id,
                'description' => $bitacoraDescription,
                'created_by' => $userId !== null ? (string) $userId : null,
            ]);

            OperationServiceOrder::query()
                ->where('operation_coordination_service_id', $coordination->id)
                ->update(['managed_by' => 'TDG']);

            if ($firstReassignment) {
                AccountsReceivableManager::createFromTdgReassignment(
                    $coordination->fresh() ?? $coordination,
                    $reason,
                    $user,
                );
                $createdReceivable = true;
            }
        });

        return [
            'coordination' => $coordination->fresh() ?? $coordination,
            'doctor' => $doctor,
            'created_receivable' => $createdReceivable,
            'doctor_changed' => $doctorChanged,
            'first_reassignment' => $firstReassignment,
            'case_reopened' => $caseReopened,
        ];
    }
}
