<?php

declare(strict_types=1);

namespace App\Support\Affiliations;

use Illuminate\Support\Facades\Schema;

/**
 * ¿Ya existen las columnas `document_type_*`? Mientras no se corra la migración
 * 2026_11_03_120000, los formularios ocultan el select y no lo guardan, y el reporte no
 * las pide: así subir el código antes que la migración no rompe ningún panel.
 */
final class AffiliationDocumentTypeColumns
{
    private static ?bool $available = null;

    public static function available(): bool
    {
        if (self::$available !== null) {
            return self::$available;
        }

        try {
            return self::$available = Schema::hasColumn('affiliations', 'document_type_ti')
                && Schema::hasColumn('affiliations', 'document_type_payer')
                && Schema::hasColumn('affiliation_corporates', 'document_type_rif');
        } catch (\Throwable $exception) {
            report($exception);

            return self::$available = false;
        }
    }

    /**
     * Copia el tipo del titular al pagador cuando «el titular es el responsable de pago»,
     * solo si el formulario trae el campo (columnas presentes).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function copyHolderTypeToPayer(array $data): array
    {
        if (array_key_exists('document_type_ti', $data)) {
            $data['document_type_payer'] = $data['document_type_ti'];
        }

        return $data;
    }

    /**
     * Solo para pruebas.
     */
    public static function flush(): void
    {
        self::$available = null;
    }
}
