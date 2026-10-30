<?php

declare(strict_types=1);

namespace App\Support\Operations\MedicalGuards;

use App\Models\MedicalGuard;
use App\Models\MedicalGuardSite;
use App\Models\TelemedicineDoctor;
use App\Models\User;
use App\Support\Telemedicine\TelemedicineMedicalTeam;
use App\Support\UserActivity\UserActivityClock;
use App\Support\UserActivity\Workday\WorkdayConfig;
use App\Support\UserActivity\Workday\WorkDayPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Único punto de escritura de las guardias médicas.
 *
 * Las guardias son abiertas: horario libre y tantos médicos por sede y día como
 * haga falta. La única regla dura es que un médico no esté en dos guardias a la
 * vez, en ninguna sede. Una carga se aplica completa o no se aplica.
 */
final class MedicalGuardScheduler
{
    /** Un mes y algo: más que esto en una carga es un error de selección. */
    private const MAX_DATES = 62;

    private const MAX_WEEKS_TO_COPY = 8;

    /**
     * Médicos de TDG activos, por nombre.
     *
     * @return array<int, string>
     */
    public static function doctorOptions(): array
    {
        return TelemedicineDoctor::query()
            ->where('managed_by', TelemedicineMedicalTeam::TDG)
            ->where('status', 'ACTIVO')
            ->orderBy('full_name')
            ->pluck('full_name', 'id')
            ->map(static fn (mixed $name): string => trim((string) $name))
            ->all();
    }

    /**
     * Horarios ya usados en la sede, del más frecuente al menos. Son los botones
     * rápidos: no hay turnos fijos que escribir en el código.
     *
     * @return array<string, string> `HH:MM|HH:MM` => etiqueta
     */
    public static function recentSchedules(int $siteId, int $limit = 5): array
    {
        $schedules = MedicalGuard::query()
            ->where('medical_guard_site_id', $siteId)
            ->latest('starts_at')
            ->limit(300)
            ->get(['id', 'starts_at', 'ends_at'])
            ->countBy(static fn (MedicalGuard $guard): string => $guard->starts_at->format('H:i').'|'.$guard->ends_at->format('H:i'))
            ->sortDesc()
            ->take($limit);

        $options = [];

        foreach ($schedules->keys() as $key) {
            [$start, $end] = explode('|', (string) $key);
            $options[(string) $key] = self::scheduleLabel($start, $end);
        }

        return $options;
    }

    public static function scheduleLabel(string $start, string $end): string
    {
        $window = WorkdayConfig::window(WorkdayConfig::parse($start), WorkdayConfig::parse($end));

        if ($window === null) {
            return '';
        }

        return UserActivityClock::formatMinute($window['from']).' – '.UserActivityClock::formatMinute($window['to'] % 1440)
            .($window['to'] > 1440 ? ' (+1 día)' : '');
    }

    /**
     * Lo que haría una carga, para enseñarlo antes de confirmar.
     *
     * @param  list<string>  $dates  `Y-m-d`
     * @return array{count: int, minutes: int, conflicts: list<string>}
     */
    public static function preview(int $doctorId, array $dates, string $start, string $end, ?int $ignoreGuardId = null): array
    {
        $slots = self::slots($dates, $start, $end);
        $minutes = 0;

        foreach ($slots as $slot) {
            $minutes += (int) $slot['starts_at']->diffInMinutes($slot['ends_at']);
        }

        return [
            'count' => count($slots),
            'minutes' => $minutes,
            'conflicts' => self::conflicts($doctorId, $slots, $ignoreGuardId),
        ];
    }

    /**
     * @param  list<int>  $doctorIds
     * @param  list<string>  $dates  `Y-m-d`
     * @return array{count: int, minutes: int, conflicts: list<string>}
     */
    public static function previewForDoctors(array $doctorIds, array $dates, string $start, string $end): array
    {
        $doctorIds = array_values(array_unique(array_map(intval(...), $doctorIds)));
        $slots = self::slots($dates, $start, $end);
        $total = ['count' => 0, 'minutes' => 0, 'conflicts' => []];

        foreach ($doctorIds as $doctorId) {
            $preview = self::preview($doctorId, $dates, $start, $end);
            $total['count'] += $preview['count'];
            $total['minutes'] += $preview['minutes'];
            $total['conflicts'] = [...$total['conflicts'], ...self::namedConflicts($doctorId, $slots, count($doctorIds) > 1)];
        }

        return $total;
    }

