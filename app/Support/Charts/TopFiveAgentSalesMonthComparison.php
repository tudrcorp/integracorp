<?php

namespace App\Support\Charts;

use Illuminate\Support\Collection;

final class TopFiveAgentSalesMonthComparison
{
    /**
     * Ordena por ventas del mes actual (desempate: mes anterior) y devuelve como máximo 5 agentes.
     *
     * @param  Collection<int, object{agent_id: int|string, label: string, total: scalar}>  $currentMonthRows
     * @param  Collection<int, object{agent_id: int|string, label: string, total: scalar}>  $previousMonthRows
     * @return Collection<int, array{label: string, current: float, previous: float}>
     */
    public static function mergeAndTakeTopFiveByCurrentMonth(
        Collection $currentMonthRows,
        Collection $previousMonthRows,
    ): Collection {
        $currentById = $currentMonthRows->keyBy(fn ($row): int => (int) $row->agent_id);
        $previousById = $previousMonthRows->keyBy(fn ($row): int => (int) $row->agent_id);

        $ids = $currentById->keys()->merge($previousById->keys())->unique()->values();

        return $ids
            ->map(function ($agentId) use ($currentById, $previousById): array {
                $c = $currentById->get($agentId);
                $p = $previousById->get($agentId);
                $current = $c ? (float) $c->total : 0.0;
                $previous = $p ? (float) $p->total : 0.0;

                return [
                    'label' => (string) ($c->label ?? $p->label ?? 'Sin nombre'),
                    'current' => $current,
                    'previous' => $previous,
                ];
            })
            ->filter(fn (array $row): bool => $row['current'] > 0.0 || $row['previous'] > 0.0)
            ->sort(function (array $a, array $b): int {
                if ($a['current'] !== $b['current']) {
                    return $b['current'] <=> $a['current'];
                }

                return $b['previous'] <=> $a['previous'];
            })
            ->values()
            ->take(10);
    }
}
