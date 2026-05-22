<?php

declare(strict_types=1);

namespace App\Enums;

enum TdgCalendarDepartment: string
{
    case Comercial = 'comercial';
    case Afiliaciones = 'afiliaciones';
    case Proveedores = 'proveedores';
    case Operaciones = 'operaciones';
    case Marketing = 'marketing';
    case Proyecto = 'proyecto';

    public function label(): string
    {
        return match ($this) {
            self::Comercial => 'COMERCIAL',
            self::Afiliaciones => 'AFILIACIONES',
            self::Proveedores => 'PROVEEDORES',
            self::Operaciones => 'OPERACIONES',
            self::Marketing => 'MARKETING',
            self::Proyecto => 'PROYECTO',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $department): array => [$department->value => $department->label()])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $department): string => $department->value, self::cases());
    }
}