    /**
     * @param  list<string>  $dates  `Y-m-d`
     * @return Collection<int, MedicalGuard>
     */
    public static function create(int $siteId, int $doctorId, array $dates, string $start, string $end, ?string $notes, ?User $actor): Collection
    {
        return self::createForDoctors($siteId, [$doctorId], $dates, $start, $end, $notes, $actor);
    }

    /**
     * Varios médicos, mismos días y mismo horario. Si uno solo choca no se
     * guarda nada: el mensaje dice quién y cuándo.
     *
     * @param  list<int>  $doctorIds
     * @param  list<string>  $dates  `Y-m-d`
     * @return Collection<int, MedicalGuard>
     */
    public static function createForDoctors(int $siteId, array $doctorIds, array $dates, string $start, string $end, ?string $notes, ?User $actor): Collection
    {
        self::assertSite($siteId);

        $doctorIds = array_values(array_unique(array_map(intval(...), $doctorIds)));
        sort($doctorIds);

        if ($doctorIds === []) {
            throw new MedicalGuardException('Elige al menos un médico.');
        }

        $slots = self::slots($dates, $start, $end);

        if ($slots === []) {
            throw new MedicalGuardException('Selecciona al menos un día en el calendario.');
        }

        if (count($slots) > self::MAX_DATES) {
            throw new MedicalGuardException('Se pueden cargar hasta '.self::MAX_DATES.' días de una vez.');
        }

        $guards = DB::transaction(static function () use ($siteId, $doctorIds, $slots, $notes, $actor): Collection {
            $conflicts = [];

            foreach ($doctorIds as $doctorId) {
                self::lockDoctor($doctorId);
                $conflicts = [...$conflicts, ...self::namedConflicts($doctorId, $slots, count($doctorIds) > 1)];
            }

            if ($conflicts !== []) {
                throw new MedicalGuardException('No se cargó ninguna guardia. '.implode(' ', $conflicts));
            }

            $created = new Collection;

            foreach ($doctorIds as $doctorId) {
                foreach ($slots as $slot) {
                    $created->push(MedicalGuard::query()->create([
                        'medical_guard_site_id' => $siteId,
                        'telemedicine_doctor_id' => $doctorId,
                        'guard_date' => $slot['starts_at']->toDateString(),
                        'starts_at' => $slot['starts_at']->toDateTimeString(),
                        'ends_at' => $slot['ends_at']->toDateTimeString(),
                        'notes' => self::cleanNotes($notes),
                        'created_by_user_id' => $actor?->id,
                        'updated_by_user_id' => $actor?->id,
                    ]));
                }
            }

            return $created;
        });

        self::forgetPlans($doctorIds);

        return $guards;
    }

    public static function update(MedicalGuard $guard, int $siteId, int $doctorId, string $date, string $start, string $end, ?string $notes, ?User $actor): MedicalGuard
    {
        self::assertSite($siteId);

        $previousDoctorId = (int) $guard->telemedicine_doctor_id;
        $slots = self::slots([$date], $start, $end);

        if ($slots === []) {
            throw new MedicalGuardException('Indica la fecha de la guardia.');
        }

        DB::transaction(static function () use ($guard, $siteId, $doctorId, $slots, $notes, $actor): void {
            self::lockDoctor($doctorId);

            $conflicts = self::conflicts($doctorId, $slots, (int) $guard->id);

            if ($conflicts !== []) {
                throw new MedicalGuardException(implode(' ', $conflicts));
            }

            $guard->update([
                'medical_guard_site_id' => $siteId,
                'telemedicine_doctor_id' => $doctorId,
                'guard_date' => $slots[0]['starts_at']->toDateString(),
                'starts_at' => $slots[0]['starts_at']->toDateTimeString(),
                'ends_at' => $slots[0]['ends_at']->toDateTimeString(),
                'notes' => self::cleanNotes($notes),
                'updated_by_user_id' => $actor?->id,
            ]);
        });

        self::forgetPlans([$previousDoctorId, $doctorId]);

        return $guard;
    }

