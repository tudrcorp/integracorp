<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\SupplierEstatusSistema;

/**
 * Catálogo de «Estatus del convenio» / estatus en sistema para proveedores jurídicos.
 * «CLINICA CERRADA» va siempre al final del select.
 */
final class SupplierEstatusSistemaOptions
{
    public const CLINICA_CERRADA = 'CLINICA CERRADA';

    /**
     * @param  iterable<int|string, mixed>  $descriptions
     * @return array<string, string>
     */
    public static function orderedForSelect(iterable $descriptions): array
    {
        $options = [];

        foreach ($descriptions as $description) {
            $label = trim((string) $description);

            if ($label === '') {
                continue;
            }

            $options[$label] = $label;
        }

        if (! array_key_exists(self::CLINICA_CERRADA, $options)) {
            return $options;
        }

        $label = $options[self::CLINICA_CERRADA];
        unset($options[self::CLINICA_CERRADA]);
        $options[self::CLINICA_CERRADA] = $label;

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function forSelect(): array
    {
        return self::orderedForSelect(
            SupplierEstatusSistema::query()
                ->orderBy('description')
                ->pluck('description')
        );
    }
}
