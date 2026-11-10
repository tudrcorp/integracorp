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

    /**
     * El tramo «· Total US$ 62.54» que llevaba la descripción automática
     * «Orden por cotización #57 · MEDICAMENTOS · 4 ítem(s) · Total US$ 62.54».
     * Ese total es el precio de venta de TDG, y frente al costo del proveedor
     * deja ver el margen.
     */
    private const DESCRIPTION_TOTAL_PATTERN = '/^(Orden\s+por\s+cotizaci[oó]n\s+#\d+.*?)\s*·\s*Total\s+(?:US\$|Bs\.)\s*[\d.,]+\s*$/iu';

    public static function contains(?string $observations): bool
    {
        return is_string($observations) && preg_match(self::PATTERN, $observations) === 1;
    }

    public static function descriptionContainsTotal(?string $description): bool
    {
        return is_string($description) && preg_match(self::DESCRIPTION_TOTAL_PATTERN, $description) === 1;
    }

    /**
     * La descripción sin el total de la cotización; cualquier otra descripción
     * queda igual.
     */
    public static function stripDescriptionTotal(?string $description): ?string
    {
        if (! is_string($description)) {
            return $description;
        }

        return preg_replace(self::DESCRIPTION_TOTAL_PATTERN, '$1', $description) ?? $description;
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
