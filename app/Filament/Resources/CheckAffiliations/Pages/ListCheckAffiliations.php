<?php

namespace App\Filament\Resources\CheckAffiliations\Pages;

use App\Filament\Resources\CheckAffiliations\CheckAffiliationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCheckAffiliations extends ListRecords
{
    protected static string $resource = CheckAffiliationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
