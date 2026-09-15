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

    /**
     * Envuelve un identificador puramente numérico (número de cuenta, ruta, ABA, ACH,
     * teléfono...) para que Excel lo conserve como texto al abrir el CSV.
     *
     * Sin esto, Excel interpreta cualquier cadena larga de solo dígitos como un número:
     * la muestra en notación científica (1,040006E+18) y, más grave, pierde precisión
     * más allá de los primeros 15 dígitos. La fórmula ="valor" es el truco estándar para
     * forzar texto en un CSV: Excel evalúa la fórmula y muestra el literal completo, con
     * ceros a la izquierda incluidos. Valores con letras (SWIFT, IBAN) no se tocan porque
     * ya los muestra Excel como texto.
     */
    public static function forceTextForExcel(?string $value): string
    {
        $value = (string) ($value ?? '');

        if ($value === '' || preg_match('/^\d+$/', $value) !== 1) {
            return $value;
        }

        return '="'.str_replace('"', '""', $value).'"';
    }
}
