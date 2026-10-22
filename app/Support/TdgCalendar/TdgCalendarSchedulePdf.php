<?php

declare(strict_types=1);

namespace App\Support\TdgCalendar;

use App\Enums\TdgCalendarGuardShift;
use App\Enums\TdgCalendarOffice;
use App\Models\RrhhColaborador;
use App\Models\TdgCalendarGuardAssignment;
use App\Models\TdgCalendarOfficeAssignment;
use App\Models\User;
use App\Support\DomPdfBatchRenderOptions;
use App\Support\Filament\UserNavigationAccess;
use App\Support\TdgCalendarEditAuthorization;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * PDF del mes visible de Guardia Oficinas o Guardias Operaciones.
 *
 * Quien puede editar descarga a todo el equipo o a una persona. Quien solo
 * consulta descarga el cronograma de su ficha de colaborador.
 */
final class TdgCalendarSchedulePdf
{
    public const OFFICES = 'oficinas';

    public const OPERATIONS = 'operaciones';

    /**
     * @var list<string>
     */
    private const MODULES = ['NEGOCIOS', 'ADMINISTRACION', 'OPERACIONES', 'MARKETING'];

    public static function canDownload(User $user, string $kind): bool
    {
        self::assertKind($kind);

        if (self::canDownloadEveryone($user, $kind)) {
            return true;
        }

        return self::canView($user, $kind) && self::ownColaborador($user) instanceof RrhhColaborador;
    }

    public static function canDownloadEveryone(User $user, string $kind): bool
    {
        self::assertKind($kind);

        if ($kind === self::OPERATIONS) {
            return TdgCalendarEditAuthorization::allowsOperationsGuards($user);
        }

        foreach (self::MODULES as $module) {
            if (TdgCalendarEditAuthorization::allows($user, $module)) {
                return true;
            }
        }

        return false;
    }

    public static function canView(User $user, string $kind): bool
    {
        self::assertKind($kind);

        $slug = $kind === self::OPERATIONS ? 'guardias-operaciones' : 'calendarios-tdg';

        foreach (self::MODULES as $module) {
            if (UserNavigationAccess::canAccessMenuItem($user, $module, [$slug])) {
                return true;
            }
        }

        return false;
    }

    public static function ownColaborador(User $user): ?RrhhColaborador
    {
        $userId = $user->getAuthIdentifier();

        if ($userId === null) {
            return null;
        }

        return RrhhColaborador::query()
            ->where('user_id', $userId)
            ->orderBy('id')
            ->first();
    }

