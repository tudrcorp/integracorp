<?php

namespace App\Filament\Business\Resources\Affiliations\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Business\Resources\Affiliations\AffiliationResource;
use App\Filament\Business\Resources\Affiliations\Widgets\StatsOverview;

class ListAffiliations extends ListRecords
{
    protected static string $resource = AffiliationResource::class;

    protected static ?string $title = 'Afiliaciones Individuales';

    protected function getActions(): array
    {
        return [
            CreateAction::make(),
        ];
    } 

    protected function getHeaderWidgets(): array
    {
        return [
            StatsOverview::class
        ];
    }

}