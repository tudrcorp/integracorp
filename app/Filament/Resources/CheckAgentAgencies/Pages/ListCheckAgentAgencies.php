<?php

namespace App\Filament\Resources\CheckAgentAgencies\Pages;

use App\Filament\Resources\CheckAgentAgencies\CheckAgentAgencyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCheckAgentAgencies extends ListRecords
{
    protected static string $resource = CheckAgentAgencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
