<?php

declare(strict_types=1);

namespace App\Support\ProspectAgents;

use App\Filament\Business\Resources\ProspectAgents\ProspectAgentLabels;
use App\Models\ProspectAgent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Agregación de prospectos por colaborador (created_by / updated_by) para gráficos.
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

    /**
     * Conteo por estatus atribuible a un colaborador en el período.
     * Incluye: actualizaciones (updated_by + updated_at) y altas iniciales
     * sin updated_by (created_by + created_at). Siempre devuelve los 5 estatus
     * del embudo en orden, aunque el conteo sea 0.
     *
     * @return array{labels: list<string>, counts: list<int>}
     */
    public static function statusChangeCountsForCollaborator(string $collaborator, int $year, ?int $month = null): array
    {
        $empty = [
            'labels' => array_values(ProspectAgentLabels::statusOptions()),
            'counts' => array_fill(0, count(ProspectAgentLabels::statusOptions()), 0),
        ];

        $collaborator = trim($collaborator);
        if ($collaborator === '') {
            return $empty;
        }

        $start = Carbon::create($year, $month ?? 1, 1)->startOfMonth();
        $end = $month
            ? Carbon::create($year, $month, 1)->endOfMonth()
            : Carbon::create($year, 1, 1)->endOfYear();

        $rows = ProspectAgent::query()
            ->where(function ($query) use ($collaborator, $start, $end): void {
                $query->where(function ($updates) use ($collaborator, $start, $end): void {
                    $updates->where('updated_by', $collaborator)
                        ->whereBetween('updated_at', [$start, $end]);
                })->orWhere(function ($creates) use ($collaborator, $start, $end): void {
                    $creates->where('created_by', $collaborator)
                        ->whereBetween('created_at', [$start, $end])
                        ->where(function ($withoutUpdater): void {
                            $withoutUpdater->whereNull('updated_by')
                                ->orWhere('updated_by', '');
                        });
                });
            })
            ->selectRaw('COALESCE(NULLIF(TRIM(status), \'\'), \'Sin estatus\') as status_key')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status_key')
            ->pluck('total', 'status_key');

        /** @var array<string, int> $totalsByCanonical */
        $totalsByCanonical = [];
        $unknown = [];

        foreach ($rows as $statusKey => $total) {
            $key = (string) $statusKey;
            $count = (int) $total;
            $normalized = ProspectAgentLabels::normalizeStatus($key);

            if ($normalized !== null && array_key_exists($normalized, ProspectAgentLabels::statusOptions())) {
                $totalsByCanonical[$normalized] = ($totalsByCanonical[$normalized] ?? 0) + $count;

                continue;
            }

            $unknown[$key] = ($unknown[$key] ?? 0) + $count;
        }

        $labels = [];
        $counts = [];

        foreach (ProspectAgentLabels::statusOptions() as $canonical => $label) {
            $labels[] = $label;
            $counts[] = (int) ($totalsByCanonical[$canonical] ?? 0);
        }

        arsort($unknown);
        foreach ($unknown as $key => $total) {
            if ($total <= 0) {
                continue;
            }

            $labels[] = $key;
            $counts[] = $total;
        }

        return ['labels' => $labels, 'counts' => $counts];
    }
}
