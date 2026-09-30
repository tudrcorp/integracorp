<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\TelemedicineConsultationPatient;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Arma el resumen que INTEGRACORP publica en el chat del caso al registrarse una consulta.
 * Solo incluye los apartados con datos; el texto se guarda plano y la vista lo escapa.
 */
final class ConsultationChatSummary
{
    public const STATUS_INITIAL = 'CONSULTA INICIAL';

    public const STATUS_FOLLOW_UP = 'EN SEGUIMIENTO';

    public const STATUS_DISCHARGE = 'ALTA MEDICA';

    public const AUTHOR_LABEL = 'INTEGRACORP';

    private const MAX_FIELD_LENGTH = 1500;

    private const MAX_BODY_LENGTH = 8000;

    /**
     * @return array<string, string>
     */
    public static function titles(): array
    {
        return [
            self::STATUS_INITIAL => 'Resumen de consulta inicial',
            self::STATUS_FOLLOW_UP => 'Resumen de seguimiento',
            self::STATUS_DISCHARGE => 'Resumen de alta médica',
        ];
    }

    public static function supportsStatus(?string $status): bool
    {
        return array_key_exists(self::normalizeStatus($status), self::titles());
    }

    public static function normalizeStatus(?string $status): string
    {
        $normalized = Str::upper(Str::squish((string) $status));

        return str_replace(['É', 'Á'], ['E', 'A'], $normalized);
    }

    /**
     * @return array{title: string, subtitle: string, status: string, sections: list<array{label: string, value: string|list<string>}>, body: string}
     */
    public static function build(TelemedicineConsultationPatient $consultation): array
    {
        $consultation->loadMissing([
            'telemedicineDoctor:id,full_name',
            'telemedicinePriority:id,name',
            'telemedicinePatientMedications',
            'telemedicinePatientLabs',
            'telemedicinePatientStudies',
            'telemedicinePatientSpecialists',
        ]);

        $status = self::normalizeStatus($consultation->status);
        $title = self::titles()[$status] ?? 'Resumen de consulta';

        $subtitle = collect([
            filled($consultation->telemedicineDoctor?->full_name) ? 'Dr(a). '.$consultation->telemedicineDoctor->full_name : null,
            $consultation->created_at?->timezone(config('app.timezone'))->format('d/m/Y h:i A'),
            filled($consultation->telemedicine_case_code) ? 'Caso '.$consultation->telemedicine_case_code : null,
        ])->filter()->implode(' · ');

        $sections = array_values(array_filter([
            self::text('Motivo de consulta', $consultation->reason_consultation),
            self::text('Enfermedad actual', $consultation->current_illness_history ?: $consultation->actual_phatology),
            self::text('Evolución del paciente', $consultation->patient_evolution),
            self::text('Antecedentes', $consultation->background),
            self::list('Signos vitales', self::vitalSigns($consultation)),
            self::text('Impresión diagnóstica', $consultation->diagnostic_impression),
            self::list('Medicamentos indicados', self::medications($consultation)),
            self::list('Laboratorios solicitados', self::merge(
                $consultation->telemedicinePatientLabs->pluck('laboratory'),
                $consultation->other_labs,
            )),
            self::list('Estudios de imagen solicitados', self::merge(
                $consultation->telemedicinePatientStudies->pluck('study'),
                $consultation->other_studies,
            )),
            self::list('Interconsultas', self::merge(
                $consultation->telemedicinePatientSpecialists->pluck('specialty'),
                $consultation->other_specialist,
            )),
            self::text('Prioridad de monitoreo', self::priority($consultation)),
            self::text('Observaciones', $consultation->observations),
        ]));

        return [
            'title' => $title,
            'subtitle' => $subtitle,
            'status' => $status,
            'sections' => $sections,
            'body' => self::plainText($title, $subtitle, $sections),
        ];
    }

    /**
     * @return array{label: string, value: string}|null
     */
    private static function text(string $label, mixed $value): ?array
    {
        $clean = self::clean($value);

        return $clean === '' ? null : ['label' => $label, 'value' => Str::limit($clean, self::MAX_FIELD_LENGTH)];
    }

