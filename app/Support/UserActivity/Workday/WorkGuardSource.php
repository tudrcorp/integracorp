<?php

declare(strict_types=1);

namespace App\Support\UserActivity\Workday;

use App\Enums\TdgCalendarGuardShift;
use App\Models\OperationOnCallUser;
use App\Models\RrhhColaborador;
use App\Models\TdgCalendarGuardAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Guardias asignadas, de las dos fuentes que ya existen:
 *
 * - Calendario TDG (Negocios): un colaborador por turno y día; el horario de
 *   cada turno sale de `workday.guard_shifts`.
 * - Operaciones → Guardias: fecha y franja propias (`hrs_init`/`hrs_end`). Sirve
 *   para guardias con horario especial, como las de los médicos.
 *
 * El colaborador se liga a su usuario por `rrhh_colaboradors.user_id` y, si no
 * lo tiene, por su correo corporativo (o el correo de la guardia).
 */
final class WorkGuardSource
{
    /** Estados de Operaciones → Guardias que no son una guardia vigente. */
    private const VOID_STATUSES = ['ANULADA', 'ANULADO', 'CANCELADA', 'CANCELADO', 'ELIMINADA', 'INACTIVA', 'INACTIVO'];

    /**
     * Ventanas de guardia por usuario y fecha en que empiezan. `to` pasa de 1440
     * cuando la guardia termina al día siguiente.
     *
     * @param  Collection<int, User>  $users
     * @return array<int, array<string, list<array{from: int, to: int, label: string}>>>
     */
    public static function windows(Collection $users, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if ($users->isEmpty()) {
            return [];
        }

        $byEmail = [];

        foreach ($users as $user) {
            $email = mb_strtolower(trim((string) $user->email));

            if ($email !== '') {
                $byEmail[$email] = (int) $user->id;
            }
        }

        $windows = [];
        $add = static function (?int $userId, string $date, ?array $window, string $label) use (&$windows): void {
            if ($userId === null || $window === null) {
                return;
            }

            $windows[$userId][$date][] = [...$window, 'label' => $label];
        };

        foreach (self::calendarAssignments($from, $to) as $assignment) {
            $shift = (string) $assignment->getRawOriginal('guard_shift');
            $date = $assignment->calendarDay?->calendar_date?->toDateString();

            if ($date === null) {
                continue;
            }

            $add(
                self::userFor($assignment->colaborador, null, $users, $byEmail),
                $date,
                WorkdayConfig::shiftWindow($shift),
                TdgCalendarGuardShift::tryFrom($shift)?->shortLabel() ?? 'Guardia',
            );
        }

        foreach (self::operationGuards($from, $to) as $guard) {
            $date = self::parseOperationDate((string) $guard->date_OnCall);

            if ($date === null || in_array(mb_strtoupper(trim((string) $guard->status)), self::VOID_STATUSES, true)) {
                continue;
            }

            $add(
                self::userFor($guard->rrhh_colaborador, (string) $guard->email, $users, $byEmail),
                $date,
                WorkdayConfig::window(WorkdayConfig::parse((string) $guard->hrs_init), WorkdayConfig::parse((string) $guard->hrs_end)),
                'Guardia de Operaciones',
            );
        }

        return $windows;
    }

    /**
     * «15/07/2026» (formato del formulario) o «2026-07-15».
     */
    public static function parseOperationDate(string $value): ?string
    {
        $value = trim($value);

        foreach (['d/m/Y', 'Y-m-d'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $value);
            } catch (Throwable) {
                continue;
            }

            if ($date !== false && $date->format($format) === $value) {
                return $date->toDateString();
            }
        }

        return null;
    }

    /**
     * @return Collection<int, TdgCalendarGuardAssignment>
     */
    private static function calendarAssignments(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        try {
            return TdgCalendarGuardAssignment::query()
                ->whereHas('calendarDay', static fn ($query) => $query->whereBetween('calendar_date', [$from->toDateString(), $to->toDateString()]))
                ->with(['calendarDay:id,calendar_date', 'colaborador:id,user_id,emailCorporativo'])
                ->get(['id', 'tdg_calendar_day_id', 'guard_shift', 'rrhh_colaborador_id']);
        } catch (Throwable $exception) {
            Log::warning('Jornada: no se pudieron leer las guardias del Calendario TDG.', ['message' => $exception->getMessage()]);

            return new Collection;
        }
    }

    /**
     * @return Collection<int, OperationOnCallUser>
     */
    private static function operationGuards(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $dates = [];

        for ($day = $from->startOfDay(); $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
            $dates[] = $day->format('d/m/Y');
            $dates[] = $day->toDateString();
        }

        try {
            return OperationOnCallUser::query()
                ->whereIn('date_OnCall', $dates)
                ->with('rrhh_colaborador:id,user_id,emailCorporativo')
                ->get(['id', 'rrhh_colaborador_id', 'email', 'hrs_init', 'hrs_end', 'date_OnCall', 'status']);
        } catch (Throwable $exception) {
            Log::warning('Jornada: no se pudieron leer las guardias de Operaciones.', ['message' => $exception->getMessage()]);

            return new Collection;
        }
    }

    /**
     * @param  Collection<int, User>  $users
     * @param  array<string, int>  $byEmail
     */
    private static function userFor(?RrhhColaborador $colaborador, ?string $email, Collection $users, array $byEmail): ?int
    {
        $userId = (int) ($colaborador?->user_id ?? 0);

        if ($userId > 0 && $users->has($userId)) {
            return $userId;
        }

        foreach ([$colaborador?->emailCorporativo, $email] as $candidate) {
            $candidate = mb_strtolower(trim((string) $candidate));

            if ($candidate !== '' && isset($byEmail[$candidate])) {
                return $byEmail[$candidate];
            }
        }

        return null;
    }
}
