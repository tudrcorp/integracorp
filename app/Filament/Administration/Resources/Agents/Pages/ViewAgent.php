<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Agents\Pages;

use App\Filament\Administration\Resources\Agents\AgentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewAgent extends ViewRecord
{
    protected static string $resource = AgentResource::class;
}
