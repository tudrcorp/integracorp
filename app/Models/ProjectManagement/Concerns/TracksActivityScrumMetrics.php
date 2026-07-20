<?php

declare(strict_types=1);

namespace App\Models\ProjectManagement\Concerns;

use App\Support\ProjectManagement\BacklogOrdering;
use App\Support\ProjectManagement\SprintMetricsRecorder;

trait TracksActivityScrumMetrics
{
    public static function bootTracksActivityScrumMetrics(): void
    {
        static::saved(function ($activity): void {
            $recorder = new SprintMetricsRecorder;
            $recorder->syncCompletedAt($activity);

            if ($activity->wasChanged(['status', 'story_points', 'sprint_id'])) {
                $recorder->recordForActivity($activity);

                $previousSprintId = $activity->getOriginal('sprint_id');
                if ($previousSprintId && (int) $previousSprintId !== (int) $activity->sprint_id) {
                    $recorder->recordForSprint(
                        \App\Models\ProjectManagement\Sprint::query()->find($previousSprintId),
                    );
                }
            }

            if ($activity->wasChanged('sprint_id') && $activity->sprint_id === null) {
                (new BacklogOrdering)->assignNextOrder($activity);
            }
        });

        static::created(function ($activity): void {
            if ($activity->sprint_id === null) {
                (new BacklogOrdering)->assignNextOrder($activity);
            }

            (new SprintMetricsRecorder)->recordForActivity($activity);
        });
    }
}
