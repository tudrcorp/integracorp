<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicinePatient;
use Illuminate\Http\Request;

/**
 * Enlace a «registrar consulta» con el contexto clínico en la URL.
 *
 * El caso y el paciente viajaban en la sesión, que es una por usuario: abrir un
 * segundo caso en otra pestaña reescribía el de la primera. La URL, en cambio,
 * es de la pestaña, así que cada punto de entrada al asistente arma el enlace
 * con esta clase y la página de creación lo lee de vuelta en `mount()`.
 */
final class ConsultationCreateRoute
{
    public const ROUTE = 'filament.telemedicina.resources.telemedicine-consultation-patients.create';

    /** Nombre histórico del parámetro del paciente; no renombrar, hay enlaces vivos. */
    public const PATIENT_PARAM = 'id';

    public const CASE_PARAM = 'caseId';

    public const CONSULTATION_PARAM = 'consultationId';

    /**
     * @return array<string, int>
     */
    public static function parameters(
        TelemedicinePatient|int|null $patient,
        TelemedicineCase|int|null $case = null,
        TelemedicineConsultationPatient|int|null $consultation = null,
    ): array {
        $parameters = [];

        $patientId = self::idOf($patient);
        if ($patientId !== null) {
            $parameters[self::PATIENT_PARAM] = $patientId;
        }

        $caseId = self::idOf($case);
        if ($caseId !== null) {
            $parameters[self::CASE_PARAM] = $caseId;
        }

        $consultationId = self::idOf($consultation);
        if ($consultationId !== null) {
            $parameters[self::CONSULTATION_PARAM] = $consultationId;
        }

        return $parameters;
    }

    public static function url(
        TelemedicinePatient|int|null $patient,
        TelemedicineCase|int|null $case = null,
        TelemedicineConsultationPatient|int|null $consultation = null,
    ): string {
        return route(self::ROUTE, self::parameters($patient, $case, $consultation));
    }

    public static function patientIdFromRequest(?Request $request = null): ?int
    {
        return self::intParameter($request, self::PATIENT_PARAM);
    }

    public static function caseIdFromRequest(?Request $request = null): ?int
    {
        return self::intParameter($request, self::CASE_PARAM);
    }

    public static function consultationIdFromRequest(?Request $request = null): ?int
    {
        return self::intParameter($request, self::CONSULTATION_PARAM);
    }

    private static function intParameter(?Request $request, string $key): ?int
    {
        $value = ($request ?? request())->query($key);

        if (! is_scalar($value)) {
            return null;
        }

        $value = (int) $value;

        return $value > 0 ? $value : null;
    }

    private static function idOf(TelemedicinePatient|TelemedicineCase|TelemedicineConsultationPatient|int|null $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $id = is_int($value) ? $value : (int) $value->getKey();

        return $id > 0 ? $id : null;
    }
}
