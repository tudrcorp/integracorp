<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Projects\Pages;

use App\Filament\Projects\Resources\ProjectManagement\Projects\Concerns\InteractsWithProjectScrumRoles;
use App\Filament\Projects\Resources\ProjectManagement\Projects\ProjectResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProject extends CreateRecord
{
    use InteractsWithProjectScrumRoles;

    protected static string $resource = ProjectResource::class;
}
