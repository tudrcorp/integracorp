<?php

declare(strict_types=1);

namespace App\Support\Rrhh;

use Illuminate\Support\Carbon;

final class RrhhNominaPeriodo
{
    public const PERIODOS_POR_ANIO = 24;

    /**
     * @return array{anio: int, periodo: int, quincena: int, mes: int, fecha_desde: string, fecha_hasta: string, label: string}
     */
    public static function resolve(int $anio, int $periodo): array
    {
        if ($periodo < 1 || $periodo > self::PERIODOS_POR_ANIO) {
            throw new \InvalidArgumentException('El período de nómina debe estar entre 1 y 24.');
        }

        if ($anio < 2000 || $anio > 2100) {
            throw new \InvalidArgumentException('El año del período de nómina no es válido.');
        }

        $mes = (int) ceil($periodo / 2);
        $quincena = $periodo % 2 === 1 ? 1 : 2;

        if ($quincena === 1) {
            $fechaDesde = Carbon::create($anio, $mes, 1)->startOfDay();
            $fechaHasta = Carbon::create($anio, $mes, 15)->startOfDay();
        } else {
            $fechaDesde = Carbon::create($anio, $mes, 16)->startOfDay();
            $fechaHasta = Carbon::create($anio, $mes, 1)->endOfMonth()->startOfDay();
        }

        $label = sprintf(
            'P%02d · %s — %s',
            $periodo,
            $fechaDesde->format('d/m/Y'),
            $fechaHasta->format('d/m/Y'),
        );

        return [
            'anio' => $anio,
            'periodo' => $periodo,
            'quincena' => $quincena,
            'mes' => $mes,
            'fecha_desde' => $fechaDesde->toDateString(),
            'fecha_hasta' => $fechaHasta->toDateString(),
            'label' => $label,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function optionsForYear(int $anio): array
    {
        $options = [];

        for ($periodo = 1; $periodo <= self::PERIODOS_POR_ANIO; $periodo++) {
            $resolved = self::resolve($anio, $periodo);
            $options[$periodo] = $resolved['label'];
        }

        return $options;
    }

    /**
     * @return array<int, int>
     */
    public static function yearOptions(?int $currentYear = null): array
    {
        $year = $currentYear ?? (int) now()->year;

        $years = range($year - 1, $year + 1);

        return array_combine($years, $years);
    }

    public static function currentPeriodNumber(?Carbon $date = null): int
    {
        $date ??= now();
        $day = (int) $date->day;
        $month = (int) $date->month;
        $quincenaOffset = $day <= 15 ? 0 : 1;

        return (($month - 1) * 2) + 1 + $quincenaOffset;
    }

    public static function sueldoDelPeriodo(float $sueldoMensual): float
    {
        return round($sueldoMensual / 2, 2);
    }
}
