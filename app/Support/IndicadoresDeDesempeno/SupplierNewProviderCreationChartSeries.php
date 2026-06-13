<?php

declare(strict_types=1);

namespace App\Support\IndicadoresDeDesempeno;

use App\Models\DoctorNurse;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

final class SupplierNewProviderCreationChartSeries
{
    public const TYPE_JURIDICOS = 'juridicos';

    public const TYPE_NATURALES = 'naturales';

    public const LABEL_JURIDICOS = 'Proveedores jurídicos';

    public const LABEL_NATURALES = 'Proveedores naturales';

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
     * @return array<string, int>
     */
    private static function countsByCollaboratorForTipo(string $tipo, ?int $year, ?string $from = null, ?string $to = null): array
    {
        $aggregates = self::queryForTipo($tipo)
            ->tap(fn (Builder $query): Builder => IndicadoresDeDesempenoPeriodFilter::apply($query, 'created_at', $year, $from, $to))
            ->tap(fn (Builder $query): Builder => self::applyCollaboratorFilter($query))
            ->tap(fn (Builder $query): Builder => self::applyEmailFilter($query))
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
     * @param  Builder<Supplier>|Builder<DoctorNurse>  $query
     * @return Builder<Supplier>|Builder<DoctorNurse>
     */
    private static function applyCollaboratorFilter(Builder $query): Builder
    {
        return $query
            ->whereNotNull('created_by')
            ->whereRaw("NULLIF(TRIM(created_by), '') IS NOT NULL");
    }

    /**
     * @param  Builder<Supplier>|Builder<DoctorNurse>  $query
     * @return Builder<Supplier>|Builder<DoctorNurse>
     */
    private static function applyEmailFilter(Builder $query): Builder
    {
        return $query
            ->whereNotNull('correo_principal')
            ->whereRaw("NULLIF(TRIM(correo_principal), '') IS NOT NULL");
    }

    /**
     * @return Builder<Supplier>|Builder<DoctorNurse>
     */
    private static function queryForTipo(string $tipo): Builder
    {
        return match ($tipo) {
            self::TYPE_JURIDICOS => Supplier::query(),
            self::TYPE_NATURALES => DoctorNurse::query(),
            default => throw new InvalidArgumentException("Tipo de proveedor inválido: {$tipo}"),
        };
    }
}
