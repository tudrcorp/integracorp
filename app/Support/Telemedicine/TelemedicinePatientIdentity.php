<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\TelemedicinePatient;
use Illuminate\Validation\ValidationException;

final class TelemedicinePatientIdentity
{
    public static function normalizeDocument(?string $document): string
    {
        $value = mb_strtoupper(trim((string) $document));

        return str_replace([' ', '.', '_'], '', $value);
    }

    public static function documentsMatch(?string $left, ?string $right): bool
    {
        $normalizedLeft = self::normalizeDocument($left);
        $normalizedRight = self::normalizeDocument($right);

        if ($normalizedLeft === '' || $normalizedRight === '') {
            return false;
        }

        return $normalizedLeft === $normalizedRight;
    }

    /**
     * Fuerza los campos de identidad de la consulta a coincidir con el paciente FK.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function enforceConsultationIdentity(array $data, TelemedicinePatient $patient): array
    {
        $normalizedDocument = self::normalizeDocument($patient->nro_identificacion);

        $data['telemedicine_patient_id'] = (int) $patient->id;
        $data['full_name'] = trim((string) $patient->full_name);
        $data['nro_identificacion'] = $normalizedDocument !== ''
            ? $normalizedDocument
            : $patient->nro_identificacion;
        $data['sex'] = $patient->sex;
        $data['age'] = $patient->age;

        return $data;
    }

    public static function assertDocumentIsAvailable(?string $document, ?int $ignorePatientId = null): void
    {
        $normalized = self::normalizeDocument($document);

        if ($normalized === '') {
            return;
        }

        $query = TelemedicinePatient::query()->where('nro_identificacion', $normalized);

        if ($ignorePatientId !== null) {
            $query->whereKeyNot($ignorePatientId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'nro_identificacion' => ["Ya existe un paciente de telemedicina con la cédula {$normalized}."],
            ]);
        }
    }
}
