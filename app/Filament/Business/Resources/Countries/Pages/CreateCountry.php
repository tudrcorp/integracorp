<?php

namespace App\Filament\Business\Resources\Countries\Pages;

use App\Filament\Business\Resources\Countries\CountryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCountry extends CreateRecord
{
    protected static string $resource = CountryResource::class;
}
