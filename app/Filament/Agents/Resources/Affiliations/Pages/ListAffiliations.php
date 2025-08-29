<?php

namespace App\Filament\Agents\Resources\Affiliations\Pages;

use App\Filament\Agents\Resources\Affiliations\AffiliationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAffiliations extends ListRecords
{
    protected static string $resource = AffiliationResource::class;

    protected static ?string $title = 'Afiliaciones individuales';

}