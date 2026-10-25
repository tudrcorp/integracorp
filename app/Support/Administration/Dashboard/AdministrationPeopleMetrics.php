<?php

declare(strict_types=1);

namespace App\Support\Administration\Dashboard;

use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Metrics\Corporate\CorporateMetrics;
use App\Support\Operations\Dashboard\OperationsDashboardPeriod as Period;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * RRHH en el Escritorio de Administración: plantilla, costo mensual por sueldo,
 * departamentos, antigüedad, perfil y próximas fechas (cumpleaños y
 * aniversarios laborales).
 *
 * - Las fechas de ingreso y nacimiento se guardan como texto dd/mm/aaaa; aquí
 *   se interpretan una vez por colaborador (son pocos) y se cachean.
 * - El costo mensual es la suma del sueldo registrado. Muchos colaboradores aún
 *   no lo tienen cargado: por eso se informa cuántos sí.
 * - Los montos solo los ve quien tiene el permiso {@see BusinessFilamentActionPermissionRegistry::VIEW_HR_AMOUNTS}.
 */
final class AdministrationPeopleMetrics
{
    public const ACTIVE = 'activo';

    /** Tramos de antigüedad (años). */
    public const SENIORITY = [
        'Menos de 1 año' => [0, 1],
        '1 a 2 años' => [1, 2],
        '2 a 5 años' => [2, 5],
        'Más de 5 años' => [5, null],
    ];

    /** @var Collection<int, array<string, mixed>>|null plantilla memorizada por proceso */
    private static ?Collection $people = null;

