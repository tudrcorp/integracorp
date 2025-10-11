<?php

namespace App\Filament\Business\Resources\BusinessUnits\Pages;

use App\Filament\Business\Resources\BusinessUnits\BusinessUnitResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBusinessUnit extends CreateRecord
{
    protected static string $resource = BusinessUnitResource::class;
}
