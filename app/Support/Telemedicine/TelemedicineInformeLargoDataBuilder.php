<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\TelemedicineDoctor;

final class TelemedicineInformeLargoDataBuilder
{
    /**
     * @param  array<string, mixed>  $clinicalData
     * @param  array<int, mixed>  $medicationsArr
     * @param  array<int, mixed>  $labsArr
     * @param  array<int, mixed>  $otherLabsArr
     * @param  array<int, mixed>  $studiesArr
     * @param  array<int, mixed>  $otherStudiesArr
     * @param  array<int, mixed>  $consultSpecialistArr
     * @param  array<int, mixed>  $otherSpecialistArr
     * @return array<string, mixed>
     */
    public static function buildFromContext(
        array $context,
        TelemedicineDoctor $doctor,
        array $clinicalData = [],
        array $medicationsArr = [],
        array $labsArr = [],
        array $otherLabsArr = [],
        array $studiesArr = [],
        array $otherStudiesArr = [],
        array $consultSpecialistArr = [],
        array $otherSpecialistArr = [],
    ): array {
        return [
            'fecha' => now()->format('d/m/Y'),
            'code_reference' => (string) ($clinicalData['code_reference'] ?? $context['code_reference'] ?? ''),
            'name_patient' => (string) ($clinicalData['full_name'] ?? $context['full_name'] ?? ''),
            'ci_patient' => (string) ($clinicalData['nro_identificacion'] ?? $context['nro_identificacion'] ?? ''),
            'age_patient' => $clinicalData['age'] ?? $context['age'] ?? null,
            'reason' => (string) ($clinicalData['reason_consultation'] ?? $context['reason_consultation'] ?? ''),
            'actual_phatology' => (string) ($clinicalData['actual_phatology'] ?? $context['actual_phatology'] ?? ''),
            'background' => (string) ($clinicalData['background'] ?? $context['background'] ?? ''),
            'diagnostic_impression' => (string) ($clinicalData['diagnostic_impression'] ?? $context['diagnostic_impression'] ?? ''),
            'peso' => $clinicalData['peso'] ?? $context['peso'] ?? null,
            'estatura' => $clinicalData['estatura'] ?? $context['estatura'] ?? null,
            'imc' => $clinicalData['imc'] ?? $context['imc'] ?? null,
            'phone' => (string) ($clinicalData['phone_ppal'] ?? $context['phone_ppal'] ?? $context['patient_phone'] ?? ''),
            'consultSpecialistArr' => $consultSpecialistArr,
            'medicationsArr' => $medicationsArr,
            'labsArr' => $labsArr,
            'otherLabsArr' => $otherLabsArr,
            'studiesArr' => $studiesArr,
            'otherStudiesArr' => $otherStudiesArr,
            'otherSpecialistArr' => $otherSpecialistArr,
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
        ];
    }

    public static function pdfDocumentName(array $data, string $typeDocument = 'informe-largo'): string
    {
        return $data['ci_patient'].'-'.$data['code_reference'].'-'.$typeDocument.'.pdf';
    }
}
