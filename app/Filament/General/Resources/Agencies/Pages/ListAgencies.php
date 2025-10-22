<?php

namespace App\Filament\General\Resources\Agencies\Pages;

use App\Filament\General\Resources\Agencies\AgencyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAgencies extends ListRecords
{
    protected static string $resource = AgencyResource::class;

    protected static ?string $title = 'Información de la Agencia';

}