<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\PlanGenerators\Pages;

use App\Filament\Business\Resources\PlanGenerators\Pages\Concerns\InteractsWithPlanGeneratorMatrix;
use App\Filament\Business\Resources\PlanGenerators\Pages\Concerns\InteractsWithPlanGeneratorQuotationGallery;
use App\Filament\Business\Resources\PlanGenerators\Pages\Concerns\ValidatesPlanGeneratorPopulation;
use App\Filament\Business\Resources\PlanGenerators\Pages\Concerns\ValidatesPlanGeneratorQuotation;
use App\Filament\Business\Resources\PlanGenerators\PlanGeneratorResource;
use App\Support\PlanGenerators\PlanGeneratorConditions;
use App\Support\PlanGenerators\PlanGeneratorPersistence;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditPlanGenerator extends EditRecord
{
    use InteractsWithPlanGeneratorMatrix;
    use InteractsWithPlanGeneratorQuotationGallery;
    use ValidatesPlanGeneratorPopulation;
    use ValidatesPlanGeneratorQuotation;

    protected static string $resource = PlanGeneratorResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = $this->normalizeMatrixFormState(
            array_merge($data, PlanGeneratorPersistence::formStateFromModel($this->getRecord())),
        );

        if ($this->getRecord()->isDerivedQuotation()) {
            $data['conditions'] = PlanGeneratorConditions::formState(
                $data['conditions'] ?? $this->getRecord()->conditions,
            );
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = $this->normalizeMatrixFormState($data);
        $this->assertPlanGeneratorPopulationTotalsMatch();
        $this->assertPlanGeneratorQuotationBodyIsValid();

        unset($data['columns'], $data['rows'], $data['rate_rows'], $data['quotation_pages']);

        if ($this->getRecord()->isDerivedQuotation()) {
            $conditions = PlanGeneratorConditions::normalize($data['conditions'] ?? null);

            if ($conditions === '') {
                throw ValidationException::withMessages([
                    'conditions' => 'Escriba las condiciones. Aparecen debajo del total grupal.',
                ]);
            }

            $data['conditions'] = $conditions;
        } else {
            unset($data['conditions']);
        }

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
