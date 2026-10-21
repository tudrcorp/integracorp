<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates\Population;

use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Normaliza una fila del archivo a una persona comparable con la base, y
 * reúne las utilidades de comparación (nombres, cédulas, fechas) que usa el
 * motor de cruce.
 */
final class PopulationPerson
{
    /** @var list<string> */
    private const EXCLUDED_MARKS = ['S', 'SI', 'SÍ', 'Y', 'YES', 'X', '1', 'BAJA', 'EXCLUIDO', 'INACTIVO'];

    /**
     * @param  array<string, string>  $values
     * @param  array<string, string|null>  $mapping
     * @return array{row: int, document: string, doc_key: string, document_type: string, minor_document: bool, first_name: string, last_name: string, full_name: string, birth_date: string|null, sex: string|null, relationship: string|null, relationship_code: string, excluded: bool, problem: string|null}
     */
    public static function fromRow(int $row, array $values, array $mapping): array
    {
        $get = static fn (string $field): string => isset($mapping[$field]) && $mapping[$field] !== null
            ? trim((string) ($values[$mapping[$field]] ?? ''))
            : '';

        $first = self::cleanName($get('first_name'));
        $last = self::cleanName(trim($get('last_name').' '.$get('second_last_name')));
        $document = preg_replace('/\s+/', '', $get('document')) ?? '';
        $type = Str::upper($get('document_type'));
        $sex = self::sex($get('sex'));
        $relationshipCode = Str::upper(Str::ascii($get('relationship')));
        $birth = self::date($get('birth_date'));

        $problem = null;
        if ($first === '' && $last === '') {
            $problem = 'La fila no trae nombre.';
        } elseif (self::documentKey($document) === '' && $birth === null) {
            $problem = 'La fila no trae cédula ni fecha de nacimiento: no se puede identificar.';
        } elseif ($get('birth_date') !== '' && $birth === null) {
            $problem = 'La fecha de nacimiento «'.$get('birth_date').'» no se entiende.';
        }

        return [
            'row' => $row,
            'document' => $document,
            'doc_key' => self::documentKey($document),
            'document_type' => $type,
            'minor_document' => self::isMinorDocument($document, $type),
            'first_name' => $first,
            'last_name' => $last,
            'full_name' => trim($first.' '.$last),
            'birth_date' => $birth,
            'sex' => $sex,
            'relationship' => self::relationship($relationshipCode, $sex),
            'relationship_code' => $relationshipCode,
            'excluded' => in_array(Str::upper(trim($get('excluded_flag'))), self::EXCLUDED_MARKS, true),
            'problem' => $problem,
        ];
    }

    /**
     * Solo dígitos y sin ceros a la izquierda: «V-010.444.385» y «10444385» son la misma.
     */
    public static function documentKey(?string $document): string
    {
        return ltrim(preg_replace('/\D+/', '', (string) $document) ?? '', '0');
    }

    /** Cédula «real» de adulto: suficiente para identificar (los rellenos «12», «1039» no). */
    public static function isIdentifyingDocument(string $docKey): bool
    {
        return strlen($docKey) >= 5;
    }

    /**
     * Cédula de menor: la del representante con sufijo (`010444385-16`) o tipo CIM.
     */
    public static function isMinorDocument(string $document, string $type = ''): bool
    {
        return $type === 'CIM' || preg_match('/^\d+-\d{1,2}$/', $document) === 1;
    }

    /**
     * Mismo criterio que el importador existente: adulto solo dígitos; menor con
     * sufijo tal cual, para no perder ceros ni el sufijo.
     */
    public static function storedDocument(string $document): string
    {
        $clean = preg_replace('/\s+/', '', trim($document)) ?? '';

        return str_contains($clean, '-') && preg_match('/^\d[\d\-]*$/', $clean) === 1
            ? $clean
            : (preg_replace('/\D+/', '', $clean) ?? '');
    }

