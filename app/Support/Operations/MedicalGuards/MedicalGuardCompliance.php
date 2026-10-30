<?php

declare(strict_types=1);

namespace App\Support\Operations\MedicalGuards;

use App\Models\MedicalGuard;
use App\Models\User;
use App\Models\UserActivityDay;
use App\Support\UserActivity\UserActivityClock;
use App\Support\UserActivity\UserActivityState;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Cruza cada guardia con el uso real del sistema de su médico.
 *
 * Es una señal, no un control de asistencia: un médico atendiendo en sede sin
 * tocar la pantalla sale «sin actividad». Por eso los textos hablan de actividad
 * en el sistema y nunca de faltas.
 */
final class MedicalGuardCompliance
{
    public const SCHEDULED = 'programada';

    public const RUNNING = 'en_curso';

    public const ACTIVE = 'con_actividad';

    public const PARTIAL = 'parcial';

    public const NONE = 'sin_actividad';

    public const NO_DETAIL = 'sin_detalle';

    public const NO_USER = 'sin_usuario';

    /** Con la mitad de la guardia conectado se considera cumplida en el sistema. */
    private const ACTIVE_FROM_PERCENT = 50;

    /**
     * @return array{label: string, color: string}
     */
    public static function describe(string $state): array
    {
        return match ($state) {
            self::SCHEDULED => ['label' => 'Programada', 'color' => 'gray'],
            self::RUNNING => ['label' => 'En curso', 'color' => 'blue'],
            self::ACTIVE => ['label' => 'Con actividad', 'color' => 'green'],
            self::PARTIAL => ['label' => 'Actividad parcial', 'color' => 'amber'],
            self::NONE => ['label' => 'Sin actividad en el sistema', 'color' => 'red'],
            self::NO_DETAIL => ['label' => 'Sin detalle guardado', 'color' => 'gray'],
            default => ['label' => 'Médico sin usuario', 'color' => 'gray'],
        };
    }

    /**
     * @param  Collection<int, MedicalGuard>  $guards
     * @return array<int, array{state: string, percent: int|null}> Por id de guardia.
     */
    public static function evaluate(Collection $guards, ?CarbonImmutable $now = null): array
    {
        if ($guards->isEmpty()) {
            return [];
        }

        $now ??= UserActivityClock::now();
        $nowText = $now->format('Y-m-d H:i:s');
        $userByDoctor = User::query()
            ->whereIn('doctor_id', $guards->pluck('telemedicine_doctor_id')->unique()->all())
            ->pluck('id', 'doctor_id');
        $states = self::dayStates($guards, $userByDoctor->values()->all());
        $results = [];

        foreach ($guards as $guard) {
            $userId = (int) ($userByDoctor[$guard->telemedicine_doctor_id] ?? 0);

            if ($guard->starts_at->format('Y-m-d H:i:s') > $nowText) {
                $results[$guard->id] = ['state' => self::SCHEDULED, 'percent' => null];

                continue;
            }

            if ($userId === 0) {
                $results[$guard->id] = ['state' => self::NO_USER, 'percent' => null];

                continue;
            }

            $results[$guard->id] = self::evaluateGuard($guard, $states[$userId] ?? [], $nowText);
        }

        return $results;
    }

    /**
     * @param  array<string, string|null>  $days  Fecha => estados por minuto.
     * @return array{state: string, percent: int|null}
     */
    private static function evaluateGuard(MedicalGuard $guard, array $days, string $nowText): array
    {
        $running = $guard->ends_at->format('Y-m-d H:i:s') > $nowText;
        $elapsedEnd = $running ? CarbonImmutable::parse($nowText) : CarbonImmutable::parse($guard->ends_at->format('Y-m-d H:i:s'));
        $moment = CarbonImmutable::parse($guard->starts_at->format('Y-m-d H:i:s'));
        $offline = UserActivityState::Offline->value;
        $elapsed = 0;
        $present = 0;
        $missingDetail = false;

        for (; $moment->lessThan($elapsedEnd); $moment = $moment->addMinute()) {
            $elapsed++;
            $date = $moment->toDateString();
            $codes = $days[$date] ?? '';

            if (array_key_exists($date, $days) && $days[$date] === null) {
                $missingDetail = true;
            }

            $code = $codes[$moment->hour * 60 + $moment->minute] ?? $offline;

            if ($code !== $offline) {
                $present++;
            }
        }

        if ($missingDetail && $present === 0) {
            return ['state' => self::NO_DETAIL, 'percent' => null];
        }

        $percent = $elapsed > 0 ? (int) round($present * 100 / $elapsed) : 0;

        if ($running) {
            return ['state' => self::RUNNING, 'percent' => $percent];
        }

        return ['state' => match (true) {
            $percent >= self::ACTIVE_FROM_PERCENT => self::ACTIVE,
            $present > 0 => self::PARTIAL,
            default => self::NONE,
        }, 'percent' => $percent];
    }

    /**
     * @param  Collection<int, MedicalGuard>  $guards
     * @param  list<int>  $userIds
     * @return array<int, array<string, string|null>> Usuario => fecha => estados.
     */
    private static function dayStates(Collection $guards, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $byUser = [];

        UserActivityDay::query()
            ->whereIn('user_id', $userIds)
            ->whereDate('activity_date', '>=', $guards->min('guard_date')->toDateString())
            ->whereDate('activity_date', '<=', $guards->max('ends_at')->toDateString())
            ->get(['user_id', 'activity_date', 'minute_states'])
            ->each(static function (UserActivityDay $day) use (&$byUser): void {
                $byUser[(int) $day->user_id][$day->activity_date->toDateString()] = $day->minute_states;
            });

        return $byUser;
    }
}
