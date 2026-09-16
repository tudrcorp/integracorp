<?php

declare(strict_types=1);

namespace App\Filament\General\Resources\Agencies\Pages;

use App\Filament\General\Resources\Agencies\AgencyResource;
use App\Filament\Shared\CommercialStructure\Actions\DownloadHierarchyStructureAction;
use App\Models\Agency;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAgency extends ViewRecord
{
    protected static string $resource = AgencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DownloadHierarchyStructureAction::forAgency(fn (): Agency => $this->getRecord()),
            EditAction::make(),
        ];
    }
}
