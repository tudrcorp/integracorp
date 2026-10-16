<?php

declare(strict_types=1);

namespace App\Support\UserActivity\Workday;

/**
 * La jornada que una persona debía cumplir un día concreto.
 *
 * - `workStart`/`workEnd`: franja laboral en minutos del día (NULL = no tiene).
 * - `guards`: tramos de guardia que caen en este día, incluida la cola de una
 *   guardia que empezó ayer (`owner` = la fecha en que empezó; esa cola cuenta
 *   para ayer, no para hoy).
 * - `spill`: la parte de una guardia que empezó hoy y termina mañana, en minutos
 *   de mañana. Cuenta para hoy.
 */
final readonly class WorkDayPlan
{
    /**
     * @param  list<array{from: int, to: int, owner: string, label: string, overtime: bool}>  $guards
     * @param  list<array{from: int, to: int, label: string, overtime: bool}>  $spill
     */
    public function __construct(
        public string $date,
        public WorkDayKind $kind,
        public WorkScheduleType $schedule,
        public ?string $note,
        public ?int $workStart,
        public ?int $workEnd,
        public array $guards = [],
        public array $spill = [],
    ) {}

    public function hasJornada(): bool
    {
        return $this->workStart !== null && $this->workEnd !== null && $this->workEnd > $this->workStart;
    }

    public function lunchAllowed(): bool
    {
        return $this->kind === WorkDayKind::Workday && $this->hasJornada();
    }

    public function windowMinutes(): int
    {
        return $this->hasJornada() ? (int) $this->workEnd - (int) $this->workStart : 0;
    }

    public function inWork(int $minute): bool
    {
        return $this->hasJornada() && $minute >= $this->workStart && $minute < $this->workEnd;
    }

    /**
     * Guardias que empezaron este día (no la cola de la de ayer).
     */
    public function ownsGuards(): bool
    {
        foreach ($this->guards as $guard) {
            if ($guard['owner'] === $this->date) {
                return true;
            }
        }

        return $this->spill !== [];
    }

    public function label(): string
    {
        return $this->note !== null && $this->note !== '' ? $this->kind->label().': '.$this->note : $this->kind->label();
    }
}
