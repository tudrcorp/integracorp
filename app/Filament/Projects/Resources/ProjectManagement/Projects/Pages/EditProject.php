<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Projects\Pages;

use App\Filament\Projects\Resources\ProjectManagement\Projects\Concerns\InteractsWithProjectScrumRoles;
use App\Filament\Projects\Resources\ProjectManagement\Projects\ProjectResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProject extends EditRecord
{
    use InteractsWithProjectScrumRoles;

    protected static string $resource = ProjectResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $roles = $this->getRecord()->scrumRoles;

        $data['product_owner_id'] = $roles?->product_owner_id;
        $data['scrum_master_id'] = $roles?->scrum_master_id;

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