    /**
     * Null means the whole team. A viewer never receives another person's id.
     */
    public static function resolvedColaboradorId(User $user, string $kind, ?int $requested): ?int
    {
        if (self::canDownloadEveryone($user, $kind)) {
            return $requested;
        }

        return self::ownColaborador($user)?->id;
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public static function peopleInMonth(string $kind, CarbonInterface $month): array
    {
        $people = [];

        foreach (self::assignments($kind, $month) as $row) {
            $people[$row['colaborador_id']] = [
                'id' => $row['colaborador_id'],
                'name' => $row['name'],
            ];
        }

        usort($people, fn (array $left, array $right): int => strcasecmp($left['name'], $right['name']));

        return array_values($people);
    }

    /**
     * @return array{
     *     title: string,
     *     month_label: string,
     *     scope_label: string,
     *     person_name: ?string,
     *     weekdays: list<string>,
     *     weeks: list<list<array{day: int, in_month: bool, lines: list<string>, extra: int}>>,
     *     people: list<array{name: string, days: list<array{date: string, weekday: string, places: string}>}>
     * }
     */
    public static function document(string $kind, CarbonInterface $month, ?int $colaboradorId): array
    {
        $month = Carbon::parse($month->toDateString())->locale('es')->startOfMonth();
        $rows = self::assignments($kind, $month);

        if ($colaboradorId !== null) {
            $rows = array_values(array_filter(
                $rows,
                fn (array $row): bool => $row['colaborador_id'] === $colaboradorId,
            ));
        }

        $byDate = [];

        foreach ($rows as $row) {
            $byDate[$row['date']][] = $row;
        }

        $weeks = [];
        $cursor = $month->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        while ($cursor->lessThanOrEqualTo($gridEnd)) {
            $week = [];

            for ($dayIndex = 0; $dayIndex < 7; $dayIndex++) {
                $dateKey = $cursor->toDateString();
                $entries = $byDate[$dateKey] ?? [];
                $lines = [];

                foreach ($entries as $entry) {
                    $lines[] = self::shortName($entry['name']).' · '.$entry['short'];
                }

                $week[] = [
                    'day' => (int) $cursor->format('j'),
                    'in_month' => $cursor->isSameMonth($month),
                    'lines' => array_slice($lines, 0, 3),
                    'extra' => max(0, count($lines) - 3),
                ];

                $cursor->addDay();
            }

            $weeks[] = $week;
        }

        $people = self::peopleSchedules($rows);

        if ($colaboradorId !== null && $people === []) {
            $person = RrhhColaborador::query()->find($colaboradorId);

            if ($person instanceof RrhhColaborador) {
                $people[] = [
                    'name' => self::displayName($person->fullName),
                    'days' => [],
                ];
            }
        }

        $personName = $colaboradorId !== null ? ($people[0]['name'] ?? null) : null;

        return [
            'title' => $kind === self::OPERATIONS ? 'Guardias Operaciones' : 'Guardia Oficinas',
            'month_label' => Str::ucfirst($month->translatedFormat('F Y')),
            'scope_label' => $personName ?? 'Todos los colaboradores',
            'person_name' => $personName,
            'weekdays' => ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
            'weeks' => $weeks,
            'people' => $people,
        ];
    }

    /**
     * @param  array<string, mixed>  $document
     */
    public static function binary(array $document): string
    {
        $pdf = Pdf::loadView('documents.tdg-calendar-schedule', [
            'document' => $document,
            'generatedAt' => now()->locale('es')->translatedFormat('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        DomPdfBatchRenderOptions::apply($pdf);

        return $pdf->output();
    }

    public static function filename(string $kind, CarbonInterface $month, ?string $personName): string
    {
        $base = $kind === self::OPERATIONS ? 'guardias-operaciones' : 'guardia-oficinas';
        $suffix = $personName !== null && $personName !== ''
            ? '-'.Str::slug($personName)
            : '-todos';

        return $base.'-'.$month->format('Y-m').$suffix.'.pdf';
    }

    public static function assertKind(string $kind): void
    {
        if (! in_array($kind, [self::OFFICES, self::OPERATIONS], true)) {
            throw new InvalidArgumentException('El calendario de guardia no existe.');
        }
    }

    /**
     * @param  list<array{date: string, colaborador_id: int, name: string, place: string, short: string}>  $rows
     * @return list<array{name: string, days: list<array{date: string, weekday: string, places: string}>}>
     */
    private static function peopleSchedules(array $rows): array
    {
        /** @var array<int, array{name: string, days: array<string, list<string>>}> $grouped */
        $grouped = [];

        foreach ($rows as $row) {
            $grouped[$row['colaborador_id']]['name'] = $row['name'];
            $grouped[$row['colaborador_id']]['days'][$row['date']][] = $row['place'];
        }

        $people = [];

        foreach ($grouped as $person) {
            ksort($person['days']);
            $days = [];

            foreach ($person['days'] as $date => $places) {
                $day = Carbon::parse($date)->locale('es');
                $days[] = [
                    'date' => $day->format('d/m/Y'),
                    'weekday' => Str::ucfirst($day->translatedFormat('l')),
                    'places' => implode(', ', array_values(array_unique($places))),
                ];
            }

            $people[] = [
                'name' => $person['name'],
                'days' => $days,
            ];
        }

        usort($people, fn (array $left, array $right): int => strcasecmp($left['name'], $right['name']));

        return $people;
    }

    /**
     * @return list<array{date: string, colaborador_id: int, name: string, place: string, short: string}>
     */
    private static function assignments(string $kind, CarbonInterface $month): array
    {
        self::assertKind($kind);

        $start = $month->copy()->startOfMonth()->toDateString();
        $end = $month->copy()->endOfMonth()->toDateString();

        if ($kind === self::OPERATIONS) {
            $records = TdgCalendarGuardAssignment::query()
                ->whereHas('calendarDay', fn (Builder $query): Builder => $query->whereBetween('calendar_date', [$start, $end]))
                ->with(['calendarDay:id,calendar_date', 'colaborador:id,fullName'])
                ->get();

            $rows = [];

            foreach ($records as $record) {
                $row = self::rowFromAssignment(
                    $record->calendarDay?->calendar_date?->toDateString(),
                    $record->colaborador,
                    $record->guard_shift instanceof TdgCalendarGuardShift ? $record->guard_shift->shortLabel() : (string) $record->guard_shift,
                    self::operationShort($record->guard_shift),
                );

                if ($row !== null) {
                    $rows[] = $row;
                }
            }

            return $rows;
        }

        $records = TdgCalendarOfficeAssignment::query()
            ->whereHas('calendarDay', fn (Builder $query): Builder => $query->whereBetween('calendar_date', [$start, $end]))
            ->with(['calendarDay:id,calendar_date', 'colaborador:id,fullName'])
            ->get();

        $rows = [];

        foreach ($records as $record) {
            $office = $record->office;
            $row = self::rowFromAssignment(
                $record->calendarDay?->calendar_date?->toDateString(),
                $record->colaborador,
                $office instanceof TdgCalendarOffice ? $office->label() : (string) $office,
                self::officeShort($office),
            );

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @return array{date: string, colaborador_id: int, name: string, place: string, short: string}|null
     */
    private static function rowFromAssignment(?string $date, ?RrhhColaborador $colaborador, string $place, string $short): ?array
    {
        if ($date === null || ! $colaborador instanceof RrhhColaborador) {
            return null;
        }

        return [
            'date' => $date,
            'colaborador_id' => (int) $colaborador->id,
            'name' => self::displayName($colaborador->fullName),
            'place' => $place,
            'short' => $short,
        ];
    }

    private static function displayName(mixed $name): string
    {
        $text = trim((string) $name);

        return $text !== '' ? $text : 'Colaborador';
    }

    private static function shortName(string $name): string
    {
        if (mb_strlen($name) <= 18) {
            return $name;
        }

        return rtrim(mb_substr($name, 0, 17)).'…';
    }

    private static function officeShort(mixed $office): string
    {
        return match ($office) {
            TdgCalendarOffice::CentralLido => 'Lido',
            TdgCalendarOffice::FarmadocSanBernardino => 'SBO',
            TdgCalendarOffice::FarmadocLasDelicias => 'Delicias',
            TdgCalendarOffice::HomeOffice => 'Casa',
            default => 'Oficina',
        };
    }

    private static function operationShort(mixed $shift): string
    {
        return match ($shift) {
            TdgCalendarGuardShift::Proveedores => 'Prov.',
            TdgCalendarGuardShift::IlsCapitado => 'ILS',
            TdgCalendarGuardShift::Nocturna => 'Noche',
            default => 'Turno',
        };
    }
}
