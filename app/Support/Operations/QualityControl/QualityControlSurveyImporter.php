<?php

declare(strict_types=1);

namespace App\Support\Operations\QualityControl;

use App\Enums\QualityControlAffiliateType;
use App\Enums\QualityControlRating;
use App\Enums\QualityControlService;
use App\Models\QualityControlSurvey;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Analiza e importa el Excel de respuestas de Quality Control.
 *
 * `analyze()` no escribe nada: dice cuántas filas entran, cuáles fallan y por qué, cuántas
 * ya estaban cargadas y cuántas quedan enlazadas a un afiliado. `import()` vuelve a leer y
 * validar el archivo (no confía en el análisis previo) y escribe con `insertOrIgnore` sobre
 * la huella única: correrlo dos veces, o reintentar el job, no duplica encuestas.
 */
final class QualityControlSurveyImporter
{
    private const INSERT_CHUNK = 250;

    private const MAX_ERRORS_LISTED = 50;

    /**
     * @return array{
     *     total: int,
     *     valid: int,
     *     new: int,
     *     duplicates: int,
     *     repeated_in_file: int,
     *     linked: int,
     *     other_type_matches: int,
     *     errors: list<array{row: int, message: string}>,
     *     error_count: int,
     *     missing_columns: list<string>
     * }
     */
    public static function analyze(string $path, QualityControlAffiliateType $type, ?string $format = null): array
    {
        $prepared = self::prepare($path, $type, $format);
        $hashes = array_column($prepared['rows'], 'dedupe_hash');

        $existing = self::existingHashes($hashes);
        $new = array_filter($prepared['rows'], fn (array $row): bool => ! isset($existing[$row['dedupe_hash']]));

        $otherType = $type === QualityControlAffiliateType::Individual ? QualityControlAffiliateType::Corporate : QualityControlAffiliateType::Individual;
        $unlinked = array_values(array_unique(array_column(array_filter($new, fn (array $row): bool => $row['affiliate_id'] === null), 'patient_document')));
        $otherMatches = $unlinked === [] ? 0 : count(QualityControlAffiliateLookup::findMany($otherType, $unlinked));

        return [
            'total' => $prepared['total'],
            'valid' => count($prepared['rows']),
            'new' => count($new),
            'duplicates' => count($prepared['rows']) - count($new),
            'repeated_in_file' => $prepared['repeated_in_file'],
            'linked' => count(array_filter($new, fn (array $row): bool => $row['affiliate_id'] !== null)),
            'other_type_matches' => $otherMatches,
            'errors' => array_slice($prepared['errors'], 0, self::MAX_ERRORS_LISTED),
            'error_count' => count($prepared['errors']),
            'missing_columns' => $prepared['missing_columns'],
        ];
    }

