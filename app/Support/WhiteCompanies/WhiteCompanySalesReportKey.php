<?php

declare(strict_types=1);

namespace App\Support\WhiteCompanies;

use Illuminate\Support\Facades\Config;

/**
 * Llave de integridad del reporte de ventas de empresas aliadas.
 *
 * Es un HMAC-SHA256 sobre el contenido del reporte (aliada, rango, filas y
 * totales) firmado con la APP_KEY. No impide editar el PDF —nada lo impide—,
 * pero un PDF alterado deja de coincidir con su llave, y eso sí es comprobable
 * en la ruta de verificación.
 */
final class WhiteCompanySalesReportKey
{
    /** Longitud en caracteres hexadecimales antes de agrupar en bloques. */
    private const KEY_LENGTH = 16;

    private const PREFIX = 'TDG';

    /**
     * Contenido canónico: cualquier cambio en importes o afiliaciones da otra llave.
     *
     * @param  list<array{code: string, sale_price: float, neta_tdg: float, neta_partner: float}>  $rows
     * @param  array{sale_price: float, neta_tdg: float, neta_partner: float}  $totals
     */
    public static function canonicalPayload(
        int $whiteCompanyId,
        string $from,
        string $to,
        array $rows,
        array $totals,
    ): string {
        $lines = array_map(
            static fn (array $row): string => implode('|', [
                $row['code'],
                number_format($row['sale_price'], 2, '.', ''),
                number_format($row['neta_tdg'], 2, '.', ''),
                number_format($row['neta_partner'], 2, '.', ''),
            ]),
            $rows,
        );

        sort($lines);

        return implode("\n", [
            'v1',
            'company:'.$whiteCompanyId,
            'from:'.$from,
            'to:'.$to,
            'rows:'.count($rows),
            'totals:'.implode('|', [
                number_format($totals['sale_price'], 2, '.', ''),
                number_format($totals['neta_tdg'], 2, '.', ''),
                number_format($totals['neta_partner'], 2, '.', ''),
            ]),
            ...$lines,
        ]);
    }

    /**
     * @param  list<array{code: string, sale_price: float, neta_tdg: float, neta_partner: float}>  $rows
     * @param  array{sale_price: float, neta_tdg: float, neta_partner: float}  $totals
     */
    public static function make(
        int $whiteCompanyId,
        string $from,
        string $to,
        array $rows,
        array $totals,
    ): string {
        return self::fromPayload(
            self::canonicalPayload($whiteCompanyId, $from, $to, $rows, $totals)
        );
    }

    public static function fromPayload(string $payload): string
    {
        $digest = hash_hmac('sha256', $payload, self::signingKey());

        return self::format(substr($digest, 0, self::KEY_LENGTH));
    }

    /**
     * Comparación en tiempo constante para no filtrar información por el tiempo de respuesta.
     */
    public static function matches(string $candidate, string $expected): bool
    {
        return hash_equals(
            self::normalize($expected),
            self::normalize($candidate),
        );
    }

    /**
     * `TDG-A3F2-9C81-4D07-BE55`: legible para dictarla por teléfono o transcribirla.
     */
    public static function format(string $hex): string
    {
        $blocks = str_split(strtoupper($hex), 4);

        return self::PREFIX.'-'.implode('-', $blocks);
    }

    public static function normalize(string $key): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $key) ?? '');
    }

    private static function signingKey(): string
    {
        $key = (string) Config::get('app.key', '');

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }

        return $key;
    }
}
