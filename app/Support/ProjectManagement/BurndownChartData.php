<?php

declare(strict_types=1);

namespace App\Support\ProjectManagement;

use App\Models\ProjectManagement\Sprint;
use App\Models\ProjectManagement\SprintDailyMetric;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BurndownChartData
{
    /**
     * @return array{
     *     labels: list<string>,
     *     ideal: list<float|int>,
     *     remaining: list<float|int|null>,
     *     committed_points: int,
     *     remaining_points: int,
     *     completed_points: int
     * }
     */
    public function forSprint(Sprint $sprint): array
    {
        $startsAt = Carbon::parse($sprint->starts_at)->startOfDay();
        $endsAt = Carbon::parse($sprint->ends_at)->startOfDay();

        if ($endsAt->lt($startsAt)) {
            $endsAt = $startsAt->copy();
        }

        /** @var Collection<string, SprintDailyMetric> $metrics */
        $metrics = SprintDailyMetric::query()
            ->where('sprint_id', $sprint->getKey())
            ->whereBetween('date', [$startsAt->toDateString(), $endsAt->toDateString()])
            ->orderBy('date')
            ->get()
            ->keyBy(fn (SprintDailyMetric $metric): string => $metric->date->toDateString());

        $committed = (int) ($metrics->last()?->committed_points
            ?? $metrics->first()?->committed_points
            ?? 0);

        $days = max(1, $startsAt->diffInDays($endsAt));
        $labels = [];
        $ideal = [];
        $remaining = [];

        for ($offset = 0; $offset <= $days; $offset++) {
            $date = $startsAt->copy()->addDays($offset);
            $dateKey = $date->toDateString();
            $labels[] = $date->format('d/m');

            $ideal[] = round(max(0, $committed - (($committed / $days) * $offset)), 2);

            $metric = $metrics->get($dateKey);
            $remaining[] = $metric instanceof SprintDailyMetric
                ? (int) $metric->remaining_points
                : ($date->lte(now()->startOfDay()) ? 0 : null);
        }

        $latest = $metrics->last();

        return [
            'labels' => $labels,
            'ideal' => $ideal,
            'remaining' => $remaining,
            'committed_points' => (int) ($latest?->committed_points ?? $committed),
            'remaining_points' => (int) ($latest?->remaining_points ?? $committed),
            'completed_points' => (int) ($latest?->completed_points ?? 0),
        ];
    }
}
