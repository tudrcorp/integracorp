<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\OperationCoordinationService;
use App\Models\TelemedicinePatientLab;
use App\Models\TelemedicinePatientMedications;
use App\Models\TelemedicinePatientSpecialty;
use App\Models\TelemedicinePatientStudy;

/**
 * Servicios abiertos de un caso al darle ALTA MÉDICA.
 *
 * Desde el 24/10/2026 el alta ya no se bloquea: el médico puede darla con
 * servicios o ítems en PENDIENTE o EN GESTIÓN. Esos servicios quedan como
 * están, para que Operaciones los termine de gestionar; aquí solo se cuentan
 * para avisar al médico y dejar la nota en la bitácora del caso.
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

    /**
     * Aviso para el médico antes de dar el alta. Null si no hay nada abierto.
     */
    public static function openServicesNotice(int $caseId): ?string
    {
        $count = self::openAssociatedServicesCount($caseId);

        if ($count <= 0) {
            return null;
        }

        return 'Este caso tiene '.self::servicesLabel($count).' en PENDIENTE o EN GESTIÓN. '
            .'Puede dar el alta igual: el alta no los cancela y Operaciones los seguirá gestionando.';
    }

    /**
     * Nota de la bitácora del caso cuando el alta se da con servicios abiertos.
     */
    public static function dischargeBitacoraNote(int $count, string $doctorName): string
    {
        return 'ALTA MÉDICA CON SERVICIOS ABIERTOS · '.self::servicesLabel($count).' quedaron en PENDIENTE o EN GESTIÓN; '
            .'el alta no los canceló y Operaciones los seguirá gestionando. Alta dada por: '.$doctorName.'.';
    }

    private static function servicesLabel(int $count): string
    {
        return $count === 1 ? '1 servicio/ítem asociado' : $count.' servicios/ítems asociados';
    }
}
