<?php

namespace App\Filament\Marketing\Resources\Events\Pages;

use App\Filament\Marketing\Resources\Events\EventResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;
}
