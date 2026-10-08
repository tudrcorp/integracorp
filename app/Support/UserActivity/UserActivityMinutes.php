<?php

declare(strict_types=1);

namespace App\Support\UserActivity;

/**
 * Convierte los minutos marcados de un día en las cifras del resumen y en los
 * tramos de la barra del día.
 */
final class UserActivityMinutes
{
    /**
     * @param  array<int, string>  $minutes  Minuto del día => código de estado.
     * @return array{online: int, active: int, idle: int, background: int, first: int|null, last: int|null, hourly: list<int>, states: string}
     */
    public static function summarize(array $minutes): array
    {
        $active = 0;
        $idle = 0;
        $background = 0;
        $hourly = array_fill(0, 24, 0);
        $states = str_repeat(UserActivityState::Offline->value, 1440);

        foreach ($minutes as $minute => $code) {
            $minute = (int) $minute;

            if ($minute < 0 || $minute > 1439) {
                continue;
            }

            $state = UserActivityState::fromCode($code);

            match ($state) {
                UserActivityState::Active => $active++,
                UserActivityState::Idle => $idle++,
                UserActivityState::Background => $background++,
                UserActivityState::Offline => null,
            };

            if ($state === UserActivityState::Active) {
                $hourly[intdiv($minute, 60)]++;
            }

            if ($state !== UserActivityState::Offline) {
                $states[$minute] = $state->value;
            }
        }

        $marked = array_keys(array_filter($minutes, static fn (string $code): bool => UserActivityState::fromCode($code) !== UserActivityState::Offline));

        return [
            'online' => $active + $idle + $background,
            'active' => $active,
            'idle' => $idle,
            'background' => $background,
            'first' => $marked === [] ? null : (int) min($marked),
            'last' => $marked === [] ? null : (int) max($marked),
            'hourly' => array_values($hourly),
            'states' => $states,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function fromStates(?string $states): array
    {
        if ($states === null || $states === '') {
            return [];
        }

        $minutes = [];
        $length = min(strlen($states), 1440);

        for ($minute = 0; $minute < $length; $minute++) {
            if ($states[$minute] !== UserActivityState::Offline->value) {
                $minutes[$minute] = $states[$minute];
            }
        }

        return $minutes;
    }

    /**
     * Tramos continuos con el mismo estado, para dibujar la barra del día.
     *
     * @param  array<int, string>  $minutes
     * @return list<array{from: int, to: int, state: UserActivityState}>
     */
    public static function segments(array $minutes): array
    {
        $segments = [];
        $current = null;

        ksort($minutes);

        foreach ($minutes as $minute => $code) {
            $state = UserActivityState::fromCode($code);

            if ($state === UserActivityState::Offline) {
                continue;
            }

            if ($current !== null && $current['state'] === $state && $current['to'] === $minute - 1) {
                $current['to'] = (int) $minute;

                continue;
            }

            if ($current !== null) {
                $segments[] = $current;
            }

            $current = ['from' => (int) $minute, 'to' => (int) $minute, 'state' => $state];
        }

        if ($current !== null) {
            $segments[] = $current;
        }

        return $segments;
    }

    /**
     * Porcentaje del tiempo conectado en que realmente usó el sistema.
     */
    public static function usagePercent(int $active, int $online): int
    {
        return $online > 0 ? (int) round($active * 100 / $online) : 0;
    }
}
