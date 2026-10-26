<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineDoctor;

final class TelemedicineInformeLargoDataBuilder
{
    /**
     * Datos del Informe Médico.
     *
     * Las indicaciones (medicamentos, laboratorios, estudios, especialistas)
     * que no se pasan se toman de la consulta guardada: el Informe AMD rehace
     * este mismo archivo y antes salía siempre «sin indicaciones», borrando lo
     * que el médico indicó (caso 54905-01277, 25/10/2026). Pasar una lista,
     * aunque sea vacía, la respeta tal cual.
     *
     * @param  array<string, mixed>  $clinicalData
     * @param  array<int, mixed>|null  $medicationsArr
     * @param  array<int, mixed>|null  $labsArr
     * @param  array<int, mixed>|null  $otherLabsArr
     * @param  array<int, mixed>|null  $studiesArr
     * @param  array<int, mixed>|null  $otherStudiesArr
     * @param  array<int, mixed>|null  $consultSpecialistArr
     * @param  array<int, mixed>|null  $otherSpecialistArr
     * @return array<string, mixed>
     */
    public static function buildFromContext(
        array $context,
        TelemedicineDoctor $doctor,
        array $clinicalData = [],
        ?array $medicationsArr = null,
        ?array $labsArr = null,
        ?array $otherLabsArr = null,
        ?array $studiesArr = null,
        ?array $otherStudiesArr = null,
        ?array $consultSpecialistArr = null,
        ?array $otherSpecialistArr = null,
    ): array {
        $passed = [
            'medicationsArr' => $medicationsArr,
            'labsArr' => $labsArr,
            'otherLabsArr' => $otherLabsArr,
            'studiesArr' => $studiesArr,
            'otherStudiesArr' => $otherStudiesArr,
            'consultSpecialistArr' => $consultSpecialistArr,
            'otherSpecialistArr' => $otherSpecialistArr,
        ];
        $saved = in_array(null, $passed, true) ? self::savedClinicalOrders($context) : [];
        $orders = [];

        foreach ($passed as $key => $list) {
            $orders[$key] = $list ?? $saved[$key] ?? [];
        }

        return [
            'fecha' => now()->format('d/m/Y'),
            'code_reference' => (string) ($clinicalData['code_reference'] ?? $context['code_reference'] ?? ''),
            'name_patient' => TelemedicinePatientDisplayName::fromContext($context, $clinicalData),
            'ci_patient' => (string) ($clinicalData['nro_identificacion'] ?? $context['nro_identificacion'] ?? ''),
            'age_patient' => $clinicalData['age'] ?? $context['age'] ?? null,
            'reason' => (string) ($clinicalData['reason_consultation'] ?? $context['reason_consultation'] ?? ''),
            'actual_phatology' => (string) ($clinicalData['actual_phatology'] ?? $context['actual_phatology'] ?? ''),
            'background' => (string) ($clinicalData['background'] ?? $context['background'] ?? ''),
            'diagnostic_impression' => (string) ($clinicalData['diagnostic_impression'] ?? $context['diagnostic_impression'] ?? ''),
            'observations' => (string) ($context['observations'] ?? ''),
            'peso' => $clinicalData['peso'] ?? $context['peso'] ?? null,
            'estatura' => $clinicalData['estatura'] ?? $context['estatura'] ?? null,
            'imc' => $clinicalData['imc'] ?? $context['imc'] ?? null,
            'phone' => (string) ($clinicalData['phone_ppal'] ?? $context['phone_ppal'] ?? $context['patient_phone'] ?? ''),
            'consultSpecialistArr' => $orders['consultSpecialistArr'],
            'medicationsArr' => $orders['medicationsArr'],
            'labsArr' => $orders['labsArr'],
            'otherLabsArr' => $orders['otherLabsArr'],
            'studiesArr' => $orders['studiesArr'],
            'otherStudiesArr' => $orders['otherStudiesArr'],
            'otherSpecialistArr' => $orders['otherSpecialistArr'],
            'doctor_name' => $doctor->full_name,
            'code_cm' => $doctor->code_cm,
            'code_mpps' => $doctor->code_mpps,
            'signature' => $doctor->signature,
            'telemedicine_case_id' => (int) ($context['telemedicine_case_id'] ?? 0),
            'telemedicine_consultation_id' => (int) ($context['telemedicine_consultation_id'] ?? 0),
            'telemedicine_patient_id' => (int) ($context['telemedicine_patient_id'] ?? 0),
            'pa' => $clinicalData['pa'] ?? $context['pa'] ?? null,
            'fc' => $clinicalData['fc'] ?? $context['fc'] ?? null,
            'fr' => $clinicalData['fr'] ?? $context['fr'] ?? null,
            'temp' => $clinicalData['temp'] ?? $context['temp'] ?? null,
            'saturacion' => $clinicalData['saturacion'] ?? $context['saturacion'] ?? null,
            'physical_exam' => self::physicalExam($context),
        ];
    }

    /**
     * Indicaciones guardadas de la consulta del contexto. Vacío si el informe
     * se arma antes de guardar la consulta (Informe AMD pendiente).
     *
     * @param  array<string, mixed>  $context
     * @return array<string, list<mixed>>
     */
    private static function savedClinicalOrders(array $context): array
    {
        $consultationId = (int) ($context['telemedicine_consultation_id'] ?? 0);

        if ($consultationId < 1) {
            return [];
        }

        $consultation = TelemedicineConsultationPatient::query()->find($consultationId);

        return $consultation instanceof TelemedicineConsultationPatient
            ? TelemedicineInformeClinicalOrders::payloadFromConsultation($consultation)
            : [];
    }

    /**
     * Examen físico AMD de la consulta (o el pendiente del caso cuando el
     * informe se genera antes de guardarla). Null si no hay: la sección no sale.
     *
     * @param  array<string, mixed>  $context
     * @return array{vitals: list<array{label: string, value: string}>, systems: list<array{label: string, text: string, is_default: bool}>}|null
     */
    private static function physicalExam(array $context): ?array
    {
        $exam = TelemedicineAmdPhysicalExamRegistrar::forInform(
            (int) ($context['telemedicine_case_id'] ?? 0),
            (int) ($context['telemedicine_consultation_id'] ?? 0),
        );

        return $exam === null ? null : TelemedicineAmdPhysicalExamTemplate::display($exam);
    }

    /**
     * Sufijo visible del archivo del informe médico. Es distinto de la clave
     * interna `DOCUMENT_INFORME_MEDICO` (`informe-medico`), que la regeneración
     * de documentos usa como identificador y no debe cambiar.
     */
    public const FILE_SUFFIX_INFORME_MEDICO = 'Informe-Médico';

    /**
     * `{cédula}-{referencia}-Informe-Médico.pdf`. Cualquier otro tipo conserva
     * su clave como sufijo, igual que antes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function pdfDocumentName(
        array $data,
        string $typeDocument = TelemedicineCaseDocumentRegenerationService::DOCUMENT_INFORME_MEDICO,
    ): string {
        $suffix = $typeDocument === TelemedicineCaseDocumentRegenerationService::DOCUMENT_INFORME_MEDICO
            ? self::FILE_SUFFIX_INFORME_MEDICO
            : $typeDocument;

        return $data['ci_patient'].'-'.$data['code_reference'].'-'.$suffix.'.pdf';
    }
}
