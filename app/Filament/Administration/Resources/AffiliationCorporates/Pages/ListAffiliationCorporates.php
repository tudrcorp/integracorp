<?php

namespace App\Filament\Administration\Resources\AffiliationCorporates\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Administration\Resources\AffiliationCorporates\Widgets\StatsOverview;
use App\Filament\Administration\Resources\AffiliationCorporates\AffiliationCorporateResource;

class ListAffiliationCorporates extends ListRecords
{
    protected static string $resource = AffiliationCorporateResource::class;

    protected static ?string $title = 'Afiliaciones Corporativas';

    protected function getHeaderWidgets(): array
    {
        return [
            StatsOverview::class
        ];
    }
}
