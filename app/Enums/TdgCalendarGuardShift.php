<?php

declare(strict_types=1);

namespace App\Enums;

enum TdgCalendarGuardShift: string
{
    case Proveedores = 'proveedores';
    case IlsCapitado = 'ils_capitado';
    case Nocturna = 'nocturna';

    public function label(): string
    {
        return match ($this) {
            self::Proveedores => '2.1 8AM-5PM PROVEEDORES - 24H@TUDRENCASA.COM',
            self::IlsCapitado => '2.2 8AM-5PM ILS/CAPITADO - 24H@TUDRENCASA.COM',
            self::Nocturna => '3.0 GUARDIA NOCTURNA - 24H@TUDRENCASA.COM',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Proveedores => 'Guardia 2.1 Proveedores',
            self::IlsCapitado => 'Guardia 2.2 ILS/Capitado',
            self::Nocturna => 'Guardia 3.0 Nocturna',
        };
    }

    public function isDaytimeOperationsShift(): bool
    {
        return $this === self::Proveedores || $this === self::IlsCapitado;
    }

    public function isNocturnalShift(): bool
    {
        return $this === self::Nocturna;
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
