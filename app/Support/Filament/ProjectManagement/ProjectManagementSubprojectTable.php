<?php

declare(strict_types=1);

namespace App\Support\Filament\ProjectManagement;

use App\Models\ProjectManagement\Project;
use App\Models\ProjectManagement\Subproject;

final class ProjectManagementSubprojectTable
{
    /**
     * @return array{label: string, color: string}
     */
    public static function statusMeta(string $status): array
    {
        return match ($status) {
            'pending' => ['label' => 'Pendiente', 'color' => 'warning'],
            'active' => ['label' => 'Activo', 'color' => 'success'],
            'completed' => ['label' => 'Completado', 'color' => 'gray'],
            default => ['label' => $status, 'color' => 'gray'],
        };
    }

    public static function resolveColor(Subproject $subproject): string
    {
        $project = $subproject->relationLoaded('project')
            ? $subproject->project
            : null;

        if ($project instanceof Project) {
            return ProjectManagementProjectTable::resolveColor($project);
        }

        return ProjectManagementProjectAppearance::DEFAULT_COLOR;
    }

    public static function resolveIcon(Subproject $subproject): string
    {
        $project = $subproject->relationLoaded('project')
            ? $subproject->project
            : null;

        if ($project instanceof Project) {
            return ProjectManagementProjectTable::resolveIcon($project);
        }

        return 'heroicon-o-rectangle-stack';
    }

    /**
     * @return array{
     *     percent: int|null,
     *     label: string,
     *     tone: string,
     *     done: int,
     *     open: int,
     *     total: int
     * }
     */
    public static function workloadMeta(Subproject $subproject): array
    {
        $total = (int) ($subproject->activities_count ?? 0);
        $done = (int) ($subproject->activities_done_count ?? 0);
        $open = (int) ($subproject->activities_open_count ?? max(0, $total - $done));

        if ($total === 0) {
            return [
                'percent' => null,
                'label' => 'Sin actividades vinculadas',
                'tone' => 'muted',
                'done' => 0,
                'open' => 0,
                'total' => 0,
            ];
        }

        $percent = (int) round(($done / $total) * 100);
        $percent = max(0, min(100, $percent));

        $tone = match (true) {
            $subproject->status === 'completed' => 'success',
            $percent >= 100 => 'success',
            $open > 0 && $subproject->status === 'pending' => 'warning',
            default => 'primary',
        };

        $label = match (true) {
            $subproject->status === 'completed' => 'Fase completada',
            $percent >= 100 => 'Todas las actividades cerradas',
            $done === 0 => 'Sin avance en actividades',
            default => "{$done} de {$total} actividades cerradas",
        };

        return [
            'percent' => $percent,
            'label' => $label,
            'tone' => $tone,
            'done' => $done,
            'open' => $open,
            'total' => $total,
        ];
    }
}
