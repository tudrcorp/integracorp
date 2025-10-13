<?php

namespace App\Filament\Business\Resources\Regions\Pages;

use App\Filament\Business\Resources\Regions\RegionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRegion extends CreateRecord
{
    protected static string $resource = RegionResource::class;

    protected static ?string $title = 'Formulario de Creación de Región';

    protected static bool $canCreateAnother = false;
}