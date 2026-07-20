<?php

declare(strict_types=1);

namespace App\Support\ProjectManagement;

use App\Models\ProjectManagement\Activity;
use Illuminate\Support\Facades\DB;

class BacklogOrdering
{
    /**
     * @param  list<int>  $orderedActivityIds
     */
    public function reorder(int $projectId, array $orderedActivityIds): void
    {
        $orderedActivityIds = array_values(array_unique(array_map('intval', $orderedActivityIds)));

        if ($orderedActivityIds === []) {
            return;
        }

        DB::transaction(function () use ($projectId, $orderedActivityIds): void {
            $activities = Activity::query()
                ->where('project_id', $projectId)
                ->whereNull('sprint_id')
                ->whereIn('id', $orderedActivityIds)
                ->get(['id'])
                ->keyBy('id');

            foreach ($orderedActivityIds as $position => $activityId) {
                if (! $activities->has($activityId)) {
                    continue;
                }

                Activity::query()
                    ->whereKey($activityId)
                    ->update([
                        'backlog_order' => $position + 1,
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    public function moveUp(Activity $activity): void
    {
        $this->swapWithNeighbor($activity, direction: 'up');
    }

    public function moveDown(Activity $activity): void
    {
        $this->swapWithNeighbor($activity, direction: 'down');
    }

    private function swapWithNeighbor(Activity $activity, string $direction): void
    {
        if ($activity->sprint_id !== null) {
            return;
        }

        $neighborQuery = Activity::query()
            ->where('project_id', $activity->project_id)
            ->whereNull('sprint_id')
            ->whereKeyNot($activity->getKey());

        if ($direction === 'up') {
            $neighbor = (clone $neighborQuery)
                ->where('backlog_order', '<', (int) $activity->backlog_order)
                ->orderByDesc('backlog_order')
                ->first();
        } else {
            $neighbor = (clone $neighborQuery)
                ->where('backlog_order', '>', (int) $activity->backlog_order)
                ->orderBy('backlog_order')
                ->first();
        }

        if (! $neighbor instanceof Activity) {
            return;
        }

        DB::transaction(function () use ($activity, $neighbor): void {
            $currentOrder = $activity->backlog_order;
            $activity->update(['backlog_order' => $neighbor->backlog_order]);
            $neighbor->update(['backlog_order' => $currentOrder]);
        });
    }

    public function assignNextOrder(Activity $activity): void
    {
        if ($activity->sprint_id !== null) {
            return;
        }

        if (filled($activity->backlog_order)) {
            return;
        }

        $nextOrder = ((int) Activity::query()
            ->where('project_id', $activity->project_id)
            ->whereNull('sprint_id')
            ->max('backlog_order')) + 1;

        $activity->update(['backlog_order' => $nextOrder]);
    }
}
