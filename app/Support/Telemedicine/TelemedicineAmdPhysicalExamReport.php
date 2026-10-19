<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\TelemedicineConsultationPatient;
use App\Models\User;

/**
 * Examen físico AMD como anexo del informe de su consulta.
 *
 * El examen va al informe de la consulta en que se hizo la visita: al Informe
 * Médico si es la consulta inicial, al Informe de Seguimiento si no. Aquí viven
 * las tres reglas de ese vínculo, para que la UI, el PDF y la regeneración no
 * puedan contar historias distintas.
 */
final class TelemedicineAmdPhysicalExamReport
{
    public const INFORME_MEDICO = 'Informe Médico';

    public const INFORME_SEGUIMIENTO = 'Informe de Seguimiento';

    /**
     * Nombre del informe al que se anexa el examen según la etapa de la
     * consulta. Null si la etapa aún no se conoce.
     */
    public static function labelForStatus(?string $status): ?string
    {
        $status = trim((string) $status);

        if ($status === '') {
            return null;
        }

        return $status === TelemedicineInitialDiagnosisUpdater::INITIAL_STATUS
            ? self::INFORME_MEDICO
            : self::INFORME_SEGUIMIENTO;
    }

    /**
     * Examen que debe pintar un informe.
     *
     * Si los datos ya lo traen (aunque sea null) se respeta: el Informe AMD lo
     * resuelve con el examen pendiente cuando la consulta aún no existe. Si no,
     * se busca **solo por la consulta del informe**: nunca el de otra consulta
     * del mismo caso.
     *
     * @param  array<string, mixed>  $data
     * @return array{vitals: list<array{label: string, value: string}>, systems: list<array{label: string, text: string, is_default: bool}>}|null
     */
    public static function forInformeData(array $data): ?array
    {
        if (array_key_exists('physical_exam', $data)) {
            return is_array($data['physical_exam']) ? $data['physical_exam'] : null;
        }

        $exam = TelemedicineAmdPhysicalExamRegistrar::forConsultation((int) ($data['telemedicine_consultation_id'] ?? 0));

        return $exam === null ? null : TelemedicineAmdPhysicalExamTemplate::display($exam);
    }

    /**
     * Encola el informe de la consulta para que salga con el examen recién
     * guardado. Devuelve el nombre del informe encolado, o null si la consulta
     * no emite informe.
     */
    public static function queueReportRefresh(TelemedicineConsultationPatient $consultation, User $user): ?string
    {
        $job = app(TelemedicineCaseDocumentRegenerationService::class)->informeJobFor($consultation, $user);

        if ($job === null) {
            return null;
        }

        dispatch($job);

        return self::labelForStatus((string) $consultation->status);
    }
}
