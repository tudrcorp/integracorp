<?php

declare(strict_types=1);

namespace App\Support\Charts;

use Illuminate\Support\Collection;

final class TopAgencySalesMonthComparison
{
    /**
     * Ordena por ventas del mes actual (desempate: mes anterior) y devuelve como máximo N agencias.
     *
     * @param  Collection<int, object{agency_code: int|string, label: string, total: scalar}>  $currentMonthRows
     * @param  Collection<int, object{agency_code: int|string, label: string, total: scalar}>  $previousMonthRows
     * @return Collection<int, array{agency_code: string, label: string, current: float, previous: float}>
     */
    public static function mergeAndTakeTopByCurrentMonth(
        Collection $currentMonthRows,
        Collection $previousMonthRows,
        int $limit = 10,
    ): Collection {
        $limit = max(1, $limit);

        $currentByCode = $currentMonthRows->keyBy(
            fn ($row): string => self::normalizeAgencyCode($row->agency_code ?? null),
        );
        $previousByCode = $previousMonthRows->keyBy(
            fn ($row): string => self::normalizeAgencyCode($row->agency_code ?? null),
        );

        $codes = $currentByCode->keys()->merge($previousByCode->keys())->unique()->values();

        return $codes
            ->map(function (string $agencyCode) use ($currentByCode, $previousByCode): array {
                $c = $currentByCode->get($agencyCode);
                $p = $previousByCode->get($agencyCode);
                $current = $c ? (float) $c->total : 0.0;
                $previous = $p ? (float) $p->total : 0.0;

                return [
                    'agency_code' => $agencyCode,
                    'label' => (string) ($c->label ?? $p->label ?? $agencyCode ?: 'Sin agencia'),
                    'current' => $current,
                    'previous' => $previous,
                ];
            })
            ->filter(fn (array $row): bool => $row['current'] > 0.0)
            ->sort(function (array $a, array $b): int {
                if ($a['current'] !== $b['current']) {
                    return $b['current'] <=> $a['current'];
                }

                return $b['previous'] <=> $a['previous'];
            })
            ->values()
            ->take($limit);
    }

    /**
     * Ordena por total descendente y toma como máximo N agencias con ventas > 0.
     *
     * @param  Collection<int, object{agency_code: int|string, label: string, total: scalar}>  $rows
     * @return Collection<int, array{agency_code: string, label: string, total: float}>
     */
    public static function takeTopByTotal(Collection $rows, int $limit = 10): Collection
    {
        $limit = max(1, $limit);

        return $rows
            ->map(function (object $row): array {
                $agencyCode = self::normalizeAgencyCode($row->agency_code ?? null);

                return [
                    'agency_code' => $agencyCode,
                    'label' => (string) ($row->label ?? $agencyCode ?: 'Sin agencia'),
                    'total' => (float) $row->total,
                ];
            })
            ->filter(fn (array $row): bool => $row['total'] > 0.0)
            ->sortByDesc('total')
            ->values()
            ->take($limit);
    }

    private static function normalizeAgencyCode(mixed $code): string
    {
        return strtoupper(trim((string) ($code ?? '')));
    }
}
