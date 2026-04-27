<?php

declare(strict_types=1);

namespace App\Support\ProspectAgents;

use App\Models\ProspectAgentTask;

/**
 * Series agregadas de tareas por usuario (nombre en texto, alineado con created_by / updated_by).
 */
final class ProspectAgentTaskChartSeries
{
    /**
     * @return array{labels: list<string>, created: list<int>, resolved: list<int>}
     */
    public static function createdAndResolvedByUser(int $year, ?int $month = null): array
    {
        $createdByLabel = ProspectAgentTask::query()
            ->selectRaw('created_by as label, COUNT(*) as total')
            ->whereNotNull('created_by')
            ->where('created_by', '!=', '')
            ->whereYear('created_at', $year)
            ->when($month, fn ($q) => $q->whereMonth('created_at', $month))
            ->groupBy('created_by')
            ->pluck('total', 'label')
            ->map(fn (mixed $n): int => (int) $n)
            ->all();

        $resolvedByLabel = ProspectAgentTask::query()
            ->selectRaw('updated_by as label, COUNT(*) as total')
            ->where('status', 'RESUELTA')
            ->whereNotNull('updated_by')
            ->where('updated_by', '!=', '')
            ->whereYear('updated_at', $year)
            ->when($month, fn ($q) => $q->whereMonth('updated_at', $month))
            ->groupBy('updated_by')
            ->pluck('total', 'label')
            ->map(fn (mixed $n): int => (int) $n)
            ->all();

        $labelKeys = collect(array_keys($createdByLabel))
            ->merge(array_keys($resolvedByLabel))
            ->unique()
            ->values();

        $labels = $labelKeys
            ->sortByDesc(fn (string $label): int => ($createdByLabel[$label] ?? 0) + ($resolvedByLabel[$label] ?? 0))
            ->map(fn (string $label): string => $label !== '' ? $label : 'Sin nombre')
            ->values()
            ->toArray();

        $created = array_map(
            static fn (string $label): int => (int) ($createdByLabel[$label] ?? 0),
            $labels
        );
        $resolved = array_map(
            static fn (string $label): int => (int) ($resolvedByLabel[$label] ?? 0),
            $labels
        );

        return [
            'labels' => $labels,
            'created' => $created,
            'resolved' => $resolved,
        ];
    }
}