    public static function cancel(MedicalGuard $guard, ?User $actor): void
    {
        $guard->forceFill(['deleted_by_user_id' => $actor?->id])->saveQuietly();
        $guard->delete();

        self::forgetPlans([(int) $guard->telemedicine_doctor_id]);
    }

    /**
     * Repite las guardias de la semana de `$sourceDate` en las semanas siguientes.
     * Lo que choca se salta y se informa: la copia no debe frenarse por un médico.
     *
     * @return array{created: int, skipped: list<string>}
     */
    public static function copyWeek(int $siteId, string $sourceDate, int $weeks, ?User $actor): array
    {
        self::assertSite($siteId);

        if ($weeks < 1 || $weeks > self::MAX_WEEKS_TO_COPY) {
            throw new MedicalGuardException('Se puede copiar a entre 1 y '.self::MAX_WEEKS_TO_COPY.' semanas.');
        }

        $monday = self::parseDate($sourceDate)?->startOfWeek();

        if ($monday === null) {
            throw new MedicalGuardException('La semana de origen no es válida.');
        }

        $source = MedicalGuard::query()
            ->where('medical_guard_site_id', $siteId)
            ->whereBetween('guard_date', [$monday->toDateString(), $monday->addDays(6)->toDateString()])
            ->with('doctor:id,full_name,status,managed_by')
            ->orderBy('starts_at')
            ->get();

        if ($source->isEmpty()) {
            throw new MedicalGuardException('Esa semana no tiene guardias en esta sede.');
        }

        $created = 0;
        $skipped = [];
        $doctorIds = [];

        foreach ($source as $guard) {
            $doctorName = trim((string) $guard->doctor?->full_name);

            for ($week = 1; $week <= $weeks; $week++) {
                $startsAt = CarbonImmutable::parse($guard->starts_at->format('Y-m-d H:i:s'))->addWeeks($week);
                $endsAt = CarbonImmutable::parse($guard->ends_at->format('Y-m-d H:i:s'))->addWeeks($week);
                $slot = ['starts_at' => $startsAt, 'ends_at' => $endsAt];

                try {
                    DB::transaction(static function () use ($guard, $slot, $siteId, $actor): void {
                        self::lockDoctor((int) $guard->telemedicine_doctor_id);

                        if (self::conflicts((int) $guard->telemedicine_doctor_id, [$slot]) !== []) {
                            throw new MedicalGuardException('choca con otra guardia');
                        }

                        MedicalGuard::query()->create([
                            'medical_guard_site_id' => $siteId,
                            'telemedicine_doctor_id' => $guard->telemedicine_doctor_id,
                            'guard_date' => $slot['starts_at']->toDateString(),
                            'starts_at' => $slot['starts_at']->toDateTimeString(),
                            'ends_at' => $slot['ends_at']->toDateTimeString(),
                            'notes' => $guard->notes,
                            'created_by_user_id' => $actor?->id,
                            'updated_by_user_id' => $actor?->id,
                        ]);
                    });

                    $created++;
                    $doctorIds[] = (int) $guard->telemedicine_doctor_id;
                } catch (MedicalGuardException $exception) {
                    $skipped[] = $doctorName.' el '.$startsAt->format('d/m').': '.$exception->getMessage();
                }
            }
        }

        self::forgetPlans(array_values(array_unique($doctorIds)));

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Guardias de un rango, con sede y médico.
     *
     * @return Collection<int, MedicalGuard>
     */
    public static function between(CarbonImmutable $from, CarbonImmutable $to, ?int $siteId = null): Collection
    {
        return MedicalGuard::query()
            ->whereBetween('guard_date', [$from->toDateString(), $to->toDateString()])
            ->when($siteId !== null, static fn ($query) => $query->where('medical_guard_site_id', $siteId))
            ->with(['site:id,name', 'doctor:id,full_name'])
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * @param  list<string>  $dates
     * @return list<array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}>
     */
    private static function slots(array $dates, string $start, string $end): array
    {
        $window = WorkdayConfig::window(WorkdayConfig::parse(substr($start, 0, 5)), WorkdayConfig::parse(substr($end, 0, 5)));

        if ($window === null) {
            throw new MedicalGuardException('Indica la hora de inicio y la de fin en formato de 24 horas.');
        }

        $slots = [];

        foreach (array_values(array_unique($dates)) as $date) {
            $day = self::parseDate((string) $date);

            if ($day === null) {
                throw new MedicalGuardException('Una de las fechas seleccionadas no es válida.');
            }

            $slots[] = ['starts_at' => $day->addMinutes($window['from']), 'ends_at' => $day->addMinutes($window['to'])];
        }

        usort($slots, static fn (array $a, array $b): int => $a['starts_at'] <=> $b['starts_at']);

        return $slots;
    }

    /**
     * @param  list<array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}>  $slots
     * @return list<string>
     */
    private static function conflicts(int $doctorId, array $slots, ?int $ignoreGuardId = null): array
    {
        if ($slots === []) {
            return [];
        }

        $existing = MedicalGuard::query()
            ->where('telemedicine_doctor_id', $doctorId)
            ->where('starts_at', '<', $slots[array_key_last($slots)]['ends_at']->toDateTimeString())
            ->where('ends_at', '>', $slots[0]['starts_at']->toDateTimeString())
            ->when($ignoreGuardId !== null, static fn ($query) => $query->whereKeyNot($ignoreGuardId))
            ->with('site:id,name')
            ->get();

        $messages = [];

        foreach ($slots as $slot) {
            foreach ($existing as $guard) {
                if ($guard->starts_at->format('Y-m-d H:i:s') < $slot['ends_at']->toDateTimeString()
                    && $guard->ends_at->format('Y-m-d H:i:s') > $slot['starts_at']->toDateTimeString()) {
                    $messages[] = 'El '.$slot['starts_at']->format('d/m').' choca con su guardia en '.$guard->site?->name.' ('.$guard->starts_at->format('d/m').', '.$guard->scheduleLabel().').';

                    break;
                }
            }
        }

        return $messages;
    }

    /**
     * Choques con el nombre del médico delante cuando la carga es de varios.
     *
     * @param  list<array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}>  $slots
     * @return list<string>
     */
    private static function namedConflicts(int $doctorId, array $slots, bool $withName): array
    {
        $conflicts = self::conflicts($doctorId, $slots);

        if (! $withName || $conflicts === []) {
            return $conflicts;
        }

        $name = trim((string) TelemedicineDoctor::query()->whereKey($doctorId)->value('full_name'));

        return array_map(static fn (string $conflict): string => $name.': '.$conflict, $conflicts);
    }

    private static function assertSite(int $siteId): void
    {
        $exists = MedicalGuardSite::query()->whereKey($siteId)->where('is_active', true)->exists();

        if (! $exists) {
            throw new MedicalGuardException('La sede no existe o está inactiva.');
        }
    }

    /**
     * Serializa las cargas del mismo médico: sin esto, dos personas guardando a
     * la vez podrían pasar ambas la revisión de choques. También valida que sea
     * un médico de TDG activo.
     */
    private static function lockDoctor(int $doctorId): void
    {
        $doctor = TelemedicineDoctor::query()->whereKey($doctorId)->lockForUpdate()->first(['id', 'managed_by', 'status']);

        if ($doctor === null || $doctor->managed_by !== TelemedicineMedicalTeam::TDG || $doctor->status !== 'ACTIVO') {
            throw new MedicalGuardException('Solo se pueden cargar guardias a médicos de TDG activos.');
        }
    }

    private static function parseDate(string $date): ?CarbonImmutable
    {
        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date);
        } catch (Throwable) {
            return null;
        }

        return $parsed !== false && $parsed->toDateString() === $date ? $parsed : null;
    }

    private static function cleanNotes(?string $notes): ?string
    {
        $notes = trim((string) $notes);

        return $notes === '' ? null : mb_substr($notes, 0, 255);
    }

    /**
     * @param  list<int>  $doctorIds
     */
    private static function forgetPlans(array $doctorIds): void
    {
        if ($doctorIds === []) {
            return;
        }

        User::query()
            ->whereIn('doctor_id', $doctorIds)
            ->pluck('id')
            ->each(static fn (mixed $userId) => WorkDayPlanner::forget((int) $userId));
    }
}
