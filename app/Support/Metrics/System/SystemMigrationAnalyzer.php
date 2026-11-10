<?php

declare(strict_types=1);

namespace App\Support\Metrics\System;

/**
 * Lee una migración (texto) y resume qué le hace a la base de datos: tablas
 * nuevas, tablas modificadas, columnas e índices. Solo cuenta lo que hay en
 * `up()`: lo de `down()` es la marcha atrás, no trabajo nuevo.
 */
final class SystemMigrationAnalyzer
{
    private const COLUMN_TYPES = 'bigIncrements|bigInteger|binary|boolean|char|date|dateTime|dateTimeTz|decimal|double|enum|float|foreignId|foreignIdFor|foreignUlid|foreignUuid|geometry|id|increments|integer|ipAddress|json|jsonb|longText|macAddress|mediumInteger|mediumText|morphs|nullableMorphs|nullableUlidMorphs|nullableUuidMorphs|set|smallInteger|string|text|time|timeTz|timestamp|timestampTz|tinyInteger|tinyText|unsignedBigInteger|unsignedInteger|unsignedMediumInteger|unsignedSmallInteger|unsignedTinyInteger|ulid|ulidMorphs|uuid|uuidMorphs|year|softDeletes|softDeletesTz';

    /**
     * @return array{label: string, created: list<string>, altered: list<string>, dropped: list<string>, columns: int, indexes: int}
     */
    public static function analyze(string $fileName, string $contents): array
    {
        $up = preg_split('/function\s+down\s*\(/', $contents, 2)[0] ?? $contents;

        $created = self::tables($up, 'create');
        $altered = array_values(array_diff(self::tables($up, 'table'), $created));
        $dropped = array_values(array_unique(array_merge(self::tables($up, 'dropIfExists'), self::tables($up, 'drop'))));

        preg_match_all('/\$table->(?:'.self::COLUMN_TYPES.')\s*\(/', $up, $columnMatches);
        preg_match_all('/->(?:index|unique|primary|foreign|fullText|spatialIndex|constrained)\s*\(/', $up, $indexMatches);
        preg_match_all('/\$table->timestamps\s*\(/', $up, $timestampMatches);

        return [
            'label' => self::label($fileName, $created, $altered),
            'created' => $created,
            'altered' => $altered,
            'dropped' => $dropped,
            'columns' => count($columnMatches[0]) + 2 * count($timestampMatches[0]),
            'indexes' => count($indexMatches[0]),
        ];
    }

    /**
     * Descripción legible de la migración a partir del nombre del archivo.
     *
     * @param  list<string>  $created
     * @param  list<string>  $altered
     */
    public static function label(string $fileName, array $created = [], array $altered = []): string
    {
        $name = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', basename($fileName, '.php')) ?? $fileName;

        if ($created !== [] && $altered === []) {
            return (count($created) === 1 ? 'Nueva tabla ' : 'Nuevas tablas ').implode(', ', $created);
        }

        if (preg_match('/^create_(.+?)_table$/', $name, $matches) === 1) {
            return 'Nueva tabla '.$matches[1];
        }

        if (preg_match('/^add_(.+?)_to_(.+?)_table$/', $name, $matches) === 1) {
            return 'Agrega '.str_replace('_', ' ', $matches[1]).' a '.$matches[2];
        }

        return ucfirst(str_replace('_', ' ', $name));
    }

    /**
     * @return list<string>
     */
    private static function tables(string $code, string $method): array
    {
        preg_match_all('/Schema::(?:connection\([^)]*\)->)?'.$method.'\s*\(\s*[\'"]([A-Za-z0-9_]+)[\'"]/', $code, $matches);

        return array_values(array_unique($matches[1]));
    }
}
