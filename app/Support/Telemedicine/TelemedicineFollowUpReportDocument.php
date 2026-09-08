<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Jobs\GeneratePdfInformeSeguimiento;
use App\Models\OperationDocumentList;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineDoctor;
use App\Models\TelemedicinePatient;
use App\Models\User;

/**
 * Informe PDF de un seguimiento: diagnóstico principal de la consulta inicial,
 * historia de la enfermedad actual y evolución del paciente.
 */
final class TelemedicineFollowUpReportDocument
{
    public const TYPE_DOCUMENT = 'informe-seguimiento';

    public const DOCUMENT_TYPE_NAME = 'INFORME DE SEGUIMIENTO';

    public static function appliesTo(?string $status): bool
    {
        $normalized = trim((string) $status);

        return $normalized !== '' && $normalized !== TelemedicineInitialDiagnosisUpdater::INITIAL_STATUS;
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $formData
     * @param  array<string, mixed>  $doctor
     * @return array<string, mixed>
     */
    public static function payloadFromCreateData(
        array $record,
        array $formData,
        array $doctor,
        string $patientDisplayName,
    ): array {
        $diagnosis = trim((string) ($formData['diagnostic_impression']
            ?? $formData[TelemedicineInitialDiagnosisUpdater::FORM_FIELD]
            ?? ''));

        if ($diagnosis === '') {
            $diagnosis = TelemedicineInitialDiagnosisUpdater::currentDiagnosis((int) ($record['telemedicine_case_id'] ?? 0));
        }

        return self::payload([
            'code_reference' => $formData['code_reference'] ?? $record['code_reference'] ?? null,
            'name_patient' => $patientDisplayName,
            'ci_patient' => $formData['nro_identificacion'] ?? $record['nro_identificacion'] ?? null,
            'age_patient' => $formData['age'] ?? $record['age'] ?? null,
            'diagnostic_impression' => $diagnosis,
            'current_illness_history' => $formData['current_illness_history'] ?? $record['current_illness_history'] ?? null,
            'patient_evolution' => $formData['patient_evolution'] ?? $record['patient_evolution'] ?? null,
            'phone' => $formData['phone_ppal'] ?? null,
            'doctor_name' => $doctor['full_name'] ?? null,
            'code_cm' => $doctor['code_cm'] ?? null,
            'code_mpps' => $doctor['code_mpps'] ?? null,
            'signature' => $doctor['signature'] ?? null,
            'telemedicine_case_id' => $record['telemedicine_case_id'] ?? null,
            'telemedicine_consultation_id' => $record['id'] ?? null,
            'telemedicine_patient_id' => $record['telemedicine_patient_id'] ?? null,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function payloadFromConsultation(
        TelemedicineConsultationPatient $consultation,
        TelemedicineDoctor $doctor,
        TelemedicinePatient $patient,
        ?string $patientDisplayName = null,
    ): ?array {
        $diagnosis = trim((string) ($consultation->diagnostic_impression ?? ''));

        if ($diagnosis === '') {
            $diagnosis = TelemedicineInitialDiagnosisUpdater::currentDiagnosis((int) $consultation->telemedicine_case_id);
        }

        $name = $patientDisplayName;
        if ($name === null || trim($name) === '') {
            $name = TelemedicinePatientDisplayName::fromPatientOrFallback(
                $patient,
                $consultation->full_name ?? $patient->full_name,
            );
        }

        return self::payload([
            'code_reference' => $consultation->code_reference,
            'name_patient' => $name,
            'ci_patient' => $consultation->nro_identificacion ?? $patient->nro_identificacion,
            'age_patient' => $consultation->age ?? $patient->age,
            'diagnostic_impression' => $diagnosis,
            'current_illness_history' => $consultation->current_illness_history,
            'patient_evolution' => $consultation->patient_evolution,
            'phone' => $patient->phone ?? $consultation->phone_ppal ?? null,
            'doctor_name' => $doctor->full_name,
            'code_cm' => $doctor->code_cm,
            'code_mpps' => $doctor->code_mpps,
            'signature' => $doctor->signature,
            'telemedicine_case_id' => $consultation->telemedicine_case_id,
            'telemedicine_consultation_id' => $consultation->id,
            'telemedicine_patient_id' => $consultation->telemedicine_patient_id ?? $patient->id,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function payloadFromSavedConsultation(
        TelemedicineConsultationPatient $consultation,
        ?User $user = null,
    ): ?array {
        $doctor = TelemedicineDoctor::query()->find($consultation->telemedicine_doctor_id)
            ?? TelemedicineConsultationSigningDoctor::forUser($user);

        $patient = TelemedicinePatient::query()->find($consultation->telemedicine_patient_id);

        if (! $doctor instanceof TelemedicineDoctor || ! $patient instanceof TelemedicinePatient) {
            return null;
        }

        return self::payloadFromConsultation($consultation, $doctor, $patient);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function payload(array $input): array
    {
        return [
            'fecha' => now()->format('d/m/Y'),
            'code_reference' => $input['code_reference'] ?? null,
            'name_patient' => $input['name_patient'] ?? null,
            'ci_patient' => $input['ci_patient'] ?? null,
            'age_patient' => $input['age_patient'] ?? null,
            'diagnostic_impression' => $input['diagnostic_impression'] ?? null,
            'current_illness_history' => $input['current_illness_history'] ?? null,
            'patient_evolution' => $input['patient_evolution'] ?? null,
            'phone' => $input['phone'] ?? null,
            'doctor_name' => $input['doctor_name'] ?? null,
            'code_cm' => $input['code_cm'] ?? null,
            'code_mpps' => $input['code_mpps'] ?? null,
            'signature' => $input['signature'] ?? null,
            'telemedicine_case_id' => $input['telemedicine_case_id'] ?? null,
            'telemedicine_consultation_id' => $input['telemedicine_consultation_id'] ?? null,
            'telemedicine_patient_id' => $input['telemedicine_patient_id'] ?? null,
        ];
    }

    public static function makeJob(array $payload, mixed $user): GeneratePdfInformeSeguimiento
    {
        return new GeneratePdfInformeSeguimiento($payload, $user, self::TYPE_DOCUMENT);
    }

    public static function documentTypeId(): int
    {
        $id = (int) OperationDocumentList::query()
            ->where('name', self::DOCUMENT_TYPE_NAME)
            ->value('id');

        if ($id > 0) {
            return $id;
        }

        $created = OperationDocumentList::query()->create([
            'name' => self::DOCUMENT_TYPE_NAME,
            'created_by' => 'system',
            'updated_by' => 'system',
        ]);

        return (int) $created->id;
    }
}
