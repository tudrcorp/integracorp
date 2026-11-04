<?php

declare(strict_types=1);

namespace App\Support\Operations\QualityControl;

use App\Support\AffiliationCorporates\Population\PopulationFileReader;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;

/**
 * Lee el Excel (o CSV) de respuestas de Quality Control.
 *
 * - El archivo del formulario trae un título arriba («CONTROL DE CALIDAD DE SERVICIOS»):
 *   la fila de encabezados es la primera que nombra las columnas obligatorias, no la primera
 *   con contenido.
 * - Las celdas se devuelven crudas (fecha, número o texto): la marca temporal lleva hora y
 *   convertirla a texto aquí la perdería.
 * - Varias columnas pueden alimentar el mismo campo: el formulario tiene dos «Servicio
 *   Recibido» y se combinan.
 */
final class QualityControlSurveyFileReader
{
    public const MAX_ROWS = 5000;

    public const MAX_BYTES = 5 * 1024 * 1024;

    /** @var list<string> */
    public const FORMATS = ['xlsx', 'csv'];

    /** @var list<string> */
    public const REQUIRED_FIELDS = ['responded_at', 'patient_document', 'patient_name'];

    private const HEADER_SCAN_ROWS = 30;

    /**
     * Etiqueta de cada campo, para los mensajes de columnas faltantes.
     *
     * @var array<string, string>
     */
    public const FIELD_LABELS = [
        'responded_at' => 'Marca temporal',
        'service_date' => 'Fecha de Servicio',
        'patient_document' => 'Cédula de Identidad del Paciente',
        'patient_name' => 'Nombre Completo del Paciente',
        'contact_phone' => 'Número de contacto',
        'rating_channels' => 'Como calificaría los canales de atención',
        'rating_response_time' => 'Como calificaría el tiempo de respuesta',
        'rating_medical_service' => 'Como calificaría el servicio médico',
        'rating_overall' => 'Quedó satisfecho con el servicio?',
        'observations' => 'Tiene alguna sugerencia u observación?',
        'services' => 'Servicio Recibido',
    ];

    /**
     * @return array{columns: array<string, list<int>>, rows: list<array{row: int, cells: list<mixed>}>}
     */
    public static function read(string $path, ?string $format = null): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('No se pudo leer el archivo cargado.');
        }

        if (filesize($path) > self::MAX_BYTES) {
            throw new RuntimeException('El archivo pesa más de 5 MB. Divídalo en partes.');
        }

        $format = strtolower((string) ($format ?: pathinfo($path, PATHINFO_EXTENSION)));

        if (! in_array($format, self::FORMATS, true)) {
            throw new RuntimeException('Formato no soportado («'.$format.'»). Use el Excel de respuestas (.xlsx) o un CSV.');
        }

        $raw = $format === 'xlsx' ? self::readXlsx($path) : self::readCsv($path);

        return self::locate($raw);
    }

    /**
     * Encabezado → campo. Tolera tildes, mayúsculas, signos y texto entre corchetes.
     */
    public static function fieldForHeader(mixed $header): ?string
    {
        $text = Str::of(Str::ascii((string) $header))->upper()->replaceMatches('/[^A-Z0-9]+/', ' ')->squish()->toString();

        return match (true) {
            $text === '' => null,
            str_contains($text, 'MARCA TEMPORAL') => 'responded_at',
            str_contains($text, 'FECHA DE SERVICIO') || $text === 'FECHA SERVICIO' => 'service_date',
            str_contains($text, 'CEDULA') => 'patient_document',
            str_contains($text, 'NOMBRE') => 'patient_name',
            str_contains($text, 'CONTACTO') || str_contains($text, 'TELEFONO') => 'contact_phone',
            str_contains($text, 'CANALES') => 'rating_channels',
            str_contains($text, 'TIEMPO DE RESPUESTA') => 'rating_response_time',
            str_contains($text, 'SERVICIO MEDICO') => 'rating_medical_service',
            str_contains($text, 'SATISFECHO') => 'rating_overall',
            str_contains($text, 'SUGERENCIA') || str_contains($text, 'OBSERVACION') => 'observations',
            str_starts_with($text, 'SERVICIO RECIBIDO') || str_starts_with($text, 'SERVICIOS RECIBIDOS') => 'services',
            default => null,
        };
    }

    /**
     * @param  list<list<mixed>>  $raw
     * @return array{columns: array<string, list<int>>, rows: list<array{row: int, cells: list<mixed>}>}
     */
    private static function locate(array $raw): array
    {
        $headerIndex = null;
        $columns = [];

        foreach (array_slice($raw, 0, self::HEADER_SCAN_ROWS, true) as $index => $cells) {
            $candidate = [];

            foreach ($cells as $position => $cell) {
                $field = self::fieldForHeader($cell);

                if ($field !== null) {
                    $candidate[$field][] = $position;
                }
            }

            if (array_diff(self::REQUIRED_FIELDS, array_keys($candidate)) === []) {
                $headerIndex = $index;
                $columns = $candidate;

                break;
            }
        }

        if ($headerIndex === null) {
            throw new RuntimeException('No se encontró la fila de encabezados. El archivo debe traer al menos las columnas «'
                .implode('», «', array_map(fn (string $field): string => self::FIELD_LABELS[$field], self::REQUIRED_FIELDS)).'».');
        }

        $rows = [];

        foreach (array_slice($raw, $headerIndex + 1, null, true) as $index => $cells) {
            if (trim(implode('', array_map(fn (mixed $cell): string => is_scalar($cell) ? (string) $cell : 'x', $cells))) === '') {
                continue;
            }

            $rows[] = ['row' => $index + 1, 'cells' => $cells];

            if (count($rows) > self::MAX_ROWS) {
                throw new RuntimeException('El archivo tiene más de '.self::MAX_ROWS.' respuestas. Divídalo en partes.');
            }
        }

        if ($rows === []) {
            throw new RuntimeException('El archivo solo tiene encabezados: no trae respuestas.');
        }

        return ['columns' => $columns, 'rows' => $rows];
    }

    /**
     * @return list<list<mixed>>
     */
    private static function readXlsx(string $path): array
    {
        /** Sin esto se saltan las filas vacías y el «Fila N» del resumen no coincide con el Excel. */
        $options = new XlsxOptions;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
        $reader = new XlsxReader($options);

        try {
            $reader->open($path);
        } catch (\Throwable) {
            throw new RuntimeException('El Excel no se pudo abrir. Guárdelo de nuevo como .xlsx e intente otra vez.');
        }

        $rows = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();

                    if (count($rows) > self::MAX_ROWS + self::HEADER_SCAN_ROWS) {
                        throw new RuntimeException('El archivo tiene más de '.self::MAX_ROWS.' respuestas. Divídalo en partes.');
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
     * @return list<list<mixed>>
     */
    private static function readCsv(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;

        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $sample = implode("\n", array_slice(explode("\n", $content), 0, self::HEADER_SCAN_ROWS));
        $delimiter = PopulationFileReader::detectDelimiter($sample);

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $rows = [];

        try {
            while (($cells = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
                $rows[] = $cells === [null] ? [] : array_map(fn (mixed $value): string => (string) $value, $cells);

                if (count($rows) > self::MAX_ROWS + self::HEADER_SCAN_ROWS) {
                    throw new RuntimeException('El archivo tiene más de '.self::MAX_ROWS.' respuestas. Divídalo en partes.');
                }
            }
        } finally {
            fclose($stream);
        }

        return $rows;
    }
}
