<?php

namespace App\Filament\General\Resources\Agents\Pages;

use App\Filament\General\Resources\Agents\AgentResource;
use App\Filament\Shared\CommercialStructure\Concerns\SyncsReferidorAssignments;
use Filament\Resources\Pages\CreateRecord;

class CreateAgent extends CreateRecord
{
    use SyncsReferidorAssignments;

    protected static string $resource = AgentResource::class;

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
