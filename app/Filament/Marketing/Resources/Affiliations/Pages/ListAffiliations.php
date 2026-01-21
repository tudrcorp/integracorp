<?php

namespace App\Filament\Marketing\Resources\Affiliations\Pages;

use App\Filament\Marketing\Resources\Affiliations\AffiliationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAffiliations extends ListRecords
{
    protected static string $resource = AffiliationResource::class;

    protected static ?string $title = 'Lista de Afiliaciones Individuales';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
