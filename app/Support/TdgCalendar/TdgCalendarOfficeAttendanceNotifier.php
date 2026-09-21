<?php

declare(strict_types=1);

namespace App\Support\TdgCalendar;

use App\Enums\TdgCalendarOffice;
use App\Mail\TdgCalendarOfficeAttendanceMail;
use App\Models\RrhhColaborador;
use App\Models\TdgCalendarOfficeAssignment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class TdgCalendarOfficeAttendanceNotifier
{
    /**
     * @var array<int, string>
     */
    private const WEEKDAY_LABELS = [
        1 => 'lunes',
        2 => 'martes',
        3 => 'miércoles',
        4 => 'jueves',
        5 => 'viernes',
        6 => 'sábado',
        7 => 'domingo',
    ];

    /**
     * @var array<int, string>
     */
    private const MONTH_LABELS = [
        1 => 'enero',
        2 => 'febrero',
        3 => 'marzo',
        4 => 'abril',
        5 => 'mayo',
        6 => 'junio',
        7 => 'julio',
        8 => 'agosto',
        9 => 'septiembre',
        10 => 'octubre',
        11 => 'noviembre',
        12 => 'diciembre',
    ];

    /**
     * @param  list<string>  $affectedDates
     * @param  list<int>|null  $colaboradorIds
     * @return array{sent: int, skipped: int}
     */
    public function notifyForDates(array $affectedDates, ?array $colaboradorIds = null, bool $isUpdate = false): array
    {
        $dates = collect($affectedDates)
            ->map(fn (mixed $date): string => Carbon::parse((string) $date)->toDateString())
            ->filter()
            ->unique()
            ->values();

        if ($dates->isEmpty()) {
            return ['sent' => 0, 'skipped' => 0];
        }

        $monthStarts = $dates
            ->map(fn (string $date): string => Carbon::parse($date)->startOfMonth()->toDateString())
            ->unique()
            ->sort()
            ->values();

        $rangeStart = (string) $monthStarts->first();
        $rangeEnd = Carbon::parse((string) $monthStarts->last())->endOfMonth()->toDateString();

        $normalizedColaboradorIds = $colaboradorIds === null
            ? null
            : collect($colaboradorIds)
                ->map(fn (mixed $id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all();

        if ($normalizedColaboradorIds === []) {
            return ['sent' => 0, 'skipped' => 0];
        }

        $assignments = TdgCalendarOfficeAssignment::query()
            ->with([
                'calendarDay:id,calendar_date',
                'colaborador:id,fullName,emailCorporativo,emailAlternativo,emailPersonal',
            ])
            ->whereHas(
                'calendarDay',
                fn ($query) => $query->whereBetween('calendar_date', [$rangeStart, $rangeEnd]),
            )
            ->when(
                $normalizedColaboradorIds !== null,
                fn ($query) => $query->whereIn('rrhh_colaborador_id', $normalizedColaboradorIds),
            )
            ->get();

        $assignmentsByColaborador = $assignments->groupBy(
            fn (TdgCalendarOfficeAssignment $assignment): int => (int) $assignment->rrhh_colaborador_id,
        );

        $targetIds = $normalizedColaboradorIds ?? $assignmentsByColaborador->keys()->map(fn (mixed $id): int => (int) $id)->all();

        if ($targetIds === []) {
            return ['sent' => 0, 'skipped' => 0];
        }

        $colaboradores = RrhhColaborador::query()
            ->whereIn('id', $targetIds)
            ->get(['id', 'fullName', 'emailCorporativo', 'emailAlternativo', 'emailPersonal'])
            ->keyBy('id');

        $sent = 0;
        $skipped = 0;

        foreach ($targetIds as $colaboradorId) {
            $colaborador = $colaboradores->get($colaboradorId);

            if (! $colaborador instanceof RrhhColaborador) {
                $skipped++;

                continue;
            }

            $email = $this->resolveEmail($colaborador);

            if ($email === null) {
                $skipped++;
                Log::warning('TdgCalendarOfficeAttendanceNotifier: colaborador sin correo', [
                    'colaborador_id' => $colaborador->id,
                ]);

                continue;
            }

            $monthLabel = $this->monthLabelForDates($dates);
            $payloadAssignments = $this->presentAssignments(
                $assignmentsByColaborador->get($colaboradorId, collect()),
            );

            try {
                Mail::to($email)->send(new TdgCalendarOfficeAttendanceMail(
                    [
                        'colaborador_name' => trim((string) ($colaborador->fullName ?? '')) ?: 'colaborador',
                        'month_label' => $monthLabel,
                        'is_update' => $isUpdate,
                        'assignments' => $payloadAssignments,
                    ],
                    $email,
                ));
                $sent++;
            } catch (Throwable $exception) {
                $skipped++;
                Log::error('TdgCalendarOfficeAttendanceNotifier: fallo al enviar correo', [
                    'colaborador_id' => $colaborador->id,
                    'email' => $email,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    /**
     * @return array{sent: int, skipped: int}
     */
    public function notifyForMonth(Carbon $month, bool $isUpdate = false): array
    {
        $start = $month->copy()->startOfMonth()->toDateString();
        $end = $month->copy()->endOfMonth()->toDateString();

        $dates = TdgCalendarOfficeAssignment::query()
            ->whereHas(
                'calendarDay',
                fn ($query) => $query->whereBetween('calendar_date', [$start, $end]),
            )
            ->with('calendarDay:id,calendar_date')
            ->get()
            ->map(function (TdgCalendarOfficeAssignment $assignment): ?string {
                $date = $assignment->calendarDay?->calendar_date;

                if ($date === null) {
                    return null;
                }

                return Carbon::parse($date)->toDateString();
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $this->notifyForDates($dates, null, $isUpdate);
    }

    /**
     * @param  array{sent?: int, skipped?: int}  $report
     */
    public function formatNotificationBody(array $report): string
    {
        $sent = (int) ($report['sent'] ?? 0);
        $skipped = (int) ($report['skipped'] ?? 0);

        if ($sent === 0 && $skipped === 0) {
            return '';
        }

        $parts = [];

        if ($sent > 0) {
            $parts[] = $this->mailerWritesToLog()
                ? ($sent === 1
                    ? 'Se generó 1 correo de asistencia, pero el entorno local lo guardó en el log (no llegó a bandejas reales).'
                    : "Se generaron {$sent} correos de asistencia, pero el entorno local los guardó en el log (no llegaron a bandejas reales).")
                : ($sent === 1
                    ? 'Se envió 1 correo de asistencia a oficina.'
                    : "Se enviaron {$sent} correos de asistencia a oficina.");
        }

        if ($skipped > 0) {
            $parts[] = $skipped === 1
                ? '1 colaborador no tiene correo registrado.'
                : "{$skipped} colaboradores no tienen correo registrado.";
        }

        return implode(' ', $parts);
    }

    /**
     * @param  array<int, string>  $previous
     * @param  array<int, string>  $next
     * @return list<int>
     */
    public static function colaboradorIdsModified(array $previous, array $next): array
    {
        $ids = [];

        foreach ($previous as $colaboradorId => $office) {
            if (! array_key_exists($colaboradorId, $next) || $next[$colaboradorId] !== $office) {
                $ids[] = (int) $colaboradorId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<int, string>  $previous
     * @param  array<int, string>  $next
     * @return list<int>
     */
    public static function colaboradorIdsAdded(array $previous, array $next): array
    {
        $ids = [];

        foreach ($next as $colaboradorId => $office) {
            if (! array_key_exists($colaboradorId, $previous)) {
                $ids[] = (int) $colaboradorId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<int, string>  $previous
     * @param  array<int, string>  $next
     * @return list<int>
     */
    public static function colaboradorIdsWithAssignmentChanges(array $previous, array $next): array
    {
        return array_values(array_unique([
            ...self::colaboradorIdsModified($previous, $next),
            ...self::colaboradorIdsAdded($previous, $next),
        ]));
    }

    public function resolveEmail(RrhhColaborador $colaborador): ?string
    {
        foreach (['emailCorporativo', 'emailAlternativo', 'emailPersonal'] as $field) {
            $email = strtolower(trim((string) ($colaborador->{$field} ?? '')));

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return null;
    }

    private function mailerWritesToLog(): bool
    {
        return config('mail.default') === 'log';
    }

    /**
     * @param  Collection<int, string>  $dates
     */
    private function monthLabelForDates(Collection $dates): string
    {
        $months = $dates
            ->map(function (string $date): string {
                $carbon = Carbon::parse($date);

                return $this->monthName($carbon->month).' '.$carbon->year;
            })
            ->unique()
            ->values();

        return $months->implode(' / ');
    }

    /**
     * @param  Collection<int, TdgCalendarOfficeAssignment>  $assignments
     * @return list<array{date: string, date_label: string, weekday_label: string, office_label: string}>
     */
    private function presentAssignments(Collection $assignments): array
    {
        return $assignments
            ->map(function (TdgCalendarOfficeAssignment $assignment): ?array {
                $rawDate = $assignment->calendarDay?->calendar_date;

                if ($rawDate === null) {
                    return null;
                }

                $date = Carbon::parse($rawDate);
                $office = $assignment->office;

                return [
                    'date' => $date->toDateString(),
                    'date_label' => $date->format('d/m/Y'),
                    'weekday_label' => self::WEEKDAY_LABELS[$date->isoWeekday()] ?? $date->format('l'),
                    'office_label' => $office instanceof TdgCalendarOffice
                        ? $office->label()
                        : TdgCalendarOffice::tryFrom((string) $office)?->label() ?? (string) $office,
                ];
            })
            ->filter()
            ->sortBy('date')
            ->unique(fn (array $row): string => $row['date'].'|'.$row['office_label'])
            ->values()
            ->all();
    }

    private function monthName(int $month): string
    {
        return self::MONTH_LABELS[$month] ?? (string) $month;
    }
}
