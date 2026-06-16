<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\PlanGenerators\Pages;

use App\Filament\Business\Resources\PlanGenerators\Pages\Concerns\InteractsWithPlanGeneratorMatrix;
use App\Filament\Business\Resources\PlanGenerators\Pages\Concerns\ValidatesPlanGeneratorPopulation;
use App\Filament\Business\Resources\PlanGenerators\Pages\Concerns\ValidatesPlanGeneratorQuotation;
use App\Filament\Business\Resources\PlanGenerators\PlanGeneratorResource;
use App\Support\PlanGenerators\PlanGeneratorPersistence;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreatePlanGenerator extends CreateRecord
{
    use InteractsWithPlanGeneratorMatrix;
    use ValidatesPlanGeneratorPopulation;
    use ValidatesPlanGeneratorQuotation;

    protected static string $resource = PlanGeneratorResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->normalizeMatrixFormState($data);
        $this->assertPlanGeneratorPopulationTotalsMatch();
        $this->assertPlanGeneratorQuotationBodyIsValid();
        $data['created_by'] = Auth::user()?->name;

        unset($data['columns'], $data['rows'], $data['rate_rows'], $data['quotation_pages']);

        return $data;
    }

    protected function afterCreate(): void
    {
        PlanGeneratorPersistence::syncFromFormState(
            $this->getRecord(),
            $this->matrixFormStateForPersistence(),
        );
    }

    protected function getRedirectUrl(): string
    {
        return PlanGeneratorResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
