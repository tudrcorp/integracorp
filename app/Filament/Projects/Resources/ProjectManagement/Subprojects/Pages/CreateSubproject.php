<?php

namespace App\Filament\Projects\Resources\ProjectManagement\Subprojects\Pages;

use App\Filament\Projects\Resources\ProjectManagement\Subprojects\SubprojectResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSubproject extends CreateRecord
{
    protected static string $resource = SubprojectResource::class;
}
