<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\Affiliate;
use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class TelemedicineCaseIdentity
{
    public static function normalizeName(?string $name): string
    {
        return mb_strtolower(trim((string) $name));
    }

    public static function namesMatch(?string $left, ?string $right): bool
    {
        return self::normalizeName($left) === self::normalizeName($right);
    }

    /**
     * Snapshot de identidad del paciente para un caso (siempre desde el modelo paciente).
     *
     * @return array{
     *     telemedicine_patient_id: int,
     *     patient_name: string,
     *     patient_age: mixed,
     *     patient_sex: mixed,
     *     patient_phone: mixed,
     *     patient_address: mixed,
     *     patient_country_id: mixed,
     *     patient_state_id: mixed,
     *     patient_city_id: mixed
     * }
     */
    public static function snapshotFromPatient(TelemedicinePatient $patient): array
    {
        $patient->refresh();

        return [
            'telemedicine_patient_id' => (int) $patient->id,
            'patient_name' => TelemedicinePatientDisplayName::fromPatient($patient),
            'patient_age' => $patient->age,
            'patient_sex' => $patient->sex,
            'patient_phone' => $patient->phone,
            'patient_address' => $patient->address,
            'patient_country_id' => $patient->country_id,
            'patient_state_id' => $patient->state_id,
            'patient_city_id' => $patient->city_id,
        ];
    }

    /**
     * Fuerza los campos de identidad del caso a coincidir con el paciente vinculado.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function enforceOnAttributes(array $attributes, ?TelemedicinePatient $patient = null): array
    {
        $patientId = $attributes['telemedicine_patient_id'] ?? $patient?->id;

        if (! filled($patientId)) {
            return $attributes;
        }

        $patient ??= TelemedicinePatient::query()->find($patientId);

        if ($patient === null) {
            return $attributes;
        }

        $snapshot = self::snapshotFromPatient($patient);
        $incomingName = $attributes['patient_name'] ?? null;

        if (filled($incomingName) && ! self::namesMatch((string) $incomingName, $snapshot['patient_name'])) {
            Log::warning('TelemedicineCaseIdentity: patient_name divergente corregido al full_name del paciente', [
                'telemedicine_patient_id' => $patient->id,
                'incoming_patient_name' => $incomingName,
                'canonical_patient_name' => $snapshot['patient_name'],
            ]);
        }

        $attributes['telemedicine_patient_id'] = $snapshot['telemedicine_patient_id'];
        $attributes['patient_name'] = $snapshot['patient_name'];
        $attributes['patient_age'] = $snapshot['patient_age'];
        $attributes['patient_sex'] = $snapshot['patient_sex'];

        return $attributes;
    }

    public static function syncCaseSnapshotsForPatient(TelemedicinePatient $patient): int
    {
        $snapshot = self::snapshotFromPatient($patient);

        return TelemedicineCase::query()
            ->where('telemedicine_patient_id', $patient->id)
            ->update([
                'patient_name' => $snapshot['patient_name'],
                'patient_age' => $snapshot['patient_age'],
                'patient_sex' => $snapshot['patient_sex'],
                'updated_at' => now(),
            ]);
    }

    /**
     * Identidad canónica para coordinación a partir del paciente FK.
     *
     * @param  array<string, mixed>  $consultationRecord
     * @param  array<string, mixed>|TelemedicinePatient  $patient
     * @return array{patient: string, ci_patient: string, birth_date_patient: mixed, age_patient: mixed, relationship_patient: string}
     */
    public static function coordinationIdentity(array $consultationRecord, array|TelemedicinePatient $patient): array
    {
        $patientData = $patient instanceof TelemedicinePatient
            ? $patient->toArray()
            : $patient;

        $canonicalName = $patient instanceof TelemedicinePatient
            ? TelemedicinePatientDisplayName::fromPatient($patient)
            : TelemedicinePatientDisplayName::fromPatientArray($patientData);
        $consultationName = trim((string) ($consultationRecord['full_name'] ?? ''));

        if ($consultationName !== '' && $canonicalName !== '' && ! self::namesMatch($consultationName, $canonicalName)) {
            Log::warning('TelemedicineCaseIdentity: nombre de consulta distinto al paciente FK; se usa el paciente', [
                'telemedicine_patient_id' => $patientData['id'] ?? null,
                'consultation_name' => $consultationName,
                'patient_name' => $canonicalName,
                'telemedicine_case_id' => $consultationRecord['telemedicine_case_id'] ?? null,
            ]);
        }

        $ciPatient = trim((string) ($patientData['nro_identificacion'] ?? ''));

        return [
            'patient' => $canonicalName !== '' ? $canonicalName : ($consultationName !== '' ? $consultationName : '—'),
            'ci_patient' => $ciPatient !== '' ? $ciPatient : 'NO ESPECIFICADO',
            'birth_date_patient' => $patientData['birth_date'] ?? null,
            'age_patient' => $patientData['age'] ?? null,
            'relationship_patient' => self::resolveRelationshipPatient($consultationRecord, $patientData),
        ];
    }

    /**
     * @param  array<string, mixed>  $consultationRecord
     * @param  array<string, mixed>  $patientData
     */
    private static function resolveRelationshipPatient(array $consultationRecord, array $patientData): string
    {
        if (filled($consultationRecord['relationship_patient'] ?? null)) {
            return trim((string) $consultationRecord['relationship_patient']);
        }

        if (filled($patientData['relationship'] ?? null)) {
            return trim((string) $patientData['relationship']);
        }

        $affiliationId = $patientData['afilliation_id'] ?? null;
        $ci = trim((string) ($patientData['nro_identificacion'] ?? ''));

        if (filled($affiliationId) && $ci !== '' && Schema::hasTable('affiliates')) {
            try {
                $relationship = Affiliate::query()
                    ->where('affiliation_id', $affiliationId)
                    ->where('nro_identificacion', $ci)
                    ->value('relationship');

                if (filled($relationship)) {
                    return trim((string) $relationship);
                }
            } catch (Throwable) {
                // Si la lookup falla, se usa el default seguro para no bloquear la coordinación.
            }
        }

        return 'TITULAR';
    }
}
