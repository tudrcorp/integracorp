<?php

declare(strict_types=1);

namespace App\Support\PlanGenerators;

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
    public static function validationMessage(string $populationSummary, array $rateRows): ?string
    {
        $expectedTotal = self::parsePopulationTotal($populationSummary);

        if ($expectedTotal === null) {
            return 'Indique un número de personas válido en Población (ej: 101 personas).';
        }

        $rateRowsTotal = self::sumRateRowPopulations($rateRows);

        if ($expectedTotal !== $rateRowsTotal) {
            return "La población total ({$expectedTotal}) debe ser igual a la suma de población por rango etario ({$rateRowsTotal}).";
        }

        return null;
    }

    /**
     * @param  array<string, array<string, mixed>>  $rateRows
     */
    public static function helperText(string $populationSummary, array $rateRows): string
    {
        $rateRowsTotal = self::sumRateRowPopulations($rateRows);

        return "Suma actual por rangos etarios: {$rateRowsTotal} persona(s). Debe coincidir con el total indicado aquí.";
    }

    /**
     * @param  array<string, array<string, mixed>>  $rateRows
     */
    public static function assertMatchesOrFail(string $populationSummary, array $rateRows): void
    {
        $message = self::validationMessage($populationSummary, $rateRows);

        if ($message === null) {
            return;
        }

        throw ValidationException::withMessages([
            'population_summary' => $message,
        ]);
    }
}
