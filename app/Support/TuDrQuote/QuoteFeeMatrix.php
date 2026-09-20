<?php

declare(strict_types=1);

namespace App\Support\TuDrQuote;

use App\Models\Fee;
use Illuminate\Support\Facades\Cache;

/**
 * Matriz de tarifas que INTEGRACORP envía al microservicio.
 *
 * El portal es el maestro de tarifas: el servicio nunca usa las suyas mientras
 * reciba este array. Se cachea diez minutos porque `fees` cambia poco y se
 * consulta en cada propuesta; al editar una tarifa hay que llamar a `flush()`.
 */
final class QuoteFeeMatrix
{
    public const CACHE_KEY = 'tudr-quote:fees-matrix-v1';

    private const CACHE_TTL_SECONDS = 600;

    /**
     * Planes históricos que el microservicio conoce por nombre.
     *
     * @var array<int, string>
     */
    private const PLAN_SLUGS = [
        1 => 'inicial',
        2 => 'ideal',
        3 => 'especial',
    ];

    /**
     * @return list<array{plan: string, cobertura: int, edad_min: int, edad_max: int, tarifa_anual: float}>
     */
    public static function all(): array
    {
        /** @var list<array{plan: string, cobertura: int, edad_min: int, edad_max: int, tarifa_anual: float}> */
        return Cache::remember(
            self::CACHE_KEY,
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            static fn (): array => self::build(),
        );
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function planSlug(int $planId): ?string
    {
        return self::PLAN_SLUGS[$planId] ?? null;
    }

    /**
     * Rango de edades en texto (`"31 a 65"`, `"0 A 30"`) a par de enteros.
     *
     * @return array{0: int, 1: int}
     */
    public static function parseAgeRange(?string $range): array
    {
        preg_match_all('/\d+/', (string) $range, $matches);

        $numbers = array_map('intval', $matches[0] ?? []);

        return match (count($numbers)) {
            0 => [0, 120],
            1 => [$numbers[0], $numbers[0]],
            default => [min($numbers[0], $numbers[1]), max($numbers[0], $numbers[1])],
        };
    }

    /**
     * @return list<array{plan: string, cobertura: int, edad_min: int, edad_max: int, tarifa_anual: float}>
     */
    private static function build(): array
    {
        $rows = Fee::query()
            ->leftJoin('age_ranges', 'age_ranges.id', '=', 'fees.age_range_id')
            ->leftJoin('coverages', 'coverages.id', '=', 'fees.coverage_id')
            ->whereIn('fees.plan_id', array_keys(self::PLAN_SLUGS))
            ->where(function ($query): void {
                $query->whereNull('fees.status')->orWhere('fees.status', '!=', 'INACTIVO');
            })
            ->orderBy('fees.plan_id')
            ->orderBy('age_ranges.id')
            ->orderBy('coverages.price')
            ->get([
                'fees.plan_id',
                'fees.price as tarifa_anual',
                'age_ranges.range as rango',
                'coverages.price as cobertura',
            ]);

        $matrix = [];

        foreach ($rows as $row) {
            $slug = self::planSlug((int) $row->plan_id);

            if ($slug === null) {
                continue;
            }

            [$edadMin, $edadMax] = self::parseAgeRange($row->rango);

            $matrix[] = [
                'plan' => $slug,
                'cobertura' => (int) round((float) ($row->cobertura ?? 0)),
                'edad_min' => $edadMin,
                'edad_max' => $edadMax,
                'tarifa_anual' => round((float) $row->tarifa_anual, 2),
            ];
        }

        return $matrix;
    }
}
