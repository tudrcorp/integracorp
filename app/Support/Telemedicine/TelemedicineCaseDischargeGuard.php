<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\OperationCoordinationService;
use App\Models\TelemedicinePatientLab;
use App\Models\TelemedicinePatientMedications;
use App\Models\TelemedicinePatientSpecialty;
use App\Models\TelemedicinePatientStudy;
use Illuminate\Validation\ValidationException;

/**
 * Reglas para dar ALTA MÉDICA a un caso: no debe haber servicios/ítems
 * asociados en PENDIENTE o EN GESTION.
 */
final class TelemedicineCaseDischargeGuard
{
    /**
     * @return list<string>
     */
    public static function openServiceStatuses(): array
    {
        return ['PENDIENTE', 'EN GESTION'];
    }

    /**
     * @return list<string>
     */
    public static function dischargeReadyServiceStatuses(): array
    {
        return ['FINALIZADO', 'CADUCADA'];
    }

    public static function statusIsOpen(mixed $status): bool
    {
        return in_array(mb_strtoupper(trim((string) $status)), self::openServiceStatuses(), true);
    }

    public static function caseHasOpenAssociatedServices(int $caseId): bool
    {
        return self::openAssociatedServicesCount($caseId) > 0;
    }

    public static function caseCanBeDischarged(int $caseId): bool
    {
        return $caseId > 0 && ! self::caseHasOpenAssociatedServices($caseId);
    }

    public static function openAssociatedServicesCount(int $caseId): int
    {
        if ($caseId <= 0) {
            return 0;
        }

        $openStatuses = self::openServiceStatuses();

        $coordinationCount = OperationCoordinationService::query()
            ->where('telemedicine_case_id', $caseId)
            ->where(function ($query) use ($openStatuses): void {
                foreach ($openStatuses as $index => $status) {
                    $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                    $query->{$method}('UPPER(TRIM(status)) = ?', [$status]);
                }
            })
            ->count();

        $clinicalCount = 0;

        foreach ([
            TelemedicinePatientMedications::class,
            TelemedicinePatientLab::class,
            TelemedicinePatientStudy::class,
            TelemedicinePatientSpecialty::class,
        ] as $modelClass) {
            $clinicalCount += $modelClass::query()
                ->where('telemedicine_case_id', $caseId)
                ->where(function ($query) use ($openStatuses): void {
                    foreach ($openStatuses as $index => $status) {
                        $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                        $query->{$method}('UPPER(TRIM(status)) = ?', [$status]);
                    }
                })
                ->count();
        }

        return $coordinationCount + $clinicalCount;
    }

    public static function blockingMessage(int $caseId): string
    {
        $count = self::openAssociatedServicesCount($caseId);

        if ($count <= 0) {
            return 'No se puede dar ALTA MÉDICA con servicios asociados abiertos.';
        }

        $label = $count === 1 ? '1 servicio/ítem asociado' : "{$count} servicios/ítems asociados";

        return "No se puede dar ALTA MÉDICA: hay {$label} en PENDIENTE o EN GESTIÓN. "
            .'El caso solo puede darse de alta cuando todos los servicios asociados estén finalizados o caducados.';
    }

    public static function assertCanBeDischarged(int $caseId): void
    {
        if (self::caseCanBeDischarged($caseId)) {
            return;
        }

        throw ValidationException::withMessages([
            'feedbackOne' => self::blockingMessage($caseId),
        ]);
    }
}
