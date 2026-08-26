<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\TelemedicinePatient;
use Illuminate\Validation\ValidationException;

final class TelemedicinePatientAssociationResolver
{
    /**
     * Alta/actualización de paciente por cédula (nunca por email compartido familiar).
     *
     * @param  array<string, mixed>  $attributes
     * @return array{patient: TelemedicinePatient, was_recently_created: bool}
     */
    public static function upsertByDocument(array $attributes): array
    {
        $document = TelemedicinePatientIdentity::normalizeDocument($attributes['nro_identificacion'] ?? null);

        if ($document === '') {
            throw ValidationException::withMessages([
                'nro_identificacion' => ['La cédula es obligatoria para asociar un afiliado como paciente de telemedicina.'],
            ]);
        }

        $attributes['nro_identificacion'] = $document;
        $attributes = self::normalizeSexAttribute($attributes);

        $existing = TelemedicinePatient::query()
            ->where('nro_identificacion', $document)
            ->first();

        if ($existing === null) {
            if (blank($attributes['sex'] ?? null)) {
                throw ValidationException::withMessages([
                    'sex' => ['El sexo es obligatorio para registrar el paciente de telemedicina. Indíquelo e intente de nuevo.'],
                ]);
            }

            $patient = TelemedicinePatient::query()->create($attributes);

            return [
                'patient' => $patient,
                'was_recently_created' => true,
            ];
        }

        $createdBy = $attributes['created_by'] ?? null;
        unset($attributes['created_by']);

        if (blank($attributes['sex'] ?? null)) {
            unset($attributes['sex']);
        }

        $existing->fill($attributes);

        if ($existing->isDirty()) {
            $existing->save();
        }

        if (filled($createdBy) && blank($existing->created_by)) {
            $existing->forceFill(['created_by' => $createdBy])->save();
        }

        return [
            'patient' => $existing->refresh(),
            'was_recently_created' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private static function normalizeSexAttribute(array $attributes): array
    {
        $attributes['sex'] = TelemedicinePatientIdentity::normalizeSex($attributes['sex'] ?? null);

        return $attributes;
    }
}
