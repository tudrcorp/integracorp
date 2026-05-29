<?php

namespace App\Filament\Projects\Resources\ProjectManagement\Groups\Pages;

use App\Filament\Projects\Resources\ProjectManagement\Groups\Concerns\InteractsWithGroupCollaboratorIds;
use App\Filament\Projects\Resources\ProjectManagement\Groups\GroupResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditGroup extends EditRecord
{
    use InteractsWithGroupCollaboratorIds;

    protected static string $resource = GroupResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->normalizeGroupCollaboratorIds($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
