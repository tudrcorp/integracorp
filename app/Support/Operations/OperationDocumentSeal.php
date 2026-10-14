<?php

declare(strict_types=1);

namespace App\Support\Operations;

/**
 * Sello de Tu Doctor en Casa que llevan los documentos de Operaciones: la orden
 * de servicio y las cotizaciones (asociada, de medicamentos y del generador).
 *
 * Va embebido como data URI porque DomPDF no lee rutas remotas y así el PDF sale
 * igual en cola, en correo y en la vista previa.
 */
final class OperationDocumentSeal
{
    public const FILE = 'image/sello-tdg.png';

    private static ?string $dataUri = null;

    /**
     * Data URI del sello, o cadena vacía si el archivo no está: el documento
     * se genera igual, sin sello, en vez de fallar.
     */
    public static function dataUri(): string
    {
        if (self::$dataUri !== null) {
            return self::$dataUri;
        }

        $path = public_path(self::FILE);

        if (! is_file($path)) {
            return '';
        }

        $contents = file_get_contents($path);

        if ($contents === false || $contents === '') {
            return '';
        }

        return self::$dataUri = 'data:image/png;base64,'.base64_encode($contents);
    }
}
