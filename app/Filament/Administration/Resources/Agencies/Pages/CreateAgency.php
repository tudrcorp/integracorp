<?php

namespace App\Filament\Administration\Resources\Agencies\Pages;

use App\Filament\Administration\Resources\Agencies\AgencyResource;
use App\Filament\Shared\CommercialStructure\Concerns\SyncsReferidorAssignments;
use Filament\Resources\Pages\CreateRecord;

class CreateAgency extends CreateRecord
{
    use SyncsReferidorAssignments;

    protected static string $resource = AgencyResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->captureReferidorAssignments($data);
    }

    protected function afterCreate(): void
    {
        $this->persistCapturedReferidorAssignments();
    }
}
