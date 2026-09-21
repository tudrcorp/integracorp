<?php

declare(strict_types=1);

namespace App\Filament\Agents\Resources\Agents\Pages;

use App\Filament\Agents\Resources\Agents\AgentResource;
use App\Filament\Shared\CommercialStructure\Actions\DownloadHierarchyStructureAction;
use App\Models\Agent;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAgent extends ViewRecord
{
    protected static string $resource = AgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DownloadHierarchyStructureAction::forAgent(fn (): Agent => $this->getRecord()),
            EditAction::make(),
        ];
    }
}
