<?php

declare(strict_types=1);

namespace App\Support\Rrhh;

final class RrhhPrestamoCuotaCalculo
{
    public static function normalizarMonto(mixed $value): float
    {
        return round((float) $value, 2);
    }

    public static function montoCuotaDesdePorcentaje(mixed $sueldoBase, mixed $porcentaje): float
    {
        return round(((float) $sueldoBase) * (((float) $porcentaje) / 100), 2);
    }

    public static function totalDescuentos(mixed $nroCuotas, mixed $montoCuota): float
    {
        return round(((int) $nroCuotas) * self::normalizarMonto($montoCuota), 2);
    }

    public static function diferencia(mixed $montoPrestamo, mixed $nroCuotas, mixed $montoCuota): float
    {
        return round(
            self::totalDescuentos($nroCuotas, $montoCuota) - self::normalizarMonto($montoPrestamo),
            2
        );
    }

    public static function cuadraExacto(mixed $montoPrestamo, mixed $nroCuotas, mixed $montoCuota): bool
    {
        $prestamo = self::normalizarMonto($montoPrestamo);
        $cuotas = (int) $nroCuotas;
        $cuota = self::normalizarMonto($montoCuota);

        if ($prestamo <= 0 || $cuotas < 1 || $cuota < 0) {
            return false;
        }

        return abs(self::diferencia($prestamo, $cuotas, $cuota)) < 0.005;
    }

    public static function mensajeError(mixed $montoPrestamo, mixed $nroCuotas, mixed $montoCuota): string
    {
        $prestamo = self::normalizarMonto($montoPrestamo);
        $cuotas = (int) $nroCuotas;
        $cuota = self::normalizarMonto($montoCuota);
        $total = self::totalDescuentos($cuotas, $cuota);
        $diferencia = self::diferencia($prestamo, $cuotas, $cuota);

        $prestamoFmt = number_format($prestamo, 2, '.', ',');
        $cuotaFmt = number_format($cuota, 2, '.', ',');
        $totalFmt = number_format($total, 2, '.', ',');
        $diferenciaFmt = number_format(abs($diferencia), 2, '.', ',');

        $sentido = $diferencia > 0
            ? "excede el préstamo en US$ {$diferenciaFmt}"
            : "queda por debajo del préstamo en US$ {$diferenciaFmt}";

        return "Error en el cálculo de las cuotas: {$cuotas} cuota(s) de US$ {$cuotaFmt} suman US$ {$totalFmt}, pero el préstamo es de US$ {$prestamoFmt} ({$sentido}). Revise el número de cuotas, el porcentaje de descuento o el monto de cada descuento.";
    }

    public static function resumenValidacion(mixed $montoPrestamo, mixed $nroCuotas, mixed $montoCuota): string
    {
        $prestamo = self::normalizarMonto($montoPrestamo);
        $cuotas = (int) $nroCuotas;
        $cuota = self::normalizarMonto($montoCuota);
        $total = self::totalDescuentos($cuotas, $cuota);

        if ($prestamo <= 0 || $cuotas < 1) {
            return 'Complete el monto del préstamo, el número de cuotas y el monto de cada descuento para validar el cálculo.';
        }

        if (self::cuadraExacto($prestamo, $cuotas, $cuota)) {
            return 'Cálculo correcto: las cuotas suman exactamente US$ '.number_format($prestamo, 2, '.', ',').' del préstamo.';
        }

        return self::mensajeError($prestamo, $cuotas, $cuota);
    }
}
