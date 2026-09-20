<?php

declare(strict_types=1);

namespace App\Support\TuDrQuote;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Traduce las filas de una cotización de INTEGRACORP al payload de `/render`.
 *
 * Tanto la cotización individual como la corporativa guardan su cálculo **por
 * rango de edad con población** (`total_persons`), no persona a persona: una
 * corporativa llega a 2.681 asegurados y mandarlos uno por uno sería un
 * payload enorme y una segunda fuente de verdad para el cálculo. Por eso el
 * portal calcula —como siempre— y el microservicio solo dibuja.
 */
final class QuoteRenderPayload
{
    /**
     * @var array<int, string>
     */
    private const PLAN_NAMES = [
        1 => 'Paquete Inicial',
        2 => 'Paquete Ideal',
        3 => 'Paquete Especial',
    ];

    /**
     * @param  list<int>  $planIds  planes a incluir; vacío = todos los de la cotización
     * @return array<string, mixed>|null null si no hay nada que dibujar
     */
    public static function forIndividualQuote(int $quoteId, string $code, string $titular, string $agente, string $fecha, array $planIds = []): ?array
    {
        return self::build('detail_individual_quotes', 'individual_quote_id', $quoteId, $code, $titular, $agente, $fecha, $planIds);
    }

    /**
     * @param  list<int>  $planIds
     * @return array<string, mixed>|null
     */
    public static function forCorporateQuote(int $quoteId, string $code, string $titular, string $agente, string $fecha, array $planIds = []): ?array
    {
        return self::build('detail_corporate_quotes', 'corporate_quote_id', $quoteId, $code, $titular, $agente, $fecha, $planIds);
    }

    /**
     * Etiqueta corta de una cobertura: 5000 → «5K», 0 → «0».
     */
    public static function coverageLabel(int $coverage): string
    {
        if ($coverage <= 0) {
            return '0';
        }

        if ($coverage % 1000 === 0) {
            return (string) intdiv($coverage, 1000).'K';
        }

        return (string) $coverage;
    }

    /**
     * Semestral y trimestral con redondeo half-up, como hace el servicio.
     */
    public static function split(float $anual, int $parts): float
    {
        return round($anual / $parts, 2, PHP_ROUND_HALF_UP);
    }

    /**
     * @param  list<int>  $planIds
     * @return array<string, mixed>|null
     */
    private static function build(
        string $table,
        string $foreignKey,
        int $quoteId,
        string $code,
        string $titular,
        string $agente,
        string $fecha,
        array $planIds,
    ): ?array {
        $rows = DB::table($table)
            ->join('age_ranges', 'age_ranges.id', '=', $table.'.age_range_id')
            ->leftJoin('coverages', 'coverages.id', '=', $table.'.coverage_id')
            ->where($table.'.'.$foreignKey, $quoteId)
            ->when($planIds !== [], fn ($query) => $query->whereIn($table.'.plan_id', $planIds))
            ->orderBy($table.'.plan_id')
            ->orderBy('age_ranges.id')
            ->orderBy('coverages.price')
            ->get([
                $table.'.plan_id',
                $table.'.total_persons',
                $table.'.fee',
                'age_ranges.id as age_range_id',
                'age_ranges.range as rango',
                'coverages.price as cobertura',
            ]);

        if ($rows->isEmpty()) {
            return null;
        }

        $planes = [];

        foreach ($rows->groupBy('plan_id') as $planId => $planRows) {
            $plan = self::buildPlan((int) $planId, collect($planRows));

            if ($plan !== null) {
                $planes[] = $plan;
            }
        }

        if ($planes === []) {
            return null;
        }

        return [
            'control' => QuoteControlNumber::fromCode($code),
            'titular' => $titular,
            'agente' => $agente,
            'fecha' => $fecha,
            'planes' => $planes,
        ];
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<string, mixed>|null
     */
    private static function buildPlan(int $planId, Collection $rows): ?array
    {
        $slug = QuoteFeeMatrix::planSlug($planId);

        /** Un plan fuera de Inicial/Ideal/Especial no lo dibuja el servicio. */
        if ($slug === null) {
            return null;
        }

        $coberturas = $rows
            ->map(fn (object $row): int => (int) round((float) ($row->cobertura ?? 0)))
            ->unique()
            ->sort()
            ->values();

        $filas = [];
        $grupalAnual = array_fill(0, max($coberturas->count(), 1), 0.0);

        foreach ($rows->groupBy('age_range_id') as $rangeRows) {
            /** @var Collection<int, object> $rangeRows */
            $first = $rangeRows->first();
            $poblacion = (int) $first->total_persons;

            $tarifas = [];

            foreach ($coberturas as $index => $cobertura) {
                $match = $rangeRows->first(
                    fn (object $row): bool => (int) round((float) ($row->cobertura ?? 0)) === $cobertura
                );

                $tarifa = $match !== null ? round((float) $match->fee, 2) : 0.0;
                $tarifas[] = $tarifa;
                $grupalAnual[$index] += $tarifa * $poblacion;
            }

            $fila = [
                'rango' => self::formatRange((string) $first->rango),
                'poblacion' => $poblacion,
                'tarifas' => $tarifas,
            ];

            /** El Inicial tiene tarifa única: el servicio la lee de `tarifa`. */
            if ($coberturas->count() === 1 && $coberturas->first() === 0) {
                $fila['tarifa'] = $tarifas[0] ?? 0.0;
            }

            $filas[] = $fila;
        }

        $grupalAnual = array_map(fn (float $total): float => round($total, 2), $grupalAnual);

        $plan = [
            'plan' => $slug,
            'nombre' => self::PLAN_NAMES[$planId] ?? ucfirst($slug),
            'coberturas' => $coberturas->map(fn (int $coverage): string => self::coverageLabel($coverage))->all(),
            'coberturas_usd' => $coberturas->all(),
            'filas' => $filas,
            'grupal_anual' => $grupalAnual,
            'grupal_semestral' => array_map(fn (float $anual): float => self::split($anual, 2), $grupalAnual),
            'grupal_trimestral' => array_map(fn (float $anual): float => self::split($anual, 4), $grupalAnual),
        ];

        if ($coberturas->count() === 1 && $coberturas->first() === 0) {
            $plan['total_anual'] = $grupalAnual[0] ?? 0.0;
            $plan['total_semestral'] = self::split($plan['total_anual'], 2);
            $plan['total_trimestral'] = self::split($plan['total_anual'], 4);
        }

        return $plan;
    }

    /**
     * «0 A 30» y «31 a 65» conviven en `age_ranges`; el PDF los muestra igual.
     */
    private static function formatRange(string $range): string
    {
        $range = trim(preg_replace('/\s+/', ' ', $range) ?? $range);
        $range = (string) preg_replace('/\bA\b/', 'a', $range);

        return str_contains(mb_strtolower($range), 'año') ? $range : $range.' años';
    }
}
