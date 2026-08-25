<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\ObservationCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Actualiza el diagnóstico principal de la consulta inicial y deja el cambio en la bitácora del caso.
 */
final class TelemedicineInitialDiagnosisUpdater
{
    public const FORM_FIELD = 'initial_diagnostic_impression';

    public const INITIAL_STATUS = 'CONSULTA INICIAL';

    public const OBSERVATION_PREFIX = 'Actualización del diagnóstico principal (consulta inicial).';

    public static function normalize(?string $value): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim((string) $value)) ?? '';

        if ($collapsed === '') {
            return '';
        }

        return mb_strtoupper($collapsed, 'UTF-8');
    }

    public static function diagnosesAreEqual(?string $previous, ?string $next): bool
    {
        return self::normalize($previous) === self::normalize($next);
    }

    public static function findInitialConsultation(int $caseId): ?TelemedicineConsultationPatient
    {
        if ($caseId < 1) {
            return null;
        }

        return TelemedicineConsultationPatient::query()
            ->where('telemedicine_case_id', $caseId)
            ->where('status', self::INITIAL_STATUS)
            ->orderBy('id')
            ->first();
    }

    public static function currentDiagnosis(int $caseId): string
    {
        $initial = self::findInitialConsultation($caseId);

        if ($initial === null) {
            return '';
        }

        return self::normalize($initial->diagnostic_impression);
    }

    /**
     * @return array<string, string>
     */
    public static function formStateForCase(int $caseId): array
    {
        return [
            self::FORM_FIELD => self::currentDiagnosis($caseId),
        ];
    }

    public static function buildBitacoraDescription(
        ?string $previous,
        string $next,
        ?string $originReference = null,
    ): string {
        $previousNormalized = self::normalize($previous);
        $nextNormalized = self::normalize($next);
        $origin = trim((string) $originReference);

        $description = self::OBSERVATION_PREFIX
            ."\n".'Diagnóstico anterior: '.($previousNormalized !== '' ? $previousNormalized : '(sin registro)')
            ."\n".'Diagnóstico nuevo: '.($nextNormalized !== '' ? $nextNormalized : '(sin registro)');

        if ($origin !== '') {
            $description .= "\n".'Origen: seguimiento '.$origin;
        }

        return $description;
    }

    /**
     * Extrae el campo del wizard, lo copia a la consulta de seguimiento y evita persistirlo como columna inexistente.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function mergeIntoConsultationFormData(array $data): array
    {
        $incoming = self::normalize(isset($data[self::FORM_FIELD]) ? (string) $data[self::FORM_FIELD] : null);
        unset($data[self::FORM_FIELD]);

        if ($incoming !== '') {
            $data['diagnostic_impression'] = $incoming;
        }

        return $data;
    }

    /**
     * @return array{updated: bool, initial: ?TelemedicineConsultationPatient, observation: ?ObservationCase}
     */
    public static function syncFromFollowUp(
        int $caseId,
        ?string $newDiagnosis,
        ?User $user = null,
        ?string $originReference = null,
    ): array {
        $initial = self::findInitialConsultation($caseId);
        $normalized = self::normalize($newDiagnosis);

        if ($initial === null || $caseId < 1 || $normalized === '') {
            return [
                'updated' => false,
                'initial' => $initial,
                'observation' => null,
            ];
        }

        $previous = self::normalize($initial->diagnostic_impression);

        if ($previous === $normalized) {
            return [
                'updated' => false,
                'initial' => $initial,
                'observation' => null,
            ];
        }

        $user ??= Auth::user();
        $bitacoraDescription = self::buildBitacoraDescription($previous, $normalized, $originReference);
        $userId = $user?->id;
        $observation = null;

        DB::transaction(function () use ($initial, $normalized, $caseId, $bitacoraDescription, $userId, &$observation): void {
            $initial->diagnostic_impression = $normalized;
            $initial->save();

            $observation = ObservationCase::query()->create([
                'telemedicine_case_id' => $caseId,
                'description' => $bitacoraDescription,
                'created_by' => $userId !== null ? (string) $userId : null,
            ]);
        });

        return [
            'updated' => true,
            'initial' => $initial->fresh() ?? $initial,
            'observation' => $observation,
        ];
    }
}