    public static function cleanName(string $name): string
    {
        $name = str_replace(['ﾑ', '�'], 'Ñ', $name);

        return mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $name) ?? ''));
    }

    /**
     * Letras para comparar: sin tildes y sin caracteres dañados. «CEDEﾑO», «CEDE?O»
     * y «CEDE O» terminan iguales al compactar.
     */
    public static function letters(string $value): string
    {
        $value = str_replace(['ﾑ', '�', '?', 'Ñ', 'ñ'], ' ', $value);
        $value = Str::upper(Str::ascii($value));
        $value = preg_replace('/[^A-Z\s]/', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    /**
     * @return list<string>
     */
    public static function tokens(string $value): array
    {
        $tokens = array_values(array_filter(explode(' ', self::letters($value)), static fn (string $token): bool => strlen($token) > 1));
        sort($tokens);

        return $tokens;
    }

    /**
     * Parecido entre dos nombres (0..1): mejor de orden normal, nombre/apellido
     * invertidos y palabras en común.
     */
    public static function similarity(string $first, string $last, string $other): float
    {
        $target = str_replace(' ', '', self::letters($other));
        $best = 0.0;

        foreach ([$first.' '.$last, $last.' '.$first] as $candidate) {
            $compact = str_replace(' ', '', self::letters($candidate));
            if ($compact === '' || $target === '') {
                continue;
            }

            similar_text($compact, $target, $percent);
            $best = max($best, $percent / 100);
        }

        $a = self::tokens($first.' '.$last);
        $b = self::tokens($other);
        if ($a !== [] && $b !== []) {
            $best = max($best, count(array_intersect($a, $b)) / max(count($a), count($b)));
        }

        return $best;
    }

    public static function tokenOverlap(string $a, string $b): int
    {
        return count(array_intersect(self::tokens($a), self::tokens($b)));
    }

    /**
     * Fecha a Y-m-d. Acepta Y-m-d, d/m/Y (y sin ceros), d-m-Y, d.m.Y y el número
     * de serie de Excel.
     */
    public static function date(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        // Número de serie de Excel desde 1910 (3654): un año suelto («1980») no es una fecha.
        if (preg_match('/^\d{4,5}(\.\d+)?$/', $value) === 1 && (float) $value >= 3654 && (float) $value < 80000) {
            return Carbon::create(1899, 12, 30)->addDays((int) floor((float) $value))->format('Y-m-d');
        }

        $value = preg_replace('/\s+\d{1,2}:\d{2}(:\d{2})?.*$/', '', $value) ?? $value;

        foreach (['Y-m-d', 'd/m/Y', 'j/n/Y', 'd-m-Y', 'j-n-Y', 'd.m.Y', 'Y/m/d'] as $format) {
            $date = \DateTime::createFromFormat('!'.$format, $value);
            $errors = \DateTime::getLastErrors();
            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                $year = (int) $date->format('Y');
                if ($year >= 1900 && $year <= (int) date('Y') + 1) {
                    return $date->format('Y-m-d');
                }
            }
        }

        return null;
    }

    /** Fecha de la base («4/12/2022», «19/02/2019», «2004-11-19») a Y-m-d. */
    public static function storedDate(?string $value): ?string
    {
        return self::date($value);
    }

    public static function ageAt(?string $birthDate, ?Carbon $at = null): ?int
    {
        if ($birthDate === null) {
            return null;
        }

        $age = Carbon::parse($birthDate)->diffInYears($at ?? now());

        return max(0, (int) floor($age));
    }

    /** Formato que usa la tabla de afiliados corporativos: d/m/Y. */
    public static function toStoredBirthDate(?string $birthDate): ?string
    {
        return $birthDate === null ? null : Carbon::parse($birthDate)->format('d/m/Y');
    }

    private static function sex(string $value): ?string
    {
        $value = Str::upper(Str::ascii(trim($value)));

        return match (true) {
            in_array($value, ['M', 'MASCULINO', 'HOMBRE', 'H', 'MASC'], true) => 'MASCULINO',
            in_array($value, ['F', 'FEMENINO', 'MUJER', 'FEM'], true) => 'FEMENINO',
            default => null,
        };
    }

    private static function relationship(string $code, ?string $sex): ?string
    {
        $female = $sex === 'FEMENINO';

        return match (true) {
            $code === '' => null,
            in_array($code, ['TI', 'TITULAR', 'T'], true) => 'TITULAR',
            in_array($code, ['CO', 'CONYUGE', 'ESPOSA', 'ESPOSO', 'CY'], true) => $code === 'ESPOSA' || ($code !== 'ESPOSO' && $female) ? 'ESPOSA' : 'ESPOSO',
            in_array($code, ['HI', 'HD', 'HIJO', 'HIJA', 'H'], true) => $code === 'HIJA' || ($code !== 'HIJO' && $female) ? 'HIJA' : 'HIJO',
            in_array($code, ['MA', 'MADRE', 'M'], true) => 'MADRE',
            in_array($code, ['PA', 'PADRE', 'P'], true) => 'PADRE',
            in_array($code, ['COLABORADOR', 'EMPLEADO', 'TRABAJADOR'], true) => 'COLABORADOR',
            default => 'OTRO',
        };
    }
}
