<?php

namespace App\Filament\Business\Resources\Cities\Pages;

use App\Filament\Business\Resources\Cities\CityResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCity extends CreateRecord
{
    protected static string $resource = CityResource::class;

    protected static ?string $title = 'Formulario de Creación de Ciudades';

    protected static bool $canCreateAnother = false;
}