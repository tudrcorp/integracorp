<?php

declare(strict_types=1);

namespace App\Support\UserActivity\Workday;

use Carbon\CarbonImmutable;

/**
 * Feriados que no tienen jornada: los federales de EE. UU. con su día observado
 * (si cae sábado se libra el viernes; si cae domingo, el lunes) más las fechas
 * extra de `live-presence.activity.workday.extra_holidays`.
 *
 * Se calculan, no se guardan: no hay que cargar un catálogo cada año.
 */
final class WorkHolidays
{
    /** @var array<int, array<string, string>> */
    private static array $cache = [];

    public static function nameFor(CarbonImmutable $date): ?string
    {
        $key = $date->toDateString();

        if (in_array($key, (array) config('live-presence.activity.workday.extra_holidays', []), true)) {
            return 'Feriado';
        }

        if (! (bool) config('live-presence.activity.workday.us_federal_holidays', true)) {
            return null;
        }

        /** El 1 de enero observado puede caer el 31 de diciembre del año anterior. */
        return self::forYear($date->year)[$key] ?? self::forYear($date->year + 1)[$key] ?? null;
    }

    /**
     * Día observado => nombre.
     *
     * @return array<string, string>
     */
    public static function forYear(int $year): array
    {
        if (isset(self::$cache[$year])) {
            return self::$cache[$year];
        }

        $fixed = static fn (int $month, int $day): CarbonImmutable => self::observed(CarbonImmutable::create($year, $month, $day, 0, 0, 0, 'UTC'));

        $holidays = [
            [$fixed(1, 1), 'Año Nuevo'],
            [self::nthWeekday($year, 1, CarbonImmutable::MONDAY, 3), 'Día de Martin Luther King Jr.'],
            [self::nthWeekday($year, 2, CarbonImmutable::MONDAY, 3), 'Día de los Presidentes'],
            [self::lastWeekday($year, 5, CarbonImmutable::MONDAY), 'Día de los Caídos (Memorial Day)'],
            [$fixed(6, 19), 'Juneteenth'],
            [$fixed(7, 4), 'Día de la Independencia de EE. UU.'],
            [self::nthWeekday($year, 9, CarbonImmutable::MONDAY, 1), 'Día del Trabajo (Labor Day)'],
            [self::nthWeekday($year, 10, CarbonImmutable::MONDAY, 2), 'Día de la Raza (Columbus Day)'],
            [$fixed(11, 11), 'Día de los Veteranos'],
            [self::nthWeekday($year, 11, CarbonImmutable::THURSDAY, 4), 'Día de Acción de Gracias'],
            [$fixed(12, 25), 'Navidad'],
        ];

        $byDate = [];

        foreach ($holidays as [$date, $name]) {
            $byDate[$date->toDateString()] = $name;
        }

        return self::$cache[$year] = $byDate;
    }

    private static function observed(CarbonImmutable $date): CarbonImmutable
    {
        return match ($date->dayOfWeek) {
            CarbonImmutable::SATURDAY => $date->subDay(),
            CarbonImmutable::SUNDAY => $date->addDay(),
            default => $date,
        };
    }

    private static function nthWeekday(int $year, int $month, int $weekday, int $nth): CarbonImmutable
    {
        $first = CarbonImmutable::create($year, $month, 1, 0, 0, 0, 'UTC');
        $offset = ($weekday - $first->dayOfWeek + 7) % 7;

        return $first->addDays($offset + 7 * ($nth - 1));
    }

    private static function lastWeekday(int $year, int $month, int $weekday): CarbonImmutable
    {
        $last = CarbonImmutable::create($year, $month, 1, 0, 0, 0, 'UTC')->endOfMonth()->startOfDay();
        $offset = ($last->dayOfWeek - $weekday + 7) % 7;

        return $last->subDays($offset);
    }
}
