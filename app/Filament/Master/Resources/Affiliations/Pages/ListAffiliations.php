<?php

namespace App\Filament\Master\Resources\Affiliations\Pages;

use App\Filament\Master\Resources\Affiliations\AffiliationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAffiliations extends ListRecords
{
    protected static string $resource = AffiliationResource::class;

    protected static ?string $title = 'Afiliaciones individuales';
}