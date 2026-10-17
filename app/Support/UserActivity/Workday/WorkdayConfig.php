<?php

declare(strict_types=1);

namespace App\Support\UserActivity\Workday;

use App\Support\UserActivity\UserActivityClock;
use App\Support\UserActivity\UserActivityTracker;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Lectura de `live-presence.activity.workday` con valores seguros: una hora mal
 * escrita en la configuración cae al valor por defecto, nunca rompe el monitor.
 */
final class WorkdayConfig
{
    private const SCHEMA_CACHE_KEY = 'ua:workday-schema-ready:v2';

    /**
     * Respaldo si la configuración cacheada aún no trae el bloque `workday`
     * (desplegar sin `config:cache`): la jornada nunca arranca en una fecha
     * anterior a la acordada ni pierde el horario de los turnos.
     */
    private const DEFAULT_SINCE = '2026-10-16';

    /** @var array<string, array{start: string, end: string}> */
    private const DEFAULT_SHIFTS = [
        'proveedores' => ['start' => '08:00', 'end' => '17:00'],
        'ils_capitado' => ['start' => '08:00', 'end' => '17:00'],
        'nocturna' => ['start' => '17:00', 'end' => '08:00'],
    ];

    private static ?bool $schemaReady = null;

    public static function enabled(): bool
    {
        return UserActivityTracker::enabled()
            && (bool) config('live-presence.activity.workday.enabled', true)
            && self::schemaReady();
    }

    /**
     * La jornada aplica desde `since`; los días anteriores conservan el cálculo de antes.
     */
    public static function appliesTo(CarbonImmutable $date): bool
    {
        if (! self::enabled()) {
            return false;
        }

        $since = self::since();

        return $since === null || $date->setTimezone(UserActivityClock::timezone())->startOfDay()->greaterThanOrEqualTo($since);
    }

    public static function since(): ?CarbonImmutable
    {
        $value = (string) config('live-presence.activity.workday.since', self::DEFAULT_SINCE);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $value, UserActivityClock::timezone())->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    public static function workStart(): int
    {
        return self::minute('start', 8 * 60);
    }

    public static function workEnd(): int
    {
        $end = self::minute('end', 17 * 60);

        return $end > self::workStart() ? $end : 17 * 60;
    }

    public static function lunchMinutes(): int
    {
        return max(15, min(180, (int) config('live-presence.activity.workday.lunch_minutes', 60)));
    }

    /**
     * El almuerzo se declara a cualquier hora de la jornada: hay quien sale tarde por carga de trabajo.
     */
    public static function lunchEarliest(): int
    {
        return self::workStart();
    }

    public static function lunchLatest(): int
    {
        return self::workEnd();
    }

    public static function meetingPromptSnoozeMinutes(): int
    {
        return max(1, (int) config('live-presence.activity.workday.meeting_prompt_snooze_minutes', 30));
    }

    public static function meetingWarningMinutes(): int
    {
        return max(15, (int) config('live-presence.activity.workday.meeting_warning_minutes', 180));
    }

    public static function meetingAutoCloseMinutes(): int
    {
        return max(0, (int) config('live-presence.activity.workday.meeting_auto_close_minutes', 60));
    }

    public static function meetingCloseGraceMinutes(): int
    {
        return max(0, (int) config('live-presence.activity.workday.meeting_close_grace_minutes', 60));
    }

    public static function promptGraceMinutes(): int
    {
        return max(0, (int) config('live-presence.activity.workday.lunch_prompt_grace_minutes', 3));
    }

    public static function promptSnoozeMinutes(): int
    {
        return max(1, (int) config('live-presence.activity.workday.lunch_prompt_snooze_minutes', 10));
    }

    /**
     * @return list<int> Días ISO laborables (1 = lunes).
     */
    public static function weekdays(): array
    {
        $days = array_values(array_filter(
            array_map('intval', (array) config('live-presence.activity.workday.weekdays', [1, 2, 3, 4, 5])),
            static fn (int $day): bool => $day >= 1 && $day <= 7,
        ));

        return $days === [] ? [1, 2, 3, 4, 5] : $days;
    }

    public static function restAfterOvernightGuard(): bool
    {
        return (bool) config('live-presence.activity.workday.rest_after_overnight_guard', true);
    }

    /**
     * Horario de un turno del Calendario TDG; el fin pasa de 1440 si cruza la medianoche.
     *
     * @return array{from: int, to: int}|null
     */
    public static function shiftWindow(string $shift): ?array
    {
        $hours = config('live-presence.activity.workday.guard_shifts.'.$shift, self::DEFAULT_SHIFTS[$shift] ?? null);

        if (! is_array($hours)) {
            return null;
        }

        return self::window(self::parse((string) ($hours['start'] ?? '')), self::parse((string) ($hours['end'] ?? '')));
    }

    /**
     * @return array{from: int, to: int}|null
     */
    public static function window(?int $start, ?int $end): ?array
    {
        if ($start === null || $end === null) {
            return null;
        }

        return ['from' => $start, 'to' => $end > $start ? $end : $end + 1440];
    }

    /**
     * «08:00» → 480. Null si no es una hora válida de 24 h.
     */
    public static function parse(string $value): ?int
    {
        if (! preg_match('/^\s*(\d{1,2}):(\d{2})\s*$/', $value, $match)) {
            return null;
        }

        $hour = (int) $match[1];
        $minute = (int) $match[2];

        if ($hour > 23 || $minute > 59) {
            return null;
        }

        return $hour * 60 + $minute;
    }

    /**
     * Las pruebas crean el esquema en memoria después del primer chequeo.
     */
    public static function flushSchemaCache(): void
    {
        self::$schemaReady = null;

        try {
            Cache::forget(self::SCHEMA_CACHE_KEY);
        } catch (Throwable) {
            // Sin caché no hay nada que olvidar.
        }
    }

    private static function minute(string $key, int $default): int
    {
        return self::parse((string) config('live-presence.activity.workday.'.$key, '')) ?? $default;
    }

    /**
     * Antes de aplicar la migración el monitor sigue funcionando con el cálculo de antes.
     */
    private static function schemaReady(): bool
    {
        if (self::$schemaReady !== null) {
            return self::$schemaReady;
        }

        try {
            /** Se guarda solo el «sí»: así aplicar la migración se nota en el acto. */
            if (Cache::get(self::SCHEMA_CACHE_KEY) === true) {
                return self::$schemaReady = true;
            }

            self::$schemaReady = Schema::hasTable('user_work_breaks')
                && Schema::hasTable('user_work_meetings')
                && Schema::hasColumn('user_activity_days', 'work_meeting_minutes');

            if (self::$schemaReady) {
                Cache::put(self::SCHEMA_CACHE_KEY, true, 3600);
            }

            return self::$schemaReady;
        } catch (Throwable) {
            return false;
        }
    }
}
