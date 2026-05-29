<?php

namespace App\Filament\Projects\Resources\ProjectManagement\Departments\Pages;

use App\Filament\Projects\Resources\ProjectManagement\Departments\DepartmentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDepartment extends ViewRecord
{
    protected static string $resource = DepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
