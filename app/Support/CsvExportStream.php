<?php

declare(strict_types=1);

namespace App\Support;

final class CsvExportStream
{
    public const UTF8_BOM = "\xEF\xBB\xBF";

    /**
     * Abre el stream de salida con BOM UTF-8 para que Excel interprete correctamente los acentos.
     *
     * @return resource|false
     */
    public static function openOutput(): mixed
    {
        $handle = fopen('php://output', 'w');

        if ($handle === false) {
            return false;
        }

        fwrite($handle, self::UTF8_BOM);

        return $handle;
    }
}
