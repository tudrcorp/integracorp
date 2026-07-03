<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\PlanGenerators\Pages\Concerns;

use App\Support\PlanGenerators\PlanGeneratorPopulationValidator;

trait ValidatesPlanGeneratorPopulation
{
    protected function assertPlanGeneratorPopulationTotalsMatch(): void
    {
        $state = $this->matrixFormStateForPersistence();

        PlanGeneratorPopulationValidator::assertMatchesOrFail(
            (string) ($state['population_summary'] ?? ''),
            (array) ($state['rate_rows'] ?? []),
            $state['population_unit'] ?? null,
        );
    }
}
