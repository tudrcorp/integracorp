<?php

declare(strict_types=1);

namespace App\Support\IndicadoresDeDesempeno;

use App\Models\DoctorNurseObservacion;
use App\Models\SupplierObservacion;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

final class SupplierObservationsChartSeries
{
    public const TYPE_JURIDICOS = 'juridicos';

    public const TYPE_NATURALES = 'naturales';

    public const LABEL_JURIDICOS = 'Proveedores jurídicos';

    public const LABEL_NATURALES = 'Proveedores naturales';

    /**
     * Responsables con observaciones en jurídicos y/o naturales (misma lista para todos los gráficos).
     *
     * @return list<string>
     */
    public static function collaboratorLabels(?int $year = null, ?string $from = null, ?string $to = null): array
    {
        return self::groupedByCollaborator($year, $from, $to)['labels'];
    }

    /**
     * @return array{labels: list<string>, juridicos: list<int>, naturales: list<int>}
     */
    public static function groupedByCollaborator(?int $year = null, ?string $from = null, ?string $to = null): array
    {
        /** @var array<string, int> $juridicos */
        $juridicos = self::countsByCollaboratorForTipo(self::TYPE_JURIDICOS, $year, $from, $to);

        /** @var array<string, int> $naturales */
        $naturales = self::countsByCollaboratorForTipo(self::TYPE_NATURALES, $year, $from, $to);

        $collaborators = collect(array_keys($juridicos))
            ->merge(array_keys($naturales))
            ->unique()
            ->sort(function (string $left, string $right) use ($juridicos, $naturales): int {
                $leftTotal = ($juridicos[$left] ?? 0) + ($naturales[$left] ?? 0);
                $rightTotal = ($juridicos[$right] ?? 0) + ($naturales[$right] ?? 0);

                if ($leftTotal !== $rightTotal) {
                    return $rightTotal <=> $leftTotal;
                }

                return strcmp($left, $right);
            })
            ->values()
            ->all();

        if ($collaborators === []) {
            return [
                'labels' => [],
                'juridicos' => [],
                'naturales' => [],
            ];
        }

        $juridicosData = [];
        $naturalesData = [];

        foreach ($collaborators as $collaborator) {
            $juridicosData[] = (int) ($juridicos[$collaborator] ?? 0);
            $naturalesData[] = (int) ($naturales[$collaborator] ?? 0);
        }

        return [
            'labels' => $collaborators,
            'juridicos' => $juridicosData,
            'naturales' => $naturalesData,
        ];
    }

    /**
     * @return array{labels: list<string>, juridicos: list<int>, naturales: list<int>}
     */
    public static function groupedByMonth(int $year, ?string $collaborator = null): array
    {
        return [
            'labels' => IndicadoresDeDesempenoTimeBuckets::monthLabels(),
            'juridicos' => IndicadoresDeDesempenoTimeBuckets::fillMonthlyTotals(
                self::countsByMonthForTipo(self::TYPE_JURIDICOS, $year, $collaborator),
            ),
            'naturales' => IndicadoresDeDesempenoTimeBuckets::fillMonthlyTotals(
                self::countsByMonthForTipo(self::TYPE_NATURALES, $year, $collaborator),
            ),
        ];
    }

    /**
     * @return array{labels: list<string>, juridicos: list<int>, naturales: list<int>}
     */
    public static function groupedByWeek(int $year, int $month, ?string $collaborator = null): array
    {
        return [
            'labels' => IndicadoresDeDesempenoTimeBuckets::weekLabels($year, $month),
            'juridicos' => IndicadoresDeDesempenoTimeBuckets::fillWeeklyTotals(
                $year,
                $month,
                self::countsByWeekForTipo(self::TYPE_JURIDICOS, $year, $month, $collaborator),
            ),
            'naturales' => IndicadoresDeDesempenoTimeBuckets::fillWeeklyTotals(
                $year,
                $month,
                self::countsByWeekForTipo(self::TYPE_NATURALES, $year, $month, $collaborator),
            ),
        ];
    }

