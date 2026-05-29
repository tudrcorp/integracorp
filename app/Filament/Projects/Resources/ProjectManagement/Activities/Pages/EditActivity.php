<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Activities\Pages;

use App\Filament\Projects\Resources\ProjectManagement\Activities\ActivityResource;
use App\Filament\Projects\Resources\ProjectManagement\Activities\Concerns\InteractsWithActivityAssignmentForm;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditActivity extends EditRecord
{
    use InteractsWithActivityAssignmentForm;

    protected static string $resource = ActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->hydrateActivityAssignmentFormData($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->normalizeActivityAssignmentFormData($data);
    }
}
