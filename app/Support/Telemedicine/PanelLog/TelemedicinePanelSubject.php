<?php

declare(strict_types=1);

namespace App\Support\Telemedicine\PanelLog;

use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicinePatient;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use ReflectionProperty;
use Throwable;

/**
 * Sobre qué caso, paciente, consulta y servicio estaba el médico cuando pasó
 * el evento. Solo identificadores: nunca el contenido del formulario.
 */
final class TelemedicinePanelSubject
{
    /**
     * @var array<int, string|null>
     */
    private static array $caseCodes = [];

    /**
     * @return array{telemedicine_case_id?: int, telemedicine_case_code?: string, telemedicine_patient_id?: int, telemedicine_consultation_id?: int, telemedicine_service_list_id?: int}
     */
    public static function fromComponent(Component $component): array
    {
        $subject = [];

        try {
            foreach ([self::read($component, 'record'), self::read($component, 'case'), self::read($component, 'patient')] as $model) {
                $subject += self::fromModel($model);
            }

            $data = self::read($component, 'data');
            $data = is_array($data) ? $data : [];

            $subject += self::ids([
                'telemedicine_case_id' => self::read($component, 'telemedicineCaseId') ?? $data['telemedicine_case_id'] ?? null,
                'telemedicine_patient_id' => self::read($component, 'telemedicinePatientId') ?? $data['telemedicine_patient_id'] ?? null,
                'telemedicine_consultation_id' => self::read($component, 'telemedicineConsultationId'),
                'telemedicine_service_list_id' => $data['telemedicine_service_list_id'] ?? null,
            ]);

            if (isset($subject['telemedicine_case_id']) && ! isset($subject['telemedicine_case_code'])) {
                $code = self::caseCode($subject['telemedicine_case_id']);

                if ($code !== null) {
                    $subject['telemedicine_case_code'] = $code;
                }
            }
        } catch (Throwable) {
            // Sin contexto el evento sigue siendo útil.
        }

        return $subject;
    }

    /**
     * @return array<string, int|string>
     */
    private static function fromModel(mixed $model): array
    {
        return match (true) {
            $model instanceof TelemedicineCase => self::ids([
                'telemedicine_case_id' => $model->getKey(),
                'telemedicine_patient_id' => $model->telemedicine_patient_id,
            ]) + (filled($model->code) ? ['telemedicine_case_code' => mb_substr((string) $model->code, 0, 40)] : []),
            $model instanceof TelemedicineConsultationPatient => self::ids([
                'telemedicine_consultation_id' => $model->getKey(),
                'telemedicine_case_id' => $model->telemedicine_case_id,
                'telemedicine_patient_id' => $model->telemedicine_patient_id,
                'telemedicine_service_list_id' => $model->telemedicine_service_list_id,
            ]),
            $model instanceof TelemedicinePatient => self::ids(['telemedicine_patient_id' => $model->getKey()]),
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, int>
     */
    private static function ids(array $values): array
    {
        $out = [];

        foreach ($values as $key => $value) {
            if (is_numeric($value) && (int) $value > 0) {
                $out[$key] = (int) $value;
            }
        }

        return $out;
    }

    /**
     * Lee propiedades públicas o protegidas sin disparar lógica del componente.
     */
    private static function read(Component $component, string $property): mixed
    {
        if (! property_exists($component, $property)) {
            return null;
        }

        try {
            $reflection = new ReflectionProperty($component, $property);

            if (! $reflection->isInitialized($component)) {
                return null;
            }

            $value = $reflection->getValue($component);
        } catch (Throwable) {
            return null;
        }

        return $value instanceof Model || is_array($value) || is_scalar($value) ? $value : null;
    }

    private static function caseCode(int $caseId): ?string
    {
        if (array_key_exists($caseId, self::$caseCodes)) {
            return self::$caseCodes[$caseId];
        }

        if (count(self::$caseCodes) > 200) {
            self::$caseCodes = [];
        }

        try {
            $code = TelemedicineCase::query()->withoutGlobalScopes()->whereKey($caseId)->value('code');
        } catch (Throwable) {
            $code = null;
        }

        return self::$caseCodes[$caseId] = filled($code) ? mb_substr((string) $code, 0, 40) : null;
    }
}