    /**
     * @return array{inserted: int, skipped: int, errors: int, linked: int}
     */
    public static function import(string $path, QualityControlAffiliateType $type, ?int $userId, string $batch, ?string $format = null): array
    {
        $prepared = self::prepare($path, $type, $format);
        $now = now()->format('Y-m-d H:i:s');
        $inserted = 0;
        $linked = 0;

        foreach (array_chunk($prepared['rows'], self::INSERT_CHUNK) as $chunk) {
            $records = array_map(fn (array $row): array => [
                'affiliate_type' => $type->value,
                'affiliate_id' => $row['affiliate_id'],
                'responded_at' => $row['responded_at'],
                'service_date' => $row['service_date'],
                'patient_document' => $row['patient_document'],
                'patient_document_key' => $row['patient_document_key'],
                'patient_name' => $row['patient_name'],
                'contact_phone' => $row['contact_phone'],
                'rating_channels' => $row['rating_channels'],
                'rating_response_time' => $row['rating_response_time'],
                'rating_medical_service' => $row['rating_medical_service'],
                'rating_overall' => $row['rating_overall'],
                'observations' => $row['observations'],
                'services' => json_encode($row['services'], JSON_UNESCAPED_UNICODE),
                'source' => QualityControlSurvey::SOURCE_IMPORT,
                'import_batch' => $batch,
                'dedupe_hash' => $row['dedupe_hash'],
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk);

            $inserted += DB::transaction(fn (): int => QualityControlSurvey::query()->insertOrIgnore($records));
        }

        if ($inserted > 0) {
            $linked = QualityControlSurvey::query()
                ->where('import_batch', $batch)
                ->whereNotNull('affiliate_id')
                ->count();
        }

        return [
            'inserted' => $inserted,
            'skipped' => count($prepared['rows']) - $inserted,
            'errors' => count($prepared['errors']),
            'linked' => $linked,
        ];
    }

    /**
     * Convierte una fila del archivo en los datos de una encuesta, o en sus errores.
     *
     * @param  list<mixed>  $cells
     * @param  array<string, list<int>>  $columns
     * @return array{data: array<string, mixed>|null, errors: list<string>}
     */
    public static function parseRow(array $cells, array $columns, QualityControlAffiliateType $type): array
    {
        $value = fn (string $field): mixed => isset($columns[$field][0]) ? ($cells[$columns[$field][0]] ?? null) : null;
        $text = fn (string $field): string => self::text($value($field));
        $errors = [];

        $respondedAt = self::dateTime($value('responded_at'));
        if ($respondedAt === null) {
            $errors[] = 'La marca temporal está vacía o no es una fecha.';
        }

        $serviceDateRaw = $value('service_date');
        $serviceDate = self::dateTime($serviceDateRaw);
        if ($serviceDate === null && self::text($serviceDateRaw) !== '') {
            $errors[] = 'La fecha de servicio «'.self::text($serviceDateRaw).'» no es una fecha.';
        }

        if ($serviceDate !== null && $respondedAt !== null && $serviceDate->startOfDay()->greaterThan($respondedAt->startOfDay())) {
            $errors[] = 'La fecha de servicio ('.$serviceDate->format('d/m/Y').') es posterior a la respuesta ('.$respondedAt->format('d/m/Y').').';
        }

        $document = mb_strtoupper(self::text($value('patient_document')));
        if ($document === '') {
            $errors[] = 'Falta la cédula del paciente.';
        } elseif (mb_strlen($document) > 60) {
            $errors[] = 'La cédula tiene más de 60 caracteres.';
        }

        $name = mb_strtoupper(Str::squish($text('patient_name')));
        if ($name === '') {
            $errors[] = 'Falta el nombre del paciente.';
        }

        $ratings = [];
        foreach (array_keys(QualityControlSurvey::RATING_FIELDS) as $field) {
            $raw = $text($field);
            $rating = QualityControlRating::fromText($raw);

            if ($raw !== '' && $rating === null) {
                $errors[] = '«'.QualityControlSurvey::RATING_FIELDS[$field].'»: «'.$raw.'» no es una calificación válida.';
            }

            $ratings[$field] = $rating?->value;
        }

        $services = [];
        $unknownServices = [];
        foreach ($columns['services'] ?? [] as $position) {
            $parsed = QualityControlService::parseList(self::text($cells[$position] ?? null));
            $services = [...$services, ...$parsed['services']];
            $unknownServices = [...$unknownServices, ...$parsed['unknown']];
        }

        if ($unknownServices !== []) {
            $errors[] = 'Servicio no reconocido: «'.implode('», «', array_unique($unknownServices)).'».';
        }

        if ($errors !== []) {
            return ['data' => null, 'errors' => $errors];
        }

        $phone = Str::squish($text('contact_phone'));
        $observations = trim($text('observations'));

        return [
            'data' => [
                'responded_at' => $respondedAt->format('Y-m-d H:i:s'),
                'service_date' => $serviceDate?->format('Y-m-d'),
                'patient_document' => $document,
                'patient_document_key' => QualityControlSurveyKeys::documentKey($document),
                'patient_name' => mb_substr($name, 0, 255),
                'contact_phone' => $phone !== '' ? mb_substr($phone, 0, 60) : null,
                ...$ratings,
                'observations' => $observations !== '' ? $observations : null,
                'services' => array_values(array_unique($services)),
                'dedupe_hash' => QualityControlSurveyKeys::dedupeHash($type, $respondedAt, $document),
            ],
            'errors' => [],
        ];
    }

    /**
     * Fecha de una celda: fecha nativa del Excel, número de serie de Excel (con fracción de
     * día = hora) o texto `dd/mm/aaaa` o `aaaa-mm-dd`, con hora opcional (hh:mm o hh:mm:ss).
     *
     * Ojo: no escribir aquí el formato con corchetes. Tailwind escanea también el PHP y toma
     * un texto entre corchetes con dos puntos como propiedad CSS arbitraria: eso rompió
     * `npm run build` («Missed semicolon» en storefront.css).
     */
    public static function dateTime(mixed $value): ?CarbonImmutable
    {
        $timezone = (string) config('app.timezone', 'America/Caracas');

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::createFromFormat('Y-m-d H:i:s', $value->format('Y-m-d H:i:s'), $timezone) ?: null;
        }

        if (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^\d{4,6}(\.\d+)?$/', trim($value)) === 1)) {
            $serial = (float) $value;

            /** 1 = 01/01/1900 en Excel; fuera de 1990–2100 no es una fecha de encuesta. */
            if ($serial < 32874 || $serial > 73051) {
                return null;
            }

            /**
             * El serial es hora de pared, sin zona. Se cuenta en UTC: en 1899 Caracas tenía
             * un desfase local de −4:27:44 y contar desde allí corría la hora 27 minutos.
             */
            $wallClock = CarbonImmutable::create(1899, 12, 30, 0, 0, 0, 'UTC')->addSeconds((int) round($serial * 86400));

            return CarbonImmutable::createFromFormat('Y-m-d H:i:s', $wallClock->format('Y-m-d H:i:s'), $timezone) ?: null;
        }

        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        foreach (['d/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d', 'd-m-Y H:i:s', 'd-m-Y'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $text, $timezone);
            } catch (\Throwable) {
                continue;
            }

            if ($date !== false && $date !== null && $date->format($format) === $text) {
                return $date;
            }
        }

