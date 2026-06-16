<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\PlanGenerators\Pages\Concerns;

use App\Support\PlanGenerators\PlanGeneratorQuotationValidator;

trait ValidatesPlanGeneratorQuotation
{
    protected function assertPlanGeneratorQuotationBodyIsValid(): void
    {
        $state = $this->matrixFormStateForPersistence();

        PlanGeneratorQuotationValidator::assertMatchesOrFail(
            filled($state['quotation_page_count'] ?? null) ? (int) $state['quotation_page_count'] : null,
            filled($state['plan_page_number'] ?? null) ? (int) $state['plan_page_number'] : null,
            (array) ($state['quotation_pages'] ?? []),
        );
    }
}
