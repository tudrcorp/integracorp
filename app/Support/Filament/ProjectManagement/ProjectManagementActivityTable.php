<?php

declare(strict_types=1);

namespace App\Support\Filament\ProjectManagement;

use App\Models\ProjectManagement\Activity;
use App\Models\ProjectManagement\Department;
use App\Models\ProjectManagement\Group;
use App\Models\ProjectManagement\Project;
use App\Models\RrhhColaborador;
use Illuminate\Support\Carbon;

final class ProjectManagementActivityTable
{
    /**
     * @return array{label: string, color: string}
     */
    public static function statusMeta(string $status): array
    {
        return match ($status) {
            'todo' => ['label' => 'Por hacer', 'color' => 'gray'],
            'in_progress' => ['label' => 'En progreso', 'color' => 'info'],
            'review' => ['label' => 'En revisión', 'color' => 'warning'],
            'done' => ['label' => 'Finalizada', 'color' => 'success'],
            default => ['label' => $status, 'color' => 'gray'],
        };
    }

    /**
     * @return array{label: string, color: string}
     */
    public static function priorityMeta(string $priority): array
    {
        return match ($priority) {
            'low' => ['label' => 'Baja', 'color' => 'gray'],
            'medium' => ['label' => 'Media', 'color' => 'warning'],
            'high' => ['label' => 'Alta', 'color' => 'danger'],
            default => ['label' => $priority, 'color' => 'gray'],
        };
    }

    public static function resolveColor(Activity $activity): string
    {
        if (filled($activity->color)) {
            return (string) $activity->color;
        }

        $project = $activity->relationLoaded('project')
            ? $activity->project
            : null;

        if ($project instanceof Project) {
            return ProjectManagementProjectTable::resolveColor($project);
        }

        return ProjectManagementActivityAppearance::DEFAULT_COLOR;
    }

    public static function isOverdue(Activity $activity): bool
    {
        if ($activity->status === 'done' || $activity->due_date === null) {
            return false;
        }

        return $activity->due_date->isPast();
    }

    /**
     * @return array{
     *     label: string,
     *     subtitle: string,
     *     icon: string,
     *     tone: string
     * }
     */
    public static function assignmentSummary(Activity $activity): array
    {
        $assignmentType = (string) ($activity->assignment_type ?? 'collaborator');

        if ($assignmentType === 'department') {
            $departmentName = $activity->relationLoaded('executor')
                && $activity->executor instanceof Department
                ? (string) $activity->executor->name
                : 'Departamento asignado';

            return [
                'label' => $departmentName,
                'subtitle' => 'Asignación por departamento',
                'icon' => 'heroicon-o-building-office-2',
                'tone' => 'warning',
            ];
        }

        if ($assignmentType === 'team') {
            $groupName = $activity->relationLoaded('executor')
                && $activity->executor instanceof Group
                ? (string) $activity->executor->name
                : 'Equipo asignado';

            return [
                'label' => $groupName,
                'subtitle' => 'Asignación por equipo',
                'icon' => 'heroicon-o-user-group',
                'tone' => 'info',
            ];
        }

        $collaboratorIds = collect($activity->assigned_collaborator_ids ?? [])
            ->filter(fn (mixed $id): bool => filled($id))
            ->values();

        if ($collaboratorIds->count() > 1) {
            return [
                'label' => $collaboratorIds->count().' colaboradores',
                'subtitle' => 'Asignación múltiple',
                'icon' => 'heroicon-o-users',
                'tone' => 'primary',
            ];
        }

        if ($activity->relationLoaded('executor') && $activity->executor instanceof RrhhColaborador) {
            return [
                'label' => (string) $activity->executor->fullName,
                'subtitle' => 'Colaborador responsable',
                'icon' => 'heroicon-o-user',
                'tone' => 'success',
            ];
        }

        return [
            'label' => 'Sin asignar',
            'subtitle' => 'Define ejecutor en edición',
            'icon' => 'heroicon-o-user-minus',
            'tone' => 'muted',
        ];
    }

    public static function calculateDueProgressPercent(Carbon $start, Carbon $due, ?Carbon $now = null): int
    {
        $now ??= Carbon::now();
        $windowStart = $start->copy();
        $windowEnd = $due->copy()->endOfDay();

        if ($now->lt($windowStart)) {
            return 0;
        }

        if ($now->gte($windowEnd)) {
            return 100;
        }

        $totalSeconds = max(1, $windowStart->diffInSeconds($windowEnd));
        $elapsedSeconds = $windowStart->diffInSeconds($now);

        return max(0, min(100, (int) round(($elapsedSeconds / $totalSeconds) * 100)));
    }

    /**
     * @return array{
     *     total_days: int,
     *     elapsed_days: int,
     *     remaining_days: int,
     *     progress_detail: string
     * }
     */
    public static function dueWindowMeta(Carbon $start, Carbon $due, ?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $windowStart = $start->copy()->startOfDay();
        $windowEnd = $due->copy()->startOfDay();
        $today = $now->copy()->startOfDay();

        $totalDays = max(1, (int) $windowStart->diffInDays($windowEnd));

        if ($today->lt($windowStart)) {
            return [
                'total_days' => $totalDays,
                'elapsed_days' => 0,
                'remaining_days' => $totalDays,
                'progress_detail' => "0 de {$totalDays} días del plazo",
            ];
        }

        if ($today->gt($windowEnd)) {
            $overdueDays = (int) $windowEnd->diffInDays($today);

            return [
                'total_days' => $totalDays,
                'elapsed_days' => $totalDays + $overdueDays,
                'remaining_days' => 0,
                'progress_detail' => "Plazo agotado · {$overdueDays} día".($overdueDays === 1 ? '' : 's').' de retraso',
            ];
        }

        $elapsedDays = (int) min($totalDays, $windowStart->diffInDays($today));
        $remainingDays = (int) $today->diffInDays($windowEnd);

        return [
            'total_days' => $totalDays,
            'elapsed_days' => $elapsedDays,
            'remaining_days' => $remainingDays,
            'progress_detail' => "{$elapsedDays} de {$totalDays} días transcurridos · {$remainingDays} restante".($remainingDays === 1 ? '' : 's'),
        ];
    }

