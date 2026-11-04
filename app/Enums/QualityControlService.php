<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * Servicios por los que se pregunta en las encuestas de Quality Control.
 * Una encuesta puede marcar varios.
 */
enum QualityControlService: string
{
    case Telemedicine = 'TELEMEDICINA';

    case HomeMedicalCare = 'ATENCION_MEDICA_DOMICILIARIA';

    case Laboratories = 'LABORATORIOS';

    case MedicationDelivery = 'ENTREGA_DE_MEDICAMENTOS';

    case ImagingStudies = 'ESTUDIOS_DE_IMAGEN';

    case SpecialistConsultation = 'CONSULTA_ESPECIALIZADA';

    public function label(): string
    {
        return match ($this) {
            self::Telemedicine => 'Telemedicina',
            self::HomeMedicalCare => 'Atención médica domiciliaria',
            self::Laboratories => 'Laboratorios',
            self::MedicationDelivery => 'Entrega de medicamentos',
            self::ImagingStudies => 'Estudios de imagen',
            self::SpecialistConsultation => 'Consulta especializada',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    public static function fromText(mixed $text): ?self
    {
        $key = Str::of(Str::ascii(trim((string) $text)))->upper()->replaceMatches('/[^A-Z]+/', '_')->trim('_')->toString();

        return $key === '' ? null : self::tryFrom($key);
    }

    /**
     * Separa «TELEMEDICINA, ENTREGA DE MEDICAMENTOS» en servicios conocidos.
     *
     * @return array{services: list<string>, unknown: list<string>}
     */
    public static function parseList(mixed $text): array
    {
        $services = [];
        $unknown = [];

        foreach (preg_split('/[,;\n]+/u', (string) $text) ?: [] as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $service = self::fromText($part);

            if ($service === null) {
                $unknown[] = $part;

                continue;
            }

            $services[$service->value] = $service->value;
        }

        return ['services' => array_values($services), 'unknown' => $unknown];
    }

    /**
     * @param  list<string>|null  $values
     */
    public static function labelsFor(?array $values): string
    {
        return collect($values ?? [])
            ->map(fn (string $value): string => self::tryFrom($value)?->label() ?? $value)
            ->implode(', ');
    }
}
