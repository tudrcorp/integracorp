<?php

declare(strict_types=1);

namespace App\Support\Filament\ProjectManagement;

use App\Filament\Projects\Resources\ProjectManagement\Subprojects\SubprojectResource;
use App\Models\ProjectManagement\Project;
use App\Models\ProjectManagement\Subproject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class ProjectManagementProjectInfolistDisplay
{
    /**
     * @return array{
     *     project_name: string,
     *     project_color: string,
     *     start_label: string,
     *     end_label: string,
     *     has_start: bool,
     *     has_end: bool,
     *     timeline_label: string,
     *     timeline_tone: string
     * }
     */
    public static function datesPayload(Project $record): array
    {
        $timeline = ProjectManagementProjectTable::timelineMeta($record);

        return [
            'project_name' => (string) $record->name,
            'project_color' => ProjectManagementProjectTable::resolveColor($record),
            'start_label' => $timeline['start_label'] ?? '—',
            'end_label' => $timeline['end_label'] ?? '—',
            'has_start' => filled($timeline['start_label'] ?? null),
            'has_end' => filled($timeline['end_label'] ?? null),
            'timeline_label' => $timeline['label'],
            'timeline_tone' => $timeline['tone'],
        ];
    }

    /**
     * @return array{
     *     project_name: string,
     *     project_color: string,
     *     description: string,
     *     has_description: bool
     * }
     */
    public static function descriptionPayload(Project $record): array
    {
        $description = ProjectManagementActivityInfolistDisplay::normalizeDescriptionText((string) $record->description);

        return [
            'project_name' => (string) $record->name,
            'project_color' => ProjectManagementProjectTable::resolveColor($record),
            'description' => $description,
            'has_description' => $description !== '',
        ];
    }

    /**
     * @return array{
     *     project: array{
     *         id: int,
     *         name: string,
     *         color: string,
     *         icon: string,
     *         status: string,
     *         status_label: string,
     *         timeline_label: string,
     *         start_label: string|null,
     *         end_label: string|null
     *     },
     *     stats: array{
     *         subprojects_total: int,
     *         subprojects_active: int,
     *         subprojects_pending: int,
     *         subprojects_completed: int,
     *         activities_total: int,
     *         activities_done: int,
     *         activities_open: int,
     *         overall_percent: int|null
     *     },
     *     subprojects: list<array{
     *         id: int,
     *         name: string,
     *         description: string,
     *         has_description: bool,
     *         status: string,
     *         status_label: string,
     *         status_color: string,
     *         position: int,
     *         view_url: string,
     *         workload: array{
     *             percent: int|null,
     *             label: string,
     *             tone: string,
     *             done: int,
     *             open: int,
     *             total: int
     *         }
     *     }>,
     *     has_subprojects: bool,
     *     create_subproject_url: string,
     *     subprojects_index_url: string
     * }
     */
    public static function flowDiagramPayload(Project $record): array
    {
        $subprojects = self::resolveSubprojects($record);
        $projectColor = ProjectManagementProjectTable::resolveColor($record);
        $projectIcon = ProjectManagementProjectTable::resolveIcon($record);
        $timeline = ProjectManagementProjectTable::timelineMeta($record);
        $projectStatus = ProjectManagementProjectTable::statusMeta((string) $record->status);

        $activitiesTotal = 0;
        $activitiesDone = 0;
        $activitiesOpen = 0;

        $mappedSubprojects = $subprojects->values()->map(function (Subproject $subproject, int $index) use (&$activitiesTotal, &$activitiesDone, &$activitiesOpen): array {
            $status = ProjectManagementSubprojectTable::statusMeta((string) $subproject->status);
            $workload = ProjectManagementSubprojectTable::workloadMeta($subproject);
            $description = ProjectManagementActivityInfolistDisplay::normalizeDescriptionText((string) $subproject->description);

            $activitiesTotal += $workload['total'];
            $activitiesDone += $workload['done'];
            $activitiesOpen += $workload['open'];

            return [
                'id' => (int) $subproject->id,
                'name' => (string) $subproject->name,
                'description' => $description,
                'description_preview' => Str::limit($description, 96),
                'has_description' => $description !== '',
                'status' => (string) $subproject->status,
                'status_label' => $status['label'],
                'status_color' => $status['color'],
                'position' => $index + 1,
                'view_url' => SubprojectResource::getUrl('view', ['record' => $subproject], panel: 'projects'),
                'workload' => $workload,
            ];
        })->all();

        $overallPercent = $activitiesTotal > 0
            ? (int) round(($activitiesDone / $activitiesTotal) * 100)
            : null;

        return [
            'project' => [
                'id' => (int) $record->id,
                'name' => (string) $record->name,
                'color' => $projectColor,
                'icon' => $projectIcon,
                'status' => (string) $record->status,
                'status_label' => $projectStatus['label'],
                'timeline_label' => $timeline['label'],
                'start_label' => $timeline['start_label'],
                'end_label' => $timeline['end_label'],
            ],
            'stats' => [
                'subprojects_total' => $subprojects->count(),
                'subprojects_active' => $subprojects->where('status', 'active')->count(),
                'subprojects_pending' => $subprojects->where('status', 'pending')->count(),
                'subprojects_completed' => $subprojects->where('status', 'completed')->count(),
                'activities_total' => $activitiesTotal,
                'activities_done' => $activitiesDone,
                'activities_open' => $activitiesOpen,
                'overall_percent' => $overallPercent,
            ],
            'subprojects' => $mappedSubprojects,
            'has_subprojects' => $subprojects->isNotEmpty(),
            'create_subproject_url' => SubprojectResource::getUrl('create', panel: 'projects'),
            'subprojects_index_url' => SubprojectResource::getUrl('index', panel: 'projects'),
        ];
    }

    /**
     * @return Collection<int, Subproject>
     */
    private static function resolveSubprojects(Project $record): Collection
    {
        if ($record->relationLoaded('subprojects')) {
            return $record->subprojects
                ->sortBy(fn (Subproject $subproject): string => Str::lower((string) $subproject->name))
                ->values();
        }

        return $record->subprojects()
            ->withCount([
                'activities',
                'activities as activities_done_count' => fn (Builder $activitiesQuery): Builder => $activitiesQuery->where('status', 'done'),
                'activities as activities_open_count' => fn (Builder $activitiesQuery): Builder => $activitiesQuery->where('status', '!=', 'done'),
            ])
            ->orderBy('name')
            ->get();
    }
}