    /**
     * @return array{
     *     percent: int|null,
     *     label: string,
     *     tone: string,
     *     due_label: string|null,
     *     created_label: string|null,
     *     progress_detail: string|null,
     *     total_days: int|null,
     *     elapsed_days: int|null,
     *     remaining_days: int|null
     * }
     */
    public static function dueMeta(Activity $activity): array
    {
        $due = $activity->due_date;
        $createdLabel = $activity->created_at?->format('d/m/Y');
        $start = $activity->created_at ?? Carbon::now();

        if ($activity->status === 'done') {
            return [
                'percent' => 100,
                'label' => 'Actividad finalizada',
                'tone' => 'success',
                'due_label' => $due?->format('d/m/Y'),
                'created_label' => $createdLabel,
                'progress_detail' => 'Plazo cerrado al completar la tarea',
                'total_days' => $due ? max(1, (int) $start->copy()->startOfDay()->diffInDays($due->copy()->startOfDay())) : null,
                'elapsed_days' => null,
                'remaining_days' => 0,
            ];
        }

        if ($due === null) {
            return [
                'percent' => null,
                'label' => 'Sin fecha límite',
                'tone' => 'muted',
                'due_label' => null,
                'created_label' => $createdLabel,
                'progress_detail' => null,
                'total_days' => null,
                'elapsed_days' => null,
                'remaining_days' => null,
            ];
        }

        $dueLabel = $due->format('d/m/Y');
        $percent = self::calculateDueProgressPercent($start, $due);
        $window = self::dueWindowMeta($start, $due);

        if (self::isOverdue($activity)) {
            return [
                'percent' => 100,
                'label' => 'Plazo vencido · 100% consumido',
                'tone' => 'danger',
                'due_label' => $dueLabel,
                'created_label' => $createdLabel,
                'progress_detail' => $window['progress_detail'],
                'total_days' => $window['total_days'],
                'elapsed_days' => $window['elapsed_days'],
                'remaining_days' => 0,
            ];
        }

        $tone = match (true) {
            $percent >= 90 => 'danger',
            $percent >= 70 => 'warning',
            default => 'primary',
        };

        $label = match (true) {
            $due->isToday() => "Vence hoy · {$percent}% del plazo",
            $window['remaining_days'] === 1 => "Vence mañana · {$percent}% del plazo",
            default => "Vence en {$window['remaining_days']} días · {$percent}% del plazo",
        };

        return [
            'percent' => $percent,
            'label' => $label,
            'tone' => $tone,
            'due_label' => $dueLabel,
            'created_label' => $createdLabel,
            'progress_detail' => $window['progress_detail'],
            'total_days' => $window['total_days'],
            'elapsed_days' => $window['elapsed_days'],
            'remaining_days' => $window['remaining_days'],
        ];
    }

    /**
     * Resumen de tiempos para tarjetas Kanban en estatus finalizada.
     *
     * @return array{
     *     started_label: string,
     *     finished_label: string,
     *     optimal_days: int|null,
     *     optimal_label: string|null,
     *     elapsed_days: int,
     *     elapsed_label: string,
     *     within_range: bool
     * }|null
     */
    public static function kanbanDoneExecutionSummary(Activity $activity): ?array
    {
        if ($activity->status !== 'done') {
            return null;
        }

        $assignedAt = $activity->created_at?->copy()->startOfDay();
        $finishedAt = $activity->updated_at?->copy()->startOfDay();

        if ($assignedAt === null || $finishedAt === null) {
            return null;
        }

        if ($finishedAt->lt($assignedAt)) {
            $finishedAt = $assignedAt->copy();
        }

        $elapsedDays = self::kanbanElapsedDaysBetween($assignedAt, $finishedAt);

        $due = $activity->due_date?->copy()->startOfDay();
        $optimalDays = null;

        if ($due !== null && $due->gte($assignedAt)) {
            $optimalDays = self::kanbanElapsedDaysBetween($assignedAt, $due);
        }

        $withinRange = $due === null || $finishedAt->lte($due);

        return [
            'started_label' => $assignedAt->translatedFormat('d M Y'),
            'finished_label' => $finishedAt->translatedFormat('d M Y'),
            'optimal_days' => $optimalDays,
            'optimal_label' => $optimalDays !== null ? self::kanbanDaysLabel($optimalDays) : null,
            'elapsed_days' => $elapsedDays,
            'elapsed_label' => self::kanbanDaysLabel($elapsedDays),
            'within_range' => $withinRange,
        ];
    }

    private static function kanbanElapsedDaysBetween(Carbon $start, Carbon $end): int
    {
        if ($start->isSameDay($end)) {
            return 1;
        }

        return max(1, (int) $start->diffInDays($end));
    }

    private static function kanbanDaysLabel(int $days): string
    {
        return $days === 1 ? '1 día' : "{$days} días";
    }
}
