<?php

declare(strict_types=1);

namespace App\Support\Filament\ProjectManagement;

use App\Filament\Projects\Resources\ProjectManagement\Activities\ActivityResource;
use App\Models\ProjectManagement\Activity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class ProjectManagementKanbanTimeline
{
    private const DEFAULT_SPAN_DAYS = 7;

    private const MIN_BAR_DAYS = 1;

    /**
     * @param  Collection<int, Activity>  $activities
     * @return array{
     *     day_count: int,
     *     range_start: string,
     *     today_index: int|null,
     *     weeks: array<int, array{label: string, span: int}>,
     *     days: array<int, array{label: string, is_today: bool, is_weekend: bool}>,
     *     groups: array<int, array{
     *         project_id: int|null,
     *         project_name: string,
     *         project_color: string,
     *         phase_start: int,
     *         phase_span: int,
     *         rows: array<int, array{
     *             id: int,
     *             title: string,
     *             color: string,
     *             status_label: string,
     *             start_index: int,
     *             span: int,
     *             view_url: string,
     *             assignees: array{
     *                 visible_members: array<int, array{id: int, name: string, initials: string, avatar_url: string|null}>,
     *                 overflow_count: int,
     *                 total_count: int,
     *                 heading: string,
     *                 title: string,
     *                 all_members: array<int, array{id: int, name: string, initials: string, avatar_url: string|null}>
     *             },
     *             is_milestone: bool
     *         }>
     *     }>
     * }
     */
    public static function build(Collection $activities): array
    {
        $today = Carbon::today();

        if ($activities->isEmpty()) {
            return self::emptyPayload($today);
        }

        /** @var array<int, array{start: Carbon, end: Carbon, activity: Activity}> $schedules */
        $schedules = [];

        foreach ($activities as $activity) {
            $schedules[$activity->id] = [
                'start' => self::resolveStartDate($activity, $today),
                'end' => self::resolveEndDate($activity, $today),
                'activity' => $activity,
            ];
        }

        $rangeStart = collect($schedules)->min(fn (array $schedule): Carbon => $schedule['start']->copy()->startOfDay());
        $rangeEnd = collect($schedules)->max(fn (array $schedule): Carbon => $schedule['end']->copy()->startOfDay());

        $rangeStart = $rangeStart->copy()->startOfWeek(Carbon::MONDAY);
        $rangeEnd = $rangeEnd->copy()->endOfWeek(Carbon::SUNDAY);

        if ($today->lt($rangeStart)) {
            $rangeStart = $today->copy()->startOfWeek(Carbon::MONDAY);
        }

        if ($today->gt($rangeEnd)) {
            $rangeEnd = $today->copy()->endOfWeek(Carbon::SUNDAY);
        }

        $days = self::buildDays($rangeStart, $rangeEnd, $today);
        $dayCount = count($days);
        $todayIndex = collect($days)->search(fn (array $day): bool => $day['is_today']);

        $groups = $activities
            ->sortBy(fn (Activity $activity): string => strtolower($activity->project?->name ?? 'zzz'))
            ->groupBy(fn (Activity $activity): string => (string) ($activity->project_id ?? 'none'))
            ->map(function (Collection $projectActivities) use ($schedules, $rangeStart, $dayCount): array {
                /** @var Activity $first */
                $first = $projectActivities->first();
                $project = $first->project;
                $projectColor = $project !== null
                    ? ProjectManagementProjectTable::resolveColor($project)
                    : '#6366f1';

                $rows = $projectActivities
                    ->sortBy(fn (Activity $activity): string => $schedules[$activity->id]['start']->format('Y-m-d'))
                    ->values()
                    ->map(function (Activity $activity) use ($schedules, $rangeStart, $dayCount): array {
                        $schedule = $schedules[$activity->id];
                        $startIndex = max(0, (int) $rangeStart->diffInDays($schedule['start']->copy()->startOfDay(), false));
                        $endIndex = min($dayCount - 1, (int) $rangeStart->diffInDays($schedule['end']->copy()->startOfDay(), false));
                        $span = max(self::MIN_BAR_DAYS, $endIndex - $startIndex + 1);
                        $startIndex = min($startIndex, max(0, $dayCount - $span));

                        $assignment = ProjectManagementActivityAssignmentDisplay::for($activity);

                        $isMilestone = $span <= 1;

                        return [
                            'id' => $activity->id,
                            'title' => $activity->title,
                            'color' => ProjectManagementActivityTable::resolveColor($activity),
                            'status_label' => self::statusLabel((string) $activity->status),
                            'start_index' => $startIndex,
                            'span' => $span,
                            'view_url' => ActivityResource::getUrl('view', ['record' => $activity], panel: 'projects'),
                            'assignees' => [
                                'visible_members' => $assignment['visible_members'],
                                'overflow_count' => $assignment['overflow_count'],
                                'total_count' => $assignment['total_count'],
                                'heading' => $assignment['heading'],
                                'title' => $assignment['title'],
                                'all_members' => $assignment['all_members'],
                            ],
                            'is_milestone' => $isMilestone,
                        ];
                    })
                    ->all();

                $phaseStart = min(array_column($rows, 'start_index'));
                $phaseEnd = max(array_map(fn (array $row): int => $row['start_index'] + $row['span'] - 1, $rows));

                return [
                    'project_id' => $project?->id,
                    'project_name' => $project?->name ?? 'Sin proyecto',
                    'project_color' => $projectColor,
                    'phase_start' => $phaseStart,
                    'phase_span' => max(1, $phaseEnd - $phaseStart + 1),
                    'rows' => $rows,
                ];
            })
            ->values()
            ->all();

        return [
            'day_count' => $dayCount,
            'range_start' => $rangeStart->toDateString(),
            'today_index' => is_int($todayIndex) ? $todayIndex : null,
            'weeks' => self::buildWeeks($rangeStart, $days),
            'days' => $days,
            'groups' => $groups,
        ];
    }

    private static function resolveStartDate(Activity $activity, Carbon $today): Carbon
    {
        $created = $activity->created_at?->copy()->startOfDay() ?? $today->copy();
        $due = $activity->due_date?->copy()->startOfDay();

        if ($due === null) {
            return $created;
        }

        if ($due->lte($created)) {
            return $due->copy();
        }

        $windowDays = min(14, max(3, (int) $created->diffInDays($due)));

        return $due->copy()->subDays($windowDays);
    }

    private static function resolveEndDate(Activity $activity, Carbon $today): Carbon
    {
        if ($activity->due_date !== null) {
            return $activity->due_date->copy()->startOfDay();
        }

        $created = $activity->created_at?->copy()->startOfDay() ?? $today->copy();

        return $created->copy()->addDays(self::DEFAULT_SPAN_DAYS);
    }

    /**
     * @return array<int, array{label: string, is_today: bool, is_weekend: bool}>
     */
    private static function buildDays(Carbon $rangeStart, Carbon $rangeEnd, Carbon $today): array
    {
        $days = [];
        $cursor = $rangeStart->copy();

        while ($cursor->lte($rangeEnd)) {
            $days[] = [
                'label' => $cursor->format('j'),
                'is_today' => $cursor->isSameDay($today),
                'is_weekend' => $cursor->isWeekend(),
            ];

            $cursor->addDay();
        }

        return $days;
    }

    /**
     * @param  array<int, array{label: string, is_today: bool, is_weekend: bool}>  $days
     * @return array<int, array{label: string, span: int}>
     */
    private static function buildWeeks(Carbon $rangeStart, array $days): array
    {
        if ($days === []) {
            return [];
        }

        $weeks = [];
        $index = 0;
        $total = count($days);

        while ($index < $total) {
            $span = min(7, $total - $index);
            $weekStart = $rangeStart->copy()->addDays($index);
            $weekEnd = $rangeStart->copy()->addDays($index + $span - 1);

            $weeks[] = [
                'label' => $weekStart->format('d M').' - '.$weekEnd->format('d M'),
                'span' => $span,
            ];

            $index += $span;
        }

        return $weeks;
    }

    private static function statusLabel(string $status): string
    {
        return match ($status) {
            'todo' => 'Por hacer',
            'in_progress' => 'En progreso',
            'review' => 'En revisión',
            'done' => 'Finalizada',
            default => $status,
        };
    }

    /**
     * @return array{
     *     day_count: int,
     *     range_start: string,
     *     today_index: int|null,
     *     weeks: array<int, array{label: string, span: int}>,
     *     days: array<int, array{label: string, is_today: bool, is_weekend: bool}>,
     *     groups: array<int, mixed>
     * }
     */
    private static function emptyPayload(Carbon $today): array
    {
        $rangeStart = $today->copy()->startOfWeek(Carbon::MONDAY);
        $rangeEnd = $today->copy()->endOfWeek(Carbon::SUNDAY);
        $days = self::buildDays($rangeStart, $rangeEnd, $today);

        return [
            'day_count' => count($days),
            'range_start' => $rangeStart->toDateString(),
            'today_index' => collect($days)->search(fn (array $day): bool => $day['is_today']),
            'weeks' => self::buildWeeks($rangeStart, $days),
            'days' => $days,
            'groups' => [],
        ];
    }
}