    public static function canSeeAmounts(): bool
    {
        try {
            return BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::VIEW_HR_AMOUNTS);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array{active: int, with_salary: int, payroll: float, average_salary: float|null, average_age: float|null, average_seniority: float|null, hires: int, previous_hires: int}
     */
    public static function summary(Period $period): array
    {
        return CorporateMetrics::remember('people_summary', $period, static function () use ($period): array {
            $people = self::people();
            $withSalary = $people->filter(static fn (array $person): bool => $person['salary'] > 0);
            $ages = $people->pluck('age')->filter(static fn (?int $age): bool => $age !== null);
            $seniority = $people->pluck('seniority')->filter(static fn (?float $years): bool => $years !== null);

            return [
                'active' => $people->count(),
                'with_salary' => $withSalary->count(),
                'payroll' => round((float) $withSalary->sum('salary'), 2),
                'average_salary' => $withSalary->isNotEmpty() ? round((float) $withSalary->avg('salary'), 2) : null,
                'average_age' => $ages->isNotEmpty() ? round((float) $ages->avg(), 1) : null,
                'average_seniority' => $seniority->isNotEmpty() ? round((float) $seniority->avg(), 1) : null,
                'hires' => self::hiresBetween($people, $period->from, $period->to),
                'previous_hires' => self::hiresBetween($people, $period->previousFrom, $period->previousTo),
            ];
        });
    }

    /**
     * Plantilla por departamento: personas y costo mensual.
     *
     * @return list<array{label: string, people: int, payroll: float}>
     */
    public static function byDepartment(): array
    {
        return CorporateMetrics::remember('people_by_department_'.CarbonImmutable::today()->toDateString(), null, static function (): array {
            return self::people()
                ->groupBy('department')
                ->map(static fn (Collection $group, string $department): array => [
                    'label' => $department,
                    'people' => $group->count(),
                    'payroll' => round((float) $group->sum('salary'), 2),
                ])
                ->sortByDesc('people')
                ->values()
                ->all();
        });
    }

    /**
     * @return array<string, int>
     */
    public static function bySeniority(): array
    {
        return CorporateMetrics::remember('people_by_seniority_'.CarbonImmutable::today()->toDateString(), null, static function (): array {
            $counts = array_fill_keys(array_keys(self::SENIORITY), 0);
            $unknown = 0;

            foreach (self::people() as $person) {
                if ($person['seniority'] === null) {
                    $unknown++;

                    continue;
                }

                foreach (self::SENIORITY as $label => [$min, $max]) {
                    if ($person['seniority'] >= $min && ($max === null || $person['seniority'] < $max)) {
                        $counts[$label]++;

                        break;
                    }
                }
            }

            if ($unknown > 0) {
                $counts['Sin fecha de ingreso'] = $unknown;
            }

            return array_filter($counts, static fn (int $count): bool => $count > 0);
        });
    }

    /**
     * @return array<string, int>
     */
    public static function bySex(): array
    {
        return CorporateMetrics::remember('people_by_sex_'.CarbonImmutable::today()->toDateString(), null, static function (): array {
            $counts = self::people()->countBy('sex')->all();
            arsort($counts);

            return $counts;
        });
    }

    /**
     * Cumpleaños y aniversarios laborales de los próximos días.
     *
     * @return list<array{name: string, kind: string, date: string, days: int, detail: string}>
     */
    public static function upcomingDates(int $days = 30): array
    {
        return CorporateMetrics::remember('people_upcoming_'.$days.'_'.CarbonImmutable::today()->toDateString(), null, static function () use ($days): array {
            $today = CarbonImmutable::today();
            $events = [];

            foreach (self::people() as $person) {
                foreach (['birthday' => $person['birth'], 'anniversary' => $person['hired']] as $kind => $date) {
                    if ($date === null) {
                        continue;
                    }

                    $next = self::nextOccurrence($date, $today);
                    $inDays = (int) $today->diffInDays($next);

                    if ($inDays > $days) {
                        continue;
                    }

                    $years = $next->year - $date->year;

                    if ($kind === 'anniversary' && $years < 1) {
                        continue;
                    }

                    $events[] = [
                        'name' => $person['name'],
                        'kind' => $kind === 'birthday' ? 'Cumpleaños' : 'Aniversario laboral',
                        'date' => $next->format('d/m'),
                        'days' => $inDays,
                        'detail' => $kind === 'birthday'
                            ? 'Cumple '.$years.' años'
                            : $years.' '.($years === 1 ? 'año' : 'años').' en la empresa',
                    ];
                }
            }

            usort($events, static fn (array $a, array $b): int => $a['days'] <=> $b['days']);

            return $events;
        });
    }

    /**
     * Colaboradores activos con sus fechas ya interpretadas.
     *
     * @return Collection<int, array{name: string, department: string, sex: string, salary: float, birth: ?CarbonImmutable, hired: ?CarbonImmutable, age: ?int, seniority: ?float}>
     */
    public static function people(): Collection
    {
        if (self::$people !== null) {
            return self::$people;
        }

        $today = CarbonImmutable::today();
        $departments = DB::table('rrhh_departamentos')->pluck('description', 'id');

        return self::$people = DB::table('rrhh_colaboradors')
            ->whereRaw('LOWER(TRIM(status)) = ?', [self::ACTIVE])
            ->get(['fullName', 'departmento_id', 'sexo', 'sueldo', 'fechaNacimiento', 'birth_date', 'fechaIngreso'])
            ->map(static function (object $row) use ($today, $departments): array {
                $birth = self::parseDate($row->birth_date) ?? self::parseDate($row->fechaNacimiento);
                $hired = self::parseDate($row->fechaIngreso);
                $department = $departments[(int) $row->departmento_id] ?? null;

                return [
                    'name' => (string) $row->fullName,
                    'department' => filled($department) ? ucfirst(mb_strtolower((string) $department)) : 'Sin departamento',
                    'sex' => filled($row->sexo) ? ucfirst(mb_strtolower(trim((string) $row->sexo))) : 'Sin dato',
                    'salary' => round((float) ($row->sueldo ?? 0), 2),
                    'birth' => $birth,
                    'hired' => $hired,
                    'age' => $birth !== null && $birth->lessThan($today) ? (int) $birth->diffInYears($today) : null,
                    'seniority' => $hired !== null && $hired->lessThanOrEqualTo($today) ? round($hired->diffInDays($today) / 365.25, 2) : null,
                ];
            })
            ->values();
    }

    /**
     * Olvida la plantilla memorizada en el proceso (tests y procesos largos).
     */
    public static function forgetPeople(): void
    {
        self::$people = null;
    }

    private static function hiresBetween(Collection $people, CarbonImmutable $from, CarbonImmutable $to): int
    {
        return $people->filter(static fn (array $person): bool => $person['hired'] !== null
            && $person['hired']->betweenIncluded($from->startOfDay(), $to->endOfDay()))->count();
    }

    private static function nextOccurrence(CarbonImmutable $date, CarbonImmutable $today): CarbonImmutable
    {
        $day = min($date->day, CarbonImmutable::create($today->year, $date->month, 1)->daysInMonth);
        $next = CarbonImmutable::create($today->year, $date->month, $day)->startOfDay();

        if ($next->lessThan($today)) {
            $day = min($date->day, CarbonImmutable::create($today->year + 1, $date->month, 1)->daysInMonth);
            $next = CarbonImmutable::create($today->year + 1, $date->month, $day)->startOfDay();
        }

        return $next;
    }

    private static function parseDate(mixed $value): ?CarbonImmutable
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        foreach (['d/m/Y', 'Y-m-d', 'Y-m-d H:i:s'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $value);

                if ($date !== false && $date->format($format) === $value) {
                    return $date;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }
}
