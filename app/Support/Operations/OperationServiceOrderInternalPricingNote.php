<?php

declare(strict_types=1);

namespace App\Support\Operations;

/**
 * La frase que el sistema escribía sola en las observaciones de una orden
 * creada desde una cotización:
 *
 *   «Generada automáticamente desde cotización #56 (costo base US$ 15.00, ganancia 20.00%).»
 *
 * Revela el costo del proveedor y el margen de TDG, y las observaciones salen
 * impresas en el PDF que recibe el proveedor. Ya no se escribe; esta clase la
 * reconoce para quitarla de los datos viejos y para que ningún documento la
 * muestre aunque quede alguna sin limpiar.
 */
final class OperationServiceOrderInternalPricingNote
{
    private const PATTERN = '/Generada\s+autom[aá]ticamente\s+desde\s+cotizaci[oó]n\s+#\d+\s*\(\s*costo\s+base[^)]*\)\.?/iu';

    public static function contains(?string $observations): bool
    {
        return is_string($observations) && preg_match(self::PATTERN, $observations) === 1;
    }

    /**
     * Las observaciones sin la frase interna. Lo que haya escrito el analista
     * se conserva; si no queda nada, null.
     */
    public static function strip(?string $observations): ?string
    {
        if (! is_string($observations)) {
            return null;
        }

        $clean = preg_replace(self::PATTERN, '', $observations) ?? $observations;
        $clean = preg_replace("/[ \t]+\n/u", "\n", $clean) ?? $clean;
        $clean = preg_replace("/\n{3,}/u", "\n\n", $clean) ?? $clean;
        $clean = preg_replace('/^[\s\-·|]+|[\s\-·|]+$/u', '', $clean) ?? trim($clean);

        return $clean === '' ? null : $clean;
    }
}
