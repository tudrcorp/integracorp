<?php

namespace App\Filament\Administration\Resources\AnnualCollections\Pages;

use App\Filament\Administration\Resources\AnnualCollections\AnnualCollectionResource;
use Filament\Resources\Pages\ListRecords;

class ListAnnualCollections extends ListRecords
{
    protected static string $resource = AnnualCollectionResource::class;

    protected static ?string $title = 'Registros de Cobranza Por Meses';
}
