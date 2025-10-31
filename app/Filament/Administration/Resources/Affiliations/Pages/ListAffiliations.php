<?php

namespace App\Filament\Administration\Resources\Affiliations\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Administration\Resources\Affiliations\AffiliationResource;
use App\Filament\Administration\Resources\Affiliations\Widgets\StatsOverview;

class ListAffiliations extends ListRecords
{
    protected static string $resource = AffiliationResource::class;

    protected static ?string $title = 'Afiliaciones Individuales';

    protected function getHeaderWidgets(): array
    {
        return [
            StatsOverview::class
        ];
    }
}
