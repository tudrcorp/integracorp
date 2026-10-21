<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates\Population;

use DateTimeInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;

/**
 * Lee el archivo de población del cliente: Excel (.xlsx), CSV o TXT.
 *
 * - Todo se devuelve como texto: las cédulas de menores llevan ceros a la
 *   izquierda (`010444385-16`) que un número perdería.
 * - CSV/TXT: el separador se detecta solo (`;` `,` tabulador `|`) y la
 *   codificación también (UTF-8 o Windows-1252/Latin-1, para no romper la Ñ).
 * - La fila de encabezados es la primera con contenido. Solo la primera hoja.
 */
final class PopulationFileReader
{
    public const MAX_ROWS = 30000;

    public const MAX_BYTES = 15 * 1024 * 1024;

    /** @var list<string> */
    public const FORMATS = ['xlsx', 'csv', 'txt'];

    /**
     * @return array{headers: list<string>, rows: list<array{row: int, values: array<string, string>}>, format: string}
     */
    public static function read(string $path, ?string $format = null): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('No se pudo leer el archivo cargado.');
        }

        if (filesize($path) > self::MAX_BYTES) {
            throw new RuntimeException('El archivo pesa más de 15 MB. Divídalo o quite columnas que no se usan.');
        }

        $format = strtolower((string) ($format ?: pathinfo($path, PATHINFO_EXTENSION)));
        if (! in_array($format, self::FORMATS, true)) {
            throw new RuntimeException('Formato no soportado («'.$format.'»). Use Excel (.xlsx), CSV o TXT.');
        }

        $raw = $format === 'xlsx' ? self::readXlsx($path) : self::readDelimited($path);

        return self::withHeaders($raw) + ['format' => $format];
    }

    /**
     * Solo los encabezados (para el mapeo de columnas en pantalla).
     *
     * @return list<string>
     */
    public static function headers(string $path, ?string $format = null): array
    {
        return self::read($path, $format)['headers'];
    }

    /**
     * @param  list<list<string>>  $raw
     * @return array{headers: list<string>, rows: list<array{row: int, values: array<string, string>}>}
     */
    private static function withHeaders(array $raw): array
    {
        $headers = null;
        $rows = [];

        foreach ($raw as $index => $cells) {
            if (trim(implode('', $cells)) === '') {
                continue;
            }

            if ($headers === null) {
                $headers = self::uniqueHeaders($cells);

                continue;
            }

            $values = [];
            foreach ($headers as $position => $header) {
                $values[$header] = trim((string) ($cells[$position] ?? ''));
            }

            $rows[] = ['row' => $index + 1, 'values' => $values];

            if (count($rows) > self::MAX_ROWS) {
                throw new RuntimeException('El archivo tiene más de '.self::MAX_ROWS.' filas. Divídalo en partes.');
            }
        }

        if ($headers === null) {
            throw new RuntimeException('El archivo está vacío.');
        }

        if ($rows === []) {
            throw new RuntimeException('El archivo solo tiene encabezados: no trae personas.');
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * Encabezados limpios y sin repetir (un segundo «APELLIDO» pasa a «APELLIDO (2)»).
     *
     * @param  list<string>  $cells
     * @return list<string>
     */
    private static function uniqueHeaders(array $cells): array
    {
        $headers = [];
        $seen = [];

        foreach ($cells as $position => $cell) {
            $header = trim(preg_replace('/\s+/u', ' ', (string) $cell) ?? '');
            if ($header === '') {
                $header = 'COLUMNA '.($position + 1);
            }

            $key = mb_strtoupper($header);
            $seen[$key] = ($seen[$key] ?? 0) + 1;
            $headers[] = $seen[$key] > 1 ? $header.' ('.$seen[$key].')' : $header;
        }

        return $headers;
    }

    /**
     * @return list<list<string>>
     */
    private static function readXlsx(string $path): array
    {
        $reader = new XlsxReader;

        try {
            $reader->open($path);
        } catch (\Throwable) {
            throw new RuntimeException('El Excel no se pudo abrir. Guárdelo de nuevo como .xlsx e intente otra vez.');
        }

        $rows = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = array_map(static fn (mixed $cell): string => self::cellToString($cell), $row->toArray());

                    if (count($rows) > self::MAX_ROWS + 50) {
                        throw new RuntimeException('El archivo tiene más de '.self::MAX_ROWS.' filas. Divídalo en partes.');
                    }
                }

                break;
            }
        } finally {
            $reader->close();
        }

        return $rows;
    }

    /**
     * @return list<list<string>>
     */
    private static function readDelimited(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;

        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $firstLine = strtok($content, "\n") ?: '';
        $delimiter = self::detectDelimiter($firstLine);

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $rows = [];
        while (($cells = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
            if ($cells === [null]) {
                $rows[] = [];

                continue;
            }

            $rows[] = array_map(static fn (mixed $value): string => (string) $value, $cells);

            if (count($rows) > self::MAX_ROWS + 50) {
                fclose($stream);
                throw new RuntimeException('El archivo tiene más de '.self::MAX_ROWS.' filas. Divídalo en partes.');
            }
        }

        fclose($stream);

        return $rows;
    }

    public static function detectDelimiter(string $line): string
    {
        $best = ';';
        $bestCount = 0;

        foreach ([';', ',', "\t", '|'] as $candidate) {
            $count = substr_count($line, $candidate);
            if ($count > $bestCount) {
                $best = $candidate;
                $bestCount = $count;
            }
        }

        if ($bestCount === 0) {
            throw new RuntimeException('No se reconoce el separador de columnas del archivo. Use punto y coma, coma, tabulador o barra vertical.');
        }

        return $best;
    }

    private static function cellToString(mixed $cell): string
    {
        if ($cell instanceof DateTimeInterface) {
            return $cell->format('Y-m-d');
        }

        if (is_float($cell) && floor($cell) === $cell && abs($cell) < 1e15) {
            return number_format($cell, 0, '', '');
        }

        if (is_bool($cell)) {
            return $cell ? '1' : '0';
        }

        return trim((string) $cell);
    }
}
