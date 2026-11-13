<?php

declare(strict_types=1);

namespace App\Support\Administration\FiscalBooks;

use InvalidArgumentException;

/**
 * Aritmética exacta de bolívares para los libros fiscales.
 *
 * Todo se opera como cadena decimal con bcmath, nunca como float: sumar 500
 * facturas de seis cifras con float acumula errores de céntimo que descuadran
 * el resumen contra la declaración. El redondeo es a dos decimales, mitad hacia
 * arriba (alejándose de cero), como lo hace Excel.
 */
final class FiscalAmount
{
    public const SCALE = 2;

    private const WORK_SCALE = 10;

    public const ZERO = '0.00';

    /**
     * Normaliza a cadena con dos decimales. Acepta enteros, floats, cadenas con
     * punto decimal y cadenas con formato venezolano («1.234.567,89»).
     */
    public static function of(mixed $value): string
    {
        return self::round(self::parse($value));
    }

    public static function add(mixed ...$values): string
    {
        $total = '0';

        foreach ($values as $value) {
            $total = bcadd($total, self::parse($value), self::WORK_SCALE);
        }

        return self::round($total);
    }

    public static function sub(mixed $minuend, mixed $subtrahend): string
    {
        return self::round(bcsub(self::parse($minuend), self::parse($subtrahend), self::WORK_SCALE));
    }

    public static function negate(mixed $value): string
    {
        return self::round(bcmul(self::parse($value), '-1', self::WORK_SCALE));
    }

    public static function abs(mixed $value): string
    {
        $value = self::of($value);

        return str_starts_with($value, '-') ? substr($value, 1) : $value;
    }

    public static function compare(mixed $left, mixed $right): int
    {
        return bccomp(self::of($left), self::of($right), self::SCALE);
    }

    public static function equals(mixed $left, mixed $right): bool
    {
        return self::compare($left, $right) === 0;
    }

    public static function isZero(mixed $value): bool
    {
        return self::compare($value, '0') === 0;
    }

    public static function isNegative(mixed $value): bool
    {
        return self::compare($value, '0') < 0;
    }

    /**
     * IVA de una base: base × alícuota / 100, redondeado al céntimo.
     */
    public static function taxFromBase(mixed $base, mixed $rate): string
    {
        return self::round(bcdiv(bcmul(self::parse($base), self::parse($rate), self::WORK_SCALE), '100', self::WORK_SCALE));
    }

    /**
     * Separa un monto gravado que ya incluye IVA en base e IVA. La base se
     * redondea y el IVA es la diferencia, así base + IVA = monto exacto.
     *
     * @return array{base: string, tax: string}
     */
    public static function splitTaxIncluded(mixed $amountWithTax, mixed $rate): array
    {
        $amount = self::of($amountWithTax);
        $divisor = bcadd('100', self::parse($rate), self::WORK_SCALE);

        if (bccomp($divisor, '0', self::WORK_SCALE) <= 0) {
            throw new InvalidArgumentException('La alícuota no es válida.');
        }

        $base = self::round(bcdiv(bcmul($amount, '100', self::WORK_SCALE), $divisor, self::WORK_SCALE));

        return ['base' => $base, 'tax' => self::sub($amount, $base)];
    }

    /**
     * Número con la escala pedida (tasas de cambio: hasta 8 decimales), sin
     * perder precisión al redondear a dos.
     */
    public static function precise(mixed $value, int $scale = 8): string
    {
        $parsed = self::parse($value);
        $adjust = '0.'.str_repeat('0', $scale).'5';
        $rounded = str_starts_with($parsed, '-') ? bcsub($parsed, $adjust, $scale) : bcadd($parsed, $adjust, $scale);
        $trimmed = rtrim(rtrim($rounded, '0'), '.');

        return $trimmed === '' || $trimmed === '-0' ? '0' : $trimmed;
    }

    /**
     * USD × tasa, redondeado al céntimo.
     */
    public static function convert(mixed $amount, mixed $rate): string
    {
        return self::round(bcmul(self::parse($amount), self::parse($rate), self::WORK_SCALE));
    }

    /**
     * Formato venezolano: 1.234.567,89 (con signo si es negativo).
     */
    public static function format(mixed $value): string
    {
        $value = self::of($value);
        $negative = str_starts_with($value, '-');
        [$integer, $decimals] = explode('.', ltrim($value, '-'));
        $grouped = strrev(implode('.', str_split(strrev($integer), 3)));

        return ($negative ? '-' : '').$grouped.','.$decimals;
    }

    /**
     * Valor numérico para escribir en una celda de Excel. Solo para salida: los
     * cálculos nunca usan este float.
     */
    public static function toFloat(mixed $value): float
    {
        return (float) self::of($value);
    }

    private static function round(string $value): string
    {
        $adjust = '0.'.str_repeat('0', self::SCALE).'5';

        return str_starts_with($value, '-')
            ? self::canonical(bcsub($value, $adjust, self::SCALE))
            : self::canonical(bcadd($value, $adjust, self::SCALE));
    }

    /**
     * Evita «-0.00».
     */
    private static function canonical(string $value): string
    {
        return bccomp($value, '0', self::SCALE) === 0 ? self::ZERO : $value;
    }

    private static function parse(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            if (! is_finite($value)) {
                throw new InvalidArgumentException('El monto no es un número válido.');
            }

            return rtrim(rtrim(sprintf('%.'.self::WORK_SCALE.'F', $value), '0'), '.') ?: '0';
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException('El monto no es un número válido.');
        }

        $clean = str_replace([' ', "\u{00A0}", 'Bs.', 'Bs', '$'], '', trim($value));

        if (str_contains($clean, ',')) {
            $clean = str_replace(['.', ','], ['', '.'], $clean);
        }

        if ($clean === '' || $clean === '-') {
            return '0';
        }

        if (preg_match('/^-?\d+(\.\d+)?$/', $clean) !== 1) {
            throw new InvalidArgumentException('«'.$value.'» no es un monto válido.');
        }

        return $clean;
    }
}
