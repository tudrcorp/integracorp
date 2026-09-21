<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\ResolvesFromMixedState;

/**
 * Puerta de consumo clínico. Un beneficio del plan se liga a una sola.
 *
 * TYPE_1 es el select de telemedicina. El resto son las tildes/selects de
 * la consulta (medicamentos, laboratorio, imagen, especialista).
 */
enum ClinicalServiceChannel: string
{
    use ResolvesFromMixedState;

    case Type1 = 'TYPE_1';
    case Medication = 'MEDICATION';
    case Laboratory = 'LABORATORY';
    case Imaging = 'IMAGING';
    case Specialist = 'SPECIALIST';

    public function label(): string
    {
        return match ($this) {
            self::Type1 => 'Servicios Macro',
            self::Medication => 'Medicamentos (tilde de la consulta)',
            self::Laboratory => 'Laboratorio (tilde de la consulta)',
            self::Imaging => 'Imagenología (tilde de la consulta)',
            self::Specialist => 'Especialista (tilde de la consulta)',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Type1 => 'Macro',
            self::Medication => 'Medicamentos',
            self::Laboratory => 'Laboratorio',
            self::Imaging => 'Imagenología',
            self::Specialist => 'Especialista',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Type1 => 'El médico lo elige en «Tipo de servicio» (telemedicina, AMD, seguimiento, ambulancia…).',
            self::Medication => 'El médico tilda «Asignación de medicamentos» y carga las entregas.',
            self::Laboratory => 'El médico tilda laboratorios y elige exámenes. Cuenta el caso, no cada examen.',
            self::Imaging => 'El médico tilda estudios de imagen. Cuenta el caso, no cada estudio.',
            self::Specialist => 'El médico tilda interconsulta con especialista.',
        };
    }

    public function usesTelemedicineServiceList(): bool
    {
        return $this === self::Type1;
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::Type1 => 'primary',
            self::Medication => 'success',
            self::Laboratory => 'info',
            self::Imaging => 'warning',
            self::Specialist => 'danger',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $out = [];

        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
