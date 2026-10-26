<?php

declare(strict_types=1);

namespace App\Support\Affiliations\PersonIdentity;

use App\Support\Operations\AffiliatePersonalDataUpdater;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Forma comparable de la identidad de una persona, para reconocerla aunque se haya escrito distinto.
 *
 * - Cédula: `M 19131154 2`, `M-19131154-2`, `19131154-2` y `191311542` son la misma (se quitan la
 *   letra inicial, los espacios, puntos y guiones, y los ceros a la izquierda). Un pasaporte, con
 *   letras en medio, se compara entero.
 * - Nombre: el mismo conjunto de palabras sin importar el orden ni las repeticiones. Así
 *   «ROBLES OTERO ESTHEFANO JOSUE ROBLES OTERO ESTHEFANO JOSUE» y «ESTHEFANO JOSUE ROBLES OTERO»
 *   son el mismo nombre. Se ignoran tildes y conectores (DE, DEL, LA…).
 * - Fecha de nacimiento: cualquier formato de la base (`4/12/2019`, `04-12-2019`, `2019-12-04`).
 *
 * Ojo: en la base hay menores registrados con la cédula del representante, y hermanos con la misma.
 * Por eso la cédula sola nunca identifica a una persona: siempre se cruza con el nombre.
 */
final class PersonIdentityNormalizer
{
    public const MIN_DOCUMENT_LENGTH = 5;

    public const MIN_NAME_WORDS = 2;

    /**
     * @var list<string>
     */
    private const NAME_CONNECTORS = ['DE', 'DEL', 'LA', 'LAS', 'LOS', 'Y', 'DA', 'DAS', 'DO', 'DOS', 'DI', 'E'];

    /**
     * Cédula comparable, o null si no sirve para identificar a nadie (vacía, demasiado corta o de
     * relleno como `00000001` o `11111111`).
     */
    public static function documentKey(mixed $value): ?string
    {
        $raw = mb_strtoupper(trim((string) $value));

        if ($raw === '') {
            return null;
        }

        $key = preg_match('/^[A-Z]?[\s.\-\/]*[\d.\-\s\/]+$/', $raw) === 1
            ? ltrim((string) preg_replace('/\D+/', '', $raw), '0')
            : (string) preg_replace('/[^A-Z0-9]+/', '', Str::ascii($raw));

        if (mb_strlen($key) < self::MIN_DOCUMENT_LENGTH || preg_match('/^(.)\1+$/', $key) === 1) {
            return null;
        }

        return mb_substr($key, 0, 40);
    }

    /**
     * Palabras del nombre en mayúsculas, sin tildes ni conectores, sin repetir y en orden.
     *
     * @return list<string>
     */
    public static function nameWords(string ...$parts): array
    {
        $words = [];

        foreach (self::orderedWords(implode(' ', $parts)) as $word) {
            if (! in_array($word, self::NAME_CONNECTORS, true)) {
                $words[$word] = true;
            }
        }

        $words = array_keys($words);
        sort($words, SORT_STRING);

        return array_values(array_map(strval(...), $words));
    }

    /**
     * Nombre comparable (las palabras ordenadas), o null si tiene menos de dos palabras útiles.
     */
    public static function nameKey(string ...$parts): ?string
    {
        $words = self::nameWords(...$parts);

        if (count($words) < self::MIN_NAME_WORDS) {
            return null;
        }

        return mb_substr(implode(' ', $words), 0, 255);
    }

    public static function birthDate(mixed $value): ?Carbon
    {
        $date = AffiliatePersonalDataUpdater::parseBirthDate($value);

        if ($date === null || $date->year < 1900 || $date->isAfter(now()->addYear())) {
            return null;
        }

        return $date->startOfDay();
    }

    public static function birthDateKey(mixed $value): ?string
    {
        return self::birthDate($value)?->format('Y-m-d');
    }

    /**
     * Cómo se parecen dos nombres: `equal` (mismas palabras), `subset` (uno contiene al otro entero,
     * p. ej. «YURAIMA VARGAS» y «YURAIMA DEL CARMEN VARGAS PEREZ») o null.
     *
     * @return 'equal'|'subset'|null
     */
    public static function nameRelation(?string $nameKeyA, ?string $nameKeyB): ?string
    {
        if ($nameKeyA === null || $nameKeyB === null) {
            return null;
        }

        if ($nameKeyA === $nameKeyB) {
            return 'equal';
        }

        $a = explode(' ', $nameKeyA);
        $b = explode(' ', $nameKeyB);

        if (array_diff($a, $b) === [] || array_diff($b, $a) === []) {
            return 'subset';
        }

        return null;
    }

    /**
     * El nombre se cargó repetido: nombres y apellidos traen lo mismo (afiliados corporativos), o el
     * nombre completo es una frase escrita dos veces («ROBLES OTERO ESTHEFANO ROBLES OTERO ESTHEFANO»).
     */
    public static function isRepeatedName(?string $firstName, ?string $lastName = null): bool
    {
        if ($lastName !== null) {
            $first = self::orderedWords((string) $firstName);
            $last = self::orderedWords($lastName);

            if (count($last) >= self::MIN_NAME_WORDS && $first === $last) {
                return true;
            }

            return self::isRepeatedName(trim((string) $firstName.' '.$lastName));
        }

        $words = self::orderedWords((string) $firstName);
        $count = count($words);

        if ($count < self::MIN_NAME_WORDS * 2 || $count % 2 !== 0) {
            return false;
        }

        $half = intdiv($count, 2);

        return array_slice($words, 0, $half) === array_slice($words, $half);
    }

    /**
     * @return list<string>
     */
    private static function orderedWords(string $value): array
    {
        $clean = mb_strtoupper(Str::ascii($value));
        $clean = (string) preg_replace('/[^A-Z0-9\s]+/', ' ', $clean);

        return array_values(array_filter(preg_split('/\s+/', trim($clean)) ?: [], static fn (string $word): bool => $word !== ''));
    }
}
