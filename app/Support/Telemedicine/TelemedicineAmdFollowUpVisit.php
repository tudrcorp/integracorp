<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

/**
 * Seguimiento hecho como visita AMD.
 *
 * En esa visita la historia clínica se registra con «Informar AMD» y el examen
 * con «Examen físico AMD», así que el formulario oculta el diagnóstico
 * principal, la historia clínica de seguimiento y las preguntas de seguimiento.
 * Formulario, revisión e informe consultan esta misma regla.
 *
 * No aplica en un alta médica: ahí no hay asignación de servicio ni botones
 * AMD, y ocultar la historia dejaría al médico sin dónde registrar la visita.
 */
final class TelemedicineAmdFollowUpVisit
{
    public const NOTICE = 'La historia clínica de esta visita se registra con «Informar AMD» y el examen físico con «Examen físico AMD». Por eso aquí no se piden el diagnóstico, la historia ni las preguntas de seguimiento.';

    /**
     * Campos del cuestionario de seguimiento que no aplican a la visita AMD.
     *
     * @var list<string>
     */
    public const HIDDEN_FIELDS = [
        TelemedicineInitialDiagnosisUpdater::FORM_FIELD,
        'current_illness_history',
        'patient_evolution',
        'cuestion_1',
        'cuestion_2',
        'cuestion_3',
        'cuestion_4',
        'cuestion_5',
    ];

    /**
     * Diagnóstico con el que un seguimiento actualiza el de la consulta inicial.
     *
     * Fuera de la AMD se respeta el respaldo histórico: lo guardado o, si no,
     * el campo del formulario. En la visita AMD ese campo está oculto y no se
     * guarda, pero el estado del formulario conserva lo que el médico haya
     * escrito antes de elegir AMD: usarlo cambiaría el diagnóstico del caso con
     * un texto que el médico ya no ve. Ahí solo cuenta lo guardado.
     *
     * @param  array<string, mixed>  $formState
     */
    public static function diagnosisToSync(?string $savedDiagnosis, array $formState): string
    {
        if (self::applies($formState['telemedicine_service_list_id'] ?? null, $formState['feedbackOne'] ?? false)) {
            return (string) ($savedDiagnosis ?? '');
        }

        return (string) ($savedDiagnosis ?? $formState[TelemedicineInitialDiagnosisUpdater::FORM_FIELD] ?? '');
    }

    public static function applies(mixed $serviceListId, mixed $isDischarge = false): bool
    {
        return ! filter_var($isDischarge, FILTER_VALIDATE_BOOLEAN)
            && is_numeric($serviceListId)
            && (int) $serviceListId === TelemedicineCaseTdgReassignmentCoordination::AMD_SERVICE_LIST_ID;
    }
}
