<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates\Population;

use App\Models\AffiliateCorporate;
use Illuminate\Support\Str;

/**
 * Compara, campo por campo, la persona del archivo con una ficha de la base.
 *
 * Es la misma vara que usa el cruce, expuesta para tres cosas: explicar al
 * analista por qué una fila quedó como quedó, decidir qué datos faltan por
 * corregir y verificar después de corregir que la ficha ya cuadra.
 */
final class PopulationFieldComparison
{
    public const FIELD_DOCUMENT = 'document';

    public const FIELD_FIRST_NAME = 'first_name';

    public const FIELD_LAST_NAME = 'last_name';

    public const FIELD_BIRTH_DATE = 'birth_date';

    public const FIELD_SEX = 'sex';

    public const FIELD_RELATIONSHIP = 'relationship';

    /** Igual en ambos lados. */
    public const SAME = 'same';

    /** Se escribe igual salvo tildes, eñes dañadas o espacios: no cuenta como diferencia. */
    public const FORMAT = 'format';

    /** Nombres y apellidos cruzados entre sí. */
    public const SWAPPED = 'swapped';

    /** Mismas palabras repartidas distinto entre nombres y apellidos. */
    public const SPLIT = 'split';

    public const DIFFERENT = 'different';

    /** La base no lo tiene y el archivo sí. */
    public const MISSING_BASE = 'missing_base';

    /** El archivo no lo trae: no hay contra qué comparar. */
    public const MISSING_FILE = 'missing_file';

    /** Cédula de menor en el archivo frente a un número de relleno en la base. */
    public const FILLER = 'filler';

    public const SEXES = ['MASCULINO', 'FEMENINO'];

    public const RELATIONSHIPS = ['TITULAR', 'ESPOSA', 'ESPOSO', 'HIJO', 'HIJA', 'MADRE', 'PADRE', 'COLABORADOR', 'OTRO'];

