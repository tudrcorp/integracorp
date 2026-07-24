<?php

declare(strict_types=1);

namespace App\Support\Rrhh;

final class RrhhValorCalculo
{
    public const TIPO_MONTO = 'monto';

    public const TIPO_PORCENTAJE = 'porcentaje';

    /**
     * @return array<string, string>
     */
    public static function tipoOptions(): array
    {
        return [
            self::TIPO_MONTO => 'Monto fijo',
            self::TIPO_PORCENTAJE => 'Porcentaje',
        ];
    }

    public static function calcular(?string $tipoValor, mixed $monto, mixed $porcentaje, float $sueldoBase): float
    {
        if ($tipoValor === self::TIPO_PORCENTAJE) {
            return round($sueldoBase * (((float) $porcentaje) / 100), 2);
        }

        return round((float) $monto, 2);
    }

    public static function valorLabel(?string $tipoValor, mixed $monto, mixed $porcentaje): string
    {
        if ($tipoValor === self::TIPO_PORCENTAJE) {
            return number_format((float) $porcentaje, 2, '.', '').'% s/sueldo base';
        }

        return 'US$ '.number_format((float) $monto, 2, '.', ',');
    }

    public static function tipoLabel(?string $tipoValor): string
    {
        return match ($tipoValor) {
            self::TIPO_PORCENTAJE => 'Porcentaje',
            self::TIPO_MONTO => 'Monto fijo',
            default => (string) ($tipoValor ?? '—'),
        };
    }
}
