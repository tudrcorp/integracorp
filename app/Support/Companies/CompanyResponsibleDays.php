<?php

declare(strict_types=1);

namespace App\Support\Companies;

use App\Models\PlanGenerator;
use App\Support\PlanGenerators\PlanGeneratorPopulationValidator;

final class CompanyResponsibleDays
{
    public static function populationTotalFor(?PlanGenerator $plan): ?int
    {
        if (! $plan instanceof PlanGenerator) {
            return null;
        }

        $summary = (string) ($plan->population_summary ?? '');

        if ($summary === '') {
            return null;
        }

        return PlanGeneratorPopulationValidator::parsePopulationTotal($summary);
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $responsibles
     */
    public static function sumContractedDays(array $responsibles): int
    {
        $sum = 0;

        foreach ($responsibles as $responsible) {
            if (! is_array($responsible)) {
                continue;
            }

            $sum += max(0, (int) ($responsible['contracted_days'] ?? 0));
        }

        return $sum;
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $responsibles
     */
    public static function validationMessage(array $responsibles, ?int $populationTotal): ?string
    {
        if ($populationTotal === null) {
            return null;
        }

        $sum = self::sumContractedDays($responsibles);

        if ($sum > $populationTotal) {
            return "La suma de días contratados ({$sum}) no puede exceder la población del plan ({$populationTotal}).";
        }

        return null;
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $responsibles
     */
    public static function helperText(array $responsibles, ?int $populationTotal): string
    {
        $sum = self::sumContractedDays($responsibles);

        if ($populationTotal === null) {
            return "Días contratados acumulados: {$sum}.";
        }

        $remaining = max(0, $populationTotal - $sum);

        return "Días contratados: {$sum} de {$populationTotal} (población del plan). Disponibles: {$remaining}.";
    }
}
