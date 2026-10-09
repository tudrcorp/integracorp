<?php

declare(strict_types=1);

namespace App\Support\UserActivity;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Minutos y días de actividad siempre en la zona horaria del sistema
 * (America/Caracas): «las 8:00» del reporte son las 8:00 de la oficina.
 */
final class UserActivityClock
{
    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone());
    }

    public static function timezone(): string
    {
        return (string) config('app.timezone', 'America/Caracas');
    }

    public static function dayKey(CarbonInterface $moment): string
    {
        return $moment->copy()->setTimezone(self::timezone())->format('Ymd');
    }

    public static function minuteOfDay(CarbonInterface $moment): int
    {
        $local = $moment->copy()->setTimezone(self::timezone());

        return $local->hour * 60 + $local->minute;
    }

    /**
     * Agrupa minutos absolutos (segundos Unix / 60) por día local.
     *
     * @param  list<int>  $epochMinutes
     * @return array<string, list<int>> Día `Ymd` => minutos del día.
     */
    public static function splitByDay(array $epochMinutes): array
    {
        $byDay = [];

        foreach ($epochMinutes as $epochMinute) {
            $moment = CarbonImmutable::createFromTimestamp($epochMinute * 60, self::timezone());
            $byDay[self::dayKey($moment)][] = self::minuteOfDay($moment);
        }

        return $byDay;
    }

    public static function formatMinute(?int $minuteOfDay): string
    {
        if ($minuteOfDay === null) {
            return '—';
        }

        $minuteOfDay = max(0, min(1439, $minuteOfDay));

        return sprintf('%d:%02d %s', ((intdiv($minuteOfDay, 60) + 11) % 12) + 1, $minuteOfDay % 60, $minuteOfDay < 720 ? 'a. m.' : 'p. m.');
    }

    /**
     * «3 h 25 min», «45 min», «0 min».
     */
    public static function formatDuration(int $minutes): string
    {
        $minutes = max(0, $minutes);
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return match (true) {
            $hours === 0 => $rest.' min',
            $rest === 0 => $hours.' h',
            default => $hours.' h '.$rest.' min',
        };
    }
}
