<?php

declare(strict_types=1);

namespace App\Support\PlanGenerators;

use App\Enums\PlanGeneratorPopulationUnit;
use Illuminate\Validation\ValidationException;

final class PlanGeneratorPopulationValidator
{
    public static function parsePopulationTotal(string $populationSummary): ?int
    {
        if (preg_match('/(\d+)/', $populationSummary, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * @param  array<string, array<string, mixed>>  $rateRows
     */
    public static function sumRateRowPopulations(array $rateRows): int
    {
        $sum = 0;

        foreach ($rateRows as $rateRow) {
            if (! is_array($rateRow)) {
                continue;
            }

            $sum += max(0, (int) ($rateRow['population'] ?? 0));
        }

        return $sum;
    }

    /**
     * @param  array<string, array<string, mixed>>  $rateRows
     */
    public static function validationMessage(string $populationSummary, array $rateRows, mixed $populationUnit = null): ?string
    {
        $unit = PlanGeneratorPopulationUnit::resolve($populationUnit);
        $expectedTotal = self::parsePopulationTotal($populationSummary);

        if ($expectedTotal === null) {
            return "Indique un número válido en {$unit->label()} (ej: 101).";
        }

        $rateRowsTotal = self::sumRateRowPopulations($rateRows);

        if ($expectedTotal !== $rateRowsTotal) {
            return "El total de {$unit->label()} ({$expectedTotal}) debe ser igual a la suma por rango etario ({$rateRowsTotal}).";
        }

        return null;
    }

    /**
     * @param  array<string, array<string, mixed>>  $rateRows
     */
    public static function helperText(string $populationSummary, array $rateRows, mixed $populationUnit = null): string
    {
        $unit = PlanGeneratorPopulationUnit::resolve($populationUnit);
        $rateRowsTotal = self::sumRateRowPopulations($rateRows);

        return "Suma total por rangos etarios: {$rateRowsTotal} {$unit->quantityLabel()}. Debe coincidir con el total indicado aquí.";
    }

    /**
     * @param  array<string, array<string, mixed>>  $rateRows
     */
    public static function assertMatchesOrFail(string $populationSummary, array $rateRows, mixed $populationUnit = null): void
    {
        $message = self::validationMessage($populationSummary, $rateRows, $populationUnit);

        if ($message === null) {
            return;
        }

        throw ValidationException::withMessages([
            'population_summary' => $message,
        ]);
    }
}
