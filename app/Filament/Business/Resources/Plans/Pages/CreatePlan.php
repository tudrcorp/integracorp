<?php

namespace App\Filament\Business\Resources\Plans\Pages;

use App\Filament\Business\Resources\Plans\PlanResource;
use App\Support\PlanCreationPersistence;
use Filament\Resources\Pages\CreateRecord;

class CreatePlan extends CreateRecord
{
    protected static string $resource = PlanResource::class;

    /** @var array<string, mixed> */
    protected array $pendingFormData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingFormData = $data;

        return PlanCreationPersistence::preparePlanAttributes($data);
    }

    protected function afterCreate(): void
    {
        PlanCreationPersistence::persistRelations($this->getRecord(), $this->pendingFormData);
    }
}
