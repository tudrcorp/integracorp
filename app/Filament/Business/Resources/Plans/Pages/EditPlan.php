<?php

namespace App\Filament\Business\Resources\Plans\Pages;

use App\Filament\Business\Resources\Plans\PlanResource;
use App\Support\PlanCreationPersistence;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPlan extends EditRecord
{
    protected static string $resource = PlanResource::class;

    /** @var array<string, mixed> */
    protected array $pendingFormData = [];

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return array_merge($data, PlanCreationPersistence::hydrateFormData($this->getRecord()));
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingFormData = $data;

        unset($data['created_by']);

        return PlanCreationPersistence::preparePlanAttributes($data);
    }

    protected function afterSave(): void
    {
        PlanCreationPersistence::syncRelations($this->getRecord(), $this->pendingFormData);
    }
}
