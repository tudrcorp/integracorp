<?php

declare(strict_types=1);

namespace App\Enums;

enum PlanGeneratorPopulationUnit: string
{
    case Poblacion = 'poblacion';
    case Meses = 'meses';
    case Dias = 'dias';
    case Horas = 'horas';

    public function label(): string
    {
        return match ($this) {
            self::Poblacion => 'Población',
            self::Meses => 'Meses',
            self::Dias => 'Días',
            self::Horas => 'Horas',
        };
    }

    public function quantityLabel(): string
    {
        return match ($this) {
            self::Poblacion => 'persona(s)',
            self::Meses => 'mes(es)',
            self::Dias => 'día(s)',
            self::Horas => 'hora(s)',
        };
    }

    public static function resolve(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return self::tryFrom((string) $value) ?? self::Poblacion;
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
}
