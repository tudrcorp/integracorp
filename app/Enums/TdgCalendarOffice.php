<?php

declare(strict_types=1);

namespace App\Enums;

enum TdgCalendarOffice: string
{
    case CentralLido = 'central_lido';
    case FarmadocLasDelicias = 'farmadoc_las_delicias';
    case FarmadocSanBernardino = 'farmadoc_san_bernardino';

    public function label(): string
    {
        return match ($this) {
            self::CentralLido => 'Central Lido',
            self::FarmadocLasDelicias => 'Farmadoc (Las Delicias)',
            self::FarmadocSanBernardino => 'Farmadoc (San Bernardino)',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $office): array => [$office->value => $office->label()])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $office): string => $office->value, self::cases());
    }
}
