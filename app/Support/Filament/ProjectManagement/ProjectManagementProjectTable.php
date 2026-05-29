<?php

declare(strict_types=1);

namespace App\Support\Filament\ProjectManagement;

use App\Models\ProjectManagement\Project;
use Illuminate\Support\Carbon;

final class ProjectManagementProjectTable
{
    /**
     * @return array{label: string, color: string}
     */
    public static function statusMeta(string $status): array
    {
        return match ($status) {
            'active' => ['label' => 'Activo', 'color' => 'success'],
            'on_hold' => ['label' => 'En espera', 'color' => 'warning'],
            'completed' => ['label' => 'Completado', 'color' => 'gray'],
            default => ['label' => $status, 'color' => 'gray'],
        };
    }

    public static function resolveColor(Project $project): string
    {
        return filled($project->color)
            ? (string) $project->color
            : ProjectManagementProjectAppearance::DEFAULT_COLOR;
    }

    public static function resolveIcon(Project $project): string
    {
        return filled($project->icon)
            ? (string) $project->icon
            : ProjectManagementProjectAppearance::DEFAULT_ICON;
    }

    public static function isOverdue(Project $project): bool
    {
        if ($project->status === 'completed' || $project->end_date === null) {
            return false;
        }

        return $project->end_date->isPast();
    }

    public static function delayDays(Project $project): ?int
    {
        if ($project->status === 'completed' || $project->end_date === null || ! $project->end_date->isPast()) {
            return null;
        }

        return (int) $project->end_date->diffInDays(Carbon::today());
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            'active' => 'Activo',
            'on_hold' => 'En espera',
            'completed' => 'Completado',
        ];
    }

    /**
     * @return array{
     *     percent: int|null,
     *     label: string,
     *     tone: string,
     *     start_label: string|null,
     *     end_label: string|null
     * }
     */
    public static function timelineMeta(Project $project): array
    {
        $start = $project->start_date;
        $end = $project->end_date;

        if ($start === null && $end === null) {
            return [
                'percent' => null,
                'label' => 'Sin fechas planificadas',
                'tone' => 'muted',
                'start_label' => null,
                'end_label' => null,
            ];
        }

        $startLabel = $start?->format('d/m/Y');
        $endLabel = $end?->format('d/m/Y');

        if ($start !== null && $end === null) {
            return [
                'percent' => null,
                'label' => 'Iniciado · sin fecha fin',
                'tone' => 'info',
                'start_label' => $startLabel,
                'end_label' => null,
            ];
        }

        if ($start === null && $end !== null) {
            return [
                'percent' => null,
                'label' => self::isOverdue($project) ? 'Fecha límite vencida' : 'Con fecha objetivo',
                'tone' => self::isOverdue($project) ? 'danger' : 'warning',
                'start_label' => null,
                'end_label' => $endLabel,
            ];
        }

        $totalDays = max(1, $start->diffInDays($end));
        $elapsedDays = $start->isFuture()
            ? 0
            : min($totalDays, $start->diffInDays(Carbon::today()));

        $percent = (int) round(($elapsedDays / $totalDays) * 100);
        $percent = max(0, min(100, $percent));

        $tone = match (true) {
            $project->status === 'completed' => 'success',
            self::isOverdue($project) => 'danger',
            $percent >= 85 => 'warning',
            default => 'primary',
        };

        $label = match (true) {
            $project->status === 'completed' => 'Proyecto completado',
            self::isOverdue($project) => 'Plazo vencido',
            $start->isFuture() => 'Por iniciar',
            default => "{$percent}% del cronograma",
        };

        return [
            'percent' => $percent,
            'label' => $label,
            'tone' => $tone,
            'start_label' => $startLabel,
            'end_label' => $endLabel,
        ];
    }
}
