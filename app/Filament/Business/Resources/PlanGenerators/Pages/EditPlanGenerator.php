<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\PlanGenerators\Pages;

use App\Filament\Business\Resources\PlanGenerators\Pages\Concerns\InteractsWithPlanGeneratorMatrix;
use App\Filament\Business\Resources\PlanGenerators\Pages\Concerns\InteractsWithPlanGeneratorQuotationGallery;
use App\Filament\Business\Resources\PlanGenerators\Pages\Concerns\ValidatesPlanGeneratorPopulation;
use App\Filament\Business\Resources\PlanGenerators\Pages\Concerns\ValidatesPlanGeneratorQuotation;
use App\Filament\Business\Resources\PlanGenerators\PlanGeneratorResource;
use App\Support\PlanGenerators\PlanGeneratorPersistence;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPlanGenerator extends EditRecord
{
    use InteractsWithPlanGeneratorMatrix;
    use InteractsWithPlanGeneratorQuotationGallery;
    use ValidatesPlanGeneratorPopulation;
    use ValidatesPlanGeneratorQuotation;

    protected static string $resource = PlanGeneratorResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->normalizeMatrixFormState(
            array_merge($data, PlanGeneratorPersistence::formStateFromModel($this->getRecord())),
        );
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = $this->normalizeMatrixFormState($data);
        $this->assertPlanGeneratorPopulationTotalsMatch();
        $this->assertPlanGeneratorQuotationBodyIsValid();

        unset($data['columns'], $data['rows'], $data['rate_rows'], $data['quotation_pages']);

        return $data;
    }

    protected function afterSave(): void
    {
        PlanGeneratorPersistence::syncFromFormState(
            $this->getRecord(),
            $this->matrixFormStateForPersistence(),
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
