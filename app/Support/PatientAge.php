<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\TelemedicinePatient;
use App\Support\Telemedicine\TelemedicinePatientBirthDate;
use Carbon\Carbon;

/**
 * Edad legible de una persona (paciente, afiliado, asociado) para mostrarla.
 *
 * La edad numérica guardada sigue siendo años enteros —0 para menores de un
 * año— porque es la que usan los rangos de tarifa, renovaciones y cupos. Esta
 * clase solo arma el texto que lee la gente, a partir de la fecha de
 * nacimiento:
 *
 * - el mismo día: «Recién nacido»
 * - menos de 1 mes: «12 días»
 * - de 1 a 11 meses: «7 meses»
 * - de 1 a 2 años: «1 año y 3 meses»
 * - 2 años o más: «34 años»
 *
 * Sin fecha de nacimiento usa la edad guardada; si esa edad es 0 dice «Menor
 * de 1 año», nunca «0 años».
 */
final class PatientAge
{
    public const UNDER_ONE_YEAR = 'Menor de 1 año';

    public const NEWBORN = 'Recién nacido';

    /** Tope de la memoria por proceso: un worker de cola vive mucho tiempo. */
    private const MEMO_LIMIT = 500;

    /** @var array<int, string|null> */
    private static array $telemedicineBirthDates = [];

    /**
     * @return string «—» si no hay ni fecha ni edad
     */
    public static function label(mixed $birthDate, mixed $storedAge = null, ?Carbon $asOf = null): string
    {
        return self::labelOrNull($birthDate, $storedAge, $asOf) ?? '—';
    }

    public static function labelOrNull(mixed $birthDate, mixed $storedAge = null, ?Carbon $asOf = null): ?string
    {
        $birth = TelemedicinePatientBirthDate::parse($birthDate);
        $today = ($asOf ?? now())->copy()->startOfDay();

        if ($birth !== null && $birth->lessThanOrEqualTo($today)) {
            return self::fromBirthDate($birth, $today);
        }

        return self::fromStoredAge($storedAge);
    }

    /**
     * Menor de un año según la fecha de nacimiento o, sin ella, la edad guardada.
     */
    public static function isUnderOneYear(mixed $birthDate, mixed $storedAge = null, ?Carbon $asOf = null): bool
    {
        $years = self::years($birthDate, $storedAge, $asOf);

        return $years !== null && $years < 1;
    }

    /**
     * Años cumplidos: de la fecha de nacimiento si existe, si no de la edad guardada.
     */
    public static function years(mixed $birthDate, mixed $storedAge = null, ?Carbon $asOf = null): ?int
    {
        $birth = TelemedicinePatientBirthDate::parse($birthDate);
        $today = ($asOf ?? now())->copy()->startOfDay();

        if ($birth !== null && $birth->lessThanOrEqualTo($today)) {
            return (int) $birth->diffInYears($today);
        }

        return self::storedYears($storedAge);
    }

    /**
     * Para plantillas que solo traen el id del paciente de telemedicina (informes,
     * récipe y órdenes): busca su fecha de nacimiento una vez por proceso.
     */
    public static function forTelemedicinePatientId(mixed $patientId, mixed $storedAge = null): string
    {
        return self::label(self::telemedicineBirthDate($patientId), $storedAge);
    }

    public static function flush(): void
    {
        self::$telemedicineBirthDates = [];
    }

    private static function fromBirthDate(Carbon $birth, Carbon $today): string
    {
        $years = (int) $birth->diffInYears($today);

        if ($years >= 2) {
            return $years.' años';
        }

        $months = (int) $birth->diffInMonths($today);

        if ($years === 1) {
            $extra = $months - 12;

            return $extra > 0 ? '1 año y '.self::plural($extra, 'mes', 'meses') : '1 año';
        }

        if ($months >= 1) {
            return self::plural($months, 'mes', 'meses');
        }

        $days = (int) $birth->diffInDays($today);

        return $days === 0 ? self::NEWBORN : self::plural($days, 'día', 'días');
    }

    private static function fromStoredAge(mixed $storedAge): ?string
    {
        $years = self::storedYears($storedAge);

        if ($years === null) {
            return null;
        }

        return $years === 0 ? self::UNDER_ONE_YEAR : self::plural($years, 'año', 'años');
    }

    private static function storedYears(mixed $storedAge): ?int
    {
        if (is_int($storedAge)) {
            return $storedAge >= 0 ? $storedAge : null;
        }

        $text = is_scalar($storedAge) ? trim((string) $storedAge) : '';

        if ($text === '' || ! preg_match('/^\d{1,3}$/', $text)) {
            return null;
        }

        return (int) $text;
    }

    private static function plural(int $value, string $singular, string $plural): string
    {
        return $value.' '.($value === 1 ? $singular : $plural);
    }

    private static function telemedicineBirthDate(mixed $patientId): ?string
    {
        $id = is_numeric($patientId) ? (int) $patientId : 0;

        if ($id <= 0) {
            return null;
        }

        if (! array_key_exists($id, self::$telemedicineBirthDates)) {
            if (count(self::$telemedicineBirthDates) >= self::MEMO_LIMIT) {
                self::$telemedicineBirthDates = [];
            }

            $birthDate = TelemedicinePatient::query()->whereKey($id)->value('birth_date');

            self::$telemedicineBirthDates[$id] = filled($birthDate) ? (string) $birthDate : null;
        }

        return self::$telemedicineBirthDates[$id];
    }
}