    /**
     * @return array{labels: list<string>, juridicos: list<int>, naturales: list<int>}
     */
    public static function groupedByCollaboratorForWeek(
        int $year,
        int $month,
        int $week,
        ?string $collaborator = null,
    ): array {
        $range = IndicadoresDeDesempenoTimeBuckets::weekDateRange($year, $month, $week);

        return IndicadoresDeDesempenoCollaboratorAccess::filterDualSeriesToCollaborator(
            self::groupedByCollaborator(from: $range['from'], to: $range['to']),
            $collaborator,
        );
    }

    /**
     * @return array<string, int>
     */
    private static function countsByCollaboratorForTipo(string $tipo, ?int $year, ?string $from = null, ?string $to = null): array
    {
        $aggregates = self::queryForTipo($tipo)
            ->tap(fn (Builder $query): Builder => IndicadoresDeDesempenoPeriodFilter::apply($query, 'created_at', $year, $from, $to))
            ->tap(fn (Builder $query): Builder => self::applyCollaboratorFilter($query))
            ->selectRaw('TRIM(created_by) as collaborator, COUNT(*) as total')
            ->groupByRaw('TRIM(created_by)')
            ->orderByDesc('total')
            ->orderBy('collaborator')
            ->get();

        $counts = [];

        foreach ($aggregates as $row) {
            $counts[(string) $row->collaborator] = (int) $row->total;
        }

        return $counts;
    }

    /**
     * @return array<int, int>
     */
    private static function countsByMonthForTipo(string $tipo, int $year, ?string $collaborator): array
    {
        $aggregates = self::scopedQueryForTipo($tipo, $collaborator)
            ->whereYear('created_at', $year)
            ->selectRaw('MONTH(created_at) as bucket, COUNT(*) as total')
            ->groupByRaw('MONTH(created_at)')
            ->get();

        $counts = [];

        foreach ($aggregates as $row) {
            $counts[(int) $row->bucket] = (int) $row->total;
        }

        return $counts;
    }

    /**
     * @return array<int, int>
     */
    private static function countsByWeekForTipo(string $tipo, int $year, int $month, ?string $collaborator): array
    {
        $aggregates = self::scopedQueryForTipo($tipo, $collaborator)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->selectRaw('FLOOR((DAY(created_at) - 1) / 7) + 1 as bucket, COUNT(*) as total')
            ->groupByRaw('FLOOR((DAY(created_at) - 1) / 7) + 1')
            ->get();

        $counts = [];

        foreach ($aggregates as $row) {
            $counts[(int) $row->bucket] = (int) $row->total;
        }

        return $counts;
    }

    /**
     * @return Builder<SupplierObservacion>|Builder<DoctorNurseObservacion>
     */
    private static function scopedQueryForTipo(string $tipo, ?string $collaborator): Builder
    {
        $query = self::queryForTipo($tipo)->tap(fn (Builder $builder): Builder => self::applyCollaboratorFilter($builder));

        if ($collaborator !== null) {
            $query->whereRaw('TRIM(created_by) = ?', [$collaborator]);
        }

        return $query;
    }

    /**
     * @param  Builder<SupplierObservacion>|Builder<DoctorNurseObservacion>  $query
     * @return Builder<SupplierObservacion>|Builder<DoctorNurseObservacion>
     */
    private static function applyCollaboratorFilter(Builder $query): Builder
    {
        return $query
            ->whereNotNull('created_by')
            ->whereRaw("NULLIF(TRIM(created_by), '') IS NOT NULL");
    }

    /**
     * @return Builder<SupplierObservacion>|Builder<DoctorNurseObservacion>
     */
    private static function queryForTipo(string $tipo): Builder
    {
        return match ($tipo) {
            self::TYPE_JURIDICOS => SupplierObservacion::query(),
            self::TYPE_NATURALES => DoctorNurseObservacion::query(),
            default => throw new InvalidArgumentException("Tipo de proveedor inválido: {$tipo}"),
        };
    }
}
