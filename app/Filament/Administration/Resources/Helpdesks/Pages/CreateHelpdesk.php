<?php

namespace App\Filament\Administration\Resources\Helpdesks\Pages;

use App\Filament\Administration\Resources\Helpdesks\HelpdeskResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHelpdesk extends CreateRecord
{
    protected static string $resource = HelpdeskResource::class;
}
