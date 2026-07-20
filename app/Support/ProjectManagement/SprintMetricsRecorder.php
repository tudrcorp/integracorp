<?php

declare(strict_types=1);

namespace App\Support\ProjectManagement;

use App\Models\ProjectManagement\Activity;
use App\Models\ProjectManagement\Sprint;
use App\Models\ProjectManagement\SprintDailyMetric;
use Illuminate\Support\Carbon;

class SprintMetricsRecorder
{
    public function recordForSprint(?Sprint $sprint, Carbon|string|null $date = null): ?SprintDailyMetric
    {
        if (! $sprint instanceof Sprint) {
            return null;
        }

        $metricDate = Carbon::parse($date ?? now())->toDateString();

        $points = Activity::query()
            ->where('sprint_id', $sprint->getKey())
            ->selectRaw('COALESCE(SUM(story_points), 0) as committed_points')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'done' THEN story_points ELSE 0 END), 0) as completed_points")
            ->selectRaw("COALESCE(SUM(CASE WHEN status != 'done' THEN story_points ELSE 0 END), 0) as remaining_points")
            ->first();

        return SprintDailyMetric::query()->updateOrCreate(
            [
                'sprint_id' => $sprint->getKey(),
                'date' => $metricDate,
            ],
            [
                'committed_points' => (int) ($points?->committed_points ?? 0),
                'completed_points' => (int) ($points?->completed_points ?? 0),
                'remaining_points' => (int) ($points?->remaining_points ?? 0),
            ],
        );
    }

    public function recordForActivity(Activity $activity): ?SprintDailyMetric
    {
        if ($activity->sprint_id === null) {
            return null;
        }

        $sprint = $activity->relationLoaded('sprint')
            ? $activity->sprint
            : Sprint::query()->find($activity->sprint_id);

        return $this->recordForSprint($sprint);
    }

    public function syncCompletedAt(Activity $activity): void
    {
        if ($activity->status === 'done' && $activity->completed_at === null) {
            $activity->forceFill(['completed_at' => now()])->saveQuietly();

            return;
        }

        if ($activity->status !== 'done' && $activity->completed_at !== null) {
            $activity->forceFill(['completed_at' => null])->saveQuietly();
        }
    }
}
