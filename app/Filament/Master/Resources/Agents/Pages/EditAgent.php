<?php

namespace App\Filament\Master\Resources\Agents\Pages;

use App\Filament\Master\Resources\Agents\AgentResource;
use App\Filament\Shared\CommercialStructure\Concerns\SyncsReferidorAssignments;
use Filament\Resources\Pages\EditRecord;

class EditAgent extends EditRecord
{
    use SyncsReferidorAssignments;

    protected static string $resource = AgentResource::class;

    protected static ?string $title = 'Perfil de Agente';

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->fillReferidorAssignmentState($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->captureReferidorAssignments($data);
    }

    protected function afterSave(): void
    {
        $this->persistCapturedReferidorAssignments();
    }
}
