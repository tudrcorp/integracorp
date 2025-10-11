<?php

namespace App\Filament\Business\Resources\BusinessLines\Pages;

use App\Filament\Business\Resources\BusinessLines\BusinessLineResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBusinessLine extends CreateRecord
{
    protected static string $resource = BusinessLineResource::class;
}
