<?php

namespace App\Filament\Administration\Resources\Agencies\Pages;

use App\Filament\Administration\Resources\Agencies\AgencyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAgencies extends ListRecords
{
    protected static string $resource = AgencyResource::class;

    protected static ?string $title = 'Gestión de Agencias';

}
