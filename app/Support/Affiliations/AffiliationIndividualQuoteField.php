<?php

declare(strict_types=1);

namespace App\Support\Affiliations;

use App\Models\IndividualQuote;

/**
 * Campo «Nombre del cliente» de la afiliación individual: la cotización de origen.
 *
 * Solo se muestra al crear la afiliación; en la edición va oculto para que un
 * `individual_quote_id` que apunte a una cotización inexistente no bloquee el
 * guardado. Resolver la etiqueta de un solo registro evita cargar la tabla entera
 * de cotizaciones en cada render. Una cotización inexistente no recibe etiqueta,
 * así Filament sigue rechazándola al crear.
 */
final class AffiliationIndividualQuoteField
{
    public static function optionLabel(mixed $value): ?string
    {
        $quoteId = self::normalizeId($value);

        if ($quoteId === null) {
            return null;
        }

        $quote = IndividualQuote::query()->select(['id', 'code', 'full_name'])->find($quoteId);

        if ($quote === null) {
            return null;
        }

        $name = trim((string) $quote->full_name);
        $code = trim((string) $quote->code);

        $name = $name !== '' ? $name : 'Sin nombre';

        return $code !== '' ? "{$name} · {$code}" : $name;
    }

    private static function normalizeId(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value) && ctype_digit(trim($value))) {
            $id = (int) trim($value);

            return $id > 0 ? $id : null;
        }

        return null;
    }
}