    /**
     * @param  list<string>  $items
     * @return array{label: string, value: list<string>}|null
     */
    private static function list(string $label, array $items): ?array
    {
        return $items === [] ? null : ['label' => $label, 'value' => $items];
    }

    /**
     * @return list<string>
     */
    private static function vitalSigns(TelemedicineConsultationPatient $consultation): array
    {
        $signs = [
            'PA' => [$consultation->pa, 'mmHg'],
            'FC' => [$consultation->fc, 'lpm'],
            'FR' => [$consultation->fr, 'rpm'],
            'Temperatura' => [$consultation->temp, '°C'],
            'Saturación' => [$consultation->saturacion, '%'],
            'Peso' => [$consultation->peso, 'kg'],
            'Talla' => [$consultation->estatura, 'm'],
            'IMC' => [$consultation->imc, ''],
        ];

        $lines = [];

        foreach ($signs as $label => [$value, $unit]) {
            $clean = self::clean($value);

            if ($clean === '') {
                continue;
            }

            if (is_numeric($clean)) {
                $clean = rtrim(rtrim(number_format((float) $clean, 2, '.', ''), '0'), '.');
            }

            $lines[] = trim($label.': '.$clean.' '.$unit);
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private static function medications(TelemedicineConsultationPatient $consultation): array
    {
        return $consultation->telemedicinePatientMedications
            ->map(function ($medication): string {
                $name = self::clean($medication->medicine);

                if ($name === '') {
                    return '';
                }

                $details = collect([
                    self::clean($medication->indications),
                    self::duration($medication->duration),
                    filled($medication->quantity) ? 'Cantidad: '.self::clean($medication->quantity) : null,
                ])->filter()->implode(' · ');

                return Str::limit($details === '' ? $name : $name.' — '.$details, 300);
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * `duration` se guarda en días (entero); los valores históricos de texto se muestran tal cual.
     */
    private static function duration(mixed $value): ?string
    {
        $clean = self::clean($value);

        if ($clean === '' || $clean === '0') {
            return null;
        }

        if (ctype_digit($clean)) {
            return 'Duración: '.$clean.' '.((int) $clean === 1 ? 'día' : 'días');
        }

        return 'Duración: '.$clean;
    }

    /**
     * @param  Collection<int, mixed>  $fromRelation
     * @return list<string>
     */
    private static function merge(Collection $fromRelation, mixed $extra): array
    {
        $extraItems = is_array($extra) ? $extra : [];

        return collect([...$fromRelation->all(), ...$extraItems])
            ->flatten()
            ->map(fn (mixed $item): string => Str::limit(self::clean($item), 200))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private static function priority(TelemedicineConsultationPatient $consultation): string
    {
        return collect([
            self::clean($consultation->telemedicinePriority?->name),
            filled($consultation->priorityMonitoring) && is_numeric($consultation->priorityMonitoring)
                ? 'cada '.(int) $consultation->priorityMonitoring.' min'
                : self::clean($consultation->priorityMonitoring),
        ])->filter()->implode(' · ');
    }

    private static function clean(mixed $value): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }

        return trim(Str::squish(strip_tags((string) $value)));
    }

    /**
     * @param  list<array{label: string, value: string|list<string>}>  $sections
     */
    private static function plainText(string $title, string $subtitle, array $sections): string
    {
        $lines = [self::AUTHOR_LABEL.' · '.$title];

        if ($subtitle !== '') {
            $lines[] = $subtitle;
        }

        foreach ($sections as $section) {
            $lines[] = '';
            $lines[] = $section['label'].':';

            foreach ((array) $section['value'] as $item) {
                $lines[] = is_array($section['value']) ? '• '.$item : $item;
            }
        }

        if ($sections === []) {
            $lines[] = '';
            $lines[] = 'La consulta se registró sin datos clínicos adicionales.';
        }

        return Str::limit(implode("\n", $lines), self::MAX_BODY_LENGTH);
    }
}