        return null;
    }

    /**
     * @return array{
     *     total: int,
     *     rows: list<array<string, mixed>>,
     *     errors: list<array{row: int, message: string}>,
     *     repeated_in_file: int,
     *     missing_columns: list<string>
     * }
     */
    private static function prepare(string $path, QualityControlAffiliateType $type, ?string $format): array
    {
        $file = QualityControlSurveyFileReader::read($path, $format);
        $rows = [];
        $errors = [];
        $seen = [];
        $repeated = 0;

        foreach ($file['rows'] as $line) {
            $parsed = self::parseRow($line['cells'], $file['columns'], $type);

            if ($parsed['data'] === null) {
                $errors[] = ['row' => $line['row'], 'message' => implode(' ', $parsed['errors'])];

                continue;
            }

            if (isset($seen[$parsed['data']['dedupe_hash']])) {
                $repeated++;

                continue;
            }

            $seen[$parsed['data']['dedupe_hash']] = true;
            $rows[] = $parsed['data'];
        }

        $matches = QualityControlAffiliateLookup::findMany($type, array_column($rows, 'patient_document'));

        foreach ($rows as &$row) {
            $row['affiliate_id'] = $matches[$row['patient_document']]['id'] ?? null;
        }
        unset($row);

        $missing = array_values(array_map(
            fn (string $field): string => QualityControlSurveyFileReader::FIELD_LABELS[$field],
            array_diff(array_keys(QualityControlSurveyFileReader::FIELD_LABELS), array_keys($file['columns'])),
        ));

        return [
            'total' => count($file['rows']),
            'rows' => $rows,
            'errors' => $errors,
            'repeated_in_file' => $repeated,
            'missing_columns' => $missing,
        ];
    }

    /**
     * Huellas ya guardadas, incluidas las eliminadas: una encuesta borrada no se reimporta.
     *
     * @param  list<string>  $hashes
     * @return array<string, true>
     */
    private static function existingHashes(array $hashes): array
    {
        $existing = [];

        foreach (array_chunk(array_values(array_unique($hashes)), 1000) as $chunk) {
            foreach (QualityControlSurvey::withTrashed()->whereIn('dedupe_hash', $chunk)->pluck('dedupe_hash') as $hash) {
                $existing[(string) $hash] = true;
            }
        }

        return $existing;
    }

    private static function text(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_float($value) && floor($value) === $value && abs($value) < 1e15) {
            return number_format($value, 0, '', '');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return trim((string) $value);
    }
}
