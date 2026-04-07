<?php

namespace App\Filament\Operations\Resources\Helpdesks\Pages;

use App\Filament\Operations\Resources\Helpdesks\HelpdeskResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHelpdesk extends CreateRecord
{
    protected static string $resource = HelpdeskResource::class;
}
