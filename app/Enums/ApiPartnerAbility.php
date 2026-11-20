<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lo que un aliado puede consultar por API. Cada endpoint exige su permiso, así un
 * aliado nuevo solo ve los datos que se le habilitan, aunque tenga un token válido.
 */
enum ApiPartnerAbility: string
{
    case NonCoveredMedicationsRead = 'medications.non_covered.read';

    public function label(): string
    {
        return match ($this) {
            self::NonCoveredMedicationsRead => 'Consultar medicamentos no cubiertos en gestión',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $ability) {
            $options[$ability->value] = $ability->label();
        }

        return $options;
    }
}
