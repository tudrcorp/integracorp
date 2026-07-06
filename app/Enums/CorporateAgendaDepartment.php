<?php

declare(strict_types=1);

namespace App\Enums;

enum CorporateAgendaDepartment: string
{
    case Comercial = 'comercial';
    case Afiliaciones = 'afiliaciones';
    case Proveedores = 'proveedores';
    case Operaciones = 'operaciones';
    case Marketing = 'marketing';
    case Proyecto = 'proyecto';
    case Administracion = 'administracion';

    public function label(): string
    {
        return match ($this) {
            self::Comercial => 'Comercial',
            self::Afiliaciones => 'Afiliaciones',
            self::Proveedores => 'Proveedores',
            self::Operaciones => 'Operaciones',
            self::Marketing => 'Marketing',
            self::Proyecto => 'Proyecto',
            self::Administracion => 'Administración',
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
