<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\TelemedicinePatient;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Str;

/**
 * Señal «Afiliación vigente: Sí/No» para quien no debe ver los datos de
 * afiliación del paciente (médicos de ATENMEDI).
 *
 * Sale de la afiliación y del afiliado reales, no de
 * `telemedicine_patients.status_affiliation`: esa columna es una copia que se
 * guarda como ACTIVO al crear el paciente y no se actualiza si la afiliación se
 * excluye después.
 */
final class TelemedicinePatientAffiliationValidity
{
    public const VALID = 'Sí';

    public const NOT_VALID = 'No';

    public const NO_AFFILIATION = 'Sin afiliación';

    /** Estatus que cuentan como vigentes, en afiliación y en afiliado. */
    private const ACTIVE_STATUSES = ['ACTIVA', 'ACTIVO', 'PRE-APROBADA', 'PRE-APROBADO'];

    /**
     * Carga en lote lo necesario para una página de pacientes (sin N+1).
     *
     * @param  iterable<TelemedicinePatient>  $patients
     */
    public static function preload(iterable $patients): void
    {
        $collection = $patients instanceof EloquentCollection
            ? $patients
            : new EloquentCollection(array_values(array_filter(
                is_array($patients) ? $patients : iterator_to_array($patients),
                static fn (mixed $patient): bool => $patient instanceof TelemedicinePatient,
            )));

        if ($collection->isEmpty()) {
            return;
        }

        $collection->loadMissing(['afilliation:id,status', 'afilliationCorporate:id,status']);
        TelemedicinePatientPlanBridge::preloadLinkedAffiliates($collection);
    }

    public static function label(TelemedicinePatient $patient): string
    {
        if ((int) ($patient->afilliation_id ?? 0) > 0) {
            return self::fromStatuses(
                $patient->afilliation?->status,
                TelemedicinePatientPlanBridge::linkedAffiliate($patient)?->status,
            );
        }

        if ((int) ($patient->afilliation_corporate_id ?? 0) > 0) {
            return self::fromStatuses(
                $patient->afilliationCorporate?->status,
                TelemedicinePatientPlanBridge::linkedAffiliateCorporate($patient)?->status,
            );
        }

        return self::NO_AFFILIATION;
    }

    public static function color(string $label): string
    {
        return match ($label) {
            self::VALID => 'success',
            self::NOT_VALID => 'danger',
            default => 'gray',
        };
    }

    /**
     * Vigente si la afiliación lo está y, cuando se encontró al afiliado, él también.
     */
    public static function fromStatuses(mixed $affiliationStatus, mixed $affiliateStatus): string
    {
        $affiliation = self::normalize($affiliationStatus);

        if ($affiliation === null) {
            return self::NOT_VALID;
        }

        if (! in_array($affiliation, self::ACTIVE_STATUSES, true)) {
            return self::NOT_VALID;
        }

        $affiliate = self::normalize($affiliateStatus);

        if ($affiliate !== null && ! in_array($affiliate, self::ACTIVE_STATUSES, true)) {
            return self::NOT_VALID;
        }

        return self::VALID;
    }

    private static function normalize(mixed $status): ?string
    {
        $status = Str::upper(Str::ascii(trim((string) $status)));

        return $status === '' ? null : $status;
    }
}
