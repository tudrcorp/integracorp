<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Activities\Pages;

use App\Filament\Projects\Resources\ProjectManagement\Activities\ActivityResource;
use App\Filament\Projects\Resources\ProjectManagement\Activities\Concerns\InteractsWithActivityAssignmentForm;
use Filament\Resources\Pages\CreateRecord;

class CreateActivity extends CreateRecord
{
    use InteractsWithActivityAssignmentForm;

    protected static string $resource = ActivityResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->normalizeActivityAssignmentFormData($data);
    }
}
