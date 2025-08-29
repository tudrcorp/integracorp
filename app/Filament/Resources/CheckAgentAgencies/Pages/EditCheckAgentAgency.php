<?php

namespace App\Filament\Resources\CheckAgentAgencies\Pages;

use App\Filament\Resources\CheckAgentAgencies\CheckAgentAgencyResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCheckAgentAgency extends EditRecord
{
    protected static string $resource = CheckAgentAgencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
