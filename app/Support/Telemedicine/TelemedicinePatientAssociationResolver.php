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

        $existing = TelemedicinePatient::query()
            ->where('nro_identificacion', $document)
            ->first();

        if ($existing === null) {
            $patient = TelemedicinePatient::query()->create($attributes);

            return [
                'patient' => $patient,
                'was_recently_created' => true,
            ];
        }

        $createdBy = $attributes['created_by'] ?? null;
        unset($attributes['created_by']);

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
}
