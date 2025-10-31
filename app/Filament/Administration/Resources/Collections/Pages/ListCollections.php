<?php

namespace App\Filament\Administration\Resources\Collections\Pages;

use App\Filament\Administration\Resources\Collections\CollectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCollections extends ListRecords
{
    protected static string $resource = CollectionResource::class;

    protected static ?string $title = 'Reporte de Cobranza';

    
}
