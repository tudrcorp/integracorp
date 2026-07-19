<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Sprints\Pages;

use App\Filament\Projects\Resources\ProjectManagement\Sprints\SprintResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSprint extends CreateRecord
{
    protected static string $resource = SprintResource::class;

    protected static ?string $title = 'Crear sprint';
}
