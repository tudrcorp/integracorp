<?php

declare(strict_types=1);

namespace App\Support\ProspectAgents;

use App\Models\ProspectAgent;
use Illuminate\Support\Facades\DB;

/**
 * Agregación de prospectos por colaborador (created_by) para gráficos.
 */
final class ProspectCollaboratorLabels
{
    /**
     * Conteo de prospectos por usuario creador, ordenado de mayor a menor.
     *
     * @return array{labels: list<string>, counts: list<int>}
     */
    public static function prospectCountsOrdered(int $year, ?int $month = null): array
    {
        $rows = ProspectAgent::query()
            ->select([
                'created_by as label',
                DB::raw('COUNT(*) as total'),
            ])
            ->whereNotNull('created_by')
            ->where('created_by', '!=', '')
            ->whereYear('created_at', $year)
            ->when($month, fn ($q) => $q->whereMonth('created_at', $month))
            ->groupBy('created_by')
            ->orderByDesc('total')
            ->get();

        $labels = $rows->pluck('label')->map(fn (?string $name): string => $name ?? 'Sin nombre')->toArray();
        $counts = $rows->pluck('total')->map(fn (mixed $v): int => (int) $v)->toArray();

        return ['labels' => $labels, 'counts' => $counts];
    }
}
