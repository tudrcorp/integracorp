<?php

declare(strict_types=1);

namespace App\Support\ProjectManagement;

use App\Enums\ProjectManagement\SprintStatus;
use App\Models\ProjectManagement\Activity;
use App\Models\ProjectManagement\Project;
use App\Models\ProjectManagement\Sprint;
use Illuminate\Support\Collection;

class VelocityCalculator
{
    /**
     * @return array{
     *     average: float,
     *     sprints: list<array{id: int, name: string, points: int}>
     * }
     */
    public function forProject(Project|int $project, int $limit = 5): array
    {
        $projectId = $project instanceof Project ? (int) $project->getKey() : $project;

        /** @var Collection<int, Sprint> $sprints */
        $sprints = Sprint::query()
            ->where('project_id', $projectId)
            ->where('status', SprintStatus::Completed)
            ->orderByDesc('ends_at')
            ->limit(max(1, $limit))
            ->get(['id', 'name']);

        if ($sprints->isEmpty()) {
            return [
                'average' => 0.0,
                'sprints' => [],
            ];
        }

        $pointsBySprint = Activity::query()
            ->whereIn('sprint_id', $sprints->modelKeys())
            ->where('status', 'done')
            ->selectRaw('sprint_id, COALESCE(SUM(story_points), 0) as points')
            ->groupBy('sprint_id')
            ->pluck('points', 'sprint_id');

        $rows = $sprints
            ->map(fn (Sprint $sprint): array => [
                'id' => (int) $sprint->getKey(),
                'name' => (string) $sprint->name,
                'points' => (int) ($pointsBySprint[$sprint->getKey()] ?? 0),
            ])
            ->values()
            ->all();

        $total = array_sum(array_column($rows, 'points'));

        return [
            'average' => round($total / max(1, count($rows)), 1),
            'sprints' => $rows,
        ];
    }
}
