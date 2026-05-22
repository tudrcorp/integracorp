<?php

declare(strict_types=1);

namespace App\Enums;

enum TdgCalendarGuardShift: string
{
    case Proveedores = 'proveedores';
    case IlsCapitado = 'ils_capitado';

    public function label(): string
    {
        return match ($this) {
            self::Proveedores => '2.1 8AM-5PM PROVEEDORES - 24H@TUDRENCASA.COM',
            self::IlsCapitado => '2.2 8AM-5PM ILS/CAPITADO - 24H@TUDRENCASA.COM',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Proveedores => 'Guardia 2.1 Proveedores',
            self::IlsCapitado => 'Guardia 2.2 ILS/Capitado',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $shift): array => [$shift->value => $shift->label()])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $shift): string => $shift->value, self::cases());
    }
}
