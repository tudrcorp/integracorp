<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Quién de la red comercial puede cotizar un upgrade. Negocios cotiza siempre todos los activos.
 */
enum UpgradeNetworkVisibility: string
{
    case BusinessOnly = 'SOLO_NEGOCIOS';
    case AllNetwork = 'TODA_LA_RED';
    case Selected = 'RED_SELECCIONADA';

    public function label(): string
    {
        return match ($this) {
            self::BusinessOnly => 'Solo Negocios',
            self::AllNetwork => 'Toda la red comercial',
            self::Selected => 'Red seleccionada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::BusinessOnly => 'gray',
            self::AllNetwork => 'success',
            self::Selected => 'info',
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
}