    /**
     * Campos cuya diferencia cuenta para «Coincide con diferencias». Sexo y
     * parentesco vacíos en la base son lo normal en las fichas viejas: solo
     * cuenta si los dos lados lo traen y se contradicen.
     *
     * @var array<string, list<string>>
     */
    private const COUNTED = [
        self::FIELD_DOCUMENT => [self::DIFFERENT, self::FILLER, self::MISSING_BASE],
        self::FIELD_FIRST_NAME => [self::DIFFERENT, self::SWAPPED, self::SPLIT, self::MISSING_BASE],
        self::FIELD_LAST_NAME => [self::DIFFERENT, self::SWAPPED, self::SPLIT, self::MISSING_BASE],
        self::FIELD_BIRTH_DATE => [self::DIFFERENT, self::MISSING_BASE],
        self::FIELD_SEX => [self::DIFFERENT],
        self::FIELD_RELATIONSHIP => [self::DIFFERENT],
    ];

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::FIELD_DOCUMENT => 'Cédula',
            self::FIELD_FIRST_NAME => 'Nombres',
            self::FIELD_LAST_NAME => 'Apellidos',
            self::FIELD_BIRTH_DATE => 'Fecha de nacimiento',
            self::FIELD_SEX => 'Sexo',
            self::FIELD_RELATIONSHIP => 'Parentesco',
        ];
    }

    public static function label(string $field): string
    {
        return self::labels()[$field] ?? $field;
    }

    /**
     * Valores de la ficha en el mismo formato que la persona del archivo.
     *
     * @return array{document: string, first_name: string, last_name: string, birth_date: string|null, sex: string|null, relationship: string|null}
     */
    public static function baseValues(AffiliateCorporate $affiliate): array
    {
        return [
            self::FIELD_DOCUMENT => trim((string) $affiliate->nro_identificacion),
            self::FIELD_FIRST_NAME => trim((string) $affiliate->first_name),
            self::FIELD_LAST_NAME => trim((string) $affiliate->last_name),
            self::FIELD_BIRTH_DATE => PopulationPerson::storedDate((string) $affiliate->birth_date),
            self::FIELD_SEX => self::normalizeSex($affiliate->sex),
            self::FIELD_RELATIONSHIP => self::normalizeRelationship($affiliate->relationship),
        ];
    }

    /**
     * Valores del archivo en el formato que se guardaría en la ficha.
     *
     * @param  array<string, mixed>  $person  salida de PopulationPerson::fromRow
     * @return array{document: string, first_name: string, last_name: string, birth_date: string|null, sex: string|null, relationship: string|null}
     */
    public static function fileValues(array $person): array
    {
        return [
            self::FIELD_DOCUMENT => PopulationPerson::storedDocument((string) ($person['document'] ?? '')),
            self::FIELD_FIRST_NAME => (string) ($person['first_name'] ?? ''),
            self::FIELD_LAST_NAME => (string) ($person['last_name'] ?? ''),
            self::FIELD_BIRTH_DATE => isset($person['birth_date']) && $person['birth_date'] !== '' ? (string) $person['birth_date'] : null,
            self::FIELD_SEX => self::normalizeSex($person['sex'] ?? null),
            self::FIELD_RELATIONSHIP => self::normalizeRelationship($person['relationship'] ?? null),
        ];
    }

    /**
     * @param  array<string, string|null>  $file  valores del archivo (fileValues)
     * @param  array<string, string|null>  $base  valores de la base (baseValues)
     * @return array<string, array{field: string, label: string, file: string|null, base: string|null, status: string, counted: bool, note: string}>
     */
    public static function compare(array $file, array $base): array
    {
        $out = [];

        $document = self::compareDocument((string) ($file[self::FIELD_DOCUMENT] ?? ''), (string) ($base[self::FIELD_DOCUMENT] ?? ''));
        $out[self::FIELD_DOCUMENT] = self::row(self::FIELD_DOCUMENT, $file, $base, $document[0], $document[1]);

        foreach (self::compareNames($file, $base) as $field => [$status, $note]) {
            $out[$field] = self::row($field, $file, $base, $status, $note);
        }

        $birth = self::compareSimple($file[self::FIELD_BIRTH_DATE] ?? null, $base[self::FIELD_BIRTH_DATE] ?? null);
        $out[self::FIELD_BIRTH_DATE] = self::row(self::FIELD_BIRTH_DATE, $file, $base, $birth, match ($birth) {
            self::SAME => 'Misma fecha.',
            self::MISSING_BASE => 'La ficha no tiene fecha de nacimiento.',
            self::MISSING_FILE => 'El archivo no trae fecha.',
            default => 'Las fechas no son iguales'.self::dateGap($file[self::FIELD_BIRTH_DATE] ?? null, $base[self::FIELD_BIRTH_DATE] ?? null).'.',
        });

        foreach ([self::FIELD_SEX, self::FIELD_RELATIONSHIP] as $field) {
            $status = self::compareSimple($file[$field] ?? null, $base[$field] ?? null);
            $out[$field] = self::row($field, $file, $base, $status, match ($status) {
                self::SAME => 'Igual.',
                self::MISSING_BASE => 'La ficha no lo tiene: se puede completar con el dato del archivo.',
                self::MISSING_FILE => 'El archivo no lo trae.',
                default => 'El archivo y la ficha dicen cosas distintas.',
            });
        }

        return $out;
    }

    /**
     * Compara la persona de una fila del archivo con una ficha.
     *
     * @param  array<string, mixed>  $person
     * @return array<string, array{field: string, label: string, file: string|null, base: string|null, status: string, counted: bool, note: string}>
     */
    public static function forPerson(array $person, AffiliateCorporate $affiliate): array
    {
        return self::compare(self::fileValues($person), self::baseValues($affiliate));
    }

    /**
     * Campos que no cuadran y que el analista no dio por buenos.
     *
     * @param  array<string, array{counted: bool}>  $comparison
     * @param  array<string, mixed>  $accepted  campo => nota de quien aceptó la diferencia
     * @return list<string>
     */
    public static function pendingDifferences(array $comparison, array $accepted = []): array
    {
        $out = [];
        foreach ($comparison as $field => $row) {
            if ($row['counted'] && ! array_key_exists($field, $accepted)) {
                $out[] = $field;
            }
        }

        return $out;
    }

    public static function normalizeSex(mixed $value): ?string
    {
        $value = Str::upper(Str::ascii(trim((string) $value)));

        return match (true) {
            in_array($value, ['M', 'MASCULINO', 'HOMBRE', 'MASC'], true) => 'MASCULINO',
            in_array($value, ['F', 'FEMENINO', 'MUJER', 'FEM'], true) => 'FEMENINO',
            default => null,
        };
    }

    public static function normalizeRelationship(mixed $value): ?string
    {
        $value = Str::upper(Str::ascii(trim((string) $value)));

        return in_array($value, self::RELATIONSHIPS, true) ? $value : null;
    }

    /**
     * Texto para mostrar un valor de la comparación.
     */
    public static function display(string $field, ?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if ($field === self::FIELD_BIRTH_DATE) {
            $date = PopulationPerson::date($value);

            return $date !== null ? date('d/m/Y', (int) strtotime($date)) : $value;
        }

        return $value;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function compareDocument(string $file, string $base): array
    {
        $fileKey = PopulationPerson::documentKey($file);
        $baseKey = PopulationPerson::documentKey($base);

        if ($fileKey === '') {
            return [self::MISSING_FILE, 'El archivo no trae cédula.'];
        }

        if ($baseKey === '') {
            return [self::MISSING_BASE, 'La ficha no tiene cédula.'];
        }

        if ($fileKey === $baseKey) {
            return $file === $base
                ? [self::SAME, 'Misma cédula.']
                : [self::SAME, 'Es el mismo número: solo cambia la forma de escribirlo (ceros a la izquierda, letra o guiones).'];
        }

        if (PopulationPerson::isMinorDocument($file) && ! PopulationPerson::isIdentifyingDocument($baseKey)) {
            return [self::FILLER, 'La ficha tiene «'.$base.'», un número de relleno que no identifica a nadie. El archivo trae la cédula de menor (la del representante con un sufijo).'];
        }

        return [self::DIFFERENT, 'Son números distintos'.(strlen($fileKey) >= 7 && strlen($baseKey) >= 7 && substr($fileKey, 0, 7) === substr($baseKey, 0, 7) ? ' (comparten los primeros 7 dígitos: suele ser un error de tipeo)' : '').'.'];
    }

    /**
     * @param  array<string, string|null>  $file
     * @param  array<string, string|null>  $base
     * @return array<string, array{0: string, 1: string}>
     */
    private static function compareNames(array $file, array $base): array
    {
        $fileFirst = PopulationPerson::letters((string) ($file[self::FIELD_FIRST_NAME] ?? ''));
        $fileLast = PopulationPerson::letters((string) ($file[self::FIELD_LAST_NAME] ?? ''));
        $baseFirst = PopulationPerson::letters((string) ($base[self::FIELD_FIRST_NAME] ?? ''));
        $baseLast = PopulationPerson::letters((string) ($base[self::FIELD_LAST_NAME] ?? ''));

        $swapped = $fileFirst !== '' && $fileLast !== '' && $fileFirst === $baseLast && $fileLast === $baseFirst;
        $split = ! $swapped
            && PopulationPerson::tokens($fileFirst.' '.$fileLast) === PopulationPerson::tokens($baseFirst.' '.$baseLast)
            && ($fileFirst !== $baseFirst || $fileLast !== $baseLast);

        $out = [];
        foreach ([self::FIELD_FIRST_NAME => [$fileFirst, $baseFirst, (string) ($file[self::FIELD_FIRST_NAME] ?? ''), (string) ($base[self::FIELD_FIRST_NAME] ?? '')], self::FIELD_LAST_NAME => [$fileLast, $baseLast, (string) ($file[self::FIELD_LAST_NAME] ?? ''), (string) ($base[self::FIELD_LAST_NAME] ?? '')]] as $field => [$fileLetters, $baseLetters, $fileRaw, $baseRaw]) {
            $out[$field] = match (true) {
                $fileLetters === '' => [self::MISSING_FILE, 'El archivo no lo trae.'],
                $baseLetters === '' => [self::MISSING_BASE, 'La ficha lo tiene vacío.'],
                $swapped => [self::SWAPPED, 'Nombres y apellidos están cruzados: en la ficha los nombres están donde van los apellidos.'],
                $split => [self::SPLIT, 'Son las mismas palabras, pero repartidas distinto entre nombres y apellidos.'],
                $fileLetters === $baseLetters => $fileRaw === $baseRaw
                    ? [self::SAME, 'Igual.']
                    : [self::FORMAT, 'Se escribe igual; solo cambian tildes, eñes dañadas o espacios.'],
                default => [self::DIFFERENT, self::spellingNote($fileLetters, $baseLetters)],
            };
        }

        return $out;
    }

    private static function spellingNote(string $file, string $base): string
    {
        similar_text(str_replace(' ', '', $file), str_replace(' ', '', $base), $percent);

        return $percent >= 80
            ? 'Se parecen en un '.(int) round($percent).' %: probablemente una errata.'
            : 'Son distintos (se parecen en un '.(int) round($percent).' %).';
    }

    private static function compareSimple(?string $file, ?string $base): string
    {
        $file = $file === null ? '' : trim($file);
        $base = $base === null ? '' : trim($base);

        return match (true) {
            $file === '' => self::MISSING_FILE,
            $base === '' => self::MISSING_BASE,
            $file === $base => self::SAME,
            default => self::DIFFERENT,
        };
    }

    private static function dateGap(?string $file, ?string $base): string
    {
        $a = PopulationPerson::date($file);
        $b = PopulationPerson::date($base);
        if ($a === null || $b === null) {
            return '';
        }

        [$ay, $am, $ad] = array_map('intval', explode('-', $a));
        [$by, $bm, $bd] = array_map('intval', explode('-', $b));

        return match (true) {
            $ad === $bm && $am === $bd && $ay === $by => ' (día y mes invertidos)',
            $ay !== $by && $am === $bm && $ad === $bd => ' (solo cambia el año: '.abs($ay - $by).' año(s) de diferencia)',
            $ay === $by && $am === $bm => ' (solo cambia el día)',
            $ay === $by && $ad === $bd => ' (solo cambia el mes)',
            default => '',
        };
    }

    /**
     * @param  array<string, string|null>  $file
     * @param  array<string, string|null>  $base
     * @return array{field: string, label: string, file: string|null, base: string|null, status: string, counted: bool, note: string}
     */
    private static function row(string $field, array $file, array $base, string $status, string $note): array
    {
        return [
            'field' => $field,
            'label' => self::label($field),
            'file' => $file[$field] ?? null,
            'base' => $base[$field] ?? null,
            'status' => $status,
            'counted' => in_array($status, self::COUNTED[$field] ?? [], true),
            'note' => $note,
        ];
    }
}
