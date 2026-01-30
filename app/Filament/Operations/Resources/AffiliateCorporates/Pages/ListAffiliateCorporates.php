<?php

namespace App\Filament\Operations\Resources\AffiliateCorporates\Pages;

use App\Filament\Operations\Resources\AffiliateCorporates\AffiliateCorporateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAffiliateCorporates extends ListRecords
{
    protected static string $resource = AffiliateCorporateResource::class;

    protected static ?string $title = 'Afiliados Corporativos';

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
        ];
    }
}
